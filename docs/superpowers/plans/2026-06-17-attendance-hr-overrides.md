# Attendance HR Overrides Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an admin/HR workflow to correct missing scanner times for one or many employees while preserving raw imported attendance data and showing corrected report status.

**Architecture:** Add a separate `attendance_record_overrides` layer, merge override values over raw scanner values inside attendance report construction, and expose admin/HR save/list endpoints through the existing attendance API. Add one focused adjustment page and JS renderer that reuse existing Select2/DataTables/SweetAlert patterns and existing HR scope helpers.

**Tech Stack:** PHP 8/XAMPP, MySQL/MariaDB via mysqli, Bootstrap, Select2, DataTables, SweetAlert, existing `assets/js/attendance.js`, focused PHP and Node contract tests.

---

## File Structure

- Create `database_attendance_record_overrides.sql`: installable schema for the new override table.
- Modify `includes/attendance_helpers.php`: add pure helpers for normalizing override rows and merging raw/effective scan values.
- Modify `tests/attendance_helpers_test.php`: cover override merge behavior and report-status semantics.
- Modify `api/attendance_api.php`: ensure table exists, load overrides for reports, add adjustment employee lookup and save endpoints.
- Create `attendance_adjustments.php`: admin/HR correction UI for single and bulk edits.
- Modify `assets/js/attendance.js`: initialize the adjustment page, render selectable rows, submit single/bulk saves, and show override metadata in attendance detail popups.
- Create `tests/attendance_adjustments_ui_test.js`: source-level UI contract for the new page and JS row renderer.
- Modify `tests/attendance_calendar_test.js`: assert override metadata appears in report details.
- Modify `includes/header.php`: add the admin/HR sidebar link and active state.
- Create `tests/attendance_adjustments_api_contract_test.php`: source-contract checks for role gates, HR scope usage, transactions, and table preservation.

---

### Task 1: Override Schema and Pure Merge Helpers

**Files:**
- Create: `database_attendance_record_overrides.sql`
- Modify: `includes/attendance_helpers.php`
- Modify: `tests/attendance_helpers_test.php`

- [ ] **Step 1: Write failing helper tests**

Append these assertions before the final `echo "attendance_helpers_test passed"` in `tests/attendance_helpers_test.php`:

```php
$overrideMap = attendanceBuildOverrideMap([
    [
        'employee_id' => '10',
        'work_date' => '2026-01-05',
        'override_check_in' => '08:00:00',
        'override_check_out' => null,
        'reason' => 'Scanner failed in the morning',
        'created_by_name' => 'HR User',
        'updated_by_name' => null,
        'created_at' => '2026-01-06 09:00:00',
        'updated_at' => null,
    ],
]);
assertSameValue(true, isset($overrideMap['2026-01-05']), 'Override map should be keyed by work date.');
assertSameValue('08:00:00', $overrideMap['2026-01-05']['override_check_in'], 'Override map should normalize check-in time.');

$merged = attendanceApplyRecordOverride(
    ['check_in' => null, 'check_out' => '17:02:00'],
    $overrideMap['2026-01-05']
);
assertSameValue('08:00:00', $merged['check_in'], 'Override check-in should replace a missing raw check-in.');
assertSameValue('17:02:00', $merged['check_out'], 'Raw check-out should remain when override check-out is empty.');
assertSameValue(true, $merged['has_override'], 'Merged record should mark rows with an override.');
assertSameValue('Scanner failed in the morning', $merged['override_reason'], 'Merged record should expose the override reason.');

$correctedStatus = attendanceEvaluateStatus(
    '2026-01-05',
    $merged['check_in'],
    $merged['check_out'],
    [
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
        'late_tolerance_mins' => 15,
        'work_days' => 'Mon,Tue,Wed,Thu,Fri',
    ]
);
assertSameValue('present', $correctedStatus['status'], 'Missing-in should become present when HR provides an on-time check-in.');

$lateMerged = attendanceApplyRecordOverride(
    ['check_in' => null, 'check_out' => '17:02:00'],
    [
        'override_check_in' => '08:30:00',
        'override_check_out' => null,
        'reason' => 'Late real arrival',
        'created_by_name' => 'HR User',
        'updated_by_name' => null,
        'created_at' => '2026-01-06 09:00:00',
        'updated_at' => null,
    ]
);
$lateCorrectedStatus = attendanceEvaluateStatus(
    '2026-01-05',
    $lateMerged['check_in'],
    $lateMerged['check_out'],
    [
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
        'late_tolerance_mins' => 15,
        'work_days' => 'Mon,Tue,Wed,Thu,Fri',
    ]
);
assertSameValue('late', $lateCorrectedStatus['status'], 'HR override should not force normal status when the entered time is late.');
```

- [ ] **Step 2: Run helper test and verify it fails**

Run:

```powershell
C:\xampp\php\php.exe tests\attendance_helpers_test.php
```

Expected: FAIL with an undefined function error for `attendanceBuildOverrideMap`.

- [ ] **Step 3: Add schema file**

Create `database_attendance_record_overrides.sql`:

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

- [ ] **Step 4: Implement pure helpers**

