# Approved Leave Report Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an admin/HR report that shows only approved actual leave, expanded to one counted row per leave date in the selected month.

**Architecture:** Add pure monthly expansion and summary helpers to `includes/leave_helpers.php`, expose a scoped bulk report through `api/leave_api.php`, and create a dedicated Bootstrap page with its own JavaScript renderer. Reuse the existing HR employee-scope helper, leave date-calculation rules, Select2/DataTables conventions, and report-center navigation.

**Tech Stack:** PHP 7+/MySQLi, MariaDB, Bootstrap, vanilla JavaScript, jQuery Select2/DataTables, focused PHP and Node.js contract tests.

## Global Constraints

- Only `admin` and `hr` may access the aggregate report.
- Include only `leave_requests.status = 'approved'` joined to `leave_types.is_actual_leave = 1`.
- Exclude `pending_cancel_hr` and every pending, rejected, cancelled, or non-actual request.
- Use Gregorian `YYYY-MM-DD` values internally and existing Thai date formatting in the browser.
- A request crossing a month boundary appears in both months, but each report emits only counted dates inside that month.
- Company holidays and employee non-working days do not produce rows.
- Full days contribute `1.0`; half days contribute `0.5` and preserve their day-part label.
- HR results and company/branch options must remain inside `hrScopeBuildEmployeeWhereClause(...)` scope.
- Use one overlapping-range request query, not one request query per employee.
- Do not change schema, balances, request capture, approval behavior, or existing attendance reports.

---

## File Structure

- Create `leave_report.php`: role-gated report markup.
- Create `assets/js/leave_report.js`: filter, fetch, summary, table, and DataTable behavior.
- Create `tests/leave_report_ui_test.js`: page/browser contract.
- Create `tests/leave_report_api_source_test.php`: authorization, query, and scope source contract.
- Modify `includes/leave_helpers.php`: pure month clipping, per-day expansion, and summary helpers.
- Modify `tests/leave_helpers_test.php`: expansion and summary regressions.
- Modify `api/leave_api.php`: scoped filter-options and approved-report actions.
- Modify `includes/footer.php`: load the dedicated report script.
- Modify `includes/header.php`: report link and active state.
- Modify `tests/sidebar_hybrid_menu_test.js`: navigation regression.

### Task 1: Pure per-day expansion and summary

**Files:**
- Modify: `tests/leave_helpers_test.php`
- Modify: `includes/leave_helpers.php`

**Interfaces:**
- Consumes: `leaveBuildDateSummary($startDate, $endDate, $startPart, $endPart, $workDays, array $companyHolidays): array`
- Produces: `leaveExpandApprovedRequestForMonth(array $request, string $month, $workDays, array $companyHolidays): array`
- Produces: `leaveCountApprovedReportRows(array $rows): array`

- [ ] **Step 1: Write failing helper tests**

Append before the test success message in `tests/leave_helpers_test.php`:

```php
$crossMonthRows = leaveExpandApprovedRequestForMonth([
    'id' => 71,
    'employee_id' => 9,
    'start_date' => '2026-07-30',
    'end_date' => '2026-08-05',
    'start_day_part' => 'afternoon',
    'end_day_part' => 'morning',
    'reason' => 'ธุระครอบครัว',
], '2026-08', 'Mon,Tue,Wed,Thu,Fri', ['2026-08-05' => 'วันหยุดบริษัท']);
assertSameValue(2, count($crossMonthRows), 'Cross-month leave should emit only counted dates inside the selected month.');
assertSameValue('2026-08-03', $crossMonthRows[0]['leave_date'], 'The first in-month counted date should be emitted.');
assertSameValue(1.0, $crossMonthRows[0]['leave_days'], 'A clipped interior date should remain a full day.');
assertSameValue('2026-08-04', $crossMonthRows[1]['leave_date'], 'The second in-month counted date should be emitted.');
assertSameValue('2026-07-30', $crossMonthRows[0]['start_date'], 'The original request range should survive expansion.');
assertSameValue('ธุระครอบครัว', $crossMonthRows[0]['reason'], 'The request reason should survive expansion.');

$halfDayRows = leaveExpandApprovedRequestForMonth([
    'id' => 72,
    'employee_id' => 10,
    'start_date' => '2026-07-16',
    'end_date' => '2026-07-16',
    'start_day_part' => 'afternoon',
    'end_day_part' => 'afternoon',
], '2026-07', 'Mon,Tue,Wed,Thu,Fri', []);
assertSameValue(0.5, $halfDayRows[0]['leave_days'], 'Half-day leave should contribute half a day.');
assertSameValue('afternoon', $halfDayRows[0]['day_part'], 'Half-day expansion should preserve its part.');

$leaveReportSummary = leaveCountApprovedReportRows([
    ['employee_id' => 9, 'leave_days' => 1.0],
    ['employee_id' => 9, 'leave_days' => 0.5],
    ['employee_id' => 10, 'leave_days' => 1.0],
]);
assertSameValue(3, $leaveReportSummary['total_rows'], 'Summary should count expanded rows.');
assertSameValue(2.5, $leaveReportSummary['total_days'], 'Summary should sum full and half days.');
assertSameValue(2, $leaveReportSummary['employee_count'], 'Summary should count distinct employees.');
```

