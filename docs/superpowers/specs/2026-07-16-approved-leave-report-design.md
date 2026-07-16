# Approved Leave Report Design

## Goal

Create a dedicated Thai report for admin and HR users to review approved actual-leave dates during a selected month. The report follows the existing missing-scan report's layout, filtering, HR-scope, safe-rendering, and DataTables conventions, while presenting one row per counted leave date.

## Chosen Approach

Add a separate `leave_report.php` page and a report action in `api/leave_api.php`. Keep leave selection and date expansion in `includes/leave_helpers.php`, rather than adding leave-specific behavior to the attendance API.

This preserves a clear responsibility boundary: the leave API owns leave data, the attendance API owns scanner and shift reports, and both report pages may share the established filter and table presentation conventions.

## Access and Scope

- Only `admin` and `hr` sessions may open the page or call the report API action.
- Unauthorized page access redirects to `dashboard.php`.
- Unauthorized API access returns the existing JSON access-denied response.
- Employee visibility includes active and probation employees and uses `hrScopeBuildEmployeeWhereClause(...)` for HR users.
- Company, branch, leave-type filter options, summary values, and detail rows must not expose employees outside the current user's HR scope.

## Page and Navigation

Create `leave_report.php` in the existing Sarabun/Bootstrap report style.

The page contains:

- Thai title `รายงานการลา` and a short explanation that only approved actual leave is shown.
- A link to the existing `my_leaves.php` leave history landing page.
- Filters for month, company, branch, and actual leave type.
- The current Gregorian month as the initial month value, using the native month picker opt-out convention.
- A report-loading button in the responsive filter grid.
- Summary cards for leave-date rows, total leave days, and distinct employees on leave.
- A responsive DataTable when more than ten rows exist.
- Initial, loading, malformed-response, API-error, empty, and no-results states matching the missing-scan report.

Add `leave_report.php` to the report-center active-page list and add a `การลา` link after the late/early report link.

## Filters

- `month` is required and uses `YYYY-MM`.
- `company_id` and `branch_id` are optional and constrained by the current user's scope.
- `leave_type_id` is optional. Available choices include only rows from `leave_types` where `is_actual_leave = 1`.
- Changing a filter reloads the report, while the explicit button remains available like the existing reports.
- Changing company refreshes the branch choices so only branches belonging to that company remain selectable.

## Inclusion Rules

A leave request is eligible only when all of these are true:

- `leave_requests.status = 'approved'`.
- Its joined leave type has `leave_types.is_actual_leave = 1`.
- The request overlaps the selected calendar month: `start_date <= month_end` and `end_date >= month_start`.
- It matches the optional company, branch, and leave-type filters.
- Its employee is inside the user's role and HR scope.

`pending_cancel_hr` is not included because the approved-only requirement is exact. Pending, rejected, and cancelled requests are also excluded. Late-arrival, early-departure, overtime, and other non-actual request types are excluded through `is_actual_leave = 1` and the existing request-unit/time-request rules.

## Per-Day Expansion

Each eligible request expands to one report row per counted leave date:

1. Clip the request range to the selected month's first and last dates.
2. Load the employee's configured workdays and the relevant company holidays.
3. Call the existing `leaveBuildDateSummary(...)` rules with clipped boundaries.
4. Preserve the original `start_day_part` only when the clipped start is the original request start; otherwise use `full`.
5. Preserve the original `end_day_part` only when the clipped end is the original request end; otherwise use `full`.
6. Emit only `included_dates`; do not emit excluded company holidays or employee non-working days.

Each emitted row carries `leave_days` from the included-date entry: `1.0` for a full day and `0.5` for a half day. It also carries the included-date part label. This makes a request spanning July 30 through August 2 appear in both monthly reports, but each report contains only its counted dates inside that month.

Rows sort by leave date, then employee name, then request ID for deterministic output.

## Table Contract

The table columns are:

1. วันที่ลา
2. เจ้าหน้าที่ (name and citizen ID)
3. ตำแหน่ง
4. บริษัท
5. สาขา
6. ประเภทการลา
7. ช่วงวันที่ตามใบลา
8. จำนวนวันของแถว (`1 วัน` or `0.5 วัน`, with half-day label when applicable)
9. เหตุผล

The request range remains the original approved request range, while the first column is the expanded date inside the selected month. Dates use the existing Thai date formatter. Missing reason text renders as `-`. All database values inserted into HTML are escaped.

## Summary Contract

- `total_rows`: number of expanded leave-date rows.
- `total_days`: sum of row `leave_days`; half days contribute `0.5`.
- `employee_count`: count of distinct employee IDs represented by the filtered rows.

The summary is computed after every filter and uses only rows returned to the user.

## API Contract

`GET api/leave_api.php` accepts:

- `action=approved_leave_report`
- `month=YYYY-MM`
- optional `company_id`
- optional `branch_id`
- optional `leave_type_id`

The endpoint validates the month and positive integer filter IDs. Invalid input returns the existing JSON error shape. A successful empty query returns an empty successful dataset.

The success response contains:

```json
{
  "status": "success",
  "month": "2026-07",
  "summary": {
    "total_rows": 2,
    "total_days": 1.5,
    "employee_count": 1
  },
  "data": []
}
```

Each data row exposes request ID, employee ID, citizen ID, full name, position, company, branch, leave type ID/name, expanded `leave_date`, original `start_date` and `end_date`, `leave_days`, day-part code/label, and reason.

The existing filter-options response may be reused if it can enforce the same HR scope. Otherwise, add a leave-report-specific filter-options action that returns scoped companies, branches, and actual leave types.

## Code Boundaries

- `includes/leave_helpers.php` owns pure filter normalization, monthly clipping, per-day expansion, summary counting, and any reusable scoped query helpers.
- `api/leave_api.php` owns authorization, input validation, scoped bulk loading, filter application, and JSON output.
- A dedicated `assets/js/leave_report.js` owns filter initialization, API calls, summary/table rendering, escaping, and DataTable lifecycle.
- `leave_report.php` owns report markup and its role gate.
- `includes/header.php` owns report navigation and active state.

No schema change is required.

## Performance

Load overlapping approved requests in one scoped query for the selected month. Load reusable holiday and workday inputs in bulk or cache them by company/employee while expanding rows. Do not execute one request query per employee or one holiday query per leave day.

## Error Handling and Safety

- Validate and normalize all query parameters on the server.
- Use prepared statements and the existing HR-scope binding helpers.
- Escape all rendered employee, organization, leave-type, and reason values.
- Return no rows for invalid date-summary entries rather than inventing counted dates.
- Parse response text before JSON so an empty PHP response becomes a readable Thai error.
- Destroy an existing DataTable before rerendering to avoid duplicate initialization.

## Verification

Focused regression coverage must verify:

- Only `approved` requests with `is_actual_leave = 1` are eligible.
- A request crossing a month boundary is clipped and expanded into only the selected month's dates.
- Company holidays and employee non-working days are omitted through the existing calculation rules.
- Full-day and half-day rows carry `1.0` and `0.5` respectively.
- Original range and reason survive per-day expansion.
- Summary totals count rows, summed days, and distinct employees correctly.
- Admin/HR authorization and HR company/branch scope are enforced in both data and filter options.
- The API uses an overlapping-range query and does not query once per employee.
- Page mount point, filters, Thai copy, table contract, API action, response-text parsing, escaping, and DataTables behavior.
- Sidebar link and active state.

Run PHP syntax checks for every changed PHP file, focused PHP helper/API tests, JavaScript syntax and UI tests, the sidebar test, and `git diff --check`.

## Out of Scope

- Pending, cancellation-pending, rejected, or cancelled leave.
- Non-actual leave types and late/early/overtime requests.
- Manager or employee access to the aggregate report.
- Creating, editing, approving, or cancelling leave from the report.
- Excel/PDF export.
- Changing leave balances, quotas, request capture, or approval behavior.
- Database schema changes.