Add these functions in `includes/attendance_helpers.php` after `attendanceBuildEmployeeIdMap(...)`:

```php
function attendanceBuildOverrideMap(array $rows) {
    $map = [];
    foreach ($rows as $row) {
        $workDate = $row['work_date'] ?? null;
        if (!$workDate) {
            continue;
        }
        $map[$workDate] = [
            'employee_id' => isset($row['employee_id']) ? (int)$row['employee_id'] : null,
            'work_date' => $workDate,
            'override_check_in' => attendanceNormalizeTime($row['override_check_in'] ?? null),
            'override_check_out' => attendanceNormalizeTime($row['override_check_out'] ?? null),
            'reason' => trim((string)($row['reason'] ?? '')),
            'created_by_name' => trim((string)($row['created_by_name'] ?? '')),
            'updated_by_name' => trim((string)($row['updated_by_name'] ?? '')),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
    return $map;
}

function attendanceApplyRecordOverride(array $record, ?array $override) {
    $checkIn = attendanceNormalizeTime($record['check_in'] ?? null);
    $checkOut = attendanceNormalizeTime($record['check_out'] ?? null);
    if (!$override) {
        return [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'has_override' => false,
            'override_reason' => null,
            'override_check_in' => null,
            'override_check_out' => null,
            'override_created_by_name' => null,
            'override_updated_by_name' => null,
            'override_created_at' => null,
            'override_updated_at' => null,
        ];
    }

    $overrideCheckIn = attendanceNormalizeTime($override['override_check_in'] ?? null);
    $overrideCheckOut = attendanceNormalizeTime($override['override_check_out'] ?? null);
    return [
        'check_in' => $overrideCheckIn ?? $checkIn,
        'check_out' => $overrideCheckOut ?? $checkOut,
        'has_override' => true,
        'override_reason' => trim((string)($override['reason'] ?? '')),
        'override_check_in' => $overrideCheckIn,
        'override_check_out' => $overrideCheckOut,
        'override_created_by_name' => trim((string)($override['created_by_name'] ?? '')),
        'override_updated_by_name' => trim((string)($override['updated_by_name'] ?? '')),
        'override_created_at' => $override['created_at'] ?? null,
        'override_updated_at' => $override['updated_at'] ?? null,
    ];
}
```

- [ ] **Step 5: Run helper test and lint**

Run:

```powershell
C:\xampp\php\php.exe tests\attendance_helpers_test.php
C:\xampp\php\php.exe -l includes\attendance_helpers.php
```

Expected: test prints `attendance_helpers_test passed`; lint prints `No syntax errors detected`.

- [ ] **Step 6: Commit Task 1**

Run:

```powershell
git add -- database_attendance_record_overrides.sql includes\attendance_helpers.php tests\attendance_helpers_test.php
git commit -m "Add attendance override merge helpers"
```

---

### Task 2: Report Integration and API Save Endpoints

**Files:**
- Modify: `api/attendance_api.php`
- Create: `tests/attendance_adjustments_api_contract_test.php`

- [ ] **Step 1: Write failing API source-contract test**

Create `tests/attendance_adjustments_api_contract_test.php`:

```php
<?php
$source = file_get_contents(__DIR__ . '/../api/attendance_api.php');

function assertContainsText($source, $needle, $message) {
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL . 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

assertContainsText($source, "attendance_record_overrides", "API should read/write attendance override rows.");
assertContainsText($source, "function attendanceEnsureOverrideTable", "API should lazily ensure override table exists.");
assertContainsText($source, "function fetchAttendanceOverridesForMonth", "Reports should load override rows by month.");
assertContainsText($source, "attendanceApplyRecordOverride", "Reports should merge override values before evaluating status.");
assertContainsText($source, "\$action === 'adjustment_employees'", "API should expose adjustment employee lookup.");
assertContainsText($source, "\$action === 'save_adjustment'", "API should expose single adjustment save.");
assertContainsText($source, "\$action === 'save_bulk_adjustments'", "API should expose bulk adjustment save.");
assertContainsText($source, "hrScopeBuildEmployeeWhereClause", "API should reuse HR scope helper for adjustment authorization.");
assertContainsText($source, "begin_transaction", "Bulk adjustment saves should use a transaction.");
assertContainsText($source, "commit()", "Bulk adjustment saves should commit only after all rows are valid.");
assertContainsText($source, "rollback()", "Bulk adjustment saves should rollback on failure.");
assertContainsText($source, "override_reason", "Report rows should include override metadata.");

echo "attendance_adjustments_api_contract_test passed" . PHP_EOL;
```

- [ ] **Step 2: Run contract test and verify it fails**

Run:

```powershell
C:\xampp\php\php.exe tests\attendance_adjustments_api_contract_test.php
```

Expected: FAIL on missing `attendance_record_overrides` or `attendanceEnsureOverrideTable`.

- [ ] **Step 3: Add GET action routing**

In the GET block of `api/attendance_api.php`, after `import_summary_detail`, add:

