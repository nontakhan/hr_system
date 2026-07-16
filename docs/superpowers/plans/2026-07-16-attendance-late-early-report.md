# Attendance Late/Early Report Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a scoped, bulk-loaded admin/HR report for late arrivals and early departures after deducting approved late/early request minutes.

**Architecture:** Extend the existing missing-scan report pattern with pure calculation/filter helpers, a bulk API report builder, a dedicated Bootstrap page and JavaScript renderer, and a sidebar entry. Keep deviation rules in `includes/attendance_helpers.php`, database loading and effective-shift construction in `api/attendance_api.php`, and browser rendering in `assets/js/attendance.js`.

**Tech Stack:** PHP 7+/MySQLi, MariaDB, Bootstrap, vanilla JavaScript, jQuery DataTables/Select2, focused PHP and Node.js contract tests.

## Global Constraints

- Only `admin` and `hr` may access the aggregate report.
- Reuse the missing-scan report's HR company/branch scope and active/probation employee rules.
- Persist and compare Gregorian dates; render dates with existing Thai UI helpers.
- Use bulk monthly loaders; never call `buildMonthlyAttendanceReport(...)` inside an employee loop.
- Deduct only final `approved` late/early requests, preferring `approved_request_minutes` and falling back to `request_minutes`.
- A single employee/date produces at most one row, even when both incident flags exist.
- Do not change schema, attendance imports, the individual calendar, or approval workflows.

---

## File Structure

- Create `attendance_late_early_report.php`: role-gated report markup.
- Create `tests/attendance_late_early_report_ui_test.js`: page/renderer contract.
- Modify `includes/attendance_helpers.php`: pure incident calculations, filtering, and summaries.
- Modify `tests/attendance_helpers_test.php`: calculation and deduction regressions.
- Modify `api/attendance_api.php`: action, approved-request bulk loader, and report builder.
- Modify `tests/attendance_api_source_test.php`: API scope/performance contract.
- Modify `assets/js/attendance.js`: filters, fetch, summary, table, and DataTable lifecycle.
- Modify `includes/header.php`: report menu link and active state.
- Modify `tests/sidebar_hybrid_menu_test.js`: navigation contract.

### Task 1: Pure late/early incident rules

**Files:**
- Modify: `tests/attendance_helpers_test.php`
- Modify: `includes/attendance_helpers.php`

**Interfaces:**
- Produces: `attendanceNormalizeLateEarlyIncidentType($type): string`
- Produces: `attendanceCalculateLateEarlyIncident($workDate, $checkIn, $checkOut, array $shift, array $approvedMinutes = []): array|null`
- Produces: `attendanceFilterLateEarlyReportRows(array $rows, $type = 'all'): array`
- Produces: `attendanceCountLateEarlyRows(array $rows): array`

- [ ] **Step 1: Add failing helper tests**

Append these cases before the final tests in `tests/attendance_helpers_test.php`:

