# Employee Monthly Request and Attendance Report Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an admin/HR monthly report for one scoped employee that combines final-approved requests with actual late, early, and overtime scanner events.

**Architecture:** Add pure event-normalization and calculation helpers, then expose one scoped action in the existing attendance API. A dedicated PHP page and JavaScript renderer use the established Select2, manual-load, Bootstrap, safe-response, and DataTables report conventions.

**Tech Stack:** PHP 7+/mysqli, MariaDB/MySQL, Bootstrap, Select2, vanilla JavaScript, DataTables, Node.js source-contract tests, PHP CLI tests.

## Global Constraints

- Only `admin` and `hr` may access the page or API.
- HR scope must be enforced before loading any selected employee events.
- Persisted dates remain Gregorian; UI dates are Thai-friendly.
- Only request rows with final `status = 'approved'` are included.
- Scanner events use effective records, shifts, overrides, swaps, holidays, leave, and activities.
- All SQL inputs use prepared statements and all rendered database text is escaped.
- Do not change schemas, approval behavior, payroll behavior, or unrelated files.
- Preserve pre-existing worktree changes.
- Do not commit or push unless the user explicitly requests it.

---

## File Structure

- Create `includes/employee_request_attendance_report_helpers.php`: pure event constructors, minute calculations, sorting, and summary.
- Create `employee_request_attendance_report.php`: role-gated page markup only.
- Create `assets/js/employee_request_attendance_report.js`: Select2, loading, rendering, type/source filters, and DataTable lifecycle.
- Create `tests/employee_request_attendance_report_helpers_test.php`: behavioral helper tests.
- Create `tests/employee_request_attendance_report_api_source_test.php`: API authorization/query/source contract.
- Create `tests/employee_request_attendance_report_ui_test.js`: page, renderer, navigation, and safety contract.
- Modify `api/attendance_api.php`: action dispatch, scoped employee lookup, bounded source loaders, and report composition.
- Modify `includes/header.php`: report-center page and link.
- Modify `includes/footer.php`: page-specific JavaScript include.

### Task 1: Pure Event and Scanner Calculations

**Files:**
- Create: `tests/employee_request_attendance_report_helpers_test.php`
- Create: `includes/employee_request_attendance_report_helpers.php`

**Interfaces:**
- Produces: `employeeRequestReportCalculateScannerEvents(string $workDate, ?string $checkIn, ?string $checkOut, array $shift): array`
- Produces: `employeeRequestReportBuildEvent(string $key, string $date, string $type, string $source, string $timeLabel, float $amount, string $unit, string $detail, string $statusLabel, array $extra = []): array`
- Produces: `employeeRequestReportSortEvents(array $events): array`
- Produces: `employeeRequestReportSummarize(array $events): array`

- [ ] **Step 1: Write failing helper tests**

Cover these exact cases in `tests/employee_request_attendance_report_helpers_test.php`:

```php
<?php
require_once __DIR__ . '/../includes/employee_request_attendance_report_helpers.php';

function assertReportSame($expected, $actual, $message) {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

$shift = ['start_time' => '08:00:00', 'end_time' => '17:00:00', 'late_tolerance_mins' => 10];
$events = employeeRequestReportCalculateScannerEvents('2026-07-03', '08:16:00', '16:40:00', $shift);
assertReportSame(16, $events['late_minutes'], 'Late row keeps full deviation after tolerance gate.');
assertReportSame(20, $events['early_minutes'], 'Early minutes use shift end minus check-out.');
assertReportSame(0, $events['overtime_minutes'], 'Early check-out is not overtime.');

$withinTolerance = employeeRequestReportCalculateScannerEvents('2026-07-03', '08:09:00', '17:45:00', $shift);
assertReportSame(0, $withinTolerance['late_minutes'], 'Tolerance suppresses late event.');
assertReportSame(45, $withinTolerance['overtime_minutes'], 'OT uses check-out after shift end.');

$missing = employeeRequestReportCalculateScannerEvents('2026-07-03', null, null, $shift);
assertReportSame(['late_minutes' => 0, 'early_minutes' => 0, 'overtime_minutes' => 0], $missing, 'Missing scans must not be guessed.');

$request = employeeRequestReportBuildEvent('leave:7:2026-07-03', '2026-07-03', 'leave', 'approved_request', '-', 1, 'day', 'ลาป่วย', 'อนุมัติแล้ว');
$scanner = employeeRequestReportBuildEvent('scanner:ot:2026-07-03', '2026-07-03', 'actual_overtime', 'scanner', '17:00-17:45', 45, 'minute', 'OT จริง', 'ข้อมูลลงเวลา');
$summary = employeeRequestReportSummarize([$request, $scanner]);
assertReportSame(['total_events' => 2, 'approved_requests' => 1, 'scanner_events' => 1, 'actual_overtime_minutes' => 45], $summary, 'Summary follows source and OT semantics.');

echo "employee_request_attendance_report_helpers_test passed" . PHP_EOL;
```

