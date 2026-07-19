# Request Cancellation Approval Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add reason-required cancellation and HR/Admin cancellation approval to late/early, overtime, day-swap, and training/activity requests.

**Architecture:** Reuse the existing ordinary-leave state contract. Hourly requests reuse `leave_requests.pending_cancel_hr` and `cancellation_reason`; day-swap and training schema helpers add the same contract. Each request API owns its employee cancellation and reviewer transitions, while existing history and approval scripts render the actions and reasons.

**Tech Stack:** PHP 8/MySQLi, MariaDB enum/text columns, vanilla JavaScript, Bootstrap/SweetAlert2, Node.js source-contract tests, PHP helper tests.

## Global Constraints

- Pending-stage cancellation is immediate; approved cancellation enters `pending_cancel_hr`.
- Every employee cancellation requires a non-empty trimmed reason.
- Only HR/Admin can approve or reject `pending_cancel_hr`; manager queues exclude it.
- `pending_cancel_hr` remains operationally effective until cancellation approval.
- Preserve HR scope filtering, employee ownership checks, prepared statements, escaped UI output, Thai labels, and existing Sarabun/Bootstrap styling.
- Do not add cancellation controls to `request_proxy.php`.
- Do not stage, commit, or push without an explicit user request.

---

## File Structure

- `tests/request_cancellation_workflows_test.js`: cross-workflow source contract and UI regression.
- `tests/request_cancellation_helpers_test.php`: pure state/role helper contract.
- `includes/request_cancellation_helpers.php`: shared state/role decisions only; no SQL or output.
- `includes/day_swap_helpers.php`, `includes/training_request_helpers.php`: idempotent schema assurance and effective-query expansion.
- `api/late_early_request_api.php`: owned hourly-request cancellation, with strict late/early versus OT type filtering.
- `api/day_swap_api.php`, `api/training_request_api.php`: employee cancellation plus HR/Admin cancellation review.
- `assets/js/late_early_request.js`, `assets/js/day_swap.js`, `assets/js/training_request.js`: cancellation dialogs, buttons, status/reason rendering, and reviewer labels.
- `api/leave_approval_api.php`, `assets/js/leave_approval.js`: verify and preserve the already-shared hourly cancellation review path.
- `includes/approval_badge_helpers.php`, `api/attendance_api.php`, `includes/attendance_warning_source_helpers.php`: badges and operational effectiveness.

### Task 1: Shared Cancellation State Contract

**Files:**
- Create: `tests/request_cancellation_helpers_test.php`
- Create: `includes/request_cancellation_helpers.php`

**Interfaces:**
- Produces: `requestCancellationEmployeeTransition(string $status): ?string`
- Produces: `requestCancellationReviewerTransition(string $status, string $action, string $role): ?string`

- [ ] **Step 1: Write the failing helper test**

```php
<?php
require_once __DIR__ . '/../includes/request_cancellation_helpers.php';

function assertCancellationSame($expected, $actual, $message) {
    if ($expected !== $actual) throw new RuntimeException($message);
}

foreach (['pending', 'pending_manager', 'pending_hr'] as $status) {
    assertCancellationSame('cancelled', requestCancellationEmployeeTransition($status), "{$status} must cancel immediately");
}
assertCancellationSame('pending_cancel_hr', requestCancellationEmployeeTransition('approved'), 'approved must require HR/Admin review');
assertCancellationSame(null, requestCancellationEmployeeTransition('pending_cancel_hr'), 'duplicate cancellation must be rejected');
assertCancellationSame('cancelled', requestCancellationReviewerTransition('pending_cancel_hr', 'approve', 'hr'), 'HR approval must cancel');
assertCancellationSame('approved', requestCancellationReviewerTransition('pending_cancel_hr', 'reject', 'admin'), 'Admin rejection must restore approval');
assertCancellationSame(null, requestCancellationReviewerTransition('pending_cancel_hr', 'approve', 'manager'), 'Manager must not review cancellation');
echo "request_cancellation_helpers_test passed\n";
```