```php
assertSameValue('all', attendanceNormalizeLateEarlyIncidentType('bad'), 'Unknown late/early filters should normalize to all.');
assertSameValue('late', attendanceNormalizeLateEarlyIncidentType('late'), 'Late filter should be accepted.');
assertSameValue('early', attendanceNormalizeLateEarlyIncidentType('early'), 'Early filter should be accepted.');

$bothIncident = attendanceCalculateLateEarlyIncident(
    '2026-07-06',
    '08:20:00',
    '16:40:00',
    ['start_time' => '08:00:00', 'end_time' => '17:00:00', 'late_tolerance_mins' => 5, 'work_days' => 'Mon,Tue,Wed,Thu,Fri'],
    ['late_arrival' => 15, 'early_departure' => 10]
);
assertSameValue(5, $bothIncident['late_minutes'], 'Approved late minutes should be deducted from actual late minutes.');
assertSameValue(10, $bothIncident['early_minutes'], 'Approved early minutes should be deducted from actual early minutes.');
assertSameValue(true, $bothIncident['is_late'], 'Partially uncovered lateness should remain reportable.');
assertSameValue(true, $bothIncident['is_early'], 'Partially uncovered early departure should remain reportable.');

$coveredIncident = attendanceCalculateLateEarlyIncident(
    '2026-07-07',
    '08:10:00',
    '16:50:00',
    ['start_time' => '08:00:00', 'end_time' => '17:00:00', 'late_tolerance_mins' => 5, 'work_days' => 'Mon,Tue,Wed,Thu,Fri'],
    ['late_arrival' => 10, 'early_departure' => 10]
);
assertSameValue(null, $coveredIncident, 'Fully approved deviations should not appear in the report.');

$withinTolerance = attendanceCalculateLateEarlyIncident(
    '2026-07-08',
    '08:05:00',
    '17:00:00',
    ['start_time' => '08:00:00', 'end_time' => '17:00:00', 'late_tolerance_mins' => 5, 'work_days' => 'Mon,Tue,Wed,Thu,Fri']
);
assertSameValue(null, $withinTolerance, 'Check-in at the tolerance boundary should not be late.');

assertSameValue(null, attendanceCalculateLateEarlyIncident(
    '2026-07-09', null, '16:30:00',
    ['start_time' => '08:00:00', 'end_time' => '17:00:00', 'late_tolerance_mins' => 0, 'work_days' => 'Mon,Tue,Wed,Thu,Fri']
), 'Rows with a missing scan belong only to the missing-scan report.');

$incidentRows = attendanceFilterLateEarlyReportRows([
    ['employee_id' => 1, 'is_late' => true, 'is_early' => false],
    ['employee_id' => 2, 'is_late' => false, 'is_early' => true],
    ['employee_id' => 3, 'is_late' => true, 'is_early' => true],
], 'late');
assertSameValue(2, count($incidentRows), 'Late filter should retain late-only and combined rows.');

$incidentCounts = attendanceCountLateEarlyRows([
    ['is_late' => true, 'is_early' => false],
    ['is_late' => false, 'is_early' => true],
    ['is_late' => true, 'is_early' => true],
]);
assertSameValue(['late' => 2, 'early' => 2, 'total' => 3], $incidentCounts, 'Summary should count rows once and flags independently.');
```

- [ ] **Step 2: Run the helper test and verify RED**

Run:

```powershell
C:\xampp\php\php.exe tests\attendance_helpers_test.php
```

Expected: fatal error naming `attendanceNormalizeLateEarlyIncidentType` because the new helper does not exist.

- [ ] **Step 3: Add the minimal pure helpers**

Add after `attendanceCountMissingScanRows(...)` in `includes/attendance_helpers.php`:

```php
function attendanceNormalizeLateEarlyIncidentType($type) {
    $type = trim((string)$type);
    return in_array($type, ['late', 'early'], true) ? $type : 'all';
}

function attendanceCalculateLateEarlyIncident($workDate, $checkIn, $checkOut, array $shift, array $approvedMinutes = []) {
    $checkIn = attendanceNormalizeTime($checkIn);
    $checkOut = attendanceNormalizeTime($checkOut);
    $startTime = attendanceNormalizeTime($shift['start_time'] ?? '');
    $endTime = attendanceNormalizeTime($shift['end_time'] ?? '');
    if ($checkIn === null || $checkOut === null || $startTime === null || $endTime === null) return null;

    $workDays = array_filter(array_map('trim', explode(',', (string)($shift['work_days'] ?? ''))));
    if ($workDays && !in_array(attendanceDayName($workDate), $workDays, true)) return null;

    $rawLate = max(0, (int)floor((strtotime($workDate . ' ' . $checkIn) - strtotime($workDate . ' ' . $startTime)) / 60));
    $rawEarly = max(0, (int)floor((strtotime($workDate . ' ' . $endTime) - strtotime($workDate . ' ' . $checkOut)) / 60));
    $isBeyondTolerance = $rawLate > max(0, (int)($shift['late_tolerance_mins'] ?? 0));
    $lateMinutes = $isBeyondTolerance ? max(0, $rawLate - (int)($approvedMinutes['late_arrival'] ?? 0)) : 0;
    $earlyMinutes = max(0, $rawEarly - (int)($approvedMinutes['early_departure'] ?? 0));
    if ($lateMinutes === 0 && $earlyMinutes === 0) return null;

    return [
        'shift_start_time' => $startTime,
        'shift_end_time' => $endTime,
        'late_minutes' => $lateMinutes,
        'early_minutes' => $earlyMinutes,
        'is_late' => $lateMinutes > 0,
        'is_early' => $earlyMinutes > 0,
    ];
}

function attendanceFilterLateEarlyReportRows(array $rows, $type = 'all') {
    $type = attendanceNormalizeLateEarlyIncidentType($type);
    return array_values(array_filter($rows, function ($row) use ($type) {
        if ($type === 'late') return !empty($row['is_late']);
        if ($type === 'early') return !empty($row['is_early']);
        return !empty($row['is_late']) || !empty($row['is_early']);
    }));
}

function attendanceCountLateEarlyRows(array $rows) {
    $counts = ['late' => 0, 'early' => 0, 'total' => count($rows)];
    foreach ($rows as $row) {
        if (!empty($row['is_late'])) $counts['late']++;
        if (!empty($row['is_early'])) $counts['early']++;
    }
    return $counts;
}
```