```php
        if ($action === 'adjustment_employees') {
            if (!$canManage) sendJsonError('Access Denied');
            $workDate = $_GET['work_date'] ?? date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) sendJsonError('Invalid date');
            sendJson([
                'status' => 'success',
                'work_date' => $workDate,
                'data' => fetchAttendanceAdjustmentEmployees($mysqli, $role, $workDate, $_GET),
            ]);
        }
```

- [ ] **Step 4: Split POST routing**

Replace the current POST import-only guard with:

```php
    if ($method === 'POST') {
        if (!$canManage) sendJsonError('Access Denied');

        if ($action === 'import') {
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                sendJsonError('กรุณาเลือกไฟล์ CSV');
            }

            $result = importAttendanceCsv($mysqli, $_FILES['csv_file']['tmp_name'], $_FILES['csv_file']['name']);
            sendJson(['status' => 'success'] + $result);
        }

        if ($action === 'save_adjustment') {
            $saved = saveAttendanceAdjustment($mysqli, $role, $_POST);
            sendJson(['status' => 'success', 'saved' => $saved]);
        }

        if ($action === 'save_bulk_adjustments') {
            $saved = saveBulkAttendanceAdjustments($mysqli, $role, $_POST);
            sendJson(['status' => 'success', 'saved' => $saved]);
        }

        sendJsonError('Invalid Action');
    }
```

- [ ] **Step 5: Add table creation and override fetch helpers**

Add these functions near other fetch helpers in `api/attendance_api.php`:

```php
function attendanceEnsureOverrideTable(mysqli $mysqli) {
    $sql = "CREATE TABLE IF NOT EXISTS attendance_record_overrides (
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
    )";
    if (!$mysqli->query($sql)) {
        throw new Exception('Create attendance override table failed: ' . $mysqli->error);
    }
}

function fetchAttendanceOverridesForMonth(mysqli $mysqli, $employeeId, $month) {
    attendanceEnsureOverrideTable($mysqli);
    $stmt = $mysqli->prepare("SELECT aro.employee_id, aro.work_date, aro.override_check_in, aro.override_check_out,
                                     aro.reason, aro.created_at, aro.updated_at,
                                     TRIM(CONCAT(COALESCE(c.first_name_th, ''), ' ', COALESCE(c.last_name_th, ''))) AS created_by_name,
                                     TRIM(CONCAT(COALESCE(u.first_name_th, ''), ' ', COALESCE(u.last_name_th, ''))) AS updated_by_name
                              FROM attendance_record_overrides aro
                              LEFT JOIN users cu ON aro.created_by = cu.id
                              LEFT JOIN employees c ON cu.employee_id = c.id
                              LEFT JOIN users uu ON aro.updated_by = uu.id
                              LEFT JOIN employees u ON uu.employee_id = u.id
                              WHERE aro.employee_id = ? AND DATE_FORMAT(aro.work_date, '%Y-%m') = ?");
    $stmt->bind_param('is', $employeeId, $month);
    $stmt->execute();
    return attendanceBuildOverrideMap($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}
```

- [ ] **Step 6: Merge overrides into monthly reports**

In `buildMonthlyAttendanceReport(...)`, after loading `$records`, add:

```php
    $overrideMap = fetchAttendanceOverridesForMonth($mysqli, (int)$employee['id'], $month);
```

Then replace:

```php
        $record = $records[$workDate] ?? ['check_in' => null, 'check_out' => null];
```

with:

```php
        $rawRecord = $records[$workDate] ?? ['check_in' => null, 'check_out' => null];
        $record = attendanceApplyRecordOverride($rawRecord, $overrideMap[$workDate] ?? null);
```

Add these keys to each report row:

```php
            'raw_check_in' => $rawRecord['check_in'],
            'raw_check_out' => $rawRecord['check_out'],
            'has_override' => $record['has_override'],
            'override_check_in' => $record['override_check_in'],
            'override_check_out' => $record['override_check_out'],
            'override_reason' => $record['override_reason'],
            'override_created_by_name' => $record['override_created_by_name'],
            'override_updated_by_name' => $record['override_updated_by_name'],
            'override_created_at' => $record['override_created_at'],
            'override_updated_at' => $record['override_updated_at'],
```

- [ ] **Step 7: Add adjustment lookup/save helpers**

Add these functions in `api/attendance_api.php`:

