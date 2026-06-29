# Proxy Request Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build one admin/HR page and API for creating approved requests on behalf of employees, with clear audit text in request history.

**Architecture:** Add shared proxy-audit helpers first, then add a dedicated proxy API that reuses existing request validation and inserts approved rows. Keep employee self-service pages unchanged; add one admin/HR page and small history rendering changes.

**Tech Stack:** PHP 8 on XAMPP, MySQLi, Bootstrap 5, Font Awesome, existing vanilla JavaScript modules, existing PHP/JS contract tests.

---

## File Structure

- Create `includes/proxy_request_helpers.php`: shared audit columns, access checks, employee lookup, creator display, and small normalization helpers.
- Create `api/proxy_request_api.php`: one JSON endpoint for employee lookup, calculations, and create actions.
- Create `request_proxy.php`: admin/HR-only page shell and form markup.
- Create `assets/js/proxy_request.js`: page behavior, employee loading, tab switching, calculations, and submissions.
- Modify `includes/header.php`: add sidebar link for admin/HR and active-state support.
- Modify `includes/leave_helpers.php`: call proxy audit column ensure from leave table ensure path, if needed.
- Modify `includes/day_swap_helpers.php`: ensure proxy audit columns on `day_swap_requests`.
- Modify `includes/training_request_helpers.php`: ensure proxy audit columns on `training_requests`.
- Modify `api/leave_history_api.php`, `api/late_early_request_api.php`, `api/day_swap_api.php`, and `api/training_request_api.php`: expose proxy creator fields in history rows.
- Modify `assets/js/my_leaves.js`, `assets/js/late_early_request.js`, `assets/js/day_swap.js`, and `assets/js/training_request.js`: render proxy audit badge/line in history cards/tables.
- Add tests under `tests/`: focused source/contract tests for helper, API source contracts, and history rendering contracts.

---

### Task 1: Add Proxy Audit Helper Contract

**Files:**
- Create: `tests/proxy_request_helpers_contract_test.php`
- Create: `includes/proxy_request_helpers.php`

- [ ] **Step 1: Write the failing helper contract test**

Create `tests/proxy_request_helpers_contract_test.php`:

```php
<?php
require_once __DIR__ . '/../includes/proxy_request_helpers.php';

function assertProxySame($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$_SESSION = [
    'user_id' => 99,
    'employee_id' => 42,
    'role' => 'hr',
];

$audit = proxyRequestBuildAuditPayload('created by phone request');
assertProxySame(99, $audit['created_by_user_id'], 'audit should store session user id');
assertProxySame(42, $audit['created_by_employee_id'], 'audit should store session employee id');
assertProxySame('hr', $audit['created_by_role'], 'audit should store session role');
assertProxySame('admin_proxy', $audit['created_via'], 'audit should mark proxy source');
assertProxySame('created by phone request', $audit['proxy_note'], 'audit should trim proxy note');

assertProxySame(true, proxyRequestRoleCanCreate('admin'), 'admin can create proxy requests');
assertProxySame(true, proxyRequestRoleCanCreate('hr'), 'hr can create proxy requests');
assertProxySame(false, proxyRequestRoleCanCreate('manager'), 'manager cannot create proxy requests');
assertProxySame(false, proxyRequestRoleCanCreate('employee'), 'employee cannot create proxy requests');

$display = proxyRequestCreatorLabel([
    'proxy_creator_name' => 'ฝ่ายบุคคล ทดสอบ',
    'created_by_role' => 'hr',
]);
assertProxySame('สร้างโดย HR/Admin: ฝ่ายบุคคล ทดสอบ', $display, 'creator label should use employee name');

$fallback = proxyRequestCreatorLabel([
    'proxy_creator_name' => '',
    'created_by_role' => 'admin',
]);
assertProxySame('สร้างโดย HR/Admin: admin', $fallback, 'creator label should fall back to role');
```

- [ ] **Step 2: Run the test and verify it fails**

Run:

```powershell
C:\xampp\php\php.exe tests\proxy_request_helpers_contract_test.php
```

Expected: fatal error because `includes/proxy_request_helpers.php` does not exist.

- [ ] **Step 3: Implement the minimal helper file**

Create `includes/proxy_request_helpers.php`:

```php
<?php

function proxyRequestRoleCanCreate($role) {
    return in_array((string)$role, ['admin', 'hr'], true);
}

function proxyRequestRequireAccess() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user_id']) || !proxyRequestRoleCanCreate($_SESSION['role'] ?? '')) {
        throw new RuntimeException('Access Denied');
    }
}

function proxyRequestBuildAuditPayload($proxyNote = '') {
    return [
        'created_by_user_id' => (int)($_SESSION['user_id'] ?? 0),
        'created_by_employee_id' => (int)($_SESSION['employee_id'] ?? 0) ?: null,
        'created_by_role' => (string)($_SESSION['role'] ?? ''),
        'created_via' => 'admin_proxy',
        'proxy_note' => trim((string)$proxyNote),
    ];
}

function proxyRequestCreatorLabel(array $row) {
    if (($row['created_via'] ?? 'admin_proxy') !== 'admin_proxy') {
        return '';
    }
    $name = trim((string)($row['proxy_creator_name'] ?? ''));
    if ($name === '') {
        $name = trim((string)($row['created_by_role'] ?? ''));
    }
    return $name === '' ? '' : 'สร้างโดย HR/Admin: ' . $name;
}

function proxyRequestEnsureAuditColumns(mysqli $mysqli, $tableName) {
    $allowed = ['leave_requests', 'day_swap_requests', 'training_requests'];
    if (!in_array($tableName, $allowed, true)) {
        throw new InvalidArgumentException('Invalid proxy audit table');
    }

    $columns = [];
    $result = $mysqli->query("SHOW COLUMNS FROM {$tableName}");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = true;
        }
    }

    if (!isset($columns['created_by_user_id'])) {
        $mysqli->query("ALTER TABLE {$tableName} ADD COLUMN created_by_user_id INT NULL AFTER rejection_reason");
    }
    if (!isset($columns['created_by_employee_id'])) {
        $mysqli->query("ALTER TABLE {$tableName} ADD COLUMN created_by_employee_id INT NULL AFTER created_by_user_id");
    }
    if (!isset($columns['created_by_role'])) {
        $mysqli->query("ALTER TABLE {$tableName} ADD COLUMN created_by_role VARCHAR(30) NULL AFTER created_by_employee_id");
    }
    if (!isset($columns['created_via'])) {
        $mysqli->query("ALTER TABLE {$tableName} ADD COLUMN created_via ENUM('self_service','admin_proxy') NOT NULL DEFAULT 'self_service' AFTER created_by_role");
    }
    if (!isset($columns['proxy_note'])) {
        $mysqli->query("ALTER TABLE {$tableName} ADD COLUMN proxy_note TEXT NULL AFTER created_via");
    }
}
```

- [ ] **Step 4: Run the helper contract test**

Run:

```powershell
C:\xampp\php\php.exe tests\proxy_request_helpers_contract_test.php
```

Expected: no output and exit code 0.

