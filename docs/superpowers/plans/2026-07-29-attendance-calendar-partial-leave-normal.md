# Attendance Calendar Partial-leave Normal Presentation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Display every approved partial-leave workday as normal attendance in the calendar and summary while retaining its leave details and raw scanner status.

**Architecture:** Classify approved actual-leave rows per calendar date in `includes/attendance_helpers.php`, producing non-overlapping full-day and partial-detail maps from persisted request values. Pass partial details through `api/attendance_api.php`, then derive a presentation-only normal status in `assets/js/attendance.js`; scanner-derived API status and stored requests remain unchanged.

**Tech Stack:** PHP 8/MySQLi, vanilla JavaScript, FullCalendar, SweetAlert2, focused PHP and Node.js regression tests.

## Global Constraints

- Cover day-based `morning`/`afternoon` leave and hourly actual leave with `time_request_type IS NULL` and persisted `total_days < 1`.
- Treat persisted hourly `total_days >= 1` and full dates of day-based requests as full-day leave.
- Preserve `approved` and `pending_cancel_hr` attendance-calendar status handling.
- Do not recalculate old requests with current leave-type settings.
- Do not change request creation, leave calculation, quota, approval, cancellation, role/scope filtering, stored data, or schema.
- Keep partial actual-leave details separate from late-arrival, early-departure, and OT labels.
- Preserve prepared SQL input, escaped rendered data, and raw check-in/check-out/status values.
- Do not commit or push unless the user explicitly requests it.

## File Structure

- `includes/attendance_helpers.php`: classify actual-leave rows per date and format partial-leave labels.
- `api/attendance_api.php`: fetch classification inputs once, attach full and partial maps to monthly rows, and keep time requests separate.
- `assets/js/attendance.js`: derive calendar/summary presentation and render partial-leave details.
- `tests/attendance_helpers_test.php`: real helper behavior for half-day, multi-day boundaries, and hourly persisted-duration boundaries.
- `tests/attendance_api_source_test.php`: SQL/data-flow boundary contract for the attendance endpoint.
- `tests/attendance_calendar_test.js`: calendar title, color, count, raw-status preservation, and popup behavior.

---

### Task 1: Per-date Actual-leave Classification

**Files:**
- Modify: `tests/attendance_helpers_test.php`
- Modify: `includes/attendance_helpers.php`

**Interfaces:**
- Consumes: `attendanceBuildApprovedLeaveMaps(array $leaveRows, string $month)`.
- Produces: `['full_day' => array<string,string>, 'partial' => array<string,array<int,string>>]`.
- Preserves: `attendanceBuildApprovedLeaveMap(array $leaveRows, string $month): array<string,string>` as a compatibility wrapper over `full_day`.

- [ ] **Step 1: Add failing helper fixtures and literal assertions**

Before writing the test, name the break: treating every day-unit request as full-day, ignoring partial multi-day boundaries, or recalculating hourly duration would make these assertions fail.

Add fixtures equivalent to:

```php
$classifiedLeaves = attendanceBuildApprovedLeaveMaps([
    [
        'start_date' => '2026-01-05',
        'end_date' => '2026-01-05',
        'request_unit' => 'day',
        'start_day_part' => 'morning',
        'end_day_part' => 'morning',
        'total_days' => 0.5,
        'type_name' => 'ลากิจ',
    ],
    [
        'start_date' => '2026-01-06',
        'end_date' => '2026-01-08',
        'request_unit' => 'day',
        'start_day_part' => 'afternoon',
        'end_day_part' => 'morning',
        'total_days' => 2.0,
        'type_name' => 'ลาป่วย',
    ],
    [
        'start_date' => '2026-01-09',
        'end_date' => '2026-01-09',
        'request_unit' => 'hour',
        'time_request_type' => null,
        'request_minutes' => 120,
        'request_start_time' => '09:00:00',
        'request_end_time' => '11:00:00',
        'total_days' => 0.25,
        'type_name' => 'ลากิจ',
    ],
    [
        'start_date' => '2026-01-12',
        'end_date' => '2026-01-12',
        'request_unit' => 'hour',
        'time_request_type' => null,
        'request_minutes' => 360,
        'request_start_time' => '08:00:00',
        'request_end_time' => '14:00:00',
        'total_days' => 1.0,
        'type_name' => 'ลากิจ',
    ],
], '2026-01');

assertSameValue([
    '2026-01-07' => 'ลาป่วย',
    '2026-01-12' => 'ลากิจ',
], $classifiedLeaves['full_day'], 'Only full dates should enter the full-day leave map.');
assertSameValue([
    '2026-01-05' => ['ลากิจ ครึ่งวันเช้า'],
    '2026-01-06' => ['ลาป่วย ครึ่งวันบ่าย'],
    '2026-01-08' => ['ลาป่วย ครึ่งวันเช้า'],
    '2026-01-09' => ['ลากิจ 09:00-11:00 2 ชม.'],
], $classifiedLeaves['partial'], 'Partial dates should retain independently formatted leave details.');
```

