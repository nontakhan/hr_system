# Bulk Employee Warnings from Reports Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let HR/admin select any eligible filtered rows from the missing-scan, late/early, and approved-leave reports and create one editable, duplicate-safe employee warning per selected event.

**Architecture:** Add stable report-source metadata and a database uniqueness guarantee to `employee_warnings`, then expose trusted source identities and duplicate state from the existing report APIs. A focused bulk-warning API re-resolves every submitted event under the current HR scope and writes one transaction, while a shared JavaScript controller supplies cross-page selection, preview, editable details, and submission to three thin report adapters.

**Tech Stack:** PHP 8/MySQLi, MySQL/MariaDB, vanilla JavaScript, Bootstrap 5 modal, SweetAlert2, DataTables, Node.js contract tests, PHP source/helper tests.

## Global Constraints

- Only authenticated `admin` and `hr` roles can use the bulk-warning workflow.
- Preserve HR company/branch scope filtering in report reads and bulk writes.
- Each selected report row creates one warning, even when several rows belong to one employee.
- Use the server's current `Y-m-d` date as `warning_date` for every bulk-created warning.
- Generate report-specific default details and allow HR/admin to edit each detail before submission.
- "Select all" means every eligible row in the current filtered result, including rows on other DataTable pages.
- Never create a second report-linked warning for the same source event.
- Keep manual single-employee warning creation and employee warning history behavior unchanged.
- Persist Gregorian dates and keep Thai-friendly visible dates.
- Use prepared statements, escape rendered text, bound batch/detail sizes, and generic public database errors.
- Treat pre-existing worktree changes as user-owned and stage or commit nothing unless the user explicitly requests it.

## File Map

### Create

- `database_employee_warning_sources.sql` — deployable schema migration for nullable report-source columns and unique index.
- `includes/employee_warning_bulk_helpers.php` — source constants, key parsing, duplicate annotation, trusted source resolution, detail limits, and transactional batch creation.
- `includes/attendance_warning_source_helpers.php` — re-resolve one missing-scan or late/early employee-day from authoritative attendance, shift, leave, training, day-swap, and override data without invoking an API controller.
- `assets/js/bulk_employee_warnings.js` — shared selection store, modal injection, editable preview, warning-type load, and batch submit controller.
- `tests/employee_warning_bulk_helpers_test.php` — pure source-key, normalization, limit, and result-shape regression tests.
- `tests/employee_warning_bulk_api_contract_test.php` — source-level contract for role gates, server date, scope revalidation, transaction, prepared inserts, and uniqueness handling.
- `tests/attendance_warning_source_api_test.php` — source-level contract that attendance reports expose stable source identity and duplicate state through the shared annotator.
- `tests/bulk_employee_warnings_ui_test.js` — shared controller contract covering cross-page selection, duplicate exclusion, editable detail, and submit payload.

### Modify

- `includes/employee_warning_helpers.php` — ensure source schema and delegate bulk-specific behavior to the new helper.
- `api/employee_warning_api.php` — load bulk helper; add `bulk_preview` and `bulk_create` POST actions.
- `api/attendance_api.php` — annotate missing and late/early rows with stable warning-source fields and existing-warning state.
- `api/leave_api.php` — annotate expanded leave-day rows with stable warning-source fields and existing-warning state.
- `attendance_missing_report.php` — add bulk action bar, select-all column, and shared modal mount contract.
- `attendance_late_early_report.php` — add the same bulk action contract.
- `leave_report.php` — add the same bulk action contract.
- `assets/js/attendance.js` — register missing and late/early report adapters and preserve selection through DataTable redraw.
- `assets/js/leave_report.js` — register the approved-leave adapter and preserve selection through DataTable redraw.
- `includes/footer.php` — load the shared bulk-warning script before the report scripts.
- `tests/employee_warnings_contract_test.php` — preserve legacy warning contracts and require the new bulk actions/helper.
- `tests/attendance_missing_report_ui_test.js` — require missing-report bulk hooks and adapter.
- `tests/attendance_late_early_report_ui_test.js` — require late/early bulk hooks and adapter.
- `tests/leave_report_ui_test.js` — require approved-leave bulk hooks and adapter.
- `tests/leave_report_api_source_test.php` — require approved-leave source identity and duplicate annotation.

---

### Task 1: Add Stable Warning Source Identity and Schema Guards

**Files:**
- Create: `database_employee_warning_sources.sql`
- Create: `includes/employee_warning_bulk_helpers.php`
- Create: `tests/employee_warning_bulk_helpers_test.php`
- Modify: `includes/employee_warning_helpers.php:1-35`
- Modify: `tests/employee_warnings_contract_test.php:20-65`

**Interfaces:**
- Produces: `EMPLOYEE_WARNING_SOURCE_ATTENDANCE_MISSING`, `EMPLOYEE_WARNING_SOURCE_ATTENDANCE_LATE_EARLY`, and `EMPLOYEE_WARNING_SOURCE_APPROVED_LEAVE` string constants.
- Produces: `employeeWarningBuildSourceKey(string $sourceType, array $event): string`.
- Produces: `employeeWarningParseSourceKey(string $sourceType, string $sourceKey): array` returning normalized integer IDs and Gregorian dates.
- Produces: `employeeWarningEnsureSourceColumns(mysqli $mysqli): void`.
- Produces: `employeeWarningFetchExistingSourceKeys(mysqli $mysqli, string $sourceType, array $sourceKeys): array<string,bool>`.
- Consumes: existing `employeeWarningEnsureTables(mysqli $mysqli): void`.