- [ ] **Step 2: Run RED**

Run: `C:\xampp\php\php.exe tests\request_cancellation_helpers_test.php`

Expected: FAIL because `includes/request_cancellation_helpers.php` does not exist.

- [ ] **Step 3: Add the minimal pure helper**

```php
<?php
function requestCancellationEmployeeTransition(string $status): ?string {
    if (in_array($status, ['pending', 'pending_manager', 'pending_hr'], true)) return 'cancelled';
    return $status === 'approved' ? 'pending_cancel_hr' : null;
}

function requestCancellationReviewerTransition(string $status, string $action, string $role): ?string {
    if ($status !== 'pending_cancel_hr' || !in_array($role, ['hr', 'admin'], true)) return null;
    if ($action === 'approve') return 'cancelled';
    return $action === 'reject' ? 'approved' : null;
}
```

- [ ] **Step 4: Run GREEN**

Run: `C:\xampp\php\php.exe tests\request_cancellation_helpers_test.php`

Expected: `request_cancellation_helpers_test passed`.

### Task 2: Schema and Static Cross-Workflow Contract

**Files:**
- Create: `tests/request_cancellation_workflows_test.js`
- Modify: `includes/day_swap_helpers.php`
- Modify: `includes/training_request_helpers.php`

**Interfaces:**
- Consumes: shared status names from Task 1.
- Produces: `day_swap_requests.cancellation_reason`, `training_requests.cancellation_reason`, and `pending_cancel_hr` enum support.

- [ ] **Step 1: Write the failing source-contract test**

```js
const fs = require('fs');
const assert = require('assert');
const read = path => fs.readFileSync(path, 'utf8');
const dayHelper = read('includes/day_swap_helpers.php');
const trainingHelper = read('includes/training_request_helpers.php');

for (const [name, source] of [['day swap', dayHelper], ['training', trainingHelper]]) {
  assert(source.includes("pending_cancel_hr"), `${name} schema must support pending cancellation`);
  assert(source.includes("cancellation_reason"), `${name} schema must store cancellation reason`);
}
console.log('request_cancellation_workflows_test passed');
```

- [ ] **Step 2: Run RED**

Run: `node tests\request_cancellation_workflows_test.js`

Expected: FAIL for missing `pending_cancel_hr` and/or `cancellation_reason` in both helpers.

- [ ] **Step 3: Extend both idempotent schema helpers**

Use the existing `SHOW COLUMNS` maps and add the equivalent of:

```php
if (isset($columns['status']) && strpos($columns['status']['Type'], 'pending_cancel_hr') === false) {
    $mysqli->query("ALTER TABLE {$tableName} MODIFY status ENUM('pending','pending_manager','pending_hr','approved','pending_cancel_hr','rejected','cancelled') NOT NULL DEFAULT 'pending_manager'");
}
if (!isset($columns['cancellation_reason'])) {
    $mysqli->query("ALTER TABLE {$tableName} ADD COLUMN cancellation_reason TEXT NULL AFTER rejection_reason");
}
```

In each real helper, use its fixed literal table name rather than interpolating user input.

- [ ] **Step 4: Run GREEN and schema regressions**

Run: `node tests\request_cancellation_workflows_test.js`

Run: `node tests\day_swap_history_page_test.js`

Run: `node tests\training_request_contract_test.js`

Expected: all print their `passed` message.

### Task 3: Late/Early and Overtime Employee Cancellation

**Files:**
- Modify: `tests/request_cancellation_workflows_test.js`
- Modify: `api/late_early_request_api.php`
- Modify: `assets/js/late_early_request.js`

**Interfaces:**
- Consumes: `requestCancellationEmployeeTransition()`.
- Produces: POST action `cancel` with JSON `{request_id, cancellation_reason, time_request_type}`.

- [ ] **Step 1: Extend the test with missing API/UI assertions**

