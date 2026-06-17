# Attendance HR Overrides Design

## Goal

Allow admin/HR users to correct missing or faulty attendance scan times without overwriting the raw imported scanner data. Corrections should support one employee or many employees for a scanner outage day, require a reason, respect existing HR scope rules, and make attendance reports show the corrected day according to normal attendance rules.

## Current Context

- Imported scanner data is stored in `attendance_records` with `check_in` and `check_out`.
- Attendance status is calculated in `includes/attendance_helpers.php` by `attendanceEvaluateStatus(...)`.
- Monthly and range reports are built in `api/attendance_api.php` through `buildMonthlyAttendanceReport(...)` and `buildAttendanceReportRange(...)`.
- HR visibility is already centralized through `includes/hr_scope_helpers.php`, especially `hrScopeCurrentSessionScopes()` and `hrScopeBuildEmployeeWhereClause(...)`.
- Existing import logic fills missing raw scan values but does not overwrite existing values. This should remain unchanged.

## Recommended Approach

Use a separate override layer instead of modifying `attendance_records` directly.

The report builder will load approved HR overrides for the requested employee/month, merge the override check-in/check-out values over the raw record for calculation and display, and still retain enough metadata to show that the displayed time was adjusted by HR.

This keeps scanner data auditable while giving HR a fast operational path for genuine scanner failures.

## Data Model

Create a new table:

```sql
CREATE TABLE IF NOT EXISTS attendance_record_overrides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    work_date DATE NOT NULL,
    override_check_in TIME NULL,
    override_check_out TIME NULL,
    reason TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by INT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_attendance_override_employee_date (employee_id, work_date),
    KEY idx_attendance_override_work_date (work_date),
    KEY idx_attendance_override_created_by (created_by)
);
```

Rules:

- One active override row per employee per work date.
- Either `override_check_in`, `override_check_out`, or both may be set.
- Reason is always required.
- Updates replace the previous override values but keep raw scanner values untouched.

## Permissions

- Admin can create and update overrides for all employees.
- HR can create and update overrides only for employees allowed by the existing HR scope helper.
- Employees and managers cannot create overrides.
- Read/report endpoints can include override metadata only for users who can already view that employee's attendance.

## User Experience

Add a new admin/HR page named `attendance_adjustments.php`, with a sidebar entry under the attendance/admin attendance area.

Single employee correction:

- Select employee.
- Select work date.
- Show current raw check-in/check-out and current evaluated status.
- Enter corrected check-in and/or check-out.
- Enter reason.
- Save.

Bulk correction:

- Select work date.
- Filter employee list by name, position, branch, and company.
- Show active/probation employees within the user's allowed scope.
- Support selecting individual employees or all filtered employees.
- Enter shared corrected check-in and/or check-out.
- Enter shared reason.
- Save one override per selected employee.

The UI should be practical and dense, similar to existing HR tables:

- Use Select2 where the app already does for employee searching.
- Use DataTables for larger employee result lists.
- Use native date picker behavior for the work-date field if the shared Thai date helper would hide the browser calendar.

## Report Behavior

When building attendance report rows:

1. Load raw `attendance_records` for the employee/month as today.
2. Load override rows for the same employee/month.
3. For each date, build effective values:
   - `effective_check_in = override_check_in` when present, otherwise raw `check_in`.
   - `effective_check_out = override_check_out` when present, otherwise raw `check_out`.
4. Pass effective values into `attendanceEvaluateStatus(...)`.
5. Return both effective display values and override metadata.

Expected examples:

- Raw check-in exists, raw check-out missing, HR sets check-out to `17:00`: report shows normal/present if shift rules pass.
- Raw check-in missing, raw check-out exists, HR sets check-in to `08:00`: report shows normal/present if not late.
- Raw both missing, HR sets both times: report shows normal/present if shift rules pass.
- HR sets check-in later than the shift tolerance: report still shows late, because the correction should be a real time, not a forced status.

The attendance detail popup should show a small note when a row has an override:

- "ปรับโดย HR"
- reason
- adjusted check-in/check-out values

## API Design

Extend or add attendance API actions under `api/attendance_api.php`:

- `GET action=adjustment_employees`
  - Inputs: date, optional search/filter fields.
  - Returns employees within scope plus raw/effective attendance info for the selected date.

- `POST action=save_adjustment`
  - Inputs: employee_id, work_date, override_check_in, override_check_out, reason.
  - Validates scope and saves one row.

- `POST action=save_bulk_adjustments`
  - Inputs: employee_ids[], work_date, override_check_in, override_check_out, reason.
  - Validates every employee against scope before saving any rows.
  - Uses a transaction so bulk saves do not partially apply.

Validation:

- `work_date` must be `YYYY-MM-DD`.
- Times must be valid `HH:MM` or `HH:MM:SS`.
- At least one of check-in/check-out must be provided.
- Reason must be non-empty after trimming.
- Employee status should be active or probation unless existing attendance-report behavior says otherwise.

## Audit Expectations

The override table itself is the primary audit record:

- raw scanner data remains unchanged in `attendance_records`.
- override value, reason, creator, and timestamps are retained.
- subsequent edits update `updated_by` and `updated_at`.

If later stricter audit history is needed, add a second append-only history table. It is out of scope for the first implementation unless requested.

## Error Handling

- Access outside HR scope returns `Access Denied`.
- Invalid date/time input returns a clear validation message.
- Empty selected employees in bulk mode returns a clear validation message.
- Bulk mode should fail the whole request if any selected employee is outside scope or invalid.
- Database errors should log server-side details and return the app's normal generic system error.

## Testing Plan

PHP/unit-level:

- Add helper tests for merging raw attendance rows with override rows.
- Assert override values affect `attendanceEvaluateStatus(...)` through report row construction.
- Assert late status still appears if HR-provided check-in is late.
- Assert missing-in/missing-out becomes present when the missing side is corrected.

API/source contract:

- Assert new actions require admin/HR.
- Assert HR scope helper is used for employee list and save authorization.
- Assert bulk save uses all selected employee IDs and validates scope before writing.

JS/UI:

- Assert adjustment row rendering includes employee name, position, branch, company, raw status, selectable checkbox, and override indicator.
- Assert bulk selection and filter UI wiring is present.
- Assert attendance calendar/detail rendering can display override metadata.

Manual QA:

- Import or use an employee/date with missing check-in.
- Save a single correction and confirm the attendance calendar/report becomes normal.
- Save a bulk correction for a branch outage day and confirm all selected employees update.
- Confirm raw imported values remain unchanged in the database.
- Confirm HR user cannot see or save employees outside their configured scope.

## Out of Scope

- Manager approval workflow for corrections.
- Employee self-request correction flow.
- Deleting raw scanner records.
- Forcing a day to "normal" independent of the entered time and shift rules.
- Full append-only history table beyond the current override row audit fields.