- [ ] **Step 2: Run the helper test and verify RED**

Run:

```powershell
C:\xampp\php\php.exe tests\leave_helpers_test.php
```

Expected: fatal error naming `leaveExpandApprovedRequestForMonth` because the helper does not exist.

- [ ] **Step 3: Implement the minimal expansion helper**

Add after `leaveBuildDateSummary(...)` in `includes/leave_helpers.php`:

```php
function leaveExpandApprovedRequestForMonth(array $request, $month, $workDays, array $companyHolidays) {
    if (!preg_match('/^\d{4}-\d{2}$/', (string)$month)) return [];
    $monthStart = $month . '-01';
    $monthEnd = date('Y-m-t', strtotime($monthStart));
    $originalStart = normalizeGregorianDateInput($request['start_date'] ?? '');
    $originalEnd = normalizeGregorianDateInput($request['end_date'] ?? '');
    if ($originalStart === '' || $originalEnd === '' || $originalStart > $monthEnd || $originalEnd < $monthStart) return [];

    $clippedStart = max($originalStart, $monthStart);
    $clippedEnd = min($originalEnd, $monthEnd);
    $summary = leaveBuildDateSummary(
        $clippedStart,
        $clippedEnd,
        $clippedStart === $originalStart ? ($request['start_day_part'] ?? 'full') : 'full',
        $clippedEnd === $originalEnd ? ($request['end_day_part'] ?? 'full') : 'full',
        $workDays,
        $companyHolidays
    );
    if (empty($summary['valid'])) return [];

    $rows = [];
    foreach ($summary['included_dates'] as $included) {
        $rows[] = $request + [
            'leave_date' => $included['date'],
            'leave_days' => (float)$included['days'],
            'day_part' => $included['part'],
            'day_part_label' => $included['label'],
        ];
    }
    return $rows;
}

function leaveCountApprovedReportRows(array $rows) {
    $employeeIds = [];
    $days = 0.0;
    foreach ($rows as $row) {
        $days += (float)($row['leave_days'] ?? 0);
        $employeeId = (int)($row['employee_id'] ?? 0);
        if ($employeeId > 0) $employeeIds[$employeeId] = true;
    }
    return [
        'total_rows' => count($rows),
        'total_days' => round($days, 2),
        'employee_count' => count($employeeIds),
    ];
}
```

- [ ] **Step 4: Verify GREEN and syntax**

Run:

```powershell
C:\xampp\php\php.exe -l includes\leave_helpers.php
C:\xampp\php\php.exe tests\leave_helpers_test.php
```

Expected: syntax succeeds and `leave_helpers_test passed`.

- [ ] **Step 5: Review the task diff**

Run `git diff -- includes/leave_helpers.php tests/leave_helpers_test.php` and confirm only focused pure helpers and their tests were added.

### Task 2: Scoped bulk API and filter options

**Files:**
- Create: `tests/leave_report_api_source_test.php`
- Modify: `api/leave_api.php`

**Interfaces:**
- Consumes: both helpers from Task 1.
- Produces: `GET api/leave_api.php?action=approved_leave_report_filters`
- Produces: `GET api/leave_api.php?action=approved_leave_report&month=YYYY-MM&company_id=&branch_id=&leave_type_id=`
- Produces: `fetchApprovedLeaveReportRows(mysqli $mysqli, string $role, array $filters): array`

- [ ] **Step 1: Create the failing API source contract**

Create `tests/leave_report_api_source_test.php`:

```php
<?php
$source = file_get_contents(__DIR__ . '/../api/leave_api.php');
function assertLeaveReportSource($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
assertLeaveReportSource(strpos($source, "'approved_leave_report'") !== false, 'Leave API should expose the approved report action.');
assertLeaveReportSource(strpos($source, "'approved_leave_report_filters'") !== false, 'Leave API should expose scoped filter options.');
assertLeaveReportSource(strpos($source, "lr.status = 'approved'") !== false, 'Report query should include only approved requests.');
assertLeaveReportSource(strpos($source, 'lt.is_actual_leave = 1') !== false, 'Report query should include only actual leave types.');
assertLeaveReportSource(strpos($source, 'lr.start_date <= ?') !== false && strpos($source, 'lr.end_date >= ?') !== false, 'Report query should use month overlap conditions.');
assertLeaveReportSource(strpos($source, 'hrScopeBuildEmployeeWhereClause') !== false, 'HR report results should use employee scope.');
assertLeaveReportSource(strpos($source, 'leaveExpandApprovedRequestForMonth') !== false, 'API should use the tested date-expansion helper.');
assertLeaveReportSource(strpos($source, 'leaveCountApprovedReportRows') !== false, 'API should use the tested summary helper.');
echo "leave_report_api_source_test passed\n";
```

