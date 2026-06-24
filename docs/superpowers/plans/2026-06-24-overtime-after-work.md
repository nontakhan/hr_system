# Overtime After Work Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add after-work OT requests that employees submit by date and duration, managers route to HR, HR final approval is capped by actual scan-out, and approved OT appears in attendance.

**Architecture:** Extend the existing hourly request path in `leave_requests` with `time_request_type = 'overtime_after_work'` and `approved_request_minutes`. Keep the current pages/routes, approval statuses, badge counts, and attendance rendering, adding OT-specific calculation and display where the existing hourly request flow already branches for late/early requests.

**Tech Stack:** PHP 8 under XAMPP, MySQL via mysqli, Bootstrap/SweetAlert frontend, plain JavaScript tests, focused PHP helper tests.

---

## File Structure

- Modify `includes/attendance_helpers.php`: OT time math, approved hourly request labels, duration formatting helpers if needed.
- Modify `includes/leave_helpers.php`: schema ensure helpers for `time_request_type` enum and `approved_request_minutes`; hourly payload support.
- Modify `api/late_early_request_api.php`: accept OT request type, validate duration, submit without scan-out requirement.
- Modify `late_early_request.php`: add OT request option and duration inputs while keeping late/early request-time input.
- Modify `late_early_history.php`: neutral copy for all hourly requests.
- Modify `assets/js/late_early_request.js`: toggle time vs duration inputs, calculate/submit OT, render OT history.
- Modify `api/leave_approval_api.php`: include scan details in hourly approval rows; block/cap HR approval for OT.
- Modify `late_early_approvals.php`: neutral copy and columns that can show OT scan details.
- Modify `assets/js/leave_approval.js`: render OT durations and HR scan/eligible details.
- Modify `api/attendance_api.php`: fetch `approved_request_minutes` and continue passing approved hourly request map.
- Modify `assets/js/attendance.js`: use existing hourly label display; add tests if label functions need adjustment.
- Modify `tests/attendance_helpers_test.php`: PHP helper coverage for OT math and labels.
- Modify `tests/leave_request_icon_ui_test.js`: request-page contract for OT option and duration input.
- Modify or create `tests/time_request_overtime_ui_test.js`: focused JS contracts for OT formatting and attendance labels.

## Task 1: Add Red Tests for OT Helper Behavior

**Files:**
- Modify: `tests/attendance_helpers_test.php`

- [ ] **Step 1: Add failing helper assertions**

Append these assertions after the existing late/early hourly request map assertions and before `$lateMinutes`:

```php
$otRequestMap = attendanceBuildApprovedHourlyRequestMap([
    [
        'start_date' => '2026-01-09',
        'request_unit' => 'hour',
        'time_request_type' => 'overtime_after_work',
        'request_minutes' => 120,
        'approved_request_minutes' => 90,
    ],
], '2026-01');
assertSameValue(['OT หลังเลิกงาน 1 ชม. 30 นาที'], $otRequestMap['2026-01-09'], 'Approved OT should use approved_request_minutes in attendance labels.');

$otFullApproval = attendanceCalculateOvertimeAfterWorkMinutes('2026-01-09', '17:00:00', '19:15:00', 120);
assertSameValue(true, $otFullApproval['valid'], 'OT should be valid when check-out is after shift end.');
assertSameValue(135, $otFullApproval['eligible_minutes'], 'OT eligible minutes should be measured from shift end to check-out.');
assertSameValue(120, $otFullApproval['approved_minutes'], 'OT approved minutes should be capped by requested minutes.');

$otPartialApproval = attendanceCalculateOvertimeAfterWorkMinutes('2026-01-09', '17:00:00', '17:45:00', 120);
assertSameValue(true, $otPartialApproval['valid'], 'OT should still be valid when scan-out supports less than requested.');
assertSameValue(45, $otPartialApproval['eligible_minutes'], 'OT eligible minutes should reflect actual scan-out.');
assertSameValue(45, $otPartialApproval['approved_minutes'], 'OT approved minutes should not exceed eligible minutes.');

$invalidOtCases = [
    attendanceCalculateOvertimeAfterWorkMinutes('2026-01-09', '', '18:00:00', 60),
    attendanceCalculateOvertimeAfterWorkMinutes('2026-01-09', '17:00:00', null, 60),
    attendanceCalculateOvertimeAfterWorkMinutes('2026-01-09', '17:00:00', '17:00:00', 60),
    attendanceCalculateOvertimeAfterWorkMinutes('bad-date', '17:00:00', '18:00:00', 60),
    attendanceCalculateOvertimeAfterWorkMinutes('2026-01-09', '17:00:00', '18:00:00', 0),
];
foreach ($invalidOtCases as $case) {
    assertSameValue(false, $case['valid'], 'Invalid OT cases should be rejected.');
    assertSameValue(false, preg_match('/[A-Za-z]/', $case['message']) === 1, 'OT validation messages should be Thai.');
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `C:\xampp\php\php.exe tests\attendance_helpers_test.php`

Expected: FAIL with a message like `Call to undefined function attendanceCalculateOvertimeAfterWorkMinutes()` or the OT label assertion failing.

- [ ] **Step 3: Commit red test**

```powershell
git add -- tests\attendance_helpers_test.php
git commit -m "test: add overtime attendance helper coverage"
```

## Task 2: Implement OT Helper Math and Attendance Labels

**Files:**
- Modify: `includes/attendance_helpers.php`
- Modify: `tests/attendance_helpers_test.php` only if the red test needs formatting adjustments, not behavior changes.

- [ ] **Step 1: Add a reusable Thai duration formatter**

In `includes/attendance_helpers.php`, add this function before `attendanceBuildApprovedHourlyRequestMap`:

```php
function attendanceFormatHourMinuteDuration($minutes) {
    $minutes = max(0, (int)$minutes);
    $hours = intdiv($minutes, 60);
    $remaining = $minutes % 60;
    $parts = [];
    if ($hours > 0) {
        $parts[] = $hours . ' ชม.';
    }
    if ($remaining > 0 || !$parts) {
        $parts[] = $remaining . ' นาที';
    }
    return implode(' ', $parts);
}
```

- [ ] **Step 2: Add OT calculation helper**

In `includes/attendance_helpers.php`, add this function before `attendanceCalculateTimeRequestMinutes`:

```php
function attendanceCalculateOvertimeAfterWorkMinutes($workDate, $shiftEndTime, $checkOutTime, $requestedMinutes) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$workDate)) {
        return ['valid' => false, 'message' => 'รูปแบบวันที่ไม่ถูกต้อง', 'eligible_minutes' => 0, 'approved_minutes' => 0];
    }

    $shiftEnd = attendanceNormalizeTime($shiftEndTime ?? '');
    if ($shiftEnd === null) {
        return ['valid' => false, 'message' => 'ยังไม่ได้ตั้งค่าเวลาเลิกกะ', 'eligible_minutes' => 0, 'approved_minutes' => 0];
    }

    $checkOut = attendanceNormalizeTime($checkOutTime ?? '');
    if ($checkOut === null) {
        return ['valid' => false, 'message' => 'ต้องมีผลสแกนออกก่อนอนุมัติ OT', 'eligible_minutes' => 0, 'approved_minutes' => 0];
    }

    $requested = (int)$requestedMinutes;
    if ($requested < 1) {
        return ['valid' => false, 'message' => 'จำนวนเวลาที่ขอ OT ไม่ถูกต้อง', 'eligible_minutes' => 0, 'approved_minutes' => 0];
    }

    $endTs = strtotime($workDate . ' ' . $shiftEnd);
    $outTs = strtotime($workDate . ' ' . $checkOut);
    $eligible = (int)ceil(($outTs - $endTs) / 60);
    if ($eligible < 1) {
        return ['valid' => false, 'message' => 'ผลสแกนออกไม่เกินเวลาเลิกงาน จึงอนุมัติ OT ไม่ได้', 'eligible_minutes' => 0, 'approved_minutes' => 0];
    }

    return [
        'valid' => true,
        'message' => '',
        'eligible_minutes' => $eligible,
        'approved_minutes' => min($requested, $eligible),
    ];
}
```

- [ ] **Step 3: Extend approved hourly map labels**

Replace the label-building block inside `attendanceBuildApprovedHourlyRequestMap` with:

```php
$type = $row['time_request_type'] ?? '';
$rawApproved = isset($row['approved_request_minutes']) && $row['approved_request_minutes'] !== null
    ? (int)$row['approved_request_minutes']
    : 0;
$minutes = $rawApproved > 0 ? $rawApproved : (int)($row['request_minutes'] ?? 0);

