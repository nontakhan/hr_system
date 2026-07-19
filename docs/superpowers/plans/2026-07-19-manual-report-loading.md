# Manual Report Loading Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the three report-menu pages fetch report rows only after the user clicks `แสดงรายงาน`.

**Architecture:** Keep lightweight filter-option initialization on page load and keep the existing report fetch/render functions unchanged. Remove every report fetch invocation from filter changes and filter-option completion, while retaining dependent company-to-branch updates and the explicit button bindings.

**Tech Stack:** Vanilla JavaScript, Select2, DataTables, Node.js contract tests, PHP-rendered Bootstrap pages.

## Global Constraints

- Scope is limited to `attendance_missing_report.php`, `attendance_late_early_report.php`, and `leave_report.php` behavior.
- Do not change PHP APIs, database queries, authorization, schemas, employee-specific attendance reporting, DataTables behavior, or bulk-warning behavior.
- Preserve all pre-existing user-owned worktree changes and do not stage or commit without an explicit user request.
- Use test-first development: observe the new regression test fail before changing production JavaScript.

---

### Task 1: Add the manual-search regression contract

**Files:**
- Create: `tests/report_manual_search_ui_test.js`
- Read: `assets/js/attendance.js`
- Read: `assets/js/leave_report.js`
- Read: `attendance_missing_report.php`
- Read: `attendance_late_early_report.php`
- Read: `leave_report.php`

**Interfaces:**
- Consumes: existing initializer functions `initAttendanceMissingReport()`, `initAttendanceLateEarlyReport()`, and `initApprovedLeaveReport()`.
- Produces: a source-level regression contract that rejects report fetch calls from filter event handlers and filter-option completion while requiring explicit load-button bindings.

- [ ] **Step 1: Write the failing source-contract test**

Create `tests/report_manual_search_ui_test.js` with helpers that extract complete named function bodies by locating a function declaration and balancing braces. Assert the following exact behavior:

```js
const fs = require('fs');
const assert = require('assert');

const attendance = fs.readFileSync('assets/js/attendance.js', 'utf8');
const leave = fs.readFileSync('assets/js/leave_report.js', 'utf8');
const missingPage = fs.readFileSync('attendance_missing_report.php', 'utf8');
const lateEarlyPage = fs.readFileSync('attendance_late_early_report.php', 'utf8');
const leavePage = fs.readFileSync('leave_report.php', 'utf8');

function functionBody(source, name) {
    const marker = `function ${name}(`;
    const start = source.indexOf(marker);
    assert.notStrictEqual(start, -1, `${name} should exist`);
    const open = source.indexOf('{', start);
    let depth = 0;
    for (let index = open; index < source.length; index += 1) {
        if (source[index] === '{') depth += 1;
        if (source[index] === '}') depth -= 1;
        if (depth === 0) return source.slice(open + 1, index);
    }
    throw new Error(`Could not parse ${name}`);
}

function assertManualSearch(source, config) {
    const init = functionBody(source, config.init);
    const options = functionBody(source, config.options);

    assert.ok(
        init.includes(`getElementById('${config.button}')?.addEventListener('click', ${config.loader})`)
            || init.includes(`if (loadBtn) loadBtn.addEventListener('click', ${config.loader})`),
        `${config.init} should load the report from its button`
    );
    assert.strictEqual(
        (init.match(new RegExp(`${config.loader}\\\\b`, 'g')) || []).length,
        1,
        `${config.init} should reference ${config.loader} only in the load-button binding`
    );
    assert.ok(!options.includes(`${config.loader}()`), `${config.options} must not auto-load report rows`);
}

assertManualSearch(attendance, {
    init: 'initAttendanceMissingReport',
    options: 'loadAttendanceMissingFilterOptions',
    button: 'attendanceMissingLoadBtn',
    loader: 'loadAttendanceMissingReport',
});
assertManualSearch(attendance, {
    init: 'initAttendanceLateEarlyReport',
    options: 'loadAttendanceLateEarlyFilterOptions',
    button: 'attendanceLateEarlyLoadBtn',
    loader: 'loadAttendanceLateEarlyReport',
});
assertManualSearch(leave, {
    init: 'initApprovedLeaveReport',
    options: 'loadApprovedLeaveReportOptions',
    button: 'approvedLeaveReportLoadBtn',
    loader: 'loadApprovedLeaveReport',
});

assert.ok(functionBody(attendance, 'initAttendanceMissingReport').includes('updateAttendanceMissingBranchOptions()'));
assert.ok(functionBody(attendance, 'initAttendanceLateEarlyReport').includes('updateAttendanceLateEarlyBranchOptions()'));
assert.ok(functionBody(leave, 'initApprovedLeaveReport').includes('updateApprovedLeaveReportBranches()'));

[missingPage, lateEarlyPage, leavePage].forEach((page) => {
    assert.ok(page.includes('เลือกเดือนแล้วแสดงรายงาน'), 'Each report should retain its initial manual-search instruction');
});

console.log('report_manual_search_ui_test passed');
```

- [ ] **Step 2: Run the test and verify RED**

Run:

```powershell
node tests\report_manual_search_ui_test.js
```

Expected: FAIL because each initializer references its loader from filter-change handlers and each filter-options function calls its loader after options arrive.

- [ ] **Step 3: Confirm the failure is behavioral**

Confirm the assertion names one of the three initializer/loader pairs and is not a syntax error or missing-file error. Fix only the test parser if necessary, rerunning until it fails for automatic report loading.

---

### Task 2: Make attendance reports button-triggered

**Files:**
- Modify: `assets/js/attendance.js`
- Test: `tests/report_manual_search_ui_test.js`
- Test: `tests/attendance_missing_report_ui_test.js`
- Test: `tests/attendance_late_early_report_ui_test.js`

**Interfaces:**
- Consumes: `updateAttendanceMissingBranchOptions()`, `updateAttendanceLateEarlyBranchOptions()`, `loadAttendanceMissingReport()`, and `loadAttendanceLateEarlyReport()`.
- Produces: initializers in which only each explicit load button triggers its report loader.

- [ ] **Step 1: Remove missing-scan automatic searches**

In `initAttendanceMissingReport()`:

- keep native and Select2 company handlers, but have them call only `updateAttendanceMissingBranchOptions()`;
- remove native and Select2 branch report handlers;
- remove missing-type and month report handlers;
- retain `if (loadBtn) loadBtn.addEventListener('click', loadAttendanceMissingReport);`.

In `loadAttendanceMissingFilterOptions()`, retain option filling and `updateAttendanceMissingBranchOptions()`, then remove the trailing `loadAttendanceMissingReport();`.

- [ ] **Step 2: Remove late/early automatic searches**

In `initAttendanceLateEarlyReport()`:

- keep the native and Select2 company handlers, but have them call only `updateAttendanceLateEarlyBranchOptions()`;
- remove native and Select2 branch report handlers;
- remove incident-type and month report handlers;
- retain the click binding for `attendanceLateEarlyLoadBtn`.

In `loadAttendanceLateEarlyFilterOptions()`, retain option filling and `updateAttendanceLateEarlyBranchOptions()`, then remove the trailing `loadAttendanceLateEarlyReport();`.

- [ ] **Step 3: Run attendance-focused tests**

Run:

```powershell
node tests\report_manual_search_ui_test.js
node tests\attendance_missing_report_ui_test.js
node tests\attendance_late_early_report_ui_test.js
node --check assets\js\attendance.js
```

Expected: the new cross-report test may still fail only on approved leave; both existing attendance UI tests pass; JavaScript syntax check exits successfully.

---

### Task 3: Make the approved-leave report button-triggered

**Files:**
- Modify: `assets/js/leave_report.js`
- Test: `tests/report_manual_search_ui_test.js`
- Test: `tests/leave_report_ui_test.js`

**Interfaces:**
- Consumes: `updateApprovedLeaveReportBranches()` and `loadApprovedLeaveReport()`.
- Produces: an approved-leave initializer in which only the explicit load button triggers the report loader.

- [ ] **Step 1: Remove approved-leave automatic searches**

In `initApprovedLeaveReport()`:

- keep the company handler, but have it call only `updateApprovedLeaveReportBranches()`;
- remove the branch, leave-type, and month report handlers;
- retain `document.getElementById('approvedLeaveReportLoadBtn')?.addEventListener('click', loadApprovedLeaveReport);`.

In `loadApprovedLeaveReportOptions()`, retain option filling and `updateApprovedLeaveReportBranches()`, then remove the trailing `loadApprovedLeaveReport();`.

- [ ] **Step 2: Run the complete focused UI set and verify GREEN**

Run:

```powershell
node tests\report_manual_search_ui_test.js
node tests\attendance_missing_report_ui_test.js
node tests\attendance_late_early_report_ui_test.js
node tests\leave_report_ui_test.js
node --check assets\js\attendance.js
node --check assets\js\leave_report.js
```

Expected: all four tests print their passed messages and both syntax checks exit successfully.

---

### Task 4: Regression and worktree verification

**Files:**
- Verify: `assets/js/attendance.js`
- Verify: `assets/js/leave_report.js`
- Verify: `tests/report_manual_search_ui_test.js`

**Interfaces:**
- Consumes: the completed manual-search behavior.
- Produces: fresh evidence that report UI contracts, warning-selection integration, and patch hygiene remain valid.

- [ ] **Step 1: Run related bulk-warning regression tests**

Run:

```powershell
node tests\bulk_employee_warnings_ui_test.js
node tests\bulk_employee_warnings_pagination_test.js
```

If the first filename is not present, use `rg --files tests | rg "bulk_employee_warnings"` and run every existing matching Node.js test. Expected: all existing matching tests pass.

- [ ] **Step 2: Inspect the focused diff**

Run:

```powershell
git diff -- assets/js/attendance.js assets/js/leave_report.js tests/report_manual_search_ui_test.js docs/superpowers/specs/2026-07-19-manual-report-loading-design.md docs/superpowers/plans/2026-07-19-manual-report-loading.md
```

Expected: only automatic loader invocations/listeners are removed from production scripts; button bindings, branch refreshes, fetch/render functions, and warning behavior remain present.

- [ ] **Step 3: Validate whitespace and preserve unrelated work**

Run:

```powershell
git diff --check
git status --short
```

Expected: no whitespace errors. Pre-existing employee-warning modifications and documents remain present but unchanged by this task. Do not stage, commit, or push.