- [ ] **Step 1: Write failing pure-helper and source-contract tests**

Create `tests/employee_warning_bulk_helpers_test.php` with assertions for exact key formats and invalid input:

```php
<?php
require_once __DIR__ . '/../includes/employee_warning_bulk_helpers.php';

function assertBulkSame($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

assertBulkSame(
    'employee:17|date:2026-07-03',
    employeeWarningBuildSourceKey(EMPLOYEE_WARNING_SOURCE_ATTENDANCE_MISSING, ['employee_id' => 17, 'work_date' => '2026-07-03']),
    'Missing-scan key must identify one employee-day'
);
assertBulkSame(
    'employee:17|date:2026-07-03',
    employeeWarningBuildSourceKey(EMPLOYEE_WARNING_SOURCE_ATTENDANCE_LATE_EARLY, ['employee_id' => 17, 'work_date' => '2026-07-03']),
    'Late/early key must identify one employee-day'
);
assertBulkSame(
    'request:91|date:2026-07-03',
    employeeWarningBuildSourceKey(EMPLOYEE_WARNING_SOURCE_APPROVED_LEAVE, ['id' => 91, 'leave_date' => '2026-07-03']),
    'Leave key must identify one request-day'
);
assertBulkSame(
    ['employee_id' => 17, 'work_date' => '2026-07-03'],
    employeeWarningParseSourceKey(EMPLOYEE_WARNING_SOURCE_ATTENDANCE_MISSING, 'employee:17|date:2026-07-03'),
    'Missing key must parse to trusted lookup values'
);

foreach ([
    ['unknown', 'employee:17|date:2026-07-03'],
    [EMPLOYEE_WARNING_SOURCE_ATTENDANCE_MISSING, 'employee:0|date:2026-07-03'],
    [EMPLOYEE_WARNING_SOURCE_APPROVED_LEAVE, 'request:91|date:2569-07-03'],
] as [$type, $key]) {
    try {
        employeeWarningParseSourceKey($type, $key);
        throw new RuntimeException('Invalid source key was accepted: ' . $type . ' ' . $key);
    } catch (InvalidArgumentException $expected) {
    }
}

echo "employee warning bulk helpers ok\n";
```

Extend `tests/employee_warnings_contract_test.php` to assert the table schema contains `source_type`, `source_key`, `source_event_date`, and `uq_employee_warnings_source`, and that manual `employeeWarningCreateRecord()` still inserts without requiring these fields.

- [ ] **Step 2: Run tests and confirm the new contract fails**

Run:

```powershell
C:\xampp\php\php.exe tests\employee_warning_bulk_helpers_test.php
C:\xampp\php\php.exe tests\employee_warnings_contract_test.php
```

Expected: the helper test fails because `includes/employee_warning_bulk_helpers.php` or its functions do not exist; the existing contract fails because source columns/index are absent.

- [ ] **Step 3: Add the migration and minimal source helper**

Create `database_employee_warning_sources.sql`:

```sql
ALTER TABLE employee_warnings
    ADD COLUMN source_type VARCHAR(50) NULL AFTER detail,
    ADD COLUMN source_key VARCHAR(100) NULL AFTER source_type,
    ADD COLUMN source_event_date DATE NULL AFTER source_key,
    ADD UNIQUE KEY uq_employee_warnings_source (source_type, source_key);
```

Implement constants, strict Gregorian parsing through `normalizeGregorianDateInput()`, exact key builders/parsers, and `employeeWarningFetchExistingSourceKeys()`. The existing-key query must use a prepared `IN` clause and return a lookup map such as `['employee:17|date:2026-07-03' => true]`.

Update `employeeWarningEnsureTables()` so new installations create all three source columns and the unique index. Add `employeeWarningEnsureSourceColumns()` that checks `information_schema.COLUMNS` and `information_schema.STATISTICS` before issuing narrowly scoped `ALTER TABLE` statements for existing installations; call it immediately after the base `CREATE TABLE IF NOT EXISTS` operations.

- [ ] **Step 4: Run focused tests and syntax checks**

Run:

```powershell
C:\xampp\php\php.exe -l includes\employee_warning_helpers.php
C:\xampp\php\php.exe -l includes\employee_warning_bulk_helpers.php
C:\xampp\php\php.exe tests\employee_warning_bulk_helpers_test.php
C:\xampp\php\php.exe tests\employee_warnings_contract_test.php
```

Expected: all commands exit 0 and both tests print their `ok` messages.

- [ ] **Step 5: Review checkpoint without committing**

Run `git diff --check -- database_employee_warning_sources.sql includes/employee_warning_helpers.php includes/employee_warning_bulk_helpers.php tests/employee_warning_bulk_helpers_test.php tests/employee_warnings_contract_test.php` and review the focused diff. Do not stage or commit unless the user explicitly requests it.

---

### Task 2: Annotate Report Rows with Trusted Source and Duplicate State

**Files:**
- Modify: `api/attendance_api.php:500-670`
- Modify: `api/leave_api.php:20-65,278-350`
- Create: `tests/attendance_warning_source_api_test.php`
- Modify: `tests/leave_report_api_source_test.php`

