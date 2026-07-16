# Attendance Late/Early Report Design

## Goal

Create a dedicated Thai report for admin and HR users to review employees who arrived late, left early, or did both during a selected month. The report must follow the same access, employee-scope, filtering, effective-shift, attendance-override, holiday, leave, training, and bulk-loading rules as the existing missing-scan report.

## Chosen Approach

Add a separate `attendance_late_early_report.php` page and a separate `late_early_report` action in `api/attendance_api.php`. Reuse the existing bulk report data-loading path rather than calling `buildMonthlyAttendanceReport(...)` once per employee.

This keeps the report easy to find and understand, preserves the performance characteristics of the missing-scan report, and avoids mixing missing scans with time deviations in one table.

## Access and Scope

- Only `admin` and `hr` sessions may open the page or call the API action.
- Unauthorized page access redirects to `dashboard.php`.
- Unauthorized API access returns the existing JSON access-denied response.
- Employee visibility uses the same active/probation employee selection and `hrScopeBuildEmployeeWhereClause(...)` behavior as the missing-scan report.
- Company and branch filter options and results must never include employees outside the current user's HR scope.

## Page and Navigation

Create `attendance_late_early_report.php` in the existing Sarabun/Bootstrap report style.

The page contains:

- Title: `รายงานมาสาย/ออกก่อน`
- A short explanation that approved late/early requests are deducted before an incident is shown.
- A link back to the per-employee attendance calendar.
- Filters for month, company, branch, and incident type.
- Incident type values: `all`, `late`, and `early`.
- A full-width report-loading button within the existing responsive filter grid.
- Summary cards for total rows, late incidents, and early-departure incidents.
- A responsive DataTable when more than ten rows exist.
- Empty, loading, malformed-response, API-error, and no-results states matching the missing-scan report behavior.

Add `attendance_late_early_report.php` to the report-center active-page list and add a `มาสาย/ออกก่อน` link below the existing missing-scan report link.

## Table Contract

Each employee/date pair appears at most once. A row may contain both incident flags.

The table columns are:

1. วันที่
2. พนักงาน (name and citizen ID)
3. ตำแหน่ง
4. บริษัท
5. สาขา
6. เวลาเข้างานตามกะ
7. สแกนเข้า
8. มาสาย (minutes after approved deduction)
9. เวลาเลิกงานตามกะ
10. สแกนออก
11. ออกก่อน (minutes after approved deduction)
12. สถานะ

The status cell shows `มาสาย`, `ออกก่อน`, or both badges. Time values use the existing attendance time formatter, dates use the existing Thai date formatter, and all data inserted into HTML is escaped.

## Incident Calculation

The report evaluates each calendar date using the same effective shift resolution as the missing-scan report:

1. Resolve the employee's dated shift assignment.
2. Apply a dated shift override.
3. Apply an approved day-swap workday override.
4. Merge an HR attendance-record override over imported scanner values.
5. Exclude non-working days, company holidays, approved full-day leave, and approved training according to the current attendance helpers.

Rows with a missing check-in or missing check-out are not part of this report; they remain the responsibility of the missing-scan report.

### Late arrival

- A date is eligible only when both scans exist and the effective shift has a valid start time.
- The raw late deviation is the number of whole minutes by which check-in is after shift start.
- The incident threshold remains the effective shift's `late_tolerance_mins`: no late incident exists when check-in is at or before shift start plus tolerance.
- If the threshold is exceeded, subtract the total approved `late_arrival` minutes for that employee/date from the raw late deviation.
- Include the late incident only when the remaining late minutes are greater than zero.

### Early departure

- A date is eligible only when both scans exist and the effective shift has a valid end time.
- The raw early deviation is the number of whole minutes by which check-out is before shift end.
- Subtract the total approved `early_departure` minutes for that employee/date.
- Include the early-departure incident only when the remaining early minutes are greater than zero.

Approved request deductions use `approved_request_minutes` when it is positive and fall back to `request_minutes` for older approved records. Only final `approved` requests apply. Pending, rejected, cancelled, or cancellation-pending requests do not reduce report minutes.

Example: a 20-minute late arrival with an approved 15-minute late-arrival request is reported as 5 minutes late. A 20-minute early departure with a fully covering approved 20-minute request is omitted unless the same date still has a late incident.

## API Contract

`GET api/attendance_api.php` accepts:

- `action=late_early_report`
- `month=YYYY-MM`
- optional `company_id`
- optional `branch_id`
- `incident_type=all|late|early`

Invalid months return the existing invalid-month JSON error. Unknown incident types normalize to `all`.

The success response contains:

```json
{
  "status": "success",
  "month": "2026-07",
  "incident_type": "all",
  "summary": {
    "total": 1,
    "late": 1,
    "early": 1
  },
  "data": []
}
```

`summary.total` counts employee/date rows. `summary.late` and `summary.early` count incident flags, so a row containing both contributes one to each category while contributing one to `total`.

Each data row exposes employee identity and organization fields, `work_date`, effective `shift_start_time` and `shift_end_time`, effective `check_in` and `check_out`, `late_minutes`, `early_minutes`, `is_late`, and `is_early`.

## Code Boundaries

- `includes/attendance_helpers.php` owns incident-type normalization, minute calculation, approved-minute deduction, row filtering, and summary counting as pure testable functions.
- `api/attendance_api.php` owns authorization, input handling, scoped employee lookup, bulk database loading, effective-shift construction, approved time-request loading, sorting, and JSON output.
- `assets/js/attendance.js` owns filter initialization, API loading, summary/table rendering, escaping, and DataTable lifecycle.
- `attendance_late_early_report.php` owns only the report markup and role gate.
- `includes/header.php` owns navigation and active-state integration.

No schema change is required.

## Performance

The API must load monthly records, attendance overrides, shift assignments, shift overrides, holidays, approved leave, approved training, approved day swaps, and approved late/early requests in bulk for all scoped employee IDs. It must not call `buildMonthlyAttendanceReport(...)` inside an employee loop.

Rows sort by `work_date` and then `full_name`, matching the missing-scan report.

## Error Handling and Safety

- Validate the month on the server.
- Normalize the incident type on the server.
- Reuse prepared statements and dynamic bind helpers for database inputs.
- Escape all rendered employee and organization data.
- Treat invalid/missing shift times as non-incidents rather than guessing.
- Treat invalid/missing scanner values as outside this report.
- Return an empty successful dataset when no scoped employees or incidents exist.

## Verification

Focused regression coverage must verify:

- Incident-type normalization.
- Late tolerance behavior.
- Late and early minute calculations.
- Partial and complete approved-request deductions.
- A single row containing both incident types.
- Row filtering and summary semantics.
- Exclusion of holidays, non-working days, leave, training, and missing scans through report construction.
- API action, authorization, scoped employee lookup, bulk loaders, and the prohibition on per-employee monthly report calls.
- Page mount point, filters, Thai copy, table columns, API action, response-text parsing, error states, escaping, and DataTable behavior.
- Sidebar link and active state.

Run PHP syntax checks for changed PHP files, the focused PHP helper/API tests, JavaScript syntax and UI tests, the sidebar test, and `git diff --check`.

## Out of Scope

- Changing attendance import behavior.
- Changing the existing per-employee attendance calendar presentation.
- Creating or approving late/early requests from this report.
- Export to Excel/PDF.
- Manager or employee access to this aggregate report.
- Database schema changes.