- [ ] **Step 2: Run the API contract and verify RED**

Run `C:\xampp\php\php.exe tests\leave_report_api_source_test.php`.

Expected: failure `Leave API should expose the approved report action.`

- [ ] **Step 3: Add safe shared setup and authorization**

In `api/leave_api.php`, require `../includes/leave_helpers.php` and `../includes/hr_scope_helpers.php`, derive `$role`, and gate both new GET actions with:

```php
$canManageReport = in_array($role, ['admin', 'hr'], true);
if (!$canManageReport) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit();
}
```

Keep all existing leave-type CRUD behavior unchanged.

- [ ] **Step 4: Implement scoped filter options**

For `approved_leave_report_filters`, query active/probation employees joined to companies and branches, append `hrScopeBuildEmployeeWhereClause($role, hrScopeCurrentSessionScopes(), 'e')`, and bind with `hrScopeBindParams(...)`. Return unique company and branch rows plus:

```sql
SELECT id, type_name
FROM leave_types
WHERE is_actual_leave = 1
ORDER BY type_name
```

Use response keys `companies`, `branches`, and `leave_types`.

- [ ] **Step 5: Implement the overlapping-range bulk query**

Add `fetchApprovedLeaveReportRows(...)` with one prepared query. Select request, employee, position, company, branch, leave type, date parts, and reason. Its fixed predicates must include:

```sql
lr.status = 'approved'
AND lt.is_actual_leave = 1
AND (lr.request_unit = 'day' OR (lr.request_unit = 'hour' AND lr.time_request_type IS NULL))
AND lr.start_date <= ?
AND lr.end_date >= ?
AND e.status IN ('active', 'probation')
```

Append optional positive `company_id`, `branch_id`, and `leave_type_id` predicates and the HR scope clause. Bind month end before month start. For each request, cache `leaveFetchEmployeeWorkDays(...)` by employee ID and `leaveFetchCompanyHolidays(...)` by company/month, expand through `leaveExpandApprovedRequestForMonth(...)`, then sort by `leave_date`, `full_name`, and `id`.

- [ ] **Step 6: Add the report response**

Validate month with `/^\d{4}-\d{2}$/`; reject non-empty filter IDs that are not positive integers. Return:

```php
echo json_encode([
    'status' => 'success',
    'month' => $month,
    'summary' => leaveCountApprovedReportRows($rows),
    'data' => $rows,
]);
exit();
```

- [ ] **Step 7: Verify GREEN**

Run:

```powershell
C:\xampp\php\php.exe -l api\leave_api.php
C:\xampp\php\php.exe tests\leave_report_api_source_test.php
C:\xampp\php\php.exe tests\leave_helpers_test.php
```

Expected: syntax succeeds and both tests print `passed`.

### Task 3: Dedicated page and browser renderer

**Files:**
- Create: `tests/leave_report_ui_test.js`
- Create: `leave_report.php`
- Create: `assets/js/leave_report.js`
- Modify: `includes/footer.php`

**Interfaces:**
- Consumes: both GET actions from Task 2.
- Produces DOM mount `approvedLeaveReportPage` and IDs prefixed `approvedLeaveReport`.

- [ ] **Step 1: Write the failing UI contract**

Create `tests/leave_report_ui_test.js`:

```js
const fs = require('fs');
function assertIncludes(text, expected, message) {
    if (!text.includes(expected)) throw new Error(`${message}\nMissing: ${expected}`);
}
const page = fs.readFileSync('leave_report.php', 'utf8');
const script = fs.readFileSync('assets/js/leave_report.js', 'utf8');
const footer = fs.readFileSync('includes/footer.php', 'utf8');
[
    'approvedLeaveReportPage', 'approvedLeaveReportMonth', 'approvedLeaveReportCompany',
    'approvedLeaveReportBranch', 'approvedLeaveReportType', 'approvedLeaveReportRows',
].forEach(id => assertIncludes(page, `id="${id}"`, `Report page should provide ${id}.`));
assertIncludes(page, 'colspan="9"', 'Report states should span all nine columns.');
assertIncludes(page, 'รายงานการลา', 'Report should include its Thai title.');
assertIncludes(script, "action: 'approved_leave_report'", 'Renderer should call the report action.');
assertIncludes(script, 'approved_leave_report_filters', 'Renderer should load scoped options.');
assertIncludes(script, 'response.text()', 'Renderer should detect empty responses.');
assertIncludes(script, 'escapeHtml', 'Renderer should escape database values.');
assertIncludes(script, 'DataTable', 'Renderer should support DataTables.');
assertIncludes(footer, 'assets/js/leave_report.js', 'Shared footer should load the report script.');
console.log('leave_report_ui_test passed');
```