```php
function normalizeAttendanceAdjustmentPayload(array $payload) {
    $workDate = trim((string)($payload['work_date'] ?? ''));
    $checkIn = attendanceNormalizeTime($payload['override_check_in'] ?? '');
    $checkOut = attendanceNormalizeTime($payload['override_check_out'] ?? '');
    $reason = trim((string)($payload['reason'] ?? ''));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) {
        sendJsonError('Invalid date');
    }
    if ($checkIn === null && $checkOut === null) {
        sendJsonError('กรุณาระบุเวลาเข้า หรือเวลาออก');
    }
    if ($reason === '') {
        sendJsonError('กรุณาระบุเหตุผล');
    }

    return [$workDate, $checkIn, $checkOut, $reason];
}

function saveAttendanceOverrideRow(mysqli $mysqli, $employeeId, $workDate, $checkIn, $checkOut, $reason, $userId) {
    attendanceEnsureOverrideTable($mysqli);
    $stmt = $mysqli->prepare("INSERT INTO attendance_record_overrides
        (employee_id, work_date, override_check_in, override_check_out, reason, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            override_check_in = VALUES(override_check_in),
            override_check_out = VALUES(override_check_out),
            reason = VALUES(reason),
            updated_by = VALUES(created_by),
            updated_at = NOW()");
    $stmt->bind_param('issssi', $employeeId, $workDate, $checkIn, $checkOut, $reason, $userId);
    if (!$stmt->execute()) {
        throw new Exception('Save attendance override failed: ' . $stmt->error);
    }
    return $employeeId;
}

function saveAttendanceAdjustment(mysqli $mysqli, $role, array $payload) {
    $employeeId = (int)($payload['employee_id'] ?? 0);
    if ($employeeId <= 0 || !attendanceCanViewEmployee($mysqli, $employeeId)) {
        sendJsonError('Access Denied');
    }
    [$workDate, $checkIn, $checkOut, $reason] = normalizeAttendanceAdjustmentPayload($payload);
    saveAttendanceOverrideRow($mysqli, $employeeId, $workDate, $checkIn, $checkOut, $reason, (int)$_SESSION['user_id']);
    return 1;
}

function saveBulkAttendanceAdjustments(mysqli $mysqli, $role, array $payload) {
    [$workDate, $checkIn, $checkOut, $reason] = normalizeAttendanceAdjustmentPayload($payload);
    $employeeIds = array_values(array_unique(array_map('intval', $payload['employee_ids'] ?? [])));
    $employeeIds = array_filter($employeeIds, fn($id) => $id > 0);
    if (!$employeeIds) {
        sendJsonError('กรุณาเลือกพนักงาน');
    }

    $mysqli->begin_transaction();
    try {
        foreach ($employeeIds as $employeeId) {
            if (!attendanceCanViewEmployee($mysqli, $employeeId)) {
                throw new InvalidArgumentException('Access Denied');
            }
            saveAttendanceOverrideRow($mysqli, $employeeId, $workDate, $checkIn, $checkOut, $reason, (int)$_SESSION['user_id']);
        }
        $mysqli->commit();
        return count($employeeIds);
    } catch (Throwable $e) {
        $mysqli->rollback();
        if ($e instanceof InvalidArgumentException) {
            sendJsonError($e->getMessage());
        }
        throw $e;
    }
}
```

- [ ] **Step 8: Implement adjustment employee query**

Add:

```php
function fetchAttendanceAdjustmentEmployees(mysqli $mysqli, $role, $workDate, array $filters) {
    attendanceEnsureOverrideTable($mysqli);
    $sql = "SELECT e.id AS employee_id, e.citizen_id, e.first_name_th, e.last_name_th,
                   p.name_th AS position_name_th, b.name_th AS branch_name_th, c.name_th AS company_name_th,
                   ar.check_in AS raw_check_in, ar.check_out AS raw_check_out,
                   aro.override_check_in, aro.override_check_out, aro.reason AS override_reason
            FROM employees e
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN branches b ON e.branch_id = b.id
            LEFT JOIN companies c ON e.company_id = c.id
            LEFT JOIN attendance_records ar ON ar.employee_id = e.id AND ar.work_date = ?
            LEFT JOIN attendance_record_overrides aro ON aro.employee_id = e.id AND aro.work_date = ?
            WHERE e.status IN ('active', 'probation')";
    $types = 'ss';
    $params = [$workDate, $workDate];

    if ($role === 'hr') {
        $scopeClause = hrScopeBuildEmployeeWhereClause($role, hrScopeCurrentSessionScopes(), 'e');
        $sql .= $scopeClause['sql'];
        $types .= $scopeClause['types'];
        $params = array_merge($params, $scopeClause['params']);
    }

    foreach (['position_id' => 'e.position_id', 'branch_id' => 'e.branch_id', 'company_id' => 'e.company_id'] as $key => $column) {
        if ((int)($filters[$key] ?? 0) > 0) {
            $sql .= " AND {$column} = ?";
            $types .= 'i';
            $params[] = (int)$filters[$key];
        }
    }
    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $sql .= " AND (e.first_name_th LIKE ? OR e.last_name_th LIKE ? OR e.citizen_id LIKE ?)";
        $types .= 'sss';
        $term = '%' . $search . '%';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $sql .= " ORDER BY e.first_name_th, e.last_name_th";
    $stmt = $mysqli->prepare($sql);
    hrScopeBindParams($stmt, $types, $params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
```

- [ ] **Step 9: Run API test and lint**

Run:

```powershell
C:\xampp\php\php.exe tests\attendance_adjustments_api_contract_test.php
C:\xampp\php\php.exe -l api\attendance_api.php
C:\xampp\php\php.exe tests\attendance_helpers_test.php
```

Expected: all tests pass; lint reports no syntax errors.

- [ ] **Step 10: Commit Task 2**

Run:

```powershell
git add -- api\attendance_api.php tests\attendance_adjustments_api_contract_test.php
git commit -m "Wire attendance override reports and API"
```