- [ ] **Step 2: Run the focused PHP test and verify RED**

Run:

```powershell
C:\xampp\php\php.exe tests\attendance_helpers_test.php
```

Expected: FAIL because `attendanceBuildApprovedLeaveMaps()` does not exist.

- [ ] **Step 3: Implement the minimal classifier**

Add `attendanceBuildApprovedLeaveMaps()` near the existing approved-leave map helper. Iterate only dates overlapping the requested month. For each row:

```php
$isHourlyActualLeave = ($row['request_unit'] ?? 'day') === 'hour'
    && empty($row['time_request_type']);
$isPartial = $isHourlyActualLeave
    ? (float)($row['total_days'] ?? 0) < 1
    : attendanceLeaveDatePart($row, $workDate) !== 'full';
```

Add a small `attendanceLeaveDatePart(array $row, $workDate)` helper with these literal rules:

```php
if ($row['start_date'] === $row['end_date']) {
    if (($row['start_day_part'] ?? 'full') !== 'full') {
        return $row['start_day_part'];
    }
    return ($row['end_day_part'] ?? 'full') !== 'full' ? $row['end_day_part'] : 'full';
}
if ($workDate === $row['start_date']) {
    return $row['start_day_part'] ?? 'full';
}
if ($workDate === $row['end_date']) {
    return $row['end_day_part'] ?? 'full';
}
return 'full';
```

Format day parts as `ครึ่งวันเช้า`/`ครึ่งวันบ่าย`. Format hourly actual leave from the persisted request range and minutes as `<type> HH:MM-HH:MM <duration>`. Append multiple partial labels on the same date without overwriting earlier requests. Make `attendanceBuildApprovedLeaveMap()` return only the `full_day` entry for backward compatibility.

- [ ] **Step 4: Run the helper test and verify GREEN**

Run:

```powershell
C:\xampp\php\php.exe tests\attendance_helpers_test.php
C:\xampp\php\php.exe -l includes\attendance_helpers.php
```

Expected: both commands exit 0 and the focused test prints `attendance_helpers_test passed`.

- [ ] **Step 5: Review Task 1 without committing**

Run:

```powershell
git diff -- includes\attendance_helpers.php tests\attendance_helpers_test.php
git diff --check -- includes\attendance_helpers.php tests\attendance_helpers_test.php
```

Expected: only classifier behavior and regression fixtures change; whitespace check exits 0.

---

### Task 2: Attendance API Partial-leave Data Flow

**Files:**
- Modify: `tests/attendance_api_source_test.php`
- Modify: `api/attendance_api.php`

**Interfaces:**
- Consumes: `attendanceBuildApprovedLeaveMaps(array $rows, string $month)` from Task 1.
- Produces: `fetchApprovedLeaveAttendanceMapsForMonth(mysqli $mysqli, int $employeeId, string $month): array`.
- Produces monthly row property: `partial_leave_details: array<int,string>`.
- Narrows `hourly_requests` to approved late-arrival, early-departure, and OT labels with non-null `time_request_type`.

- [ ] **Step 1: Add failing API boundary coverage**

Before writing the test, name the break: omitting day parts/persisted totals, querying only requests that start within the month, or continuing to mix actual hourly leave into time requests would make the endpoint misclassify dates.

Add source-boundary assertions with the test file's existing `assertAttendanceApiSource()` helper:

```php
assertAttendanceApiSource(
    strpos($source, 'lr.start_day_part') !== false,
    'Attendance leave query must fetch the first-date part.'
);
assertAttendanceApiSource(
    strpos($source, 'lr.end_day_part') !== false,
    'Attendance leave query must fetch the last-date part.'
);
assertAttendanceApiSource(
    strpos($source, 'lr.total_days') !== false,
    'Attendance leave query must use persisted duration.'
);
assertAttendanceApiSource(
    strpos($source, 'lr.start_date <= ?') !== false,
    'Attendance leave query must include requests overlapping the month end.'
);
assertAttendanceApiSource(
    strpos($source, 'lr.end_date >= ?') !== false,
    'Attendance leave query must include requests overlapping the month start.'
);
assertAttendanceApiSource(
    strpos($source, "'partial_leave_details' =>") !== false,
    'Monthly rows must expose partial-leave details.'
);
```