**Interfaces:**
- Consumes: `employeeWarningBuildSourceKey()` and `employeeWarningFetchExistingSourceKeys()` from Task 1.
- Produces on every report row: `warning_source_type: string`, `warning_source_key: string`, `warning_event_date: string`, and `already_warned: bool`.
- Produces: `employeeWarningAnnotateReportRows(mysqli $mysqli, array $rows, string $sourceType, callable $eventMapper): array`.

- [ ] **Step 1: Add failing report-source assertions**

Create `tests/attendance_warning_source_api_test.php` to inspect the PHP report source rather than the browser script:

```php
<?php
$source = file_get_contents(__DIR__ . '/../api/attendance_api.php');

$requireAttendanceWarningSource = function (string $needle, string $message) use ($source): void {
    if (strpos($source, $needle) === false) throw new RuntimeException($message . ': ' . $needle);
};

$requireAttendanceWarningSource('EMPLOYEE_WARNING_SOURCE_ATTENDANCE_MISSING', 'Missing rows need stable warning identity');
$requireAttendanceWarningSource('EMPLOYEE_WARNING_SOURCE_ATTENDANCE_LATE_EARLY', 'Late/early rows need stable warning identity');
$requireAttendanceWarningSource('employeeWarningAnnotateReportRows', 'Attendance reports must use one duplicate annotator');
$requireAttendanceWarningSource('already_warned', 'Attendance reports must expose duplicate state');

echo "attendance warning source API test passed\n";
```

Extend `tests/leave_report_api_source_test.php`:

```php
assertLeaveReportSource(strpos($source, 'EMPLOYEE_WARNING_SOURCE_APPROVED_LEAVE') !== false, 'Leave report must use approved-leave warning identity.');
assertLeaveReportSource(strpos($source, 'warning_source_key') !== false, 'Leave report must expose stable source keys.');
assertLeaveReportSource(strpos($source, 'already_warned') !== false, 'Leave report must expose duplicate warning state.');
```

- [ ] **Step 2: Run the three contracts and verify failure**

Run:

```powershell
C:\xampp\php\php.exe tests\attendance_warning_source_api_test.php
C:\xampp\php\php.exe tests\leave_report_api_source_test.php
```

Expected: each test fails on its first new source-identity or duplicate-state assertion.

- [ ] **Step 3: Add one reusable annotation helper**

Implement `employeeWarningAnnotateReportRows()` in `includes/employee_warning_bulk_helpers.php`. It must build source keys, issue one duplicate lookup per report response rather than one query per row, and append only the four source fields.

Use it after report filtering/sorting:

```php
$rows = employeeWarningAnnotateReportRows(
    $mysqli,
    $rows,
    EMPLOYEE_WARNING_SOURCE_ATTENDANCE_MISSING,
    fn(array $row): array => ['employee_id' => $row['employee_id'], 'work_date' => $row['work_date']]
);
```

For late/early, use the same employee-day mapper under the late/early source type. For leave, map `id` and `leave_date`, preserving one identity per expanded leave day.

Require `employee_warning_helpers.php` and `employee_warning_bulk_helpers.php` only inside the existing HR/admin report path, then call `employeeWarningEnsureTables()` before duplicate lookup so deployments without the source columns are upgraded safely.

- [ ] **Step 4: Run contracts and PHP lint**

Run:

```powershell
C:\xampp\php\php.exe -l api\attendance_api.php
C:\xampp\php\php.exe -l api\leave_api.php
C:\xampp\php\php.exe tests\attendance_warning_source_api_test.php
C:\xampp\php\php.exe tests\leave_report_api_source_test.php
```

Expected: syntax checks pass and all three contracts exit 0.

- [ ] **Step 5: Review checkpoint without committing**

Run `git diff --check -- api/attendance_api.php api/leave_api.php includes/employee_warning_bulk_helpers.php tests/attendance_warning_source_api_test.php tests/leave_report_api_source_test.php`. Do not stage or commit unless explicitly requested.

---

### Task 3: Build the Scope-Safe Transactional Bulk API

**Files:**
- Create: `includes/attendance_warning_source_helpers.php`
- Create: `tests/employee_warning_bulk_api_contract_test.php`
- Modify: `includes/employee_warning_bulk_helpers.php`
- Modify: `api/employee_warning_api.php:25-125`
- Modify: `tests/employee_warnings_contract_test.php`

**Interfaces:**
- Consumes: `employeeWarningParseSourceKey()`, current session role/scopes, attendance evaluation helpers, and approved-leave expansion helpers.
- Produces: `employeeWarningResolveSourceEvent(mysqli $mysqli, string $sourceType, string $sourceKey, string $role, array $scopes): array`.
- Produces: `employeeWarningRequireValidType(mysqli $mysqli, int $warningTypeId): int`.
- Produces: `employeeWarningResolveBulkEvents(mysqli $mysqli, array $items, string $role, array $scopes): array`.
- Produces: `employeeWarningFetchExistingSourcesByType(mysqli $mysqli, array $resolved): array`.
- Produces: `employeeWarningInsertResolvedBulk(mysqli $mysqli, array $resolved, array $existing, int $warningTypeId, string $processingDate, int $userId): array`.
- Produces: `employeeWarningPreviewBulk(mysqli $mysqli, array $input, string $role, array $scopes): array`.
- Produces: `employeeWarningCreateBulk(mysqli $mysqli, array $input, int $userId, string $role, array $scopes): array`.
- Produces: `attendanceResolveMissingWarningSource(mysqli $mysqli, int $employeeId, string $workDate, string $role, array $scopes): array`.
- Produces: `attendanceResolveLateEarlyWarningSource(mysqli $mysqli, int $employeeId, string $workDate, string $role, array $scopes): array`.
- API request: `{action:"bulk_create", warning_type_id:number, items:[{source_type:string, source_key:string, detail:string}]}`.
- API response data: `{processing_date:string, created_count:number, duplicate_count:number, skipped_count:number, created_keys:string[], duplicate_keys:string[], skipped:array}`.