- [ ] **Step 5: Lint the helper**

Run:

```powershell
C:\xampp\php\php.exe -l includes\proxy_request_helpers.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 6: Commit**

```powershell
git add tests/proxy_request_helpers_contract_test.php includes/proxy_request_helpers.php
git commit -m "feat: add proxy request audit helpers"
```

---

### Task 2: Ensure Audit Columns From Existing Request Helpers

**Files:**
- Modify: `includes/leave_helpers.php`
- Modify: `includes/day_swap_helpers.php`
- Modify: `includes/training_request_helpers.php`
- Test: `tests/proxy_request_schema_source_test.php`

- [ ] **Step 1: Write the failing source contract test**

Create `tests/proxy_request_schema_source_test.php`:

```php
<?php
$files = [
    'leave' => __DIR__ . '/../includes/leave_helpers.php',
    'day_swap' => __DIR__ . '/../includes/day_swap_helpers.php',
    'training' => __DIR__ . '/../includes/training_request_helpers.php',
];

foreach ($files as $name => $file) {
    $source = file_get_contents($file);
    if (strpos($source, "require_once __DIR__ . '/proxy_request_helpers.php'") === false) {
        fwrite(STDERR, "{$name} helper must require proxy_request_helpers.php\n");
        exit(1);
    }
}

$expectations = [
    'leave' => "proxyRequestEnsureAuditColumns($mysqli, 'leave_requests')",
    'day_swap' => "proxyRequestEnsureAuditColumns($mysqli, 'day_swap_requests')",
    'training' => "proxyRequestEnsureAuditColumns($mysqli, 'training_requests')",
];

foreach ($expectations as $name => $needle) {
    $source = file_get_contents($files[$name]);
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, "{$name} helper must ensure proxy audit columns with {$needle}\n");
        exit(1);
    }
}
```

- [ ] **Step 2: Run the test and verify it fails**

Run:

```powershell
C:\xampp\php\php.exe tests\proxy_request_schema_source_test.php
```

Expected: failure saying helpers must require proxy helper.

- [ ] **Step 3: Wire audit column ensures**

At the top of `includes/leave_helpers.php`, after `<?php`, add:

```php
require_once __DIR__ . '/proxy_request_helpers.php';
```

Inside `leaveEnsureTwoStepApprovalColumns(mysqli $mysqli)`, after the existing `$columns` collection block and before adding approval columns, add:

```php
proxyRequestEnsureAuditColumns($mysqli, 'leave_requests');
```

At the top of `includes/day_swap_helpers.php`, after `<?php`, add:

```php
require_once __DIR__ . '/proxy_request_helpers.php';
```

Inside `daySwapEnsureTable($mysqli)`, after `$columns` is populated, add:

```php
proxyRequestEnsureAuditColumns($mysqli, 'day_swap_requests');
```

At the top of `includes/training_request_helpers.php`, after `<?php`, add:

```php
require_once __DIR__ . '/proxy_request_helpers.php';
```

Inside `trainingRequestEnsureTable(mysqli $mysqli): void`, after the `CREATE TABLE IF NOT EXISTS training_requests` query, add a column scan that calls the shared helper:

```php
proxyRequestEnsureAuditColumns($mysqli, 'training_requests');
```

- [ ] **Step 4: Run the schema source test**

Run:

```powershell
C:\xampp\php\php.exe tests\proxy_request_schema_source_test.php
```

Expected: no output and exit code 0.

- [ ] **Step 5: Lint modified helpers**

Run:

```powershell
C:\xampp\php\php.exe -l includes\leave_helpers.php
C:\xampp\php\php.exe -l includes\day_swap_helpers.php
C:\xampp\php\php.exe -l includes\training_request_helpers.php
```

Expected: `No syntax errors detected` for each file.

- [ ] **Step 6: Commit**

```powershell
git add includes/leave_helpers.php includes/day_swap_helpers.php includes/training_request_helpers.php tests/proxy_request_schema_source_test.php
git commit -m "feat: ensure proxy audit columns"
```

---

### Task 3: Add Proxy API Source Contract

**Files:**
- Create: `tests/proxy_request_api_source_test.php`
- Create: `api/proxy_request_api.php`

- [ ] **Step 1: Write the failing API source test**

Create `tests/proxy_request_api_source_test.php`:

```php
<?php
$file = __DIR__ . '/../api/proxy_request_api.php';
if (!is_file($file)) {
    fwrite(STDERR, "api/proxy_request_api.php must exist\n");
    exit(1);
}
$source = file_get_contents($file);
$needles = [
    "require_once '../includes/proxy_request_helpers.php'",
    "proxyRequestRequireAccess()",
    "function proxyRequestCanAccessEmployee",
    "function proxyRequestCreateLeave",
    "function proxyRequestCreateTimeRequest",
    "function proxyRequestCreateDaySwap",
    "function proxyRequestCreateTraining",
    "'approved'",
    "'admin_proxy'",
    "trainingRequestCreateHistoryRecord",
    "daySwapHasPendingOrApprovedConflict",
    "leaveFetchConflictingLeaveDates",
];
foreach ($needles as $needle) {
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, "proxy API missing required source marker: {$needle}\n");
        exit(1);
    }
}
```

- [ ] **Step 2: Run the test and verify it fails**

Run:

```powershell
C:\xampp\php\php.exe tests\proxy_request_api_source_test.php
```

Expected: failure because `api/proxy_request_api.php` does not exist.

- [ ] **Step 3: Create the API skeleton with action routing**

Create `api/proxy_request_api.php` with this routing skeleton:

```php
<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