---

### Task 3: Adjustment Page, Sidebar, and UI Renderer

**Files:**
- Create: `attendance_adjustments.php`
- Modify: `includes/header.php`
- Modify: `assets/js/attendance.js`
- Create: `tests/attendance_adjustments_ui_test.js`

- [ ] **Step 1: Write failing UI contract test**

Create `tests/attendance_adjustments_ui_test.js`:

```js
const fs = require('fs');
const vm = require('vm');

global.document = { addEventListener() {} };

vm.runInThisContext(fs.readFileSync('assets/js/utils.js', 'utf8'));
vm.runInThisContext(fs.readFileSync('assets/js/attendance.js', 'utf8'));

const page = fs.readFileSync('attendance_adjustments.php', 'utf8');
const header = fs.readFileSync('includes/header.php', 'utf8');

function assertIncludes(text, expected, message) {
    if (!String(text).includes(expected)) {
        console.error(message);
        console.error('Missing:', expected);
        process.exit(1);
    }
}

const row = buildAttendanceAdjustmentEmployeeRowHtml({
    employee_id: 15,
    first_name_th: 'Somchai',
    last_name_th: 'Jaidee',
    citizen_id: '1234567890123',
    position_name_th: 'Technician',
    branch_name_th: 'Bangkok',
    company_name_th: 'ACME',
    raw_check_in: null,
    raw_check_out: '17:05:00',
    override_check_in: '08:00:00',
    override_check_out: null,
    override_reason: 'Scanner failed',
});

assertIncludes(row, 'Somchai Jaidee', 'Adjustment row should show full employee name.');
assertIncludes(row, 'Technician', 'Adjustment row should show position.');
assertIncludes(row, 'Bangkok', 'Adjustment row should show branch.');
assertIncludes(row, 'ACME', 'Adjustment row should show company.');
assertIncludes(row, '17:05', 'Adjustment row should show raw checkout.');
assertIncludes(row, '08:00', 'Adjustment row should show existing override check-in.');
assertIncludes(row, 'Scanner failed', 'Adjustment row should show existing override reason.');
assertIncludes(row, 'type="checkbox"', 'Adjustment row should be selectable for bulk saves.');

assertIncludes(page, 'attendanceAdjustmentPage', 'Adjustment page should expose the JS mount point.');
assertIncludes(page, 'data-native-date-picker="true"', 'Adjustment date fields should keep native date picker behavior.');
assertIncludes(page, 'attendanceBulkSaveBtn', 'Adjustment page should include a bulk save button.');
assertIncludes(page, 'attendanceSingleSaveForm', 'Adjustment page should include a single save form.');
assertIncludes(header, 'attendance_adjustments.php', 'Sidebar should link to attendance adjustments.');

console.log('attendance_adjustments_ui_test passed');
```

- [ ] **Step 2: Run UI test and verify it fails**

Run:

```powershell
node tests\attendance_adjustments_ui_test.js
```

Expected: FAIL because `attendance_adjustments.php` or `buildAttendanceAdjustmentEmployeeRowHtml` does not exist.

- [ ] **Step 3: Create adjustment page**

Create `attendance_adjustments.php`:

```php
<?php
require_once 'includes/auth_check.php';
if (!in_array($_SESSION['role'], ['admin', 'hr'], true)) {
    header('Location: dashboard.php');
    exit();
}

$page_title = "ปรับแก้เวลาสแกน";
$use_select2 = true;
require_once 'includes/header.php';
?>

<div id="attendanceAdjustmentPage" class="attendance-adjustments-page">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">ปรับแก้เวลาสแกน</h1>
            <p class="text-muted small mb-0">แก้ไขเวลาเข้าออกเมื่อเครื่องสแกนไม่บันทึก โดยไม่ทับข้อมูลสแกนจริง</p>
        </div>
        <a href="attendance.php" class="btn btn-outline-secondary">
            <i class="fas fa-calendar-days me-1"></i> ดูปฏิทินเวลา
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">แก้รายคน</h2>
                    <form id="attendanceSingleSaveForm">
                        <input type="hidden" name="action" value="save_adjustment">
                        <div class="mb-3">
                            <label class="form-label">พนักงาน</label>
                            <select id="attendanceSingleEmployee" name="employee_id" class="form-select attendance-select2" data-placeholder="เลือกพนักงาน"></select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">วันที่</label>
                            <input type="date" id="attendanceSingleDate" name="work_date" class="form-control" data-native-date-picker="true" required>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">เวลาเข้า</label>
                                <input type="time" name="override_check_in" class="form-control">
                            </div>
                            <div class="col-6">
                                <label class="form-label">เวลาออก</label>
                                <input type="time" name="override_check_out" class="form-control">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">เหตุผล</label>
                            <textarea name="reason" class="form-control" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mt-3">
                            <i class="fas fa-floppy-disk me-1"></i> บันทึกรายคน
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h2 class="h5 mb-3">แก้หลายคน</h2>
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-4">
                            <label class="form-label">วันที่</label>
                            <input type="date" id="attendanceBulkDate" class="form-control" data-native-date-picker="true">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ค้นหา</label>
                            <input type="search" id="attendanceAdjustmentSearch" class="form-control" placeholder="ชื่อ หรือเลขบัตร">
                        </div>
                        <div class="col-md-4">
                            <button type="button" id="attendanceAdjustmentLoadBtn" class="btn btn-outline-primary w-100">
                                <i class="fas fa-search me-1"></i> โหลดรายชื่อ
                            </button>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-4"><select id="attendanceAdjustmentCompany" class="form-select attendance-select2" data-placeholder="บริษัท"></select></div>
                        <div class="col-md-4"><select id="attendanceAdjustmentBranch" class="form-select attendance-select2" data-placeholder="สาขา"></select></div>
                        <div class="col-md-4"><select id="attendanceAdjustmentPosition" class="form-select attendance-select2" data-placeholder="ตำแหน่ง"></select></div>
                    </div>

                    <div class="table-responsive">
                        <table id="attendanceAdjustmentTable" class="table table-sm table-hover align-middle">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="attendanceAdjustmentSelectAll"></th>
                                    <th>พนักงาน</th>
                                    <th>ตำแหน่ง</th>
                                    <th>สาขา</th>
                                    <th>บริษัท</th>
                                    <th>ข้อมูลสแกน/ปรับแก้</th>
                                </tr>
                            </thead>
                            <tbody id="attendanceAdjustmentRows">
                                <tr><td colspan="6" class="text-center text-muted py-4">เลือกวันที่แล้วโหลดรายชื่อ</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <form id="attendanceBulkSaveForm" class="border-top pt-3 mt-3">
                        <div class="row g-2">
                            <div class="col-md-3"><input type="time" name="override_check_in" class="form-control" aria-label="เวลาเข้า"></div>
                            <div class="col-md-3"><input type="time" name="override_check_out" class="form-control" aria-label="เวลาออก"></div>
                            <div class="col-md-4"><input type="text" name="reason" class="form-control" placeholder="เหตุผล" required></div>
                            <div class="col-md-2">
                                <button type="submit" id="attendanceBulkSaveBtn" class="btn btn-primary w-100">
                                    <i class="fas fa-users-gear me-1"></i> บันทึก
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
```

- [ ] **Step 4: Add sidebar link**

In `includes/header.php`, after `attendance_import.php`, add:

```php
            <a href="attendance_adjustments.php" class="list-group-item list-group-item-action bg-transparent <?php echo isActive('attendance_adjustments.php'); ?>">
                <i class="fas fa-pen-to-square me-2"></i> ปรับแก้เวลาสแกน
            </a>
```

- [ ] **Step 5: Add JS row renderer and initialization**

In `assets/js/attendance.js`, add this function near other row builders:

```js
function buildAttendanceAdjustmentEmployeeRowHtml(row) {
    const fullName = `${row.first_name_th || ''} ${row.last_name_th || ''}`.trim() || '-';
    const rawIn = formatTime(row.raw_check_in);
    const rawOut = formatTime(row.raw_check_out);
    const overrideIn = formatTime(row.override_check_in);
    const overrideOut = formatTime(row.override_check_out);
    const reason = row.override_reason ? `<div class="small text-primary mt-1">เหตุผล: ${escapeHtml(row.override_reason)}</div>` : '';
    return `
        <tr>
            <td><input type="checkbox" class="attendance-adjustment-select" value="${escapeAttr(row.employee_id)}"></td>
            <td>
                <div class="fw-semibold">${escapeHtml(fullName)}</div>
                <div class="small text-muted">${escapeHtml(row.citizen_id || '-')}</div>
            </td>
            <td>${escapeHtml(row.position_name_th || '-')}</td>
            <td>${escapeHtml(row.branch_name_th || '-')}</td>
            <td>${escapeHtml(row.company_name_th || '-')}</td>
            <td>
                <div class="small">สแกน: ${rawIn} - ${rawOut}</div>
                <div class="small">ปรับแก้: ${overrideIn} - ${overrideOut}</div>
                ${reason}
            </td>
        </tr>`;
}
```

Then extend `DOMContentLoaded`:

```js
    const adjustmentPage = document.getElementById('attendanceAdjustmentPage');
    if (adjustmentPage) {
        initAttendanceAdjustments();
    }
```

Add these functions after `buildAttendanceAdjustmentEmployeeRowHtml(...)`:

```js
let attendanceAdjustmentRows = [];
let attendanceAdjustmentDataTable = null;

function initAttendanceAdjustments() {
    $('.attendance-select2').select2({ width: '100%', allowClear: true });

    const today = new Date().toISOString().slice(0, 10);
    const singleDate = document.getElementById('attendanceSingleDate');
    const bulkDate = document.getElementById('attendanceBulkDate');
    if (singleDate && !singleDate.value) singleDate.value = today;
    if (bulkDate && !bulkDate.value) bulkDate.value = today;

    const loadBtn = document.getElementById('attendanceAdjustmentLoadBtn');
    if (loadBtn) loadBtn.addEventListener('click', loadAttendanceAdjustmentEmployees);

    const selectAll = document.getElementById('attendanceAdjustmentSelectAll');
    if (selectAll) {
        selectAll.addEventListener('change', () => {
            document.querySelectorAll('.attendance-adjustment-select').forEach(input => {
                input.checked = selectAll.checked;
            });
        });
    }

    const singleForm = document.getElementById('attendanceSingleSaveForm');
    if (singleForm) {
        singleForm.addEventListener('submit', (event) => saveAttendanceAdjustmentForm(event, false));
    }

    const bulkForm = document.getElementById('attendanceBulkSaveForm');
    if (bulkForm) {
        bulkForm.addEventListener('submit', (event) => saveAttendanceAdjustmentForm(event, true));
    }

    loadAttendanceAdjustmentEmployees();
}

async function loadAttendanceAdjustmentEmployees() {
    const rowsEl = document.getElementById('attendanceAdjustmentRows');
    const workDate = document.getElementById('attendanceBulkDate')?.value || new Date().toISOString().slice(0, 10);
    const params = new URLSearchParams({
        action: 'adjustment_employees',
        work_date: workDate,
        search: document.getElementById('attendanceAdjustmentSearch')?.value || '',
        company_id: document.getElementById('attendanceAdjustmentCompany')?.value || '',
        branch_id: document.getElementById('attendanceAdjustmentBranch')?.value || '',
        position_id: document.getElementById('attendanceAdjustmentPosition')?.value || '',
    });

    if (rowsEl) {
        rowsEl.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">กำลังโหลดรายชื่อ...</td></tr>';
    }

    const response = await fetch(`api/attendance_api.php?${params.toString()}`);
    const res = await response.json();
    if (res.status !== 'success') {
        if (rowsEl) rowsEl.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${escapeHtml(res.message || 'โหลดรายชื่อไม่สำเร็จ')}</td></tr>`;
        return;
    }

    attendanceAdjustmentRows = res.data || [];
    renderAttendanceAdjustmentEmployees(attendanceAdjustmentRows);
    syncAttendanceSingleEmployeeOptions(attendanceAdjustmentRows);
}