```js
const timeApi = read('api/late_early_request_api.php');
const timeUi = read('assets/js/late_early_request.js');
for (const token of ['cancellation_reason', 'requestCancellationEmployeeTransition', "time_request_type IN ('late_arrival','early_departure')", "time_request_type = 'overtime_after_work'", 'affected_rows']) {
  assert(timeApi.includes(token), `time request API missing ${token}`);
}
for (const token of ["input: 'textarea'", 'กรุณาระบุเหตุผลการยกเลิก', 'pending_cancel_hr', 'ขอยกเลิก']) {
  assert(timeUi.includes(token), `time request UI missing ${token}`);
}
```

- [ ] **Step 2: Run RED**

Run: `node tests\request_cancellation_workflows_test.js`

Expected: FAIL at the first missing time-request cancellation token.

- [ ] **Step 3: Implement the owned, type-scoped API transition**

Require `includes/request_cancellation_helpers.php`, accept `cancel`, trim the reason, normalize the history family, and select using employee ownership plus one of these fixed predicates:

```sql
AND lr.request_unit = 'hour'
AND lr.time_request_type IN ('late_arrival','early_departure')
```

or:

```sql
AND lr.request_unit = 'hour'
AND lr.time_request_type = 'overtime_after_work'
```

Update with both the selected status and expected-status predicate:

```sql
UPDATE leave_requests
SET status = ?, cancellation_reason = ?
WHERE id = ? AND employee_id = ? AND status = ?
```

Reject blank reasons, mismatched types, unowned rows, invalid states, and `affected_rows !== 1`.

- [ ] **Step 4: Add the history button and required-reason dialog**

Render a cancel button only for `pending`, `pending_manager`, `pending_hr`, and `approved`. Reuse `window.timeRequestHistoryType` to send `late_early` or `overtime_after_work`, display `pending_cancel_hr` as `รอ HR/Admin อนุมัติยกเลิก`, and escape the displayed cancellation reason with the existing `escapeHtml()`.

- [ ] **Step 5: Run GREEN and related regressions**

Run: `node tests\request_cancellation_workflows_test.js`

Run: `node --check assets\js\late_early_request.js`

Run: `node tests\late_early_history_page_test.js`

Run: `node tests\time_request_overtime_ui_test.js`

Expected: all exit 0.

### Task 4: Day-Swap Cancellation and HR/Admin Review

**Files:**
- Modify: `tests/request_cancellation_workflows_test.js`
- Modify: `api/day_swap_api.php`
- Modify: `includes/day_swap_helpers.php`
- Modify: `assets/js/day_swap.js`

**Interfaces:**
- Consumes: both Task 1 transition helpers.
- Produces: day-swap POST `cancel`; existing `approve`/`reject` actions process `pending_cancel_hr` for HR/Admin.

- [ ] **Step 1: Add failing assertions**

```js
const dayApi = read('api/day_swap_api.php');
const dayUi = read('assets/js/day_swap.js');
for (const token of ['cancellation_reason', 'requestCancellationEmployeeTransition', 'requestCancellationReviewerTransition', "status = 'pending_cancel_hr'", 'affected_rows']) {
  assert(dayApi.includes(token), `day swap API missing ${token}`);
}
for (const token of ["input: 'textarea'", 'เหตุผลขอยกเลิก', 'อนุมัติยกเลิก', 'ไม่อนุมัติยกเลิก']) {
  assert(dayUi.includes(token), `day swap UI missing ${token}`);
}
```

- [ ] **Step 2: Run RED**

Run: `node tests\request_cancellation_workflows_test.js`

Expected: FAIL for the day-swap cancellation contract.

- [ ] **Step 3: Implement ownership and reviewer transitions**

Employee cancellation must select `WHERE id = ? AND requester_employee_id = ?`; the target employee cannot cancel someone else's request. Reviewer fetching keeps existing HR scope/manager-supervisor restrictions, but `pending_cancel_hr` immediately rejects manager role. Approval uses `cancelled`; rejection uses `approved` and requires a reviewer reason. Both updates set existing HR/general approver audit fields and include `WHERE id = ? AND status = 'pending_cancel_hr'`.