if ($type === 'overtime_after_work') {
    $minutes = max(1, $minutes);
    $label = 'OT หลังเลิกงาน ' . attendanceFormatHourMinuteDuration($minutes);
} else {
    $minutes = max(1, min(60, $minutes ?: 60));
    $label = $type === 'early_departure'
        ? 'ขอออกก่อน ' . $minutes . ' นาที'
        : 'ขอมาสาย ' . $minutes . ' นาที';
}
```

- [ ] **Step 4: Run helper test to verify pass**

Run: `C:\xampp\php\php.exe tests\attendance_helpers_test.php`

Expected: `attendance_helpers_test passed`

- [ ] **Step 5: Commit implementation**

```powershell
git add -- includes\attendance_helpers.php tests\attendance_helpers_test.php
git commit -m "feat: add overtime attendance helpers"
```

## Task 3: Add Red Tests for Request Page and JS Formatting

**Files:**
- Modify: `tests/leave_request_icon_ui_test.js`
- Create: `tests/time_request_overtime_ui_test.js`

- [ ] **Step 1: Extend request page contract test**

In `tests/leave_request_icon_ui_test.js`, add these assertions near the existing time request page checks:

```js
assertIncludes(timeRequestPage, 'value="overtime_after_work"', 'Time request page should offer after-work OT.');
assertIncludes(timeRequestPage, 'name="overtime_minutes"', 'Time request page should collect requested OT duration.');
assertIncludes(timeRequestScript, 'overtime_after_work', 'Time request script should handle after-work OT.');
```

- [ ] **Step 2: Create focused JS contract test**

Create `tests/time_request_overtime_ui_test.js`:

```js
const fs = require('fs');

function assertIncludes(haystack, needle, message) {
  if (!haystack.includes(needle)) {
    console.error(message);
    console.error(`Expected to find: ${needle}`);
    process.exit(1);
  }
}

const timeScript = fs.readFileSync('assets/js/late_early_request.js', 'utf8');
const approvalScript = fs.readFileSync('assets/js/leave_approval.js', 'utf8');
const attendanceScript = fs.readFileSync('assets/js/attendance.js', 'utf8');

assertIncludes(timeScript, "type === 'overtime_after_work'", 'History formatting should branch for OT requests.');
assertIncludes(timeScript, 'formatHourMinuteDuration', 'Time request script should format OT as hours and minutes.');
assertIncludes(approvalScript, "time_request_type === 'overtime_after_work'", 'Approval duration formatting should branch for OT requests.');
assertIncludes(approvalScript, 'eligible_overtime_minutes', 'Approval UI should show eligible OT from scan-out.');
assertIncludes(attendanceScript, 'attendanceHourlyRequestLabels', 'Attendance should continue rendering approved hourly request labels.');