Add cases for overnight shift `20:00-05:00`, stable type order, unique event keys, zero/invalid time values, and deterministic date/source sorting.

- [ ] **Step 2: Run the helper test and verify RED**

Run:

```powershell
C:\xampp\php\php.exe tests\employee_request_attendance_report_helpers_test.php
```

Expected: FAIL because `includes/employee_request_attendance_report_helpers.php` or its functions do not exist.

- [ ] **Step 3: Implement minimal pure helpers**

Create `includes/employee_request_attendance_report_helpers.php` with strict validation and a single event shape:

```php
<?php
function employeeRequestReportBuildEvent($key, $date, $type, $source, $timeLabel, $amount, $unit, $detail, $statusLabel, array $extra = []) {
    return array_merge([
        'event_key' => (string)$key,
        'event_date' => (string)$date,
        'event_type' => (string)$type,
        'source' => (string)$source,
        'time_label' => (string)$timeLabel,
        'amount' => (float)$amount,
        'amount_unit' => (string)$unit,
        'detail' => (string)$detail,
        'status_label' => (string)$statusLabel,
    ], $extra);
}
```

Implement scanner calculations with `DateTimeImmutable`, anchoring overnight shift end/check-out to the next date when appropriate. Apply tolerance only as an inclusion gate, while retaining the full late deviation. Implement stable order `leave`, `late_request`, `actual_late`, `early_request`, `actual_early`, `activity`, `shift_swap`, `overtime_request`, `actual_overtime`.

- [ ] **Step 4: Run helper tests and verify GREEN**

Run the same PHP test. Expected: `employee_request_attendance_report_helpers_test passed`.

### Task 2: Scoped API and Source Loaders

**Files:**
- Create: `tests/employee_request_attendance_report_api_source_test.php`
- Modify: `api/attendance_api.php`
- Use: `includes/employee_request_attendance_report_helpers.php`

**Interfaces:**
- Consumes Task 1 event helpers.
- Produces: `fetchEmployeeRequestAttendanceReportEmployee(mysqli $mysqli, string $role, int $employeeId): ?array`
- Produces: `buildEmployeeRequestAttendanceReport(mysqli $mysqli, array $employee, string $month): array`
- Produces GET action `employee_request_attendance_report`.

- [ ] **Step 1: Write the failing API source contract**

Create assertions that require:

```php
$source = file_get_contents(__DIR__ . '/../api/attendance_api.php');
assert(strpos($source, "\$action === 'employee_request_attendance_report'") !== false);
assert(strpos($source, 'fetchEmployeeRequestAttendanceReportEmployee') !== false);
assert(strpos($source, 'hrScopeBuildEmployeeWhereClause') !== false);
assert(strpos($source, "lr.status = 'approved'") !== false);
assert(strpos($source, "tr.status = 'approved'") !== false);
assert(strpos($source, "dsr.status = 'approved'") !== false);
assert(strpos($source, "time_request_type IN ('late_arrival','early_departure','overtime_after_work')") !== false);
assert(strpos($source, 'buildMonthlyAttendanceReport(') === false || strpos($source, 'buildEmployeeRequestAttendanceReport') < strpos($source, 'buildMonthlyAttendanceReport('));
```