- [ ] **Step 4: Expand queries and UI**

For pending queries, use HR `IN ('pending_hr','pending_cancel_hr')`, Admin `IN ('pending','pending_manager','pending_hr','pending_cancel_hr')`, and leave manager unchanged. History includes `approved`, `rejected`, and `cancelled`. Render reason and cancellation-specific Thai actions.

- [ ] **Step 5: Run GREEN**

Run: `node tests\request_cancellation_workflows_test.js`

Run: `node --check assets\js\day_swap.js`

Run: `node tests\day_swap_history_page_test.js`

Run: `node tests\day_swap_calendar_test.js`

Expected: all exit 0.

### Task 5: Training/Activity Cancellation and HR/Admin Review

**Files:**
- Modify: `tests/request_cancellation_workflows_test.js`
- Modify: `api/training_request_api.php`
- Modify: `includes/training_request_helpers.php`
- Modify: `assets/js/training_request.js`

**Interfaces:**
- Consumes: both Task 1 transition helpers.
- Produces: training POST `cancel`; existing `approve`/`reject` process `pending_cancel_hr` for HR/Admin.

- [ ] **Step 1: Add failing assertions**

```js
const trainingApi = read('api/training_request_api.php');
const trainingUi = read('assets/js/training_request.js');
for (const token of ['cancellation_reason', 'requestCancellationEmployeeTransition', 'requestCancellationReviewerTransition', "status = 'pending_cancel_hr'", 'affected_rows']) {
  assert(trainingApi.includes(token), `training API missing ${token}`);
}
for (const token of ["input: 'textarea'", 'เหตุผลขอยกเลิก', 'อนุมัติยกเลิก', 'ไม่อนุมัติยกเลิก']) {
  assert(trainingUi.includes(token), `training UI missing ${token}`);
}
```

- [ ] **Step 2: Run RED**

Run: `node tests\request_cancellation_workflows_test.js`

Expected: FAIL for the training cancellation contract.

- [ ] **Step 3: Implement employee and reviewer transitions**

Employee cancellation selects `WHERE id = ? AND employee_id = ?`, rejects blank reason and stale status, then performs a status-qualified update. HR/Admin cancellation approval updates to `cancelled`; rejection restores `approved`. Preserve `training_record_id` during both transitions so rejection restores the already-created history link and approval does not delete historical audit data.

- [ ] **Step 4: Expand queries and UI**

Use the same HR/Admin/manager pending status sets as Task 4. Include `cancelled` in history. Render the employee cancel action, reason, pending-cancellation status, and distinct reviewer action labels.

- [ ] **Step 5: Run GREEN**

Run: `node tests\request_cancellation_workflows_test.js`

Run: `node --check assets\js\training_request.js`

Run: `node tests\training_request_contract_test.js`

Expected: all exit 0.

### Task 6: Effective-State Consumers and Approval Badges

**Files:**
- Modify: `tests/request_cancellation_workflows_test.js`
- Modify: `includes/day_swap_helpers.php`
- Modify: `includes/training_request_helpers.php`
- Modify: `api/attendance_api.php`
- Modify: `includes/attendance_warning_source_helpers.php`
- Verify/modify: `includes/approval_badge_helpers.php`

**Interfaces:**
- Produces: every operational query treats `pending_cancel_hr` like `approved`; HR/Admin badge stages count cancellation requests.

- [ ] **Step 1: Add failing effective-state assertions**

```js
const attendanceApi = read('api/attendance_api.php');
const warningHelper = read('includes/attendance_warning_source_helpers.php');
const badgeHelper = read('includes/approval_badge_helpers.php');
assert((attendanceApi.match(/pending_cancel_hr/g) || []).length >= 4, 'attendance consumers must retain pending cancellations');
assert(warningHelper.includes('pending_cancel_hr'), 'warning sources must retain pending cancellations');
assert(badgeHelper.includes("['pending_hr', 'pending_cancel_hr']"), 'HR badges must count cancellations');
```