- [ ] **Step 4: Verify GREEN and syntax**

Run:

```powershell
C:\xampp\php\php.exe -l includes\attendance_helpers.php
C:\xampp\php\php.exe tests\attendance_helpers_test.php
```

Expected: `No syntax errors detected` and `attendance_helpers_test passed`.

- [ ] **Step 5: Review the task diff without committing**

Run `git diff -- includes/attendance_helpers.php tests/attendance_helpers_test.php` and confirm only the four helpers and focused cases were added. Do not commit unless the user explicitly requests it.

### Task 2: Bulk API action and approved-request deductions

**Files:**
- Modify: `tests/attendance_api_source_test.php`
- Modify: `api/attendance_api.php`

**Interfaces:**
- Consumes: all four helpers from Task 1.
- Produces: `buildAttendanceLateEarlyReport(mysqli $mysqli, array $employees, string $month, string $incidentType): array`
- Produces: `fetchApprovedLateEarlyMinutesForEmployeesMonth(mysqli $mysqli, array $employeeIds, string $startDate, string $endDate): array`

- [ ] **Step 1: Add failing API contract assertions**

Add to `tests/attendance_api_source_test.php` before its success message:

```php
assertAttendanceApiSource(strpos($source, "\$action === 'late_early_report'") !== false, 'Attendance API should expose the late_early_report action.');
assertAttendanceApiSource(strpos($source, 'buildAttendanceLateEarlyReport') !== false, 'Late/early report should have a dedicated bulk builder.');
assertAttendanceApiSource(strpos($source, 'fetchApprovedLateEarlyMinutesForEmployeesMonth') !== false, 'Late/early report should bulk load approved request minutes.');
assertAttendanceApiSource(strpos($source, "lr.status = 'approved'") !== false, 'Only final approved requests should reduce incident minutes.');
assertAttendanceApiSource(strpos($source, "lr.time_request_type IN ('late_arrival', 'early_departure')") !== false, 'Only late and early request types should reduce report minutes.');
assertAttendanceApiSource(strpos($source, 'attendanceCalculateLateEarlyIncident') !== false, 'Bulk rows should use the tested incident calculator.');
assertAttendanceApiSource(substr_count($source, 'buildMonthlyAttendanceReport($mysqli, $employee, $month)') === 1, 'Aggregate reports must not add per-employee monthly report calls.');
```

- [ ] **Step 2: Run the API test and verify RED**

Run `C:\xampp\php\php.exe tests\attendance_api_source_test.php`.

Expected: failure `Attendance API should expose the late_early_report action.`

- [ ] **Step 3: Add the API action**

Immediately after the `missing_scan_report` action block, add a sibling block that validates `YYYY-MM`, normalizes `incident_type`, obtains scoped employees with `fetchAttendanceMissingScanEmployees(...)`, calls `buildAttendanceLateEarlyReport(...)`, and returns `attendanceCountLateEarlyRows($rows)` plus `data`.

Use this exact response structure:

```php
if ($action === 'late_early_report') {
    if (!$canManage) sendJsonError('Access Denied');
    $month = $_GET['month'] ?? date('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) sendJsonError('Invalid month');
    $incidentType = attendanceNormalizeLateEarlyIncidentType($_GET['incident_type'] ?? 'all');
    $employees = fetchAttendanceMissingScanEmployees($mysqli, $role, $_GET);
    $rows = buildAttendanceLateEarlyReport($mysqli, $employees, $month, $incidentType);
    sendJson([
        'status' => 'success',
        'month' => $month,
        'incident_type' => $incidentType,
        'summary' => attendanceCountLateEarlyRows($rows),
        'data' => $rows,
    ]);
}
```