The test must also pin inclusion of selected employee as either `requester_employee_id` or `target_employee_id`, overlapping leave/activity date predicates, prepared statements, attendance overrides, effective shift assignments, and Task 1 helper calls.

- [ ] **Step 2: Run the API contract and verify RED**

Run:

```powershell
C:\xampp\php\php.exe tests\employee_request_attendance_report_api_source_test.php
```

Expected: FAIL because the action and loaders do not exist.

- [ ] **Step 3: Add authorization and input dispatch**

Require the new helper beside the existing attendance/leave/day-swap helpers. Add a GET action that:

```php
if ($action === 'employee_request_attendance_report') {
    if (!in_array($role, ['admin', 'hr'], true)) sendJsonError('Access Denied');
    $employeeId = (int)($_GET['employee_id'] ?? 0);
    $month = normalizeAttendanceMonth($_GET['month'] ?? '');
    if ($employeeId <= 0 || $month === '') sendJsonError('กรุณาเลือกพนักงานและเดือน');
    $employee = fetchEmployeeRequestAttendanceReportEmployee($mysqli, $role, $employeeId);
    if (!$employee) sendJsonError('ไม่พบพนักงานในขอบเขตที่รับผิดชอบ');
    sendJson(['status' => 'success', 'month' => $month, 'employee' => $employee] + buildEmployeeRequestAttendanceReport($mysqli, $employee, $month));
}
```

Reuse the existing month normalizer name if it differs. The scoped employee loader must build its predicate with `hrScopeBuildEmployeeWhereClause(...)` before any report query.

- [ ] **Step 4: Implement bounded source loaders and composition**

Load for one employee/month:

- effective scanner records and attendance overrides;
- base and dated shifts plus day-swap effects;
- final-approved actual leave and hourly requests;
- final-approved overlapping activities;
- final-approved shift swaps where the employee is requester or target, joining both employee names.

Use `leaveExpandApprovedRequestForMonth(...)` for actual leave. Clip and expand activity ranges with existing day-part/workday helpers. Convert every result to Task 1's event shape. Add actual OT minutes to an OT request row from the scanner calculation map for the same date. Return `['summary' => employeeRequestReportSummarize($events), 'data' => employeeRequestReportSortEvents($events)]`.

- [ ] **Step 5: Run API and helper tests**

Run:

```powershell
C:\xampp\php\php.exe tests\employee_request_attendance_report_api_source_test.php
C:\xampp\php\php.exe tests\employee_request_attendance_report_helpers_test.php
C:\xampp\php\php.exe tests\attendance_helpers_test.php
C:\xampp\php\php.exe tests\leave_helpers_test.php
```

Expected: all exit 0 with their pass messages.

### Task 3: Page, Select2, Timeline Table, and Navigation

**Files:**
- Create: `tests/employee_request_attendance_report_ui_test.js`
- Create: `employee_request_attendance_report.php`
- Create: `assets/js/employee_request_attendance_report.js`
- Modify: `includes/header.php`
- Modify: `includes/footer.php`

**Interfaces:**
- Consumes API action from Task 2.
- Produces DOM mount `employeeRequestAttendanceReportPage` and renderer initializer `initEmployeeRequestAttendanceReport()`.

- [ ] **Step 1: Write the failing UI contract**

Require the test to assert:

```js
assertIncludes(page, 'id="employeeRequestAttendanceReportPage"');
assertIncludes(page, 'id="employeeRequestAttendanceReportEmployee"');
assertIncludes(page, 'id="employeeRequestAttendanceReportMonth"');
assertIncludes(page, 'id="employeeRequestAttendanceReportLoad"');
assertIncludes(page, 'รายงานคำขอและเหตุการณ์พนักงาน');
assertIncludes(script, 'initEmployeeRequestAttendanceReport');
assertIncludes(script, "action', 'employee_request_attendance_report'");
assertIncludes(script, 'response.text()');
assertIncludes(script, 'escapeHtml');
assertIncludes(script, 'DataTable');
assertIncludes(header, 'employee_request_attendance_report.php');
assertIncludes(footer, 'assets/js/employee_request_attendance_report.js');
```

Also assert seven table headings, source/type filters, four summary targets, initial/loading/empty/error Thai copy, and that no filter change triggers a fetch before the load button is pressed.