- [ ] **Step 1: Write a failing bulk API source contract**

Create `tests/employee_warning_bulk_api_contract_test.php` that loads the API/helper sources and requires these exact safety markers:

```php
<?php
$root = dirname(__DIR__);
$api = file_get_contents($root . '/api/employee_warning_api.php');
$bulk = file_get_contents($root . '/includes/employee_warning_bulk_helpers.php');

function requireBulkText(string $source, string $needle, string $message): void {
    if (strpos($source, $needle) === false) throw new RuntimeException($message . ': ' . $needle);
}

foreach (['bulk_preview', 'bulk_create'] as $action) requireBulkText($api, $action, 'Warning API must expose bulk action');
requireBulkText($api, 'employeeWarningRequireHr($role)', 'Bulk actions must retain the role gate');
requireBulkText($bulk, 'hrScopeBuildEmployeeWhereClause', 'Bulk source resolution must reapply HR scope');
requireBulkText($bulk, 'date(\'Y-m-d\')', 'Bulk warnings must use the server date');
requireBulkText($bulk, 'begin_transaction', 'Bulk creation must start a transaction');
requireBulkText($bulk, 'commit()', 'Bulk creation must commit successful work');
requireBulkText($bulk, 'rollback()', 'Bulk creation must roll back invalid work');
requireBulkText($bulk, 'uq_employee_warnings_source', 'Duplicate-key handling must recognize the source uniqueness rule');
requireBulkText($bulk, 'INSERT INTO employee_warnings', 'Bulk creation must insert warning records');
requireBulkText($bulk, 'source_type, source_key, source_event_date', 'Bulk insert must persist trusted source metadata');
requireBulkText($bulk, 'attendanceResolveMissingWarningSource', 'Bulk creation must use the non-controller attendance resolver');
requireBulkText($bulk, 'attendanceResolveLateEarlyWarningSource', 'Bulk creation must use the non-controller attendance resolver');

echo "employee warning bulk API contract ok\n";
```

Also extend `tests/employee_warnings_contract_test.php` to require both bulk action names while retaining every legacy action assertion.

- [ ] **Step 2: Run contracts and verify failure**

Run:

```powershell
C:\xampp\php\php.exe tests\employee_warning_bulk_api_contract_test.php
C:\xampp\php\php.exe tests\employee_warnings_contract_test.php
```

Expected: failure because bulk actions and transaction functions are absent.

- [ ] **Step 3: Implement strict payload normalization and source resolvers**

Add `employeeWarningNormalizeBulkInput()` with exact server bounds:

```php
const EMPLOYEE_WARNING_BULK_MAX_ITEMS = 500;
const EMPLOYEE_WARNING_DETAIL_MAX_LENGTH = 2000;
```

Reject empty batches, more than 500 submitted items, duplicate keys inside one payload, unsupported types, and non-string/overlength details.

Create `includes/attendance_warning_source_helpers.php` so the write path does not include or execute `api/attendance_api.php`. Implement the two employee-day resolvers by reusing `includes/attendance_helpers.php`, `includes/employee_shift_assignment_helpers.php`, and the same prepared lookup/evaluation contracts as the report. Implement source resolvers that re-query authoritative data:

- Attendance missing: parse employee/date, fetch the employee under `employeeWarningEmployeeScopeClause()`, rebuild effective attendance/shift/leave/training/day-swap status for that date, and require a status in `attendanceMissingScanStatuses()`.
- Attendance late/early: parse employee/date, fetch under scope, rebuild effective shift/check values and approved hourly offsets, and require `late_minutes > 0 || early_minutes > 0`.
- Approved leave: parse request/date, query an approved actual-leave request joined to an in-scope employee, expand the request for that month, and require an expanded row whose `leave_date` exactly matches the parsed date.

Each resolver returns trusted `employee_id`, `event_date`, `event_label`, and generated detail inputs. Never accept employee ID, event date, name, minutes, or status from the client outside the parsed opaque source key.

- [ ] **Step 4: Implement preview and transactional creation**

`employeeWarningPreviewBulk()` resolves all sources, checks existing keys in one query, and returns server `processing_date`, trusted labels/default details, and `already_warned` per item.

`employeeWarningCreateBulk()` must:

```php
$processingDate = date('Y-m-d');
$batch = employeeWarningNormalizeBulkInput($input);
$warningTypeId = employeeWarningRequireValidType($mysqli, $batch['warning_type_id']);
$resolved = employeeWarningResolveBulkEvents($mysqli, $batch['items'], $role, $scopes);
$existing = employeeWarningFetchExistingSourcesByType($mysqli, $resolved);
$mysqli->begin_transaction();
try {
    $result = employeeWarningInsertResolvedBulk(
        $mysqli,
        $resolved,
        $existing,
        $warningTypeId,
        $processingDate,
        $userId
    );
    $mysqli->commit();
    return $result + ['processing_date' => $processingDate];
} catch (Throwable $e) {
    $mysqli->rollback();
    throw $e;
}
```