console.log('time_request_overtime_ui_test passed');
```

- [ ] **Step 3: Run tests to verify they fail**

Run:

```powershell
node tests\leave_request_icon_ui_test.js
node tests\time_request_overtime_ui_test.js
```

Expected: the first test fails because the page has no OT option or minutes input; the second test fails because JS does not branch for OT yet.

- [ ] **Step 4: Commit red tests**

```powershell
git add -- tests\leave_request_icon_ui_test.js tests\time_request_overtime_ui_test.js
git commit -m "test: add overtime time request UI coverage"
```

## Task 4: Extend Schema and Request Submission for OT

**Files:**
- Modify: `includes/leave_helpers.php`
- Modify: `api/late_early_request_api.php`
- Modify: `late_early_request.php`
- Modify: `assets/js/late_early_request.js`

- [ ] **Step 1: Extend schema helper**

In `includes/leave_helpers.php`, update the `time_request_type` enum ensure/modify logic so it allows `overtime_after_work`, and add an ensure block for:

```php
if (!isset($columns['approved_request_minutes'])) {
    $mysqli->query("ALTER TABLE leave_requests ADD COLUMN approved_request_minutes SMALLINT UNSIGNED NULL AFTER request_minutes");
}
```

Also update `leaveBuildHourlyRequestPayload` to accept:

```php
if ($timeRequestType === 'overtime_after_work') {
    return [
        'request_unit' => 'hour',
        'time_request_type' => 'overtime_after_work',
        'request_minutes' => max(1, (int)$requestMinutes),
    ];
}
```

- [ ] **Step 2: Extend API type normalization**

In `api/late_early_request_api.php`, replace `normalizeTimeRequestType` with a version that allows OT:

```php
function normalizeTimeRequestType($value) {
    return in_array($value, ['late_arrival', 'early_departure', 'overtime_after_work'], true) ? $value : '';
}
```

Update `timeRequestTypeName`:

```php
function timeRequestTypeName($type) {
    if ($type === 'overtime_after_work') {
        return 'OT หลังเลิกงาน';
    }
    return $type === 'early_departure' ? 'ขอออกก่อน' : 'ขอมาสาย';
}
```

- [ ] **Step 3: Accept OT duration in submit/calculate**

In `submitTimeRequest`, read:

```php
$overtimeMinutes = (int)($_POST['overtime_minutes'] ?? 0);
```

For OT, require `$overtimeMinutes > 0 && $overtimeMinutes <= 480`, set calculation data without requiring scan-out:

```php
if ($type === 'overtime_after_work') {
    $calculation = calculateMyOvertimeRequest($mysqli, $workDate, $overtimeMinutes);
} else {
    $calculation = calculateMyTimeRequest($mysqli, $type, $workDate, $requestTime);
}
```

Add:

```php
function calculateMyOvertimeRequest(mysqli $mysqli, $workDate, $requestedMinutes) {
    $employeeId = (int)($_SESSION['employee_id'] ?? 0);
    $shift = fetchEffectiveShiftForTimeRequest($mysqli, $employeeId, $workDate);
    if (!$shift) {
        return ['valid' => false, 'message' => 'ไม่พบข้อมูลกะของพนักงาน', 'request_minutes' => 0];
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$workDate)) {
        return ['valid' => false, 'message' => 'รูปแบบวันที่ไม่ถูกต้อง', 'request_minutes' => 0];
    }
    $workDays = array_filter(array_map('trim', explode(',', (string)($shift['work_days'] ?? ''))));
    if (!empty($workDays) && !in_array(attendanceDayName($workDate), $workDays, true)) {
        return ['valid' => false, 'message' => 'วันที่เลือกไม่ใช่วันทำงานตามกะ', 'request_minutes' => 0];
    }
    $minutes = (int)$requestedMinutes;
    if ($minutes < 1 || $minutes > 480) {
        return ['valid' => false, 'message' => 'จำนวนเวลา OT ต้องอยู่ระหว่าง 1-480 นาที', 'request_minutes' => $minutes];
    }
    return [
        'valid' => true,
        'message' => '',
        'request_minutes' => $minutes,
        'shift_start_time' => $shift['start_time'] ?? null,
        'shift_end_time' => $shift['end_time'] ?? null,
    ];
}
```

- [ ] **Step 4: Add OT option and duration input to page**

In `late_early_request.php`, add a third radio option:

```html
<input type="radio" name="time_request_type" id="timeRequestOvertime" value="overtime_after_work" class="btn-check time-request-type-option" autocomplete="off" required>
<label class="btn btn-outline-primary py-3 time-request-type-btn" for="timeRequestOvertime">
    <i class="fas fa-business-time me-1"></i>
    <span>ขอ OT หลังเลิกงาน</span>
</label>
```

Add duration input near the time input:

```html
<div class="col-md-6 d-none" id="overtimeDurationField">
    <label class="form-label">จำนวน OT ที่ขอ (นาที) <span class="text-danger">*</span></label>
    <input type="number" name="overtime_minutes" id="overtimeMinutes" class="form-control" min="1" max="480" step="1" placeholder="เช่น 120">
</div>
```

- [ ] **Step 5: Update request JS toggles and formatting**

In `assets/js/late_early_request.js`, add:

```js
function isOvertimeRequest() {
    return getSelectedTimeRequestType() === 'overtime_after_work';
}

