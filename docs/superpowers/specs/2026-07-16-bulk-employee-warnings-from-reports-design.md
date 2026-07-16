# Bulk Employee Warnings from Reports Design

**Date:** 2026-07-16
**Status:** Approved design, pending user review of written specification

## Objective

Allow HR and admin users to create employee warnings in bulk from these existing reports:

- Missing check-in/check-out report
- Approved leave report
- Late-arrival/early-departure report

The workflow must remain fast for large result sets, preserve HR company and branch scope rules, create one warning per selected report event, and prevent a report event from producing more than one warning.

## Confirmed Decisions

- Each selected report row creates one employee warning, including when several selected rows belong to the same employee.
- Every created warning uses the server's current processing date as `warning_date`.
- The report event date remains visible in the generated detail and source metadata but is not used as `warning_date`.
- The server generates a trusted event-specific detail for every selected event.
- HR/admin can enter one optional shared note that is appended to every created warning.
- "Select all" selects every eligible row in the current filtered result set, including rows on other DataTable pages.
- An event that has already produced a warning is shown as already used and cannot produce another warning.
- The selected warning type comes from the existing `warning_types` master data.
- Existing manual single-employee warning creation remains available and unchanged.

## User Experience

### Report Table Controls

Each of the three report tables gains:

- A checkbox column before the existing data columns.
- A header checkbox that selects all eligible rows in the complete filtered result set, not only the current DataTable page.
- A selected-count indicator.
- An "Add warnings" action button that is disabled when no eligible rows are selected.
- An "Already warned" badge for events that already have a linked warning. Their checkboxes are disabled.

Selection is held in page-level JavaScript state keyed by the event source key, so DataTable pagination and redraw do not lose selection. Reloading the report or changing month, company, branch, incident type, or leave type clears the previous selection.

### Bulk Warning Modal

The action button opens a shared bulk-warning modal containing:

- One required warning-type selector populated from the existing warning-type API.
- A read-only processing date derived from the server date.
- A lightweight summary showing the number of selected report events and unique employees.
- One optional shared-note field applied to every created warning.
- A submit button whose label shows the selected event count.

The modal does not render event rows and does not call a per-event preview endpoint. It opens immediately even for hundreds of selected rows. Duplicate and eligibility checks happen once during submission, with a disabled submit button and visible processing state.

### Generated Details

Default details are built from trusted report data:

- Missing scan: event date, missing-in/missing-out status, and available check-in/check-out times.
- Late/early: event date, late minutes and/or early minutes, and actual check-in/check-out times.
- Approved leave: leave type, expanded leave date, day or half-day amount, and leave reason.

Generated event details are not rendered in the modal. If HR/admin enters a shared note, the server appends `หมายเหตุ: {shared note}` to each trusted generated detail.

### Completion Feedback

After submission, the UI reports:

- Number of warnings created.
- Number of duplicates skipped.
- Number of other events skipped, with a safe reason when applicable.

Successfully created and duplicate events are marked as already warned in the current report without requiring the user to navigate to the warning-management page.

## Architecture

### Shared Frontend Module

A reusable bulk-warning JavaScript module owns:

- Selection state and select-all behavior across DataTable pages.
- Loading warning types.
- Building and displaying the lightweight count-and-note modal.
- Submitting one batch request and applying the result to the active report.

The three existing report scripts provide a small adapter that converts their trusted response rows into a common event shape:

```text
source_type
source_key
employee_id
employee_name
event_date
event_label
already_warned
```

Report-specific code remains responsible for rendering its domain columns. The shared module remains responsible for warning selection and creation behavior.

### Event Identity

Each report event has a stable identity:

- Missing scan: `employee_id + work_date` under source type `attendance_missing`.
- Late/early: `employee_id + work_date` under source type `attendance_late_early`.
- Approved leave: `leave_request_id + leave_date` under source type `approved_leave`.

A late/early row is one event even when the row contains both late arrival and early departure. Selecting that row creates one warning whose generated detail contains both conditions.

Missing-scan identity does not include the evaluated missing status. If a day's calculated status later changes from missing-in to absent, it is still the same employee-day event and cannot produce a second report-linked warning.

### Persistence

The `employee_warnings` table gains nullable report-source metadata:

- `source_type`
- `source_key`
- `source_event_date`

A unique index on `(source_type, source_key)` enforces one warning per report event. Existing manually created warnings keep null source values; MySQL/MariaDB allows multiple rows with null values in a unique index, so the manual workflow is unaffected.

A versioned SQL migration documents the schema change. The existing runtime table-ensure helper also adds missing columns and index safely for installations that depend on runtime schema initialization.

### Report Responses

The three report endpoints continue enforcing their current role and HR-scope filters. They additionally return the stable event identity, employee ID, and whether a matching report-linked warning already exists. This allows the UI to disable ineligible rows immediately and calculate event and unique-employee counts without another request.

The report response is presentation assistance only. The write API does not trust client-provided employee names, statuses, times, or source ownership.

### Bulk API

`api/employee_warning_api.php` gains a bulk-create action. The request contains:

- One `warning_type_id`.
- One optional `shared_note`.
- A list of source type and source key values.

The API obtains the processing date from the server. It does not accept a client-controlled warning date for this bulk action.

For every submitted event, the server:

1. Requires an authenticated `admin` or `hr` role.
2. Validates the warning type.
3. Validates the supported source type and source-key format.
4. Resolves the source event from current database/report data.
5. Confirms that the resolved employee remains within the current HR scope.
6. Rebuilds trusted event metadata.
7. Generates the trusted event detail and appends the validated shared note when present.
8. Checks the report-source uniqueness constraint.

The batch is written in one transaction. An out-of-scope or malformed event rejects and rolls back the whole request. A valid event that is already linked to a warning is skipped while other valid new events continue. The unique index is the final race-condition guard if two requests attempt the same event concurrently.

The response returns created, duplicate, and skipped event keys plus aggregate counts. It does not expose SQL errors or sensitive scope details.

## Security and Validation

- Preserve the existing page-level and API-level HR/admin role checks.
- Reapply HR scope filtering during bulk creation instead of trusting report-page access.
- Use prepared statements for every lookup and insert.
- Escape employee, report, warning-type, and detail text before rendering.
- Limit batch size and detail length to bounded server-side values.
- Reject unsupported source types, malformed keys, unknown warning types, and events that no longer exist.
- Derive `created_by` from the authenticated session user and `warning_date` from the server date.
- Disable the submit button while the request is in progress, but rely on database uniqueness for real duplicate protection.

## Error Handling

- Report-loading failures retain the existing inline error behavior and disable bulk actions.
- Warning-type loading failure prevents modal submission and displays a clear error.
- If the whole batch is invalid or out of scope, no warnings are created.
- If some events are duplicates, valid new warnings are created and duplicates are reported separately.
- If an event disappears or becomes ineligible between report loading and submit, it is skipped with a safe status unless the condition indicates a scope or authorization violation, which rejects the batch.
- Database failures roll back the transaction and return a generic system error while logging the server-side cause.

## Testing Strategy

### PHP Tests

- Source-key parsing and normalization for all three report types.
- One event creates one warning and the same source cannot create a second warning.
- Several selected rows for the same employee create several warnings.
- Bulk warnings use the server processing date.
- Manual warning creation still accepts its existing date contract and null source metadata.
- Approved leave source resolution distinguishes individual expanded leave dates from one leave request.
- Late/early combined rows remain one warning event.
- HR scope accepts permitted employees and rejects an out-of-scope batch atomically.
- Duplicate rows are skipped while new rows in the same valid batch are inserted.
- Malformed events, unsupported sources, invalid warning types, and excessive detail or batch sizes are rejected.

### JavaScript and UI Contract Tests

- All three pages expose checkbox, select-all, selected-count, and bulk-action hooks.
- Selection survives DataTable paging and redraw.
- Select-all covers the complete filtered dataset.
- Filter changes clear selection.
- Already-warned rows are disabled.
- The modal renders counts and one shared-note field without event rows.
- The unique employee count is calculated from the complete selected result set.
- The batch payload contains one shared note and no per-event detail text.
- The server generates the correct report-specific detail and appends the shared note.
- Successful and duplicate results update the current table state.
- Existing report rendering and existing employee-warning screens retain their contracts.

### Verification Commands

Run checks matched to the changed files, including:

```powershell
C:\xampp\php\php.exe -l api\employee_warning_api.php
C:\xampp\php\php.exe -l includes\employee_warning_helpers.php
C:\xampp\php\php.exe -l api\attendance_api.php
C:\xampp\php\php.exe -l api\leave_api.php
node --check assets\js\employee_warnings.js
node --check assets\js\attendance.js
node --check assets\js\leave_report.js
C:\xampp\php\php.exe tests\employee_warnings_contract_test.php
node tests\attendance_missing_report_ui_test.js
node tests\attendance_late_early_report_ui_test.js
node tests\leave_report_ui_test.js
git diff --check
```

Focused regression tests for the new shared bulk-warning module and bulk API will be added and included in verification.

## Out of Scope

- Changing or removing the existing manual warning form.
- Automatically choosing a warning type from the report condition.
- Combining several selected events into one warning.
- Creating warnings for events outside the three named reports.
- Editing or deleting existing employee warnings.
- Allowing a duplicate report event to be overridden and warned again.

## Acceptance Criteria

- HR/admin can select individual eligible rows or all eligible filtered rows on each named report.
- A selection spanning DataTable pages is fully included.
- The modal loads existing warning types and shows selected-event and unique-employee counts without rendering event rows.
- HR/admin can enter one optional shared note that is appended to every generated warning detail.
- Submitting three selected events for one employee creates three warnings.
- All warnings in a batch use the server's processing date.
- A previously used report event is visibly disabled and is not created again.
- HR scope is enforced during both report reading and warning creation.
- Existing manual warning creation and employee warning history continue working.
- Relevant PHP, JavaScript, contract, and whitespace checks pass.