Prepare one insert statement outside the loop. Treat MySQL duplicate key error `1062` on `uq_employee_warnings_source` as a duplicate result and continue; rethrow all other database errors so the transaction rolls back.

Add POST branches in `api/employee_warning_api.php` after `employeeWarningRequireHr($role)`:

```php
if ($postAction === 'bulk_preview') {
    sendEmployeeWarningJson(['status' => 'success', 'data' => employeeWarningPreviewBulk($mysqli, $input, $role, $scopes)]);
}
if ($postAction === 'bulk_create') {
    sendEmployeeWarningJson(['status' => 'success', 'data' => employeeWarningCreateBulk($mysqli, $input, $userId, $role, $scopes)]);
}
```

- [ ] **Step 5: Run focused tests and lint**

Run:

```powershell
C:\xampp\php\php.exe -l api\employee_warning_api.php
C:\xampp\php\php.exe -l includes\employee_warning_bulk_helpers.php
C:\xampp\php\php.exe -l includes\attendance_warning_source_helpers.php
C:\xampp\php\php.exe tests\employee_warning_bulk_helpers_test.php
C:\xampp\php\php.exe tests\employee_warning_bulk_api_contract_test.php
C:\xampp\php\php.exe tests\employee_warnings_contract_test.php
```

Expected: all commands exit 0. Database race protection is verified later through the real local workflow by submitting the same source twice; if local MySQL is unavailable, report that DB-backed duplicate verification was not run.

- [ ] **Step 6: Review checkpoint without committing**

Review `git diff -- api/employee_warning_api.php includes/employee_warning_bulk_helpers.php includes/attendance_warning_source_helpers.php tests/employee_warning_bulk_api_contract_test.php tests/employee_warnings_contract_test.php` for scope bypasses, client-trusted identity, and insert statements inside validation. Run `git diff --check` on those files. Do not stage or commit unless explicitly requested.

---

### Task 4: Create the Shared Bulk Warning Controller and Modal

**Files:**
- Create: `assets/js/bulk_employee_warnings.js`
- Create: `tests/bulk_employee_warnings_ui_test.js`
- Modify: `includes/footer.php:25-55`

**Interfaces:**
- Produces: `window.EmployeeWarningBulk.create(config): BulkWarningController`.
- `config` requires `sourceType`, `getRows(): array`, `getDataTable(): object|null`, `buildEvent(row): object`, `onCompleted(result): void`, and DOM IDs for action/count/select-all hooks.
- Controller methods: `replaceRows(rows)`, `clearSelection()`, `toggleKey(key, checked)`, `toggleAllEligible(checked)`, `openPreview()`, `submit()`.
- Consumes API actions `get_warning_types`, `bulk_preview`, and `bulk_create`.

- [ ] **Step 1: Write the failing shared UI contract**

Create `tests/bulk_employee_warnings_ui_test.js`:

```js
const fs = require('fs');
const script = fs.readFileSync('assets/js/bulk_employee_warnings.js', 'utf8');
const footer = fs.readFileSync('includes/footer.php', 'utf8');

function includes(text, needle, message) {
  if (!text.includes(needle)) throw new Error(`${message}: ${needle}`);
}

includes(script, 'window.EmployeeWarningBulk', 'Shared module must publish one namespace');
includes(script, 'selectedKeys = new Set()', 'Selection must be independent of DataTable pages');
includes(script, 'toggleAllEligible', 'Controller must select the complete eligible result set');
includes(script, 'bulk_preview', 'Modal must preview trusted server events');
includes(script, 'bulk_create', 'Controller must submit one batch request');
includes(script, 'warningDetail', 'Each preview row must expose editable detail');
includes(script, 'already_warned', 'Duplicates must be excluded');
includes(script, 'disabled = true', 'Submit must be disabled during requests');
includes(footer, 'assets/js/bulk_employee_warnings.js', 'Footer must load shared module');

console.log('bulk employee warnings UI contract passed');
```

- [ ] **Step 2: Run the contract and verify failure**

Run `node tests\bulk_employee_warnings_ui_test.js`.

Expected: FAIL because the shared script does not exist.

- [ ] **Step 3: Implement the controller and injected modal shell**

Use one module-scoped namespace and inject one modal only when `create()` is first called. The modal IDs must be stable:

```html
<div class="modal fade" id="bulkEmployeeWarningModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <form id="bulkEmployeeWarningForm">
        <select id="bulkEmployeeWarningType" required></select>
        <span id="bulkEmployeeWarningProcessingDate"></span>
        <div id="bulkEmployeeWarningPreviewRows"></div>
        <button id="bulkEmployeeWarningSubmitBtn" type="submit"></button>
      </form>
    </div>
  </div>
</div>
```

Keep raw report rows in `rowsByKey` and selection in `selectedKeys`. `toggleAllEligible(true)` must iterate the full `getRows()` array, not DataTables' current page nodes. `openPreview()` calls `bulk_preview`, renders trusted labels/default details with escaped text, and excludes `already_warned` rows from the submit count. Store edited detail by source key so rerendering does not move text to another employee.