function formatHourMinuteDuration(minutes) {
    const safeMinutes = Math.max(0, Number.parseInt(minutes || 0, 10) || 0);
    const hours = Math.floor(safeMinutes / 60);
    const remaining = safeMinutes % 60;
    const parts = [];
    if (hours > 0) parts.push(`${hours} ชม.`);
    if (remaining > 0 || !parts.length) parts.push(`${remaining} นาที`);
    return parts.join(' ');
}
```

Toggle required/display:

```js
function syncTimeRequestFields() {
    const timeField = document.getElementById('timeRequestTime')?.closest('.col-md-6');
    const timeInput = document.getElementById('timeRequestTime');
    const overtimeField = document.getElementById('overtimeDurationField');
    const overtimeInput = document.getElementById('overtimeMinutes');
    const ot = isOvertimeRequest();
    timeField?.classList.toggle('d-none', ot);
    overtimeField?.classList.toggle('d-none', !ot);
    if (timeInput) timeInput.required = !ot;
    if (overtimeInput) overtimeInput.required = ot;
}
```

Call `syncTimeRequestFields()` on type changes and before submit.

- [ ] **Step 6: Run red UI tests to verify pass**

Run:

```powershell
node tests\leave_request_icon_ui_test.js
node tests\time_request_overtime_ui_test.js
```

Expected: both pass after JS/page changes that satisfy the contract.

- [ ] **Step 7: Commit request flow**

```powershell
git add -- includes\leave_helpers.php api\late_early_request_api.php late_early_request.php assets\js\late_early_request.js tests\leave_request_icon_ui_test.js tests\time_request_overtime_ui_test.js
git commit -m "feat: add overtime request submission"
```

## Task 5: Add HR Approval Scan Validation

**Files:**
- Modify: `api/leave_approval_api.php`
- Modify: `assets/js/leave_approval.js`
- Modify: `late_early_approvals.php`
- Modify: `tests/time_request_overtime_ui_test.js`

- [ ] **Step 1: Add approval query fields**

In `api/leave_approval_api.php`, when `request_unit = 'hour'`, include attendance and shift context in list rows:

```sql
lr.approved_request_minutes,
ar.check_out AS raw_check_out
```

Use existing employee/shift data already joined or add `work_shifts` join if absent:

```sql
LEFT JOIN attendance_records ar ON ar.employee_id = lr.employee_id AND ar.work_date = lr.start_date
```

For each OT row, compute:

```php
if (($row['time_request_type'] ?? '') === 'overtime_after_work') {
    $shift = fetchEffectiveShiftForApprovalRequest($mysqli, (int)$row['employee_id'], $row['start_date']);
    $calc = attendanceCalculateOvertimeAfterWorkMinutes($row['start_date'], $shift['end_time'] ?? null, $row['raw_check_out'] ?? null, (int)$row['request_minutes']);
    $row['shift_end_time'] = $shift['end_time'] ?? null;
    $row['actual_check_out'] = $row['raw_check_out'] ?? null;
    $row['eligible_overtime_minutes'] = $calc['eligible_minutes'] ?? 0;
    $row['approval_overtime_minutes'] = $calc['approved_minutes'] ?? 0;
    $row['overtime_scan_valid'] = $calc['valid'];
    $row['overtime_scan_message'] = $calc['message'];
}
```

- [ ] **Step 2: Validate on HR final approval**

In the POST approval path, when request is `pending_hr` and `time_request_type = 'overtime_after_work'`, fetch the row, effective shift, and attendance check-out. Before setting `approved`, run:

```php
$ot = attendanceCalculateOvertimeAfterWorkMinutes($request['start_date'], $shift['end_time'] ?? null, $record['check_out'] ?? null, (int)$request['request_minutes']);
if (!$ot['valid']) {
    sendJsonError($ot['message']);
}
$approvedMinutes = (int)$ot['approved_minutes'];
```

Update final approval SQL for OT to set:

```sql
approved_request_minutes = ?
```

For non-OT rows, leave `approved_request_minutes` unchanged.

- [ ] **Step 3: Render OT scan details in approval UI**

In `assets/js/leave_approval.js`, update `formatLeaveDuration(item)`:

```js
if (item.time_request_type === 'overtime_after_work') {
    const requested = Number.parseInt(item.request_minutes || 0, 10) || 0;
    const approved = Number.parseInt(item.approved_request_minutes || item.approval_overtime_minutes || 0, 10) || 0;
    const suffix = approved > 0 && approved !== requested ? `, อนุมัติได้ ${formatHourMinuteDuration(approved)}` : '';
    return `OT หลังเลิกงาน ${formatHourMinuteDuration(requested)}${suffix}`;
}
```

Add `formatHourMinuteDuration` to this file using the same JS implementation from Task 4.

In pending row reason/details, add for OT:

```js
const otScanHtml = item.time_request_type === 'overtime_after_work'
    ? `<small class="d-block text-primary">สแกนออก: ${formatAttendanceTime(item.actual_check_out)} | เลิกกะ: ${formatAttendanceTime(item.shift_end_time)} | OT ที่อนุมัติได้: ${formatHourMinuteDuration(item.eligible_overtime_minutes || 0)}</small>`
    : '';
