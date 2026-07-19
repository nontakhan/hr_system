# Manual Report Loading Design

## Goal

Prevent the three HR report pages in the report menu from fetching large report datasets automatically. Users must finish choosing filters and click the existing report button before report data is requested and displayed.

## Scope

The change applies only to these report-menu pages:

- `attendance_missing_report.php` — missing attendance scan report
- `attendance_late_early_report.php` — late arrival and early departure report
- `leave_report.php` — approved leave report

The employee-specific attendance report and pages outside the report menu are out of scope.

## User Experience

When a report page opens:

1. The current month remains selected by default.
2. Lightweight filter-option requests still run so company, branch, and report-specific type filters are usable.
3. No report-data API request runs.
4. The table displays an instruction telling the user to choose filters and click the report button.
5. Summary cards do not present fetched report totals before the first search.

When the user changes filters:

- Changing month, branch, missing-scan type, incident type, or leave type does not fetch report data.
- Changing company updates the available branch options but does not fetch report data.
- Existing displayed results remain visible while filters are edited. They are replaced only after the user clicks the report button and the new request completes.

When the user clicks `แสดงรายงาน`:

- The page sends one report-data request using the current filter values.
- Existing loading, success, empty-result, error, DataTables, and bulk-warning behavior remains unchanged.
- Repeated clicks intentionally run a new search with the current filters.

## Runtime Changes

### Missing-scan report

In `assets/js/attendance.js`, `initAttendanceMissingReport()` will retain the report button handler and filter-option initialization. Automatic calls to `loadAttendanceMissingReport()` from filter change handlers and from `loadAttendanceMissingFilterOptions()` will be removed. Company changes will continue to call `updateAttendanceMissingBranchOptions()`.

### Late/early report

In `assets/js/attendance.js`, `initAttendanceLateEarlyReport()` will retain the report button handler and filter-option initialization. Automatic calls to `loadAttendanceLateEarlyReport()` from native and Select2 filter events and from `loadAttendanceLateEarlyFilterOptions()` will be removed. Company changes will continue to call `updateAttendanceLateEarlyBranchOptions()`.

### Approved-leave report

In `assets/js/leave_report.js`, `initApprovedLeaveReport()` will retain the report button handler and filter-option initialization. Automatic calls to `loadApprovedLeaveReport()` from filter events and from `loadApprovedLeaveReportOptions()` will be removed. Company changes will continue to call `updateApprovedLeaveReportBranches()`.

No PHP API, helper, query, authorization, or schema changes are required because the expensive requests are already separated from filter-option requests.

## Error Handling

Filter-option errors continue to use each page's current behavior. Report request errors continue to clear stale selectable warning rows and show the existing readable table error. Because no report request occurs before a button click, report-data errors cannot appear merely from opening a page or changing a filter.

## Testing

Focused UI contract tests will assert that:

- each report initializer binds its report-loading function to the existing report button;
- filter changes do not bind or invoke the report-loading function;
- filter-option completion does not invoke the report-loading function;
- company changes still refresh dependent branch options;
- all three pages retain their initial manual-search instruction;
- existing report rendering, DataTables, and bulk-warning contracts remain intact.

Verification will include the focused Node.js report UI tests, JavaScript syntax checks, and `git diff --check`. Existing user-owned worktree changes will remain unstaged and untouched.

## Acceptance Criteria

1. Opening any of the three scoped report pages sends no report-data request.
2. Changing any report filter sends no report-data request.
3. Clicking `แสดงรายงาน` sends a report-data request with the current filters and renders the result.
4. Company selection still narrows the branch options without running the report.
5. Existing DataTables and bulk employee-warning behavior still works after a manual search.
6. No page outside the report menu changes behavior.