`submit()` sends only `source_type`, `source_key`, and the edited `detail`, disables submit until completion, then shows a SweetAlert summary and calls `onCompleted(result)`.

- [ ] **Step 4: Load shared code before report adapters**

In `includes/footer.php`, add a cache-busted script immediately before `leave_report.js` and `attendance.js`:

```php
<script src="assets/js/bulk_employee_warnings.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/bulk_employee_warnings.js'); ?>"></script>
```

- [ ] **Step 5: Run JS syntax and contract tests**

Run:

```powershell
node --check assets\js\bulk_employee_warnings.js
node tests\bulk_employee_warnings_ui_test.js
```

Expected: syntax passes and the contract prints its success message.

- [ ] **Step 6: Review checkpoint without committing**

Review escaping, selection-by-key, editable-detail association, and submit-button restoration on errors. Run `git diff --check -- assets/js/bulk_employee_warnings.js includes/footer.php tests/bulk_employee_warnings_ui_test.js`. Do not stage or commit unless explicitly requested.

---

### Task 5: Integrate Missing-Scan and Late/Early Reports

**Files:**
- Modify: `attendance_missing_report.php:12-85`
- Modify: `attendance_late_early_report.php:12-90`
- Modify: `assets/js/attendance.js:1-40,420-735`
- Modify: `tests/attendance_missing_report_ui_test.js`
- Modify: `tests/attendance_late_early_report_ui_test.js`

**Interfaces:**
- Consumes: `window.EmployeeWarningBulk.create(config)` from Task 4.
- Consumes row fields from Task 2: `warning_source_type`, `warning_source_key`, `warning_event_date`, `already_warned`.
- Produces adapters `attendanceMissingWarningBulk` and `attendanceLateEarlyWarningBulk`.

- [ ] **Step 1: Add failing page and adapter contracts**

Require each page to contain its own action button, selected counter, and header select-all checkbox. Example missing-report assertions:

```js
assertIncludes(page, 'id="attendanceMissingWarningBulkBtn"', 'Missing report needs a bulk warning action.');
assertIncludes(page, 'id="attendanceMissingWarningSelectedCount"', 'Missing report needs a selected count.');
assertIncludes(page, 'id="attendanceMissingWarningSelectAll"', 'Missing report needs select all.');
assertIncludes(script, 'attendanceMissingWarningBulk', 'Missing report needs a shared-controller adapter.');
assertIncludes(script, 'warning_source_key', 'Missing rows must render stable selection keys.');
```

Repeat with `attendanceLateEarlyWarningBulkBtn`, `attendanceLateEarlyWarningSelectedCount`, `attendanceLateEarlyWarningSelectAll`, and `attendanceLateEarlyWarningBulk`.

- [ ] **Step 2: Run both tests and verify failure**

Run:

```powershell
node tests\attendance_missing_report_ui_test.js
node tests\attendance_late_early_report_ui_test.js
```

Expected: both fail because action hooks and adapters are absent.

- [ ] **Step 3: Add report action bars and checkbox columns**

Above each table, add a compact Bootstrap action bar with selected count and a disabled primary button. Add the select-all checkbox as the first header cell. Update empty/loading/error `colspan` values from 8 to 9 for missing scan and 12 to 13 for late/early.

Each rendered row starts with:

```html
<td class="text-center">
  <input class="form-check-input employee-warning-row-select"
         type="checkbox"
         data-warning-source-key="..."
         aria-label="เลือกเหตุการณ์เพื่อเพิ่มใบเตือน">
</td>
```

Already-warned rows render a disabled checkbox plus a small `ออกใบเตือนแล้ว` badge in the status cell.

- [ ] **Step 4: Register both adapters and synchronize redraw state**

Build trusted display adapters only from report response rows. Missing detail includes formatted event date, missing status, check-in, and check-out. Late/early detail includes event date, positive late/early minutes, check-in, and check-out; a row containing both conditions remains one event.

After every successful report load:

```js
attendanceMissingWarningBulk.replaceRows(attendanceMissingRows);
```

Before filter-triggered reload, call `clearSelection()`. After DataTable creation, listen for `draw.dt` and call the controller's checkbox synchronization method so current-page DOM mirrors the complete selection set. Row checkbox delegation must update selection without requiring direct listeners on every redraw.

For `onCompleted`, mark returned created/duplicate keys as `already_warned`, clear their selection, rerender the report, and retain the current filters.

- [ ] **Step 5: Run attendance syntax and contracts**

Run:

```powershell
node --check assets\js\attendance.js
node tests\attendance_missing_report_ui_test.js
node tests\attendance_late_early_report_ui_test.js
C:\xampp\php\php.exe -l attendance_missing_report.php
C:\xampp\php\php.exe -l attendance_late_early_report.php
```

Expected: all commands exit 0.

- [ ] **Step 6: Review checkpoint without committing**

Review DataTable select-all semantics using more than 25 fixture rows, verify filter changes clear selection, and run `git diff --check` on the five task files. Do not stage or commit unless explicitly requested.

---

### Task 6: Integrate the Approved Leave Report

**Files:**
- Modify: `leave_report.php:12-90`
- Modify: `assets/js/leave_report.js:1-175`
- Modify: `tests/leave_report_ui_test.js`
- Modify: `tests/leave_report_api_source_test.php`