```

Render `${otScanHtml}` below the reason.

- [ ] **Step 4: Run UI contract test**

Run: `node tests\time_request_overtime_ui_test.js`

Expected: `time_request_overtime_ui_test passed`

- [ ] **Step 5: Commit approval validation**

```powershell
git add -- api\leave_approval_api.php assets\js\leave_approval.js late_early_approvals.php tests\time_request_overtime_ui_test.js
git commit -m "feat: validate overtime against scan out"
```

## Task 6: Extend Attendance Fetching and Display

**Files:**
- Modify: `api/attendance_api.php`
- Modify: `assets/js/attendance.js`
- Modify: `tests/attendance_calendar_test.js`

- [ ] **Step 1: Fetch approved request minutes**

In `fetchApprovedHourlyRequestsForMonth`, add `lr.approved_request_minutes` to the SELECT:

```sql
SELECT lr.start_date, lr.request_unit, lr.time_request_type, lr.request_minutes, lr.approved_request_minutes
```

- [ ] **Step 2: Add JS test assertion for OT label pass-through**

In `tests/attendance_calendar_test.js`, add an approved OT label in the row fixture used for `attendanceCalendarEventTitle`:

```js
hourly_requests: ['OT หลังเลิกงาน 1 ชม. 30 นาที'],
```

Add assertion:

```js
assertIncludes(hourlyRequestEvent.title, 'OT หลังเลิกงาน 1 ชม. 30 นาที', 'Calendar event title should mention approved OT requests.');
```

- [ ] **Step 3: Run attendance UI test**

Run: `node tests\attendance_calendar_test.js`

Expected: `attendance_calendar_test passed`

- [ ] **Step 4: Commit attendance display**

```powershell
git add -- api\attendance_api.php assets\js\attendance.js tests\attendance_calendar_test.js
git commit -m "feat: show approved overtime in attendance"
```

## Task 7: Final Verification

**Files:**
- Verify all touched PHP and JS files.

- [ ] **Step 1: PHP syntax checks**

Run:

```powershell
C:\xampp\php\php.exe -l includes\attendance_helpers.php
C:\xampp\php\php.exe -l includes\leave_helpers.php
C:\xampp\php\php.exe -l api\late_early_request_api.php
C:\xampp\php\php.exe -l api\leave_approval_api.php
C:\xampp\php\php.exe -l api\attendance_api.php
```

Expected for each: `No syntax errors detected`

- [ ] **Step 2: JavaScript syntax checks**

Run:

```powershell
node --check assets\js\late_early_request.js
node --check assets\js\leave_approval.js
node --check assets\js\attendance.js
```

Expected: no output and exit code 0 for each.

- [ ] **Step 3: Focused regression tests**

Run:

```powershell
C:\xampp\php\php.exe tests\attendance_helpers_test.php
node tests\leave_request_icon_ui_test.js
node tests\time_request_overtime_ui_test.js
node tests\attendance_calendar_test.js
node tests\time_request_approval_menu_test.js
```

Expected: each prints its `passed` message.

- [ ] **Step 4: Whitespace and staged diff check**

Run:

```powershell
git diff --check
git status --short
```

Expected: `git diff --check` has no output. `git status --short` shows only task-owned modified files plus pre-existing unrelated untracked files.

- [ ] **Step 5: Final commit if needed**

If any verification-only fixes were made:

```powershell
git add -- includes\attendance_helpers.php includes\leave_helpers.php api\late_early_request_api.php api\leave_approval_api.php api\attendance_api.php late_early_request.php late_early_history.php late_early_approvals.php assets\js\late_early_request.js assets\js\leave_approval.js assets\js\attendance.js tests\attendance_helpers_test.php tests\leave_request_icon_ui_test.js tests\time_request_overtime_ui_test.js tests\attendance_calendar_test.js
git commit -m "chore: verify overtime request flow"
```

Expected: commit succeeds, or no final commit is needed because prior task commits already include all changes.