Also replace the old expectation that partial actual hourly leave belongs to the hourly-request query with an assertion that the query requires `lr.time_request_type IS NOT NULL`.

- [ ] **Step 2: Run the focused source test and verify RED**

Run:

```powershell
C:\xampp\php\php.exe tests\attendance_api_source_test.php
```

Expected: FAIL because the approved-leave query does not select both day parts/persisted duration and rows do not expose `partial_leave_details`.

- [ ] **Step 3: Implement one actual-leave fetch and classification path**

Replace the monthly full-leave fetch with `fetchApprovedLeaveAttendanceMapsForMonth()`. Its prepared query must select:

```sql
SELECT lr.start_date, lr.end_date, lr.start_day_part, lr.end_day_part,
       lr.request_unit, lr.time_request_type, lr.request_minutes,
       lr.request_start_time, lr.request_end_time, lr.total_days,
       lt.type_name
FROM leave_requests lr
JOIN leave_types lt ON lr.leave_type_id = lt.id
WHERE lr.employee_id = ?
  AND lr.status IN ('approved','pending_cancel_hr')
  AND (lr.request_unit = 'day'
       OR (lr.request_unit = 'hour' AND lr.time_request_type IS NULL))
  AND lr.start_date <= ?
  AND lr.end_date >= ?
ORDER BY lr.start_date, lr.id
```

Buffer all rows with `fetch_all(MYSQLI_ASSOC)`, close the statement, and return `attendanceBuildApprovedLeaveMaps($rows, $month)`.

In `buildMonthlyAttendanceReport()`:

```php
$leaveMaps = fetchApprovedLeaveAttendanceMapsForMonth(
    $mysqli,
    (int)$employee['id'],
    $month
);
$leaves = $leaveMaps['full_day'];
$partialLeaves = $leaveMaps['partial'];
```

Attach the new field to every row:

```php
'partial_leave_details' => $partialLeaves[$workDate] ?? [],
```

Keep `fetchApprovedHourlyRequestsForMonth()` for time requests, but require `lr.time_request_type IS NOT NULL` so actual partial leave is not duplicated.

- [ ] **Step 4: Run API checks and verify GREEN**

Run:

```powershell
C:\xampp\php\php.exe tests\attendance_api_source_test.php
C:\xampp\php\php.exe -l api\attendance_api.php
C:\xampp\php\php.exe tests\attendance_helpers_test.php
```

Expected: all commands exit 0.

- [ ] **Step 5: Review Task 2 without committing**

Run:

```powershell
git diff -- api\attendance_api.php tests\attendance_api_source_test.php
git diff --check -- api\attendance_api.php tests\attendance_api_source_test.php
```

Expected: one actual-leave query feeds both maps, time requests remain separate, role/scope code is untouched, and whitespace check exits 0.

---

### Task 3: Calendar, Summary, and Popup Presentation

**Files:**
- Modify: `tests/attendance_calendar_test.js`
- Modify: `assets/js/attendance.js`

**Interfaces:**
- Consumes row property: `partial_leave_details: array<string>`.
- Produces: `attendancePartialLeaveLabels(row): array<string>`.
- Produces: `attendanceCalendarPresentationStatus(row): string`, returning `present` for non-holiday rows with partial leave.
- Preserves raw `row.status`, `row.status_label`, and scanner times.

- [ ] **Step 1: Add failing calendar behavior tests**

Before writing the test, name the break: failing to derive normal presentation for absent/late/incomplete rows, dropping details, changing raw status, or counting a partial date as an exception would make these assertions fail.

Add a representative raw-absent row:

```js
const partialLeaveRow = {
    work_date: '2026-01-05',
    status: 'absent',
    status_label: 'ขาด',
    check_in: null,
    check_out: null,
    partial_leave_details: ['ลากิจ ครึ่งวันเช้า'],
    hourly_requests: [],
};
const partialLeaveEvent = buildAttendanceCalendarEvent(partialLeaveRow);
assertSame('ปกติ\nลากิจ ครึ่งวันเช้า', partialLeaveEvent.title, 'Partial leave should display as normal with leave detail.');
assertSame('#bbf7d0', partialLeaveEvent.backgroundColor, 'Partial leave should use the normal green color.');
assertIncludes(partialLeaveEvent.classNames.join(' '), 'attendance-event-present', 'Partial leave should use the normal event class.');
assertSame('absent', partialLeaveEvent.extendedProps.row.status, 'Calendar presentation must retain the raw scanner status.');
```

Add table-driven assertions for raw `late`, `missing_in`, and `missing_out` rows with partial details, all returning `present` presentation. Add summary assertions:

```js
const partialCounts = countAttendanceReportStatuses([partialLeaveRow]);
assertSame(1, partialCounts.present, 'Partial leave should increment normal attendance.');
assertSame(0, partialCounts.absent, 'Partial leave should not increment absence.');
```

Assert the popup contains `รายละเอียดการลา`, the leave label, and the normal badge text. Keep existing full-day leave and late/early/OT assertions unchanged.

- [ ] **Step 2: Run the Node.js test and verify RED**

Run:

```powershell
node tests\attendance_calendar_test.js
```

Expected: FAIL because partial-leave rows currently retain the raw scanner presentation and their labels are not rendered.

- [ ] **Step 3: Implement minimal presentation helpers**

Add:

```js
function attendancePartialLeaveLabels(row) {
    return Array.isArray(row.partial_leave_details)
        ? row.partial_leave_details.map(item => String(item || '').trim()).filter(Boolean)
        : [];
}
```

In `attendanceCalendarPresentationStatus(row)`, preserve company-holiday detection first, then return `present` when `row.status !== 'holiday'` and partial labels exist. Preserve the approved-late rule.

In `attendanceCalendarEventTitle(row)`, set `statusTitle = 'ปกติ'` when presentation is `present` and partial labels exist, and append those labels to `details` before time-request labels.

In `countAttendanceReportStatuses(rows)`, derive the count status without converting company holidays:

```js
const countStatus = presentationStatus === 'present' && row.status !== 'present'
    ? 'present'
    : row.status;
```

In `buildAttendanceCalendarDetails(row)`, derive presentation status/label, pass those values to `attendanceStatusBadge()`, and render escaped partial labels under a dedicated `รายละเอียดการลา` list. Continue showing check-in/check-out, overrides, and time requests.

- [ ] **Step 4: Run frontend checks and verify GREEN**

Run:

```powershell
node tests\attendance_calendar_test.js
node --check assets\js\attendance.js
```

Expected: both commands exit 0 and the focused test prints `attendance_calendar_test passed`.

- [ ] **Step 5: Review Task 3 without committing**

Run:

```powershell
git diff -- assets\js\attendance.js tests\attendance_calendar_test.js
git diff --check -- assets\js\attendance.js tests\attendance_calendar_test.js
```

Expected: only presentation/count/popup behavior and its regression tests change; raw row data is not overwritten.

---

### Task 4: Integrated Regression and Handoff Evidence

**Files:**
- Review: `includes/attendance_helpers.php`
- Review: `api/attendance_api.php`
- Review: `assets/js/attendance.js`
- Review: `tests/attendance_helpers_test.php`
- Review: `tests/attendance_api_source_test.php`
- Review: `tests/attendance_calendar_test.js`

**Interfaces:**
- Verifies the complete page → JavaScript → API → helper → SQL classification path.
- Produces fresh command evidence for the final handoff.

- [ ] **Step 1: Run all focused regressions**

Run:

```powershell
C:\xampp\php\php.exe tests\attendance_helpers_test.php
C:\xampp\php\php.exe tests\attendance_api_source_test.php
node tests\attendance_calendar_test.js
```

Expected: all three tests exit 0.

- [ ] **Step 2: Run syntax checks**

Run:

```powershell
C:\xampp\php\php.exe -l includes\attendance_helpers.php
C:\xampp\php\php.exe -l api\attendance_api.php
node --check assets\js\attendance.js
```

Expected: PHP reports no syntax errors and Node.js exits 0.

- [ ] **Step 3: Validate the entire task-owned patch**

Run:

```powershell
git diff --check
git status --short
git diff -- docs\superpowers\specs\2026-07-29-attendance-calendar-partial-leave-normal-design.md docs\superpowers\plans\2026-07-29-attendance-calendar-partial-leave-normal.md includes\attendance_helpers.php api\attendance_api.php assets\js\attendance.js tests\attendance_helpers_test.php tests\attendance_api_source_test.php tests\attendance_calendar_test.js
```

Expected: whitespace check exits 0, only task-owned files contain task changes, and unrelated worktree changes remain untouched.

- [ ] **Step 4: Perform runtime acceptance when an authenticated test path is available**

Load one known half-day or partial-hour leave date in `attendance.php` and verify:

- the date is green and says `ปกติ`;
- the second line contains the leave type and period;
- the normal summary increases while leave/scanner-exception totals do not;
- clicking the date shows leave details and scanner times;
- a full-day leave date remains blue and says `ลา`.

If no authenticated employee/test data is available, report browser acceptance as unverified rather than fabricating it.

- [ ] **Step 5: Prepare the evidence-backed handoff**

Report the exact files changed, red-to-green regression evidence, verification commands and results, runtime acceptance status, and the fact that no commit/push occurred.