**Interfaces:**
- Consumes: `window.EmployeeWarningBulk.create(config)` from Task 4.
- Consumes row fields from Task 2 and expanded `id`, `leave_date`, `leave_days`, `day_part_label`, `leave_type_name`, and `reason`.
- Produces adapter `approvedLeaveWarningBulk`.

- [ ] **Step 1: Add failing leave-page bulk UI assertions**

Extend `tests/leave_report_ui_test.js`:

```js
assertIncludes(page, 'id="approvedLeaveWarningBulkBtn"', 'Leave report needs a bulk warning action.');
assertIncludes(page, 'id="approvedLeaveWarningSelectedCount"', 'Leave report needs a selected count.');
assertIncludes(page, 'id="approvedLeaveWarningSelectAll"', 'Leave report needs complete-result select all.');
assertIncludes(script, 'approvedLeaveWarningBulk', 'Leave report needs a shared-controller adapter.');
assertIncludes(script, 'warning_source_key', 'Leave report selection must use request-day keys.');
assertIncludes(script, 'leave_date', 'Generated warning detail must identify the expanded leave day.');
```

- [ ] **Step 2: Run the UI test and verify failure**

Run `node tests\leave_report_ui_test.js`.

Expected: FAIL on the first new bulk-warning hook.

- [ ] **Step 3: Add leave action bar and checkbox column**

Use the same action-bar visual language as the attendance reports. Add the select-all cell before leave date and update all table `colspan` values from 9 to 10. Render duplicate rows with disabled selection and the `ออกใบเตือนแล้ว` badge next to the leave type or in the selection cell without removing existing leave information.

- [ ] **Step 4: Register the approved-leave adapter**

Keep `approvedLeaveReportRows` as module-level state instead of passing rows directly from fetch to render only. Build default detail from the trusted row:

```text
ลา{leave_type_name} วันที่ {Thai leave_date} จำนวน {leave_days} วัน{optional day_part_label} เหตุผล: {reason or '-'}
```

Call `approvedLeaveWarningBulk.replaceRows(approvedLeaveReportRows)` after load, clear selection before any filter reload, synchronize checkboxes on `draw.dt`, and mark returned keys as warned in `onCompleted`.

- [ ] **Step 5: Run leave report tests and syntax checks**

Run:

```powershell
node --check assets\js\leave_report.js
node tests\leave_report_ui_test.js
C:\xampp\php\php.exe tests\leave_report_api_source_test.php
C:\xampp\php\php.exe -l leave_report.php
C:\xampp\php\php.exe -l api\leave_api.php
```

Expected: all commands exit 0.

- [ ] **Step 6: Review checkpoint without committing**

Verify that a multi-day leave request produces independent request-date keys, selecting three expanded dates creates three preview items, and changing leave type/month clears selection. Run `git diff --check` on the four task files. Do not stage or commit unless explicitly requested.

---

### Task 7: End-to-End Regression and Delivery Verification

**Files:**
- Modify only if a failing focused regression exposes a defect in task-owned files.
- Test: all warning/report tests named below.

**Interfaces:**
- Consumes: all prior task outputs.
- Produces: verified bulk-warning workflow with an evidence log of commands and results.

- [ ] **Step 1: Run all focused PHP tests**

Run:

```powershell
C:\xampp\php\php.exe tests\employee_warning_bulk_helpers_test.php
C:\xampp\php\php.exe tests\employee_warning_bulk_api_contract_test.php
C:\xampp\php\php.exe tests\employee_warnings_contract_test.php
C:\xampp\php\php.exe tests\attendance_warning_source_api_test.php
C:\xampp\php\php.exe tests\leave_report_api_source_test.php
```

Expected: every test exits 0 and prints its success message.

- [ ] **Step 2: Run all focused JavaScript tests**

Run:

```powershell
node tests\bulk_employee_warnings_ui_test.js
node tests\attendance_missing_report_ui_test.js
node tests\attendance_late_early_report_ui_test.js
node tests\leave_report_ui_test.js
```

Expected: every test exits 0.

- [ ] **Step 3: Run syntax checks on every changed executable file**

Run:

```powershell
C:\xampp\php\php.exe -l includes\employee_warning_helpers.php
C:\xampp\php\php.exe -l includes\employee_warning_bulk_helpers.php
C:\xampp\php\php.exe -l includes\attendance_warning_source_helpers.php
C:\xampp\php\php.exe -l api\employee_warning_api.php
C:\xampp\php\php.exe -l api\attendance_api.php
C:\xampp\php\php.exe -l api\leave_api.php
C:\xampp\php\php.exe -l attendance_missing_report.php
C:\xampp\php\php.exe -l attendance_late_early_report.php
C:\xampp\php\php.exe -l leave_report.php
node --check assets\js\bulk_employee_warnings.js
node --check assets\js\attendance.js
node --check assets\js\leave_report.js
```

Expected: PHP reports `No syntax errors detected` for every file and Node exits 0.

- [ ] **Step 4: Exercise the real browser workflows when the local app is available**

For each report:

1. Log in as an HR user with limited company/branch scope.
2. Apply filters that return more than 25 rows.
3. Select one row, change DataTable page, select another, and confirm count is 2.
4. Use select-all and confirm the count equals all eligible filtered rows, excluding already-warned rows.
5. Open the modal, choose an existing warning type, edit two different details, and submit.
6. Confirm each selected event creates its own warning with the server processing date.
7. Reload the same report and confirm those rows are disabled as already warned.
8. Attempt the same source keys again through the API and confirm zero duplicates are created.
9. Confirm an out-of-scope source causes no batch insert.