- [ ] **Step 2: Run UI test and verify RED**

Run:

```powershell
node tests\employee_request_attendance_report_ui_test.js
```

Expected: FAIL because the page and script do not exist.

- [ ] **Step 3: Create the role-gated page**

Follow `attendance_late_early_report.php`, set `$use_select2 = true`, redirect non-admin/HR sessions, and render:

- required employee Select2;
- native month input with current month;
- explicit `แสดงรายงาน` button;
- four summary values;
- source and type filters disabled until results load;
- responsive seven-column table and accessible status region.

- [ ] **Step 4: Implement safe manual-load renderer**

In `assets/js/employee_request_attendance_report.js`:

- initialize only when the mount exists;
- populate employees using the existing scoped attendance filter-options action if it returns employee IDs/names; otherwise add a scoped employee-options action in Task 2;
- initialize Select2 once with a Thai placeholder;
- attach API loading only to the load button;
- use `response.text()` before `JSON.parse` and show `เซิร์ฟเวอร์ไม่ส่งข้อมูลกลับ` for empty responses;
- escape all interpolated values;
- destroy a previous DataTable before rebuilding;
- filter the loaded rows client-side by source/type;
- keep the initial, loading, empty, and error states distinct;
- format Gregorian dates with the existing Thai date utility and show amount units as `วัน` or `นาที`.

- [ ] **Step 5: Add report navigation and script inclusion**

Add the page to `$reportCenterPages`, place its Thai link after the leave report entry, and load its script from `includes/footer.php` with `filemtime(...)`, matching current page-specific includes.

- [ ] **Step 6: Run UI and syntax checks**

```powershell
node tests\employee_request_attendance_report_ui_test.js
node --check assets\js\employee_request_attendance_report.js
C:\xampp\php\php.exe -l employee_request_attendance_report.php
C:\xampp\php\php.exe -l includes\header.php
C:\xampp\php\php.exe -l includes\footer.php
```

Expected: every command exits 0.

### Task 4: Integrated Regression and Completion Gate

**Files:**
- Modify tests only if a real regression reveals an uncovered requirement.

- [ ] **Step 1: Run all focused feature tests**

```powershell
C:\xampp\php\php.exe tests\employee_request_attendance_report_helpers_test.php
C:\xampp\php\php.exe tests\employee_request_attendance_report_api_source_test.php
node tests\employee_request_attendance_report_ui_test.js
C:\xampp\php\php.exe tests\attendance_helpers_test.php
C:\xampp\php\php.exe tests\leave_helpers_test.php
node tests\activity_request_contract_test.js
node tests\sidebar_hybrid_menu_test.js
```

Expected: all pass with exit code 0.

- [ ] **Step 2: Lint every changed source file**

```powershell
C:\xampp\php\php.exe -l includes\employee_request_attendance_report_helpers.php
C:\xampp\php\php.exe -l api\attendance_api.php
C:\xampp\php\php.exe -l employee_request_attendance_report.php
C:\xampp\php\php.exe -l includes\header.php
C:\xampp\php\php.exe -l includes\footer.php
node --check assets\js\employee_request_attendance_report.js
```

Expected: no PHP syntax errors and no JavaScript syntax errors.

- [ ] **Step 3: Exercise the real API boundary when a local authenticated fixture is available**

Use a scoped admin/HR session and a known employee/month containing approved requests and scanner data. Verify the response contains only that employee, excludes `pending_cancel_hr`, and matches database dates/times. If no safe fixture exists, report this boundary as unverified rather than fabricating evidence.

- [ ] **Step 4: Review the final patch and worktree**

```powershell
git diff --check
git status --short
git diff -- includes/employee_request_attendance_report_helpers.php api/attendance_api.php employee_request_attendance_report.php assets/js/employee_request_attendance_report.js includes/header.php includes/footer.php tests/employee_request_attendance_report_helpers_test.php tests/employee_request_attendance_report_api_source_test.php tests/employee_request_attendance_report_ui_test.js
```

Expected: no whitespace errors; only task-owned files are reported as this feature's changes; pre-existing user changes remain untouched.