- [ ] **Step 4: Implement the bulk request loader**

Add a prepared bulk query beside the other multi-employee monthly loaders. Select employee, date, request type, approved minutes and requested minutes from `leave_requests`, restricted to employee IDs, date range, `status = 'approved'`, and late/early types. Build this map:

```php
$map[$employeeId][$workDate][$type] =
    ($map[$employeeId][$workDate][$type] ?? 0) +
    max(0, (int)$row['effective_minutes']);
```

The SQL expression for `effective_minutes` must be:

```sql
CASE
    WHEN COALESCE(lr.approved_request_minutes, 0) > 0 THEN lr.approved_request_minutes
    ELSE COALESCE(lr.request_minutes, 0)
END
```

- [ ] **Step 5: Implement the bulk report builder**

Mirror `fetchAttendanceMissingScanReportRows(...)` for the existing bulk maps and effective-shift loop. Before calculating an incident, skip dates present in the holiday, approved full-day leave, or approved training maps. Call:

```php
$incident = attendanceCalculateLateEarlyIncident(
    $workDate,
    $record['check_in'],
    $record['check_out'],
    $effectiveShift,
    $approvedMinuteMap[$employeeId][$workDate] ?? []
);
```

When non-null, merge the incident values with the same escaped-at-render employee/organization fields used by the missing-scan rows plus effective `check_in` and `check_out`. Filter with `attendanceFilterLateEarlyReportRows(...)` and sort by `work_date` then `full_name`.

- [ ] **Step 6: Verify GREEN**

Run:

```powershell
C:\xampp\php\php.exe -l api\attendance_api.php
C:\xampp\php\php.exe tests\attendance_api_source_test.php
C:\xampp\php\php.exe tests\attendance_helpers_test.php
```

Expected: syntax passes and both focused tests print `passed`.

- [ ] **Step 7: Review the task diff without committing**

Run `git diff -- api/attendance_api.php tests/attendance_api_source_test.php`. Confirm the aggregate builder uses only bulk loaders and retains the HR scope helper. Do not commit unless requested.

### Task 3: Report page and browser behavior

**Files:**
- Create: `tests/attendance_late_early_report_ui_test.js`
- Create: `attendance_late_early_report.php`
- Modify: `assets/js/attendance.js`

**Interfaces:**
- Consumes: `GET api/attendance_api.php?action=late_early_report` from Task 2.
- Produces DOM mount `attendanceLateEarlyReportPage` and filter/table IDs prefixed `attendanceLateEarly`.

- [ ] **Step 1: Write the failing UI contract test**

Create `tests/attendance_late_early_report_ui_test.js` using the assertion helper from `attendance_missing_report_ui_test.js`. Assert the page contains the mount, month/company/branch/type filters, 12-column row target, Thai strings `รายงานมาสาย/ออกก่อน`, `มาสาย`, and `ออกก่อน`. Assert the script contains `initAttendanceLateEarlyReport`, `late_early_report`, all filter IDs, `response.text()`, the empty-server Thai message, `escapeHtml`, and `DataTable`.

- [ ] **Step 2: Run the UI test and verify RED**

Run `node tests\attendance_late_early_report_ui_test.js`.

Expected: `ENOENT` for `attendance_late_early_report.php`.

- [ ] **Step 3: Create the role-gated page**

Copy the structural pattern of `attendance_missing_report.php`, change all mount IDs to the `attendanceLateEarly` prefix, and use the 12 columns from the approved design. Keep `$use_select2 = true`, native month input behavior, the link to `attendance.php`, and the existing footer include. The type select values must be `all`, `late`, and `early`.

- [ ] **Step 4: Add JavaScript state and initialization**

At module state level add separate row and DataTable variables. From the existing DOMContentLoaded handler call `initAttendanceLateEarlyReport()`; the function must exit when its mount is absent, set the current month, load scoped filter options through the same option endpoint used by the missing-scan report, wire branch resets, and wire the load button.

- [ ] **Step 5: Add fetch, summary, and row rendering**