- [ ] **Step 2: Run RED**

Run: `node tests\request_cancellation_workflows_test.js`

Expected: FAIL for at least day-swap/training effective queries.

- [ ] **Step 3: Expand fixed approved predicates**

Change relevant operational predicates from:

```sql
status = 'approved'
```

to:

```sql
status IN ('approved','pending_cancel_hr')
```

Apply only to attendance/calendar/conflict consumers where the request remains effective. Do not include `pending_cancel_hr` in completed-history counts that are intended to mean final status.

- [ ] **Step 4: Run GREEN and affected regressions**

Run: `node tests\request_cancellation_workflows_test.js`

Run: `C:\xampp\php\php.exe tests\approval_badge_helpers_test.php`

Run: `node tests\day_swap_calendar_test.js`

Run: `node tests\attendance_calendar_test.js`

Expected: all exit 0.

### Task 7: Full Verification and Acceptance Review

**Files:**
- Verify all files changed by Tasks 1-6.

**Interfaces:**
- Consumes: complete feature.
- Produces: fresh evidence for syntax, focused tests, requirements, and worktree isolation.

- [ ] **Step 1: PHP syntax checks**

Run `C:\xampp\php\php.exe -l` separately on:

```text
includes/request_cancellation_helpers.php
includes/day_swap_helpers.php
includes/training_request_helpers.php
api/late_early_request_api.php
api/day_swap_api.php
api/training_request_api.php
api/attendance_api.php
includes/attendance_warning_source_helpers.php
includes/approval_badge_helpers.php
```

Expected: `No syntax errors detected` for every file.

- [ ] **Step 2: JavaScript syntax checks**

Run `node --check` separately on:

```text
assets/js/late_early_request.js
assets/js/day_swap.js
assets/js/training_request.js
tests/request_cancellation_workflows_test.js
```

Expected: exit 0 for every file.

- [ ] **Step 3: Focused regression suite**

Run:

```powershell
C:\xampp\php\php.exe tests\request_cancellation_helpers_test.php
node tests\request_cancellation_workflows_test.js
node tests\leave_cancellation_request_test.js
C:\xampp\php\php.exe tests\approval_badge_helpers_test.php
node tests\late_early_history_page_test.js
node tests\time_request_overtime_ui_test.js
node tests\day_swap_history_page_test.js
node tests\day_swap_calendar_test.js
node tests\training_request_contract_test.js
node tests\attendance_calendar_test.js
```

Expected: every test exits 0 and prints its pass message.

- [ ] **Step 4: Patch and ownership checks**

Run: `git diff --check`

Run: `git status --short`

Run: `git diff -- docs/superpowers/specs/2026-07-19-request-cancellation-approval-design.md docs/superpowers/plans/2026-07-19-request-cancellation-approval.md includes/request_cancellation_helpers.php includes/day_swap_helpers.php includes/training_request_helpers.php api/late_early_request_api.php api/day_swap_api.php api/training_request_api.php api/attendance_api.php includes/attendance_warning_source_helpers.php includes/approval_badge_helpers.php assets/js/late_early_request.js assets/js/day_swap.js assets/js/training_request.js tests/request_cancellation_helpers_test.php tests/request_cancellation_workflows_test.js`

Expected: no whitespace errors; only task-owned files appear in the scoped diff, while unrelated user-owned changes remain untouched.

- [ ] **Step 5: Acceptance checklist**

Confirm from code and tests that all four request families require a reason, pending states cancel immediately, approved states require HR/Admin, manager review is blocked, rejection restores approval, `pending_cancel_hr` remains effective, reasons/statuses render in Thai, family/ownership SQL filters are present, and stale updates require exactly one affected row.