function proxyRequestJson($payload) {
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

function proxyRequestError($message) {
    proxyRequestJson(['status' => 'error', 'message' => $message]);
}

try {
    if (session_status() === PHP_SESSION_NONE) session_start();
    require_once '../includes/db_connect.php';
    require_once '../includes/proxy_request_helpers.php';
    require_once '../includes/hr_scope_helpers.php';
    require_once '../includes/leave_helpers.php';
    require_once '../includes/day_swap_helpers.php';
    require_once '../includes/training_request_helpers.php';
    require_once '../includes/attendance_helpers.php';
    require_once '../includes/upload_security.php';

    proxyRequestRequireAccess();

    leaveEnsureTwoStepApprovalColumns($mysqli);
    leaveEnsureHourlyRequestTypes($mysqli);
    daySwapEnsureTable($mysqli);
    trainingRequestEnsureTable($mysqli);

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');

    if ($method === 'GET') {
        if ($action === 'employees') proxyRequestJson(['status' => 'success', 'data' => proxyRequestFetchEmployees($mysqli)]);
        if ($action === 'leave_types') proxyRequestJson(['status' => 'success', 'data' => proxyRequestFetchLeaveTypes($mysqli)]);
        if ($action === 'day_swap_holidays') proxyRequestJson(['status' => 'success', 'data' => proxyRequestFetchDaySwapHolidays($mysqli)]);
        if ($action === 'calculate_leave') proxyRequestJson(['status' => 'success', 'data' => proxyRequestCalculateLeave($mysqli)]);
        if ($action === 'calculate_time_request') proxyRequestJson(['status' => 'success', 'data' => proxyRequestCalculateTimeRequest($mysqli)]);
        proxyRequestError('Invalid Action');
    }

    if ($method === 'POST') {
        if ($action === 'create_leave') proxyRequestCreateLeave($mysqli);
        if ($action === 'create_late_early') proxyRequestCreateTimeRequest($mysqli, false);
        if ($action === 'create_overtime') proxyRequestCreateTimeRequest($mysqli, true);
        if ($action === 'create_day_swap') proxyRequestCreateDaySwap($mysqli);
        if ($action === 'create_training') proxyRequestCreateTraining($mysqli);
        proxyRequestError('Invalid Action');
    }

    proxyRequestError('Method Not Allowed');
} catch (Throwable $e) {
    error_log($e->getMessage());
    proxyRequestError($e instanceof InvalidArgumentException ? $e->getMessage() : 'System Error');
}
```

- [ ] **Step 4: Add employee access helpers**

Append the following helper functions to `api/proxy_request_api.php`:

```php
function proxyRequestCurrentRole() {
    return (string)($_SESSION['role'] ?? '');
}

function proxyRequestCurrentEmployeeId() {
    return (int)($_SESSION['employee_id'] ?? 0);
}

function proxyRequestCanAccessEmployee(mysqli $mysqli, int $employeeId): bool {
    $role = proxyRequestCurrentRole();
    if ($role === 'admin') return true;

    $sql = "SELECT e.id FROM employees e WHERE e.id = ? AND e.status IN ('active', 'probation')";
    $types = 'i';
    $params = [$employeeId];

    $scopeClause = hrScopeBuildEmployeeWhereClause($role, hrScopeCurrentSessionScopes(), 'e');
    $sql .= $scopeClause['sql'];
    $types .= $scopeClause['types'];
    $params = array_merge($params, $scopeClause['params']);

    $stmt = $mysqli->prepare($sql);
    hrScopeBindParams($stmt, $types, $params);
    $stmt->execute();
    return $stmt->get_result()->num_rows === 1;
}

function proxyRequestRequireEmployee(mysqli $mysqli, int $employeeId): void {
    if ($employeeId <= 0 || !proxyRequestCanAccessEmployee($mysqli, $employeeId)) {
        throw new InvalidArgumentException('Access Denied');
    }
}

function proxyRequestFetchEmployees(mysqli $mysqli): array {
    $role = proxyRequestCurrentRole();
    $sql = "SELECT e.id, e.citizen_id, e.first_name_th, e.last_name_th
            FROM employees e
            WHERE e.status IN ('active', 'probation')";
    $types = '';
    $params = [];
    if ($role === 'hr') {
        $scopeClause = hrScopeBuildEmployeeWhereClause($role, hrScopeCurrentSessionScopes(), 'e');
        $sql .= $scopeClause['sql'];
        $types .= $scopeClause['types'];
        $params = array_merge($params, $scopeClause['params']);
    }
    $sql .= " ORDER BY e.first_name_th, e.last_name_th";
    $stmt = $mysqli->prepare($sql);
    if ($types !== '') {
        hrScopeBindParams($stmt, $types, $params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
```

- [ ] **Step 5: Implement GET helpers**

Append the GET helper implementations to `api/proxy_request_api.php`:

```php
function proxyRequestFetchLeaveTypes(mysqli $mysqli): array {
    leaveEnsureHourlyRequestTypes($mysqli);
    leaveEnsureLeaveTypeCalculationColumns($mysqli);
    $result = $mysqli->query("SELECT id, type_name, days_per_year, requires_file, calculation_unit, hours_per_day, hour_full_day_threshold, vacation_min_months_before_leave
                              FROM leave_types
                              ORDER BY id ASC");
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    return array_values(array_filter($rows, function ($row) {
        return leaveDetectHourlyRequestType($row['type_name'] ?? '') === null
            && ($row['type_name'] ?? '') !== 'OT หลังเลิกงาน';
    }));
}

function proxyRequestFetchDaySwapHolidays(mysqli $mysqli): array {
    $employeeId = (int)($_GET['employee_id'] ?? 0);
    $month = trim((string)($_GET['month'] ?? date('Y-m')));
    proxyRequestRequireEmployee($mysqli, $employeeId);
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        throw new InvalidArgumentException('Invalid month');
    }
    return daySwapBuildHolidayOptions($mysqli, $employeeId, $month);
}

function proxyRequestCalculateLeave(mysqli $mysqli): array {
    $employeeId = (int)($_GET['employee_id'] ?? 0);
    proxyRequestRequireEmployee($mysqli, $employeeId);
    $start = trim((string)($_GET['start_date'] ?? ''));
    $end = trim((string)($_GET['end_date'] ?? ''));
    return leaveBuildDateSummary(
        $start,
        $end,
        $_GET['start_day_part'] ?? 'full',
        $_GET['end_day_part'] ?? 'full',
        leaveFetchEmployeeWorkDays($mysqli, $employeeId),
        leaveFetchCompanyHolidays($mysqli, $start, $end)
    );
}

function proxyRequestCalculateTimeRequest(mysqli $mysqli): array {
    $employeeId = (int)($_GET['employee_id'] ?? 0);
    proxyRequestRequireEmployee($mysqli, $employeeId);
    $type = proxyRequestNormalizeTimeType($_GET['time_request_type'] ?? '');
    $workDate = trim((string)($_GET['work_date'] ?? ''));
    $requestTime = trim((string)($_GET['request_time'] ?? ''));
    $minutes = (int)($_GET['overtime_minutes'] ?? 0);
    $shift = proxyRequestFetchEffectiveShift($mysqli, $employeeId, $workDate);
    if (!$shift) {
        return ['valid' => false, 'message' => 'ไม่พบข้อมูลกะของพนักงาน', 'request_minutes' => 0];
    }
    if ($type === 'overtime_after_work') {
        if ($minutes < 1 || $minutes > 480) {
            return ['valid' => false, 'message' => 'จำนวน OT ต้องอยู่ระหว่าง 1-480 นาที', 'request_minutes' => $minutes];
        }
        return ['valid' => true, 'message' => '', 'request_minutes' => $minutes, 'shift_start_time' => $shift['start_time'] ?? null, 'shift_end_time' => $shift['end_time'] ?? null];
    }
    return attendanceCalculateTimeRequestMinutes($type, $workDate, $requestTime, $shift);
}
```

- [ ] **Step 4: Implement shared time helpers**

Add:

```php
function proxyRequestNormalizeTimeType($value) {
    return in_array($value, ['late_arrival', 'early_departure', 'overtime_after_work'], true) ? $value : '';
}

function proxyRequestTimeTypeName($type) {
    if ($type === 'overtime_after_work') return 'OT หลังเลิกงาน';
    return $type === 'early_departure' ? 'ขอออกก่อน' : 'ขอมาสาย';
}

function proxyRequestFetchEffectiveShift(mysqli $mysqli, int $employeeId, string $workDate): ?array {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) return null;
    $stmt = $mysqli->prepare("SELECT ws.start_time, ws.end_time, ws.late_tolerance_mins, ws.work_days
                              FROM employees e
                              LEFT JOIN work_shifts ws ON e.default_shift_id = ws.id
                              WHERE e.id = ?");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $shift = $stmt->get_result()->fetch_assoc();
    if (!$shift) return null;
    $month = substr($workDate, 0, 7);
    $overrides = daySwapFetchShiftOverridesForMonth($mysqli, $employeeId, $month);
    $effective = attendanceResolveShiftForDate($shift, $overrides, $workDate);
    $swapMap = attendanceBuildApprovedDaySwapMap(daySwapFetchApprovedRowsForMonth($mysqli, $employeeId, $month), $employeeId, $month);
    return isset($swapMap[$workDate]) ? attendanceApplyDayTypeOverride($effective, $workDate, $swapMap[$workDate]) : $effective;
}

function proxyRequestBindAuditValues(array $audit): array {
    return [
        (int)$audit['created_by_user_id'],
        $audit['created_by_employee_id'] === null ? null : (int)$audit['created_by_employee_id'],
        (string)$audit['created_by_role'],
        (string)$audit['created_via'],
        (string)$audit['proxy_note'],
    ];
}
```

- [ ] **Step 5: Implement `proxyRequestCreateLeave`**

Add code that validates the selected employee, computes the summary, checks duplicate dates, and inserts an approved leave row:

```php
function proxyRequestCreateLeave(mysqli $mysqli): void {
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    proxyRequestRequireEmployee($mysqli, $employeeId);
    $typeId = (int)($_POST['leave_type_id'] ?? 0);
    $start = trim((string)($_POST['start_date'] ?? ''));
    $end = trim((string)($_POST['end_date'] ?? ''));
    $startPart = leaveNormalizeDayPart($_POST['start_day_part'] ?? 'full');
    $endPart = leaveNormalizeDayPart($_POST['end_day_part'] ?? 'full');
    $reason = trim((string)($_POST['reason'] ?? ''));
    if ($typeId <= 0 || $start === '' || $end === '' || $reason === '') {
        throw new InvalidArgumentException('กรุณากรอกข้อมูลให้ครบถ้วน');
    }
    if ($end < $start) {
        throw new InvalidArgumentException('วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่ม');
    }

    $summary = leaveBuildDateSummary($start, $end, $startPart, $endPart, leaveFetchEmployeeWorkDays($mysqli, $employeeId), leaveFetchCompanyHolidays($mysqli, $start, $end));
    if (!$summary['valid']) {
        throw new InvalidArgumentException($summary['message']);
    }
    $requestedDates = array_column($summary['included_dates'] ?? [], 'date');
    $conflicts = leaveFetchConflictingLeaveDates($mysqli, $employeeId, $start, $end, $requestedDates);
    if ($conflicts) {
        throw new InvalidArgumentException('มีใบลาในวันที่เลือกอยู่แล้ว: ' . implode(', ', $conflicts));
    }

    $audit = proxyRequestBuildAuditPayload($_POST['proxy_note'] ?? '');
    $now = date('Y-m-d H:i:s');
    $totalDays = (float)$summary['total_days'];
    $requestUnit = 'day';
    $timeType = null;
    $requestMinutes = 0;
    $approverEmployeeId = (int)($_SESSION['employee_id'] ?? 0) ?: null;

    $mysqli->begin_transaction();
    try {
        $stmt = $mysqli->prepare("INSERT INTO leave_requests
            (employee_id, leave_type_id, start_date, end_date, start_day_part, end_day_part, request_unit, time_request_type, request_minutes, total_days, reason, status, approver_id, approval_date, manager_approver_id, manager_approval_date, hr_approver_id, hr_approval_date, created_by_user_id, created_by_employee_id, created_by_role, created_via, proxy_note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iissssssidsisisisiisss', $employeeId, $typeId, $start, $end, $startPart, $endPart, $requestUnit, $timeType, $requestMinutes, $totalDays, $reason, $approverEmployeeId, $now, $approverEmployeeId, $now, $approverEmployeeId, $now, $audit['created_by_user_id'], $audit['created_by_employee_id'], $audit['created_by_role'], $audit['created_via'], $audit['proxy_note']);
        if (!$stmt->execute()) throw new RuntimeException($stmt->error ?: 'Cannot save proxy leave request');
        $mysqli->commit();
        proxyRequestJson(['status' => 'success', 'message' => 'บันทึกและอนุมัติรายการเรียบร้อยแล้ว', 'data' => $summary]);
    } catch (Throwable $e) {
        $mysqli->rollback();
        throw $e;
    }
}
```

- [ ] **Step 6: Implement `proxyRequestCreateTimeRequest`**

Add the time-request implementation:

```php
function proxyRequestCreateTimeRequest(mysqli $mysqli, bool $isOvertime): void {
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    proxyRequestRequireEmployee($mysqli, $employeeId);
    $type = proxyRequestNormalizeTimeType($_POST['time_request_type'] ?? '');
    if ($isOvertime) $type = 'overtime_after_work';
    $workDate = trim((string)($_POST['work_date'] ?? ''));
    $requestTime = trim((string)($_POST['request_time'] ?? ''));
    $overtimeMinutes = (int)($_POST['overtime_minutes'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? ''));
    if ($type === '' || $workDate === '' || $reason === '' || (!$isOvertime && $requestTime === '')) {
        throw new InvalidArgumentException('กรุณากรอกข้อมูลให้ครบถ้วน');
    }
    $calc = proxyRequestCalculateTimeRequestFromValues($mysqli, $employeeId, $type, $workDate, $requestTime, $overtimeMinutes);
    if (!$calc['valid']) throw new InvalidArgumentException($calc['message']);
    $leaveTypeId = proxyRequestFetchHourlyLeaveTypeId($mysqli, proxyRequestTimeTypeName($type));
    if ($leaveTypeId <= 0) throw new RuntimeException('Time request type not found');
    $payload = leaveBuildHourlyRequestPayload($type, (int)$calc['request_minutes']);
    $audit = proxyRequestBuildAuditPayload($_POST['proxy_note'] ?? '');
    $now = date('Y-m-d H:i:s');
    $part = 'full';
    $totalDays = 0.0;
    $approverEmployeeId = (int)($_SESSION['employee_id'] ?? 0) ?: null;

    $stmt = $mysqli->prepare("INSERT INTO leave_requests
        (employee_id, leave_type_id, start_date, end_date, start_day_part, end_day_part, request_unit, time_request_type, request_minutes, approved_request_minutes, total_days, reason, status, approver_id, approval_date, manager_approver_id, manager_approval_date, hr_approver_id, hr_approval_date, created_by_user_id, created_by_employee_id, created_by_role, created_via, proxy_note)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iissssssiidisisisiisss', $employeeId, $leaveTypeId, $workDate, $workDate, $part, $part, $payload['request_unit'], $payload['time_request_type'], $payload['request_minutes'], $payload['request_minutes'], $totalDays, $reason, $approverEmployeeId, $now, $approverEmployeeId, $now, $approverEmployeeId, $now, $audit['created_by_user_id'], $audit['created_by_employee_id'], $audit['created_by_role'], $audit['created_via'], $audit['proxy_note']);
    if (!$stmt->execute()) throw new RuntimeException($stmt->error ?: 'Cannot save proxy time request');
    proxyRequestJson(['status' => 'success', 'message' => 'บันทึกและอนุมัติรายการเรียบร้อยแล้ว', 'data' => $calc]);
}
```

Also add these support functions:

```php
function proxyRequestCalculateTimeRequestFromValues(mysqli $mysqli, int $employeeId, string $type, string $workDate, string $requestTime, int $overtimeMinutes): array {
    $shift = proxyRequestFetchEffectiveShift($mysqli, $employeeId, $workDate);
    if (!$shift) return ['valid' => false, 'message' => 'ไม่พบข้อมูลกะของพนักงาน', 'request_minutes' => 0];
    if ($type === 'overtime_after_work') {
        if ($overtimeMinutes < 1 || $overtimeMinutes > 480) return ['valid' => false, 'message' => 'จำนวน OT ต้องอยู่ระหว่าง 1-480 นาที', 'request_minutes' => $overtimeMinutes];
        return ['valid' => true, 'message' => '', 'request_minutes' => $overtimeMinutes];
    }
    return attendanceCalculateTimeRequestMinutes($type, $workDate, $requestTime, $shift);
}

function proxyRequestFetchHourlyLeaveTypeId(mysqli $mysqli, string $typeName): int {
    $stmt = $mysqli->prepare("SELECT id FROM leave_types WHERE type_name = ? LIMIT 1");
    $stmt->bind_param('s', $typeName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['id'] ?? 0);
}
```

- [ ] **Step 7: Implement day swap and training create functions**

Add the day swap and training implementations that use existing helpers:

```php
function proxyRequestCreateDaySwap(mysqli $mysqli): void {
    $requesterId = (int)($_POST['requester_employee_id'] ?? $_POST['employee_id'] ?? 0);
    $targetId = (int)($_POST['target_employee_id'] ?? 0);
    proxyRequestRequireEmployee($mysqli, $requesterId);
    proxyRequestRequireEmployee($mysqli, $targetId);
    $requesterDate = trim((string)($_POST['requester_date'] ?? ''));
    $targetDate = trim((string)($_POST['target_date'] ?? ''));
    $reason = trim((string)($_POST['reason'] ?? ''));
    if ($targetId <= 0 || $targetId === $requesterId || $reason === '') throw new InvalidArgumentException('กรุณากรอกข้อมูลให้ครบถ้วน');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $requesterDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) throw new InvalidArgumentException('กรุณาเลือกวันที่ให้ครบ');
    if (!daySwapDateIsSelectableHoliday($mysqli, $requesterId, $requesterDate, substr($requesterDate, 0, 7))) throw new InvalidArgumentException('วันที่ของพนักงานไม่ใช่วันหยุดที่เลือกได้');
    if (!daySwapDateIsSelectableHoliday($mysqli, $targetId, $targetDate, substr($targetDate, 0, 7))) throw new InvalidArgumentException('วันที่ของพนักงานคู่สลับไม่ใช่วันหยุดที่เลือกได้');
    if (daySwapHasPendingOrApprovedConflict($mysqli, $requesterId, $targetId, $requesterDate, $targetDate)) throw new InvalidArgumentException('มีคำขอสลับวันหยุดของวันที่เลือกอยู่แล้ว');
    $audit = proxyRequestBuildAuditPayload($_POST['proxy_note'] ?? '');
    $now = date('Y-m-d H:i:s');
    $approverEmployeeId = (int)($_SESSION['employee_id'] ?? 0) ?: null;
    $stmt = $mysqli->prepare("INSERT INTO day_swap_requests
        (requester_employee_id, target_employee_id, requester_date, target_date, reason, status, approver_id, approval_date, manager_approver_id, manager_approval_date, hr_approver_id, hr_approval_date, created_by_user_id, created_by_employee_id, created_by_role, created_via, proxy_note)
        VALUES (?, ?, ?, ?, ?, 'approved', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iisssisisisiisss', $requesterId, $targetId, $requesterDate, $targetDate, $reason, $approverEmployeeId, $now, $approverEmployeeId, $now, $approverEmployeeId, $now, $audit['created_by_user_id'], $audit['created_by_employee_id'], $audit['created_by_role'], $audit['created_via'], $audit['proxy_note']);
    if (!$stmt->execute()) throw new RuntimeException($stmt->error ?: 'Cannot save proxy day swap request');
    proxyRequestJson(['status' => 'success', 'message' => 'บันทึกและอนุมัติรายการเรียบร้อยแล้ว']);
}

function proxyRequestCreateTraining(mysqli $mysqli): void {
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    proxyRequestRequireEmployee($mysqli, $employeeId);
    $courseName = trainingRequestTrim((string)($_POST['course_name'] ?? ''), 255);
    $provider = trainingRequestTrim((string)($_POST['provider'] ?? ''), 255);
    $trainingType = trainingRequestTrim((string)($_POST['training_type'] ?? ''), 100);
    $startDate = trainingRequestNormalizeDate((string)($_POST['start_date'] ?? ''), 'กรุณาระบุวันที่เริ่มอบรม');
    $endDate = trainingRequestNormalizeDate((string)($_POST['end_date'] ?? ''), 'กรุณาระบุวันที่สิ้นสุดอบรม');
    $location = trainingRequestTrim((string)($_POST['location'] ?? ''), 255);
    $objective = trim((string)($_POST['objective'] ?? ''));
    $estimatedCost = trim((string)($_POST['estimated_cost'] ?? ''));
    if ($courseName === '' || $objective === '') throw new InvalidArgumentException('กรุณากรอกข้อมูลให้ครบถ้วน');
    if ($endDate < $startDate) throw new InvalidArgumentException('วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มอบรม');
    $cost = $estimatedCost === '' ? null : max(0, (float)$estimatedCost);
    $attachmentPath = '';
    if (isset($_FILES['attachment']) && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $attachmentPath = saveEmployeeTrainingAttachment($_FILES['attachment'], $employeeId);
    }
    $audit = proxyRequestBuildAuditPayload($_POST['proxy_note'] ?? '');
    $now = date('Y-m-d H:i:s');
    $approverEmployeeId = (int)($_SESSION['employee_id'] ?? 0) ?: null;
    $mysqli->begin_transaction();
    try {
        $stmt = $mysqli->prepare("INSERT INTO training_requests
            (employee_id, course_name, provider, training_type, start_date, end_date, location, estimated_cost, objective, attachment_path, status, manager_approver_id, manager_approval_date, hr_approver_id, hr_approval_date, approver_id, approval_date, created_by_user_id, created_by_employee_id, created_by_role, created_via, proxy_note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('issssssdssisisiisss', $employeeId, $courseName, $provider, $trainingType, $startDate, $endDate, $location, $cost, $objective, $attachmentPath, $approverEmployeeId, $now, $approverEmployeeId, $now, $approverEmployeeId, $now, $audit['created_by_user_id'], $audit['created_by_employee_id'], $audit['created_by_role'], $audit['created_via'], $audit['proxy_note']);
        if (!$stmt->execute()) throw new RuntimeException($stmt->error ?: 'Cannot save proxy training request');
        $requestId = (int)$stmt->insert_id;
        $request = [
            'id' => $requestId,
            'employee_id' => $employeeId,
            'course_name' => $courseName,
            'provider' => $provider,
            'training_type' => $trainingType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'location' => $location,
            'estimated_cost' => $cost,
            'objective' => $objective,
            'attachment_path' => $attachmentPath,
        ];
        $recordId = trainingRequestCreateHistoryRecord($mysqli, $request, (int)$approverEmployeeId);
        $update = $mysqli->prepare("UPDATE training_requests SET training_record_id = ? WHERE id = ?");
        $update->bind_param('ii', $recordId, $requestId);
        $update->execute();
        $mysqli->commit();
        proxyRequestJson(['status' => 'success', 'message' => 'บันทึกและอนุมัติรายการเรียบร้อยแล้ว']);
    } catch (Throwable $e) {
        $mysqli->rollback();
        throw $e;
    }
}
```

- [ ] **Step 8: Run source test and lint**

Run:

```powershell
C:\xampp\php\php.exe tests\proxy_request_api_source_test.php
C:\xampp\php\php.exe -l api\proxy_request_api.php
```

Expected: source test passes; lint passes. If lint reports a `bind_param` type-string mismatch, fix the type string and rerun.

- [ ] **Step 9: Commit**

```powershell
git add api/proxy_request_api.php tests/proxy_request_api_source_test.php
git commit -m "feat: implement proxy request API"
```

---

### Task 5: Build Proxy Request Page and JavaScript

**Files:**
- Create: `request_proxy.php`
- Create: `assets/js/proxy_request.js`
- Modify: `includes/header.php`
- Test: `tests/proxy_request_ui_source_test.js`

- [ ] **Step 1: Write failing UI source test**

Create `tests/proxy_request_ui_source_test.js`:

```js
const fs = require('fs');

function assertIncludes(file, needle) {
  const source = fs.readFileSync(file, 'utf8');
  if (!source.includes(needle)) {
    throw new Error(`${file} missing ${needle}`);
  }
}

assertIncludes('request_proxy.php', 'id="proxyEmployeeId"');
assertIncludes('request_proxy.php', 'data-proxy-panel="leave"');
assertIncludes('request_proxy.php', 'data-proxy-panel="late_early"');
assertIncludes('request_proxy.php', 'data-proxy-panel="overtime"');
assertIncludes('request_proxy.php', 'data-proxy-panel="day_swap"');
assertIncludes('request_proxy.php', 'data-proxy-panel="training"');
assertIncludes('request_proxy.php', 'assets/js/proxy_request.js');
assertIncludes('assets/js/proxy_request.js', 'api/proxy_request_api.php?action=employees');
assertIncludes('assets/js/proxy_request.js', 'create_leave');
assertIncludes('assets/js/proxy_request.js', 'create_late_early');
assertIncludes('assets/js/proxy_request.js', 'create_overtime');
assertIncludes('assets/js/proxy_request.js', 'create_day_swap');
assertIncludes('assets/js/proxy_request.js', 'create_training');
assertIncludes('includes/header.php', 'request_proxy.php');
```

- [ ] **Step 2: Run test and verify it fails**

Run:

```powershell
node tests\proxy_request_ui_source_test.js
```

Expected: failure because `request_proxy.php` does not exist.

- [ ] **Step 3: Create page shell**

Create `request_proxy.php` with admin/HR guard, Bootstrap form panels, and script include:

```php
<?php
require_once 'includes/auth_check.php';
if (!in_array($_SESSION['role'] ?? '', ['admin', 'hr'], true)) {
    header('Location: dashboard.php');
    exit();
}
$page_title = 'ทำรายการแทนพนักงาน';
$use_select2 = true;
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1 text-gray-800">ทำรายการแทนพนักงาน</h1>
        <p class="text-muted small mb-0">บันทึกคำขอในนามพนักงานและอนุมัติทันที พร้อมเก็บประวัติผู้ทำรายการ</p>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <label class="form-label">พนักงาน <span class="text-danger">*</span></label>
        <select id="proxyEmployeeId" class="form-select" required></select>
    </div>
</div>

<ul class="nav nav-pills mb-3" id="proxyRequestTabs">
    <li class="nav-item"><button class="nav-link active" data-proxy-tab="leave" type="button">ลา</button></li>
    <li class="nav-item"><button class="nav-link" data-proxy-tab="late_early" type="button">มาสาย/ออกก่อน</button></li>
    <li class="nav-item"><button class="nav-link" data-proxy-tab="overtime" type="button">OT</button></li>
    <li class="nav-item"><button class="nav-link" data-proxy-tab="day_swap" type="button">สลับวันหยุด</button></li>
    <li class="nav-item"><button class="nav-link" data-proxy-tab="training" type="button">อบรม</button></li>
</ul>

<div class="proxy-request-panels">
    <form class="card shadow-sm border-0 proxy-panel" data-proxy-panel="leave" data-action="create_leave">
        <div class="card-body row g-3">
            <div class="col-md-6"><label class="form-label">ประเภทการลา</label><select name="leave_type_id" id="proxyLeaveTypeId" class="form-select" required></select></div>
            <div class="col-md-3"><label class="form-label">วันที่เริ่ม</label><input type="date" name="start_date" class="form-control" data-native-date-picker="true" required></div>
            <div class="col-md-3"><label class="form-label">วันที่สิ้นสุด</label><input type="date" name="end_date" class="form-control" data-native-date-picker="true" required></div>
            <div class="col-md-6"><label class="form-label">ช่วงวันเริ่ม</label><select name="start_day_part" class="form-select"><option value="full">เต็มวัน</option><option value="morning">ครึ่งวันเช้า</option><option value="afternoon">ครึ่งวันบ่าย</option></select></div>
            <div class="col-md-6"><label class="form-label">ช่วงวันสิ้นสุด</label><select name="end_day_part" class="form-select"><option value="full">เต็มวัน</option><option value="morning">ครึ่งวันเช้า</option><option value="afternoon">ครึ่งวันบ่าย</option></select></div>
            <div class="col-12"><label class="form-label">เหตุผล</label><textarea name="reason" class="form-control" rows="3" required></textarea></div>
            <div class="col-12"><label class="form-label">หมายเหตุ HR/Admin</label><textarea name="proxy_note" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">บันทึกและอนุมัติทันที</button></div>
        </div>
    </form>
    <form class="card shadow-sm border-0 proxy-panel d-none" data-proxy-panel="late_early" data-action="create_late_early">
        <div class="card-body row g-3">
            <div class="col-md-4"><label class="form-label">ประเภท</label><select name="time_request_type" class="form-select" required><option value="late_arrival">มาสาย</option><option value="early_departure">ออกก่อน</option></select></div>
            <div class="col-md-4"><label class="form-label">วันที่</label><input type="date" name="work_date" class="form-control" data-native-date-picker="true" required></div>
            <div class="col-md-4"><label class="form-label">เวลา</label><input type="time" name="request_time" class="form-control" required></div>
            <div class="col-12"><label class="form-label">เหตุผล</label><textarea name="reason" class="form-control" rows="3" required></textarea></div>
            <div class="col-12"><label class="form-label">หมายเหตุ HR/Admin</label><textarea name="proxy_note" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">บันทึกและอนุมัติทันที</button></div>
        </div>
    </form>
    <form class="card shadow-sm border-0 proxy-panel d-none" data-proxy-panel="overtime" data-action="create_overtime">
        <div class="card-body row g-3">
            <div class="col-md-6"><label class="form-label">วันที่ทำ OT</label><input type="date" name="work_date" class="form-control" data-native-date-picker="true" required></div>
            <div class="col-md-6"><label class="form-label">จำนวนนาที</label><input type="number" name="overtime_minutes" class="form-control" min="1" max="480" required></div>
            <div class="col-12"><label class="form-label">เหตุผล</label><textarea name="reason" class="form-control" rows="3" required></textarea></div>
            <div class="col-12"><label class="form-label">หมายเหตุ HR/Admin</label><textarea name="proxy_note" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">บันทึกและอนุมัติทันที</button></div>
        </div>
    </form>
    <form class="card shadow-sm border-0 proxy-panel d-none" data-proxy-panel="day_swap" data-action="create_day_swap">
        <div class="card-body row g-3">
            <div class="col-md-4"><label class="form-label">พนักงานคู่สลับ</label><select name="target_employee_id" id="proxyTargetEmployeeId" class="form-select" required></select></div>
            <div class="col-md-4"><label class="form-label">วันหยุดของพนักงานหลัก</label><input type="date" name="requester_date" class="form-control" data-native-date-picker="true" required></div>
            <div class="col-md-4"><label class="form-label">วันหยุดของคู่สลับ</label><input type="date" name="target_date" class="form-control" data-native-date-picker="true" required></div>
            <div class="col-12"><label class="form-label">เหตุผล</label><textarea name="reason" class="form-control" rows="3" required></textarea></div>
            <div class="col-12"><label class="form-label">หมายเหตุ HR/Admin</label><textarea name="proxy_note" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">บันทึกและอนุมัติทันที</button></div>
        </div>
    </form>
    <form class="card shadow-sm border-0 proxy-panel d-none" data-proxy-panel="training" data-action="create_training" enctype="multipart/form-data">
        <div class="card-body row g-3">
            <div class="col-md-6"><label class="form-label">หลักสูตร</label><input type="text" name="course_name" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">ผู้จัด/สถาบัน</label><input type="text" name="provider" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">ประเภทอบรม</label><input type="text" name="training_type" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">วันที่เริ่ม</label><input type="date" name="start_date" class="form-control" data-native-date-picker="true" required></div>
            <div class="col-md-4"><label class="form-label">วันที่สิ้นสุด</label><input type="date" name="end_date" class="form-control" data-native-date-picker="true" required></div>
            <div class="col-md-6"><label class="form-label">สถานที่/รูปแบบ</label><input type="text" name="location" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">ค่าใช้จ่ายประมาณ</label><input type="number" name="estimated_cost" class="form-control" min="0" step="0.01"></div>
            <div class="col-12"><label class="form-label">วัตถุประสงค์</label><textarea name="objective" class="form-control" rows="3" required></textarea></div>
            <div class="col-12"><label class="form-label">ไฟล์แนบ</label><input type="file" name="attachment" class="form-control"></div>
            <div class="col-12"><label class="form-label">หมายเหตุ HR/Admin</label><textarea name="proxy_note" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">บันทึกและอนุมัติทันที</button></div>
        </div>
    </form>
</div>

<script src="assets/js/proxy_request.js"></script>
<?php require_once 'includes/footer.php'; ?>
```

Replace the HTML comment with actual forms using the same field names expected by the API:

- `late_early`: `time_request_type`, `work_date`, `request_time`, `reason`, `proxy_note`
- `overtime`: `work_date`, `overtime_minutes`, `reason`, `proxy_note`
- `day_swap`: `target_employee_id`, `requester_date`, `target_date`, `reason`, `proxy_note`
- `training`: `course_name`, `provider`, `training_type`, `start_date`, `end_date`, `location`, `estimated_cost`, `objective`, `attachment`, `proxy_note`

- [ ] **Step 4: Create JavaScript behavior**

Create `assets/js/proxy_request.js`:

```js
(function () {
  const api = 'api/proxy_request_api.php';
  const employeeSelect = document.getElementById('proxyEmployeeId');
  const panels = Array.from(document.querySelectorAll('[data-proxy-panel]'));

  function showPanel(name) {
    panels.forEach((panel) => panel.classList.toggle('d-none', panel.dataset.proxyPanel !== name));
    document.querySelectorAll('[data-proxy-tab]').forEach((tab) => tab.classList.toggle('active', tab.dataset.proxyTab === name));
  }

  function selectedEmployeeId() {
    return employeeSelect ? employeeSelect.value : '';
  }

  async function loadJson(url, options) {
    const response = await fetch(url, options);
    return response.json();
  }

  async function loadEmployees() {
    const result = await loadJson(`${api}?action=employees`);
    if (result.status !== 'success') throw new Error(result.message || 'Load employees failed');
    employeeSelect.innerHTML = '<option value="">เลือกพนักงาน</option>' + result.data.map((row) => {
      const name = `${row.citizen_id || ''} ${row.first_name_th || ''} ${row.last_name_th || ''}`.trim();
      return `<option value="${row.id}">${name}</option>`;
    }).join('');
  }

  async function loadLeaveTypes() {
    const select = document.getElementById('proxyLeaveTypeId');
    if (!select) return;
    const result = await loadJson(`${api}?action=leave_types`);
    if (result.status !== 'success') return;
    select.innerHTML = '<option value="">เลือกประเภทการลา</option>' + result.data.map((row) => `<option value="${row.id}">${row.type_name}</option>`).join('');
  }

  async function submitProxyForm(event) {
    event.preventDefault();
    const form = event.currentTarget;
    if (!selectedEmployeeId()) {
      Swal.fire('กรุณาเลือกพนักงาน', '', 'warning');
      return;
    }
    const data = new FormData(form);
    data.set('employee_id', selectedEmployeeId());
    data.set('action', form.dataset.action);
    const result = await loadJson(`${api}?action=${encodeURIComponent(form.dataset.action)}`, { method: 'POST', body: data });
    if (result.status !== 'success') {
      Swal.fire('ไม่สำเร็จ', result.message || 'System Error', 'error');
      return;
    }
    Swal.fire('สำเร็จ', result.message || 'บันทึกเรียบร้อยแล้ว', 'success');
    form.reset();
  }

  document.querySelectorAll('[data-proxy-tab]').forEach((tab) => tab.addEventListener('click', () => showPanel(tab.dataset.proxyTab)));
  panels.forEach((panel) => panel.addEventListener('submit', submitProxyForm));
  loadEmployees().catch((error) => Swal.fire('ไม่สำเร็จ', error.message, 'error'));
  loadLeaveTypes();
  showPanel('leave');
})();
```

- [ ] **Step 5: Add sidebar link**

In `includes/header.php`, inside the admin/hr menu area near employee management, add:

```php
<a href="request_proxy.php" class="list-group-item list-group-item-action bg-transparent <?php echo isActive('request_proxy.php'); ?>">
    <i class="fas fa-user-pen me-2"></i> ทำรายการแทนพนักงาน
</a>
```

- [ ] **Step 6: Run UI source test and syntax checks**

Run:

```powershell
node tests\proxy_request_ui_source_test.js
C:\xampp\php\php.exe -l request_proxy.php
C:\xampp\php\php.exe -l includes\header.php
node --check assets\js\proxy_request.js
```

Expected: all pass. If the test fails because a form marker is missing, add the missing form markup.

- [ ] **Step 7: Commit**

```powershell
git add request_proxy.php assets/js/proxy_request.js includes/header.php tests/proxy_request_ui_source_test.js
git commit -m "feat: add proxy request page"
```

---

### Task 6: Expose and Render Proxy Creator In History

**Files:**
- Modify: `api/leave_history_api.php`
- Modify: `api/late_early_request_api.php`
- Modify: `api/day_swap_api.php`
- Modify: `api/training_request_api.php`
- Modify: relevant history JS files
- Test: `tests/proxy_request_history_source_test.js`

- [ ] **Step 1: Write failing history source test**

Create `tests/proxy_request_history_source_test.js`:

```js
const fs = require('fs');

const apiFiles = [
  'api/leave_history_api.php',
  'api/late_early_request_api.php',
  'api/day_swap_api.php',
  'api/training_request_api.php',
];

for (const file of apiFiles) {
  const source = fs.readFileSync(file, 'utf8');
  if (!source.includes('created_via') || !source.includes('proxy_creator_name')) {
    throw new Error(`${file} must expose created_via and proxy_creator_name`);
  }
}

const jsFiles = [
  'assets/js/my_leaves.js',
  'assets/js/late_early_request.js',
  'assets/js/day_swap.js',
  'assets/js/training_request.js',
];

for (const file of jsFiles) {
  const source = fs.readFileSync(file, 'utf8');
  if (!source.includes('created_via') || !source.includes('สร้างโดย HR/Admin')) {
    throw new Error(`${file} must render proxy creator text`);
  }
}
```

- [ ] **Step 2: Run test and verify it fails**

Run:

```powershell
node tests\proxy_request_history_source_test.js
```

Expected: failure on missing proxy fields.

- [ ] **Step 3: Add proxy creator joins to history APIs**

In each history query, select:

```sql
lr.created_via,
lr.created_by_role,
lr.proxy_note,
CONCAT_WS(' ', pce.first_name_th, pce.last_name_th) AS proxy_creator_name
```

For non-leave tables, use the table alias already present:

```sql
dsr.created_via,
dsr.created_by_role,
dsr.proxy_note,
CONCAT_WS(' ', pce.first_name_th, pce.last_name_th) AS proxy_creator_name
```

and:

```sql
tr.created_via,
tr.created_by_role,
tr.proxy_note,
CONCAT_WS(' ', pce.first_name_th, pce.last_name_th) AS proxy_creator_name
```

Add a left join:

```sql
LEFT JOIN employees pce ON <request_alias>.created_by_employee_id = pce.id
```

- [ ] **Step 4: Render proxy audit text in history JS**

Add a small helper in each touched JS file:

```js
function proxyCreatorText(row) {
  if (!row || row.created_via !== 'admin_proxy') return '';
  const name = row.proxy_creator_name || row.created_by_role || '';
  return name ? `สร้างโดย HR/Admin: ${escapeHtml(name)}` : 'สร้างโดย HR/Admin';
}
```

If the file uses a differently named HTML escape helper, use that helper instead of `escapeHtml`.

In each history row/card template, render:

```js
const proxyText = proxyCreatorText(row);
const proxyHtml = proxyText ? `<div class="small text-muted mt-1">${proxyText}</div>` : '';
```

Place `proxyHtml` near the status/date metadata so it does not replace the primary status.

- [ ] **Step 5: Run tests and syntax checks**

Run:

```powershell
node tests\proxy_request_history_source_test.js
C:\xampp\php\php.exe -l api\leave_history_api.php
C:\xampp\php\php.exe -l api\late_early_request_api.php
C:\xampp\php\php.exe -l api\day_swap_api.php
C:\xampp\php\php.exe -l api\training_request_api.php
node --check assets\js\my_leaves.js
node --check assets\js\late_early_request.js
node --check assets\js\day_swap.js
node --check assets\js\training_request.js
```

Expected: all pass.

- [ ] **Step 6: Commit**

```powershell
git add api/leave_history_api.php api/late_early_request_api.php api/day_swap_api.php api/training_request_api.php assets/js/my_leaves.js assets/js/late_early_request.js assets/js/day_swap.js assets/js/training_request.js tests/proxy_request_history_source_test.js
git commit -m "feat: show proxy request creator in history"
```

---

### Task 7: Final Verification

**Files:**
- No new files unless failures require focused fixes.

- [ ] **Step 1: Run all new tests**

Run:

```powershell
C:\xampp\php\php.exe tests\proxy_request_helpers_contract_test.php
C:\xampp\php\php.exe tests\proxy_request_schema_source_test.php
C:\xampp\php\php.exe tests\proxy_request_api_source_test.php
node tests\proxy_request_ui_source_test.js
node tests\proxy_request_history_source_test.js
```

Expected: all pass.

- [ ] **Step 2: Run PHP lint on touched PHP files**

Run:

```powershell
C:\xampp\php\php.exe -l includes\proxy_request_helpers.php
C:\xampp\php\php.exe -l includes\leave_helpers.php
C:\xampp\php\php.exe -l includes\day_swap_helpers.php
C:\xampp\php\php.exe -l includes\training_request_helpers.php
C:\xampp\php\php.exe -l api\proxy_request_api.php
C:\xampp\php\php.exe -l request_proxy.php
C:\xampp\php\php.exe -l includes\header.php
```

Expected: `No syntax errors detected` for every file.

- [ ] **Step 3: Run JS syntax checks**

Run:

```powershell
node --check assets\js\proxy_request.js
node --check assets\js\my_leaves.js
node --check assets\js\late_early_request.js
node --check assets\js\day_swap.js
node --check assets\js\training_request.js
```

Expected: no output and exit code 0 for every file.

- [ ] **Step 4: Check staged/working diff hygiene**

Run:

```powershell
git diff --check
git status --short
```

Expected: `git diff --check` has no output. `git status --short` shows only task-owned tracked modifications plus pre-existing unrelated untracked files.

- [ ] **Step 5: Commit final fixes if any**

If verification required small fixes:

```powershell
git add <only-task-owned-files>
git commit -m "fix: harden proxy request workflow"
```

If no fixes were needed, do not create an empty commit.