Implement functions parallel to the missing-scan renderer. Send `action`, `month`, `company_id`, `branch_id`, and `incident_type`. Read text before JSON parsing. Use 12-column loading/error/empty rows.

Each rendered row must use:

```js
const statusBadges = [
    row.is_late ? attendanceStatusBadge('late', 'มาสาย') : '',
    row.is_early ? '<span class="badge bg-warning text-dark">ออกก่อน</span>' : '',
].filter(Boolean).join(' ');
```

Render `late_minutes` and `early_minutes` as localized integer minutes or `-`; escape every text value. Summary cards show `total`, `late`, and `early`. Initialize DataTables only above ten rows, with initial ordering by date then employee.

- [ ] **Step 6: Verify GREEN and syntax**

Run:

```powershell
C:\xampp\php\php.exe -l attendance_late_early_report.php
node --check assets\js\attendance.js
node tests\attendance_late_early_report_ui_test.js
node tests\attendance_missing_report_ui_test.js
```

Expected: PHP/JS syntax passes and both UI tests print `passed`.

- [ ] **Step 7: Review the task diff without committing**

Run `git diff -- attendance_late_early_report.php assets/js/attendance.js tests/attendance_late_early_report_ui_test.js`. Confirm the existing missing-scan behavior is unchanged. Do not commit unless requested.

### Task 4: Sidebar integration and full verification

**Files:**
- Modify: `tests/sidebar_hybrid_menu_test.js`
- Modify: `includes/header.php`

**Interfaces:**
- Consumes: `attendance_late_early_report.php` from Task 3.
- Produces: report-center navigation and active submenu state.

- [ ] **Step 1: Add failing sidebar assertions**

Before the sidebar test's success message add:

```js
assertIncludes(header, 'href="attendance_late_early_report.php"', 'Report submenu should link to the late/early report.');
assertIncludes(header, "isActive('attendance_late_early_report.php')", 'Report submenu should stay active on the late/early report.');
```

- [ ] **Step 2: Run the sidebar test and verify RED**

Run `node tests\sidebar_hybrid_menu_test.js`.

Expected: failure `Report submenu should link to the late/early report.`

- [ ] **Step 3: Add the sidebar page and link**

Add `attendance_late_early_report.php` to `$reportCenterPages`. Add a sibling link after the missing-scan link with its own `isActive(...)`, a suitable clock/business-time Font Awesome icon, and Thai label `มาสาย/ออกก่อน`.

- [ ] **Step 4: Verify the focused suite**

Run:

```powershell
C:\xampp\php\php.exe -l includes\header.php
C:\xampp\php\php.exe -l includes\attendance_helpers.php
C:\xampp\php\php.exe -l api\attendance_api.php
C:\xampp\php\php.exe -l attendance_late_early_report.php
C:\xampp\php\php.exe tests\attendance_helpers_test.php
C:\xampp\php\php.exe tests\attendance_api_source_test.php
node --check assets\js\attendance.js
node tests\attendance_late_early_report_ui_test.js
node tests\attendance_missing_report_ui_test.js
node tests\sidebar_hybrid_menu_test.js
git diff --check
```

Expected: every syntax/test command succeeds and `git diff --check` returns no output.

- [ ] **Step 5: Review scope and working tree**

Run:

```powershell
git status --short
git diff --stat
git diff -- includes/attendance_helpers.php api/attendance_api.php attendance_late_early_report.php assets/js/attendance.js includes/header.php tests/attendance_helpers_test.php tests/attendance_api_source_test.php tests/attendance_late_early_report_ui_test.js tests/sidebar_hybrid_menu_test.js
```

Confirm no unrelated user-owned changes were altered. Do not stage, commit, or push unless the user explicitly requests it.

## Manual Acceptance Check

1. Sign in as HR with a limited company/branch scope.
2. Open รายงาน > มาสาย/ออกก่อน and confirm the report submenu remains expanded.
3. Load a month containing a known late arrival and early departure.
4. Confirm company/branch results remain inside HR scope.
5. Confirm a partially approved deviation shows only uncovered minutes.
6. Confirm a fully approved deviation disappears unless the same date has the other incident type.
7. Confirm missing scans remain absent from this report and available in the missing-scan report.
8. Confirm the `late` and `early` filters retain combined rows appropriately.