function renderAttendanceAdjustmentEmployees(rows) {
    const rowsEl = document.getElementById('attendanceAdjustmentRows');
    if (!rowsEl) return;
    if (attendanceAdjustmentDataTable) {
        attendanceAdjustmentDataTable.destroy();
        attendanceAdjustmentDataTable = null;
    }
    if (!rows.length) {
        rowsEl.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">ไม่พบพนักงาน</td></tr>';
        return;
    }
    rowsEl.innerHTML = rows.map(buildAttendanceAdjustmentEmployeeRowHtml).join('');
    const table = $('#attendanceAdjustmentTable');
    if (rows.length > 10 && table.length) {
        attendanceAdjustmentDataTable = table.DataTable({
            pageLength: 10,
            order: [],
            language: { search: 'ค้นหา:', lengthMenu: 'แสดง _MENU_ รายการ', info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ' },
        });
    }
}

function syncAttendanceSingleEmployeeOptions(rows) {
    const select = document.getElementById('attendanceSingleEmployee');
    if (!select) return;
    const currentValue = select.value;
    select.innerHTML = '<option value="">เลือกพนักงาน</option>' + rows.map(row => {
        const fullName = `${row.first_name_th || ''} ${row.last_name_th || ''}`.trim() || '-';
        return `<option value="${escapeAttr(row.employee_id)}">${escapeHtml(fullName)}</option>`;
    }).join('');
    select.value = currentValue;
    $(select).trigger('change.select2');
}

function getSelectedAttendanceAdjustmentEmployeeIds() {
    return Array.from(document.querySelectorAll('.attendance-adjustment-select:checked'))
        .map(input => input.value)
        .filter(Boolean);
}

async function saveAttendanceAdjustmentForm(event, isBulk) {
    event.preventDefault();
    const form = event.currentTarget;
    const formData = new FormData(form);
    formData.set('action', isBulk ? 'save_bulk_adjustments' : 'save_adjustment');

    if (isBulk) {
        const workDate = document.getElementById('attendanceBulkDate')?.value || '';
        const employeeIds = getSelectedAttendanceAdjustmentEmployeeIds();
        formData.set('work_date', workDate);
        employeeIds.forEach(id => formData.append('employee_ids[]', id));
    }

    const response = await fetch('api/attendance_api.php', { method: 'POST', body: formData });
    const res = await response.json();
    if (res.status !== 'success') {
        Swal.fire('Error', res.message || 'บันทึกไม่สำเร็จ', 'error');
        return;
    }

    Swal.fire('สำเร็จ', `บันทึก ${Number(res.saved || 0).toLocaleString('th-TH')} รายการ`, 'success');
    loadAttendanceAdjustmentEmployees();
}
```

- [ ] **Step 6: Run UI test and JS syntax check**

Run:

```powershell
node tests\attendance_adjustments_ui_test.js
node --check assets\js\attendance.js
C:\xampp\php\php.exe -l attendance_adjustments.php
C:\xampp\php\php.exe -l includes\header.php
```

Expected: UI test passes; syntax checks pass.

- [ ] **Step 7: Commit Task 3**

Run:

```powershell
git add -- attendance_adjustments.php includes\header.php assets\js\attendance.js tests\attendance_adjustments_ui_test.js
git commit -m "Add attendance adjustment page"
```

---

### Task 4: Attendance Detail Override Metadata

**Files:**
- Modify: `assets/js/attendance.js`
- Modify: `tests/attendance_calendar_test.js`

- [ ] **Step 1: Write failing calendar detail assertion**

Append after the `leaveDetails` assertions in `tests/attendance_calendar_test.js`:

```js
const overrideDetails = buildAttendanceCalendarDetails({
    work_date: '2026-01-09',
    day_name: 'Fri',
    check_in: '08:00:00',
    check_out: '17:00:00',
    raw_check_in: null,
    raw_check_out: '17:00:00',
    status: 'present',
    status_label: 'ปกติ',
    has_override: true,
    override_check_in: '08:00:00',
    override_check_out: null,
    override_reason: 'เครื่องสแกนเสียช่วงเช้า',
    override_created_by_name: 'ฝ่ายบุคคล',
});
assertIncludes(overrideDetails, 'ปรับโดย HR', 'Popup details should disclose HR attendance corrections.');
assertIncludes(overrideDetails, 'เครื่องสแกนเสียช่วงเช้า', 'Popup details should include the override reason.');
assertIncludes(overrideDetails, 'ฝ่ายบุคคล', 'Popup details should include the adjusting HR user when available.');
```

- [ ] **Step 2: Run calendar test and verify it fails**

Run:

```powershell
node tests\attendance_calendar_test.js
```

Expected: FAIL because override metadata is not displayed.

- [ ] **Step 3: Update detail HTML builder**

In `buildAttendanceCalendarDetails(row)` in `assets/js/attendance.js`, add an override section:

```js
    const overrideHtml = row.has_override ? `
        <div class="alert alert-info text-start mt-3 mb-0 py-2">
            <div class="fw-semibold">ปรับโดย HR</div>
            <div class="small">เหตุผล: ${escapeHtml(row.override_reason || '-')}</div>
            <div class="small">ผู้แก้: ${escapeHtml(row.override_updated_by_name || row.override_created_by_name || '-')}</div>
        </div>` : '';
```

Include `${overrideHtml}` before the closing wrapper returned by the function.

- [ ] **Step 4: Run calendar test and syntax check**

Run:

```powershell
node tests\attendance_calendar_test.js
node --check assets\js\attendance.js
```

Expected: test passes; syntax check passes.

- [ ] **Step 5: Commit Task 4**

Run:

```powershell
git add -- assets\js\attendance.js tests\attendance_calendar_test.js
git commit -m "Show attendance override details"
```

---

### Task 5: Final Verification

**Files:**
- Verify all files from Tasks 1-4.

- [ ] **Step 1: Run PHP lint for touched PHP files**

Run:

```powershell
C:\xampp\php\php.exe -l includes\attendance_helpers.php
C:\xampp\php\php.exe -l api\attendance_api.php
C:\xampp\php\php.exe -l attendance_adjustments.php
C:\xampp\php\php.exe -l includes\header.php
```

Expected: every command reports `No syntax errors detected`.

- [ ] **Step 2: Run focused tests**

Run:

```powershell
C:\xampp\php\php.exe tests\attendance_helpers_test.php
C:\xampp\php\php.exe tests\attendance_adjustments_api_contract_test.php
node tests\attendance_adjustments_ui_test.js
node tests\attendance_calendar_test.js
node tests\attendance_import_detail_ui_test.js
```

Expected: all tests print their `passed` message.

- [ ] **Step 3: Run JS syntax check**

Run:

```powershell
node --check assets\js\attendance.js
```

Expected: no output and exit code 0.

- [ ] **Step 4: Check whitespace and staged scope**

Run:

```powershell
git diff --check
git status --short
```

Expected: `git diff --check` has no output; status only shows intentional files if any are uncommitted.

- [ ] **Step 5: Manual QA checklist**

Use a local browser with an admin/HR session:

```text
1. Open attendance_adjustments.php.
2. Pick a date with a missing scan.
3. Save one employee correction with a reason.
4. Open attendance.php for that employee/month and confirm the day changes from missing to normal or late based on the entered time.
5. Confirm the day popup shows "ปรับโดย HR" and the reason.
6. Use bulk mode for two employees and confirm both report rows update.
7. Confirm attendance_records values are unchanged and attendance_record_overrides contains the corrections.
8. Confirm an HR-scoped account cannot load or save employees outside its scope.
```

- [ ] **Step 6: Final commit if needed**

If manual QA or final verification required small fixes in the files from this plan, commit the touched feature files:

```powershell
git add -- database_attendance_record_overrides.sql includes\attendance_helpers.php api\attendance_api.php attendance_adjustments.php includes\header.php assets\js\attendance.js tests\attendance_helpers_test.php tests\attendance_adjustments_api_contract_test.php tests\attendance_adjustments_ui_test.js tests\attendance_calendar_test.js
git commit -m "Polish attendance override workflow"
```