- [ ] **Step 2: Run the UI contract and verify RED**

Run `node tests\leave_report_ui_test.js`.

Expected: `ENOENT` for `leave_report.php`.

- [ ] **Step 3: Create the role-gated page**

Create `leave_report.php` from the structural pattern in `attendance_missing_report.php`. Gate to admin/HR, set `$page_title = 'รายงานการลา'`, `$use_select2 = true`, and render filters for month/company/branch/leave type. Render three summary cards and a nine-column table matching the design. Link the top-right button to `my_leaves.php`.

- [ ] **Step 4: Implement filter loading**

In `assets/js/leave_report.js`, exit unless `approvedLeaveReportPage` exists, default the month to the current `YYYY-MM`, initialize Select2 if available, fetch `approved_leave_report_filters`, populate scoped companies/branches/actual types, and filter branch choices by their `company_id` when company changes.

- [ ] **Step 5: Implement report fetch and safe rendering**

Build parameters with `action`, `month`, `company_id`, `branch_id`, and `leave_type_id`. Read `response.text()` before `JSON.parse`. Render readable Thai loading, empty-response, API-error, and no-results states using `colspan="9"`.

Render rows with existing `formatThaiDate(...)` and `escapeHtml(...)`. Format the original range as one date when equal or `start - end` otherwise. Format day quantity as `1 วัน` or `0.5 วัน (ช่วงเช้า/ช่วงบ่าย)`. Use `-` for an empty reason.

- [ ] **Step 6: Add summary and DataTable lifecycle**

Render cards from `total_rows`, `total_days`, and `employee_count`. Before replacing rows, destroy an existing DataTable. Initialize only when row count exceeds ten, order by leave date then employee, and use the existing Thai DataTables copy.

- [ ] **Step 7: Load the dedicated script and verify GREEN**

Add to `includes/footer.php` after the leave scripts:

```php
<script src="assets/js/leave_report.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/leave_report.js'); ?>"></script>
```

Run:

```powershell
C:\xampp\php\php.exe -l leave_report.php
C:\xampp\php\php.exe -l includes\footer.php
node --check assets\js\leave_report.js
node tests\leave_report_ui_test.js
```

Expected: both syntax checks succeed and the UI test prints `passed`.

### Task 4: Report-center navigation and complete verification

**Files:**
- Modify: `tests/sidebar_hybrid_menu_test.js`
- Modify: `includes/header.php`

**Interfaces:**
- Consumes: `leave_report.php` from Task 3.
- Produces: report-center link and active submenu state.

- [ ] **Step 1: Add failing sidebar assertions**

Before the success message in `tests/sidebar_hybrid_menu_test.js`, add:

```js
assertIncludes(header, 'href="leave_report.php"', 'Report submenu should link to the approved leave report.');
assertIncludes(header, "isActive('leave_report.php')", 'Report submenu should stay active on the approved leave report.');
```

- [ ] **Step 2: Run the sidebar test and verify RED**

Run `node tests\sidebar_hybrid_menu_test.js`.

Expected: failure `Report submenu should link to the approved leave report.`

- [ ] **Step 3: Add the page and link**

Add `leave_report.php` to `$reportCenterPages`. After the late/early link add a sibling link with `isActive('leave_report.php')`, Font Awesome `fa-calendar-check`, and Thai label `การลา`.

- [ ] **Step 4: Run the focused verification suite**

Run:

```powershell
C:\xampp\php\php.exe -l includes\leave_helpers.php
C:\xampp\php\php.exe -l api\leave_api.php
C:\xampp\php\php.exe -l leave_report.php
C:\xampp\php\php.exe -l includes\header.php
C:\xampp\php\php.exe -l includes\footer.php
C:\xampp\php\php.exe tests\leave_helpers_test.php
C:\xampp\php\php.exe tests\leave_report_api_source_test.php
node --check assets\js\leave_report.js
node tests\leave_report_ui_test.js
node tests\sidebar_hybrid_menu_test.js
git diff --check
```

Expected: all syntax and focused tests succeed; `git diff --check` returns no output.

- [ ] **Step 5: Review scope and working tree**

Run:

```powershell
git status --short
git diff --stat
git diff -- includes/leave_helpers.php api/leave_api.php leave_report.php assets/js/leave_report.js includes/header.php includes/footer.php tests/leave_helpers_test.php tests/leave_report_api_source_test.php tests/leave_report_ui_test.js tests/sidebar_hybrid_menu_test.js
```

Confirm all edits belong to the approved report and no unrelated user-owned files were changed. Do not stage, commit, or push unless the user explicitly requests it.