Also create one manual warning from `employee_warnings.php` and verify it still appears in HR monthly summary and the employee's `my_warnings.php` history.

- [ ] **Step 5: Inspect the complete task-owned diff and whitespace**

Run:

```powershell
git status --short
git diff --stat
git diff --check
```

Expected: only task-owned files plus the already approved design/plan documents are changed; `git diff --check` reports no whitespace errors. If unrelated files are present, leave them untouched and exclude them from any later staging.

- [ ] **Step 6: Report completion without publishing unless requested**

Summarize changed files, focused test commands and results, browser coverage or any unavailable runtime dependency, and any migration deployment step. Do not stage, commit, or push unless the user explicitly asks.

---

### Task 8: Replace Per-Event Preview with a Lightweight Shared-Note Modal

**Files:**
- Modify: `assets/js/bulk_employee_warnings.js`
- Modify: `assets/js/attendance.js`
- Modify: `assets/js/leave_report.js`
- Modify: `includes/employee_warning_bulk_helpers.php`
- Modify: `api/employee_warning_api.php`
- Modify: `tests/bulk_employee_warnings_ui_test.js`
- Modify: `tests/employee_warning_bulk_helpers_test.php`
- Modify: `tests/employee_warning_bulk_api_contract_test.php`
- Modify: `tests/employee_warnings_contract_test.php`

**Interfaces:**
- Produces: `employeeWarningNormalizeSharedNote($value): string` with a 2,000-character server limit.
- Produces: `employeeWarningAppendSharedNote(string $generatedDetail, string $sharedNote): string`.
- Changes bulk-create request to `{action:"bulk_create", warning_type_id:number, shared_note:string, items:[{source_type:string, source_key:string}]}`.
- Requires each report adapter's `buildEvent(row)` to expose `employee_id` for unique-employee counting only; server identity still comes exclusively from the source key.
- Removes the `bulk_preview` client/API path and all per-event modal textareas.

- [ ] **Step 1: Write failing helper and UI contracts**

Add pure helper assertions:

```php
assertBulkSame('หมายเหตุร่วม', employeeWarningNormalizeSharedNote('  หมายเหตุร่วม  '), 'Shared note must be trimmed');
assertBulkSame("รายละเอียดอัตโนมัติ\nหมายเหตุ: หมายเหตุร่วม", employeeWarningAppendSharedNote('รายละเอียดอัตโนมัติ', 'หมายเหตุร่วม'), 'Shared note must append to trusted detail');
assertBulkSame('รายละเอียดอัตโนมัติ', employeeWarningAppendSharedNote('รายละเอียดอัตโนมัติ', ''), 'Blank shared note must not alter detail');
```

Update the UI contract to require `bulkEmployeeWarningSharedNote`, `uniqueEmployeeCount`, and a `shared_note` payload, and to reject `bulk_preview`, `warningDetail`, and `bulkEmployeeWarningPreviewRows`.

- [ ] **Step 2: Run tests and confirm expected failure**

Run:

```powershell
C:\xampp\php\php.exe tests\employee_warning_bulk_helpers_test.php
node tests\bulk_employee_warnings_ui_test.js
```

Expected: helper test fails because shared-note functions are absent; UI test fails because the modal still contains per-event preview behavior.

- [ ] **Step 3: Implement shared-note normalization and server-generated details**

Normalize one optional string note with the existing 2,000-character limit. Remove client detail from normalized items. During trusted source resolution, set each insert detail to:

```php
$event['detail'] = employeeWarningAppendSharedNote($event['generated_detail'], $sharedNote);
```

Keep the transaction, duplicate handling, server date, and HR scope behavior unchanged.

- [ ] **Step 4: Replace modal preview with immediate summary rendering**

On open, calculate:

```js
const selectedEvents = [...selectedKeys].map((key) => eventsByKey.get(key)).filter(Boolean);
const uniqueEmployeeCount = new Set(selectedEvents.map((event) => String(event.employee_id))).size;
```

Render only selected-event count, unique-employee count, warning-type selector, and one optional shared-note textarea. Load warning types but do not call a per-event preview API. Submit source keys plus `shared_note`; show the processing spinner only after submit.

- [ ] **Step 5: Run focused regression and syntax checks**

Run:

```powershell
C:\xampp\php\php.exe tests\employee_warning_bulk_helpers_test.php
C:\xampp\php\php.exe tests\employee_warning_bulk_api_contract_test.php
C:\xampp\php\php.exe tests\employee_warnings_contract_test.php
node tests\bulk_employee_warnings_ui_test.js
node tests\attendance_missing_report_ui_test.js
node tests\attendance_late_early_report_ui_test.js
node tests\leave_report_ui_test.js
C:\xampp\php\php.exe -l includes\employee_warning_bulk_helpers.php
C:\xampp\php\php.exe -l api\employee_warning_api.php
node --check assets\js\bulk_employee_warnings.js
node --check assets\js\attendance.js
node --check assets\js\leave_report.js
git diff --check
```

Expected: all commands exit 0, modal contract contains no per-event preview path, and legacy report/warning contracts remain green.
