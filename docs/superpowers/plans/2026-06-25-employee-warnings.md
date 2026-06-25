# Employee Warnings Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the employee warning-record module described in `docs/superpowers/specs/2026-06-25-employee-warnings-design.md`.

**Architecture:** Add a small helper layer for schema creation and warning queries, a dedicated JSON API, one HR/admin overview page, one read-only employee page, and one shared JS file. Keep the module separate from leave, attendance, and approval workflows.

**Tech Stack:** PHP 8/mysqli, Bootstrap 5, SweetAlert2, Font Awesome, vanilla JavaScript, focused PHP/JS contract tests.

---

## File Structure

- Create `includes/employee_warning_helpers.php`: schema guards, month validation, warning type CRUD, monthly summary queries, self-service query helpers.
- Create `api/employee_warning_api.php`: request routing, auth/role gates, JSON response handling.
- Create `employee_warnings.php`: HR/admin monthly overview, warning record modal, warning type management UI.
- Create `my_warnings.php`: employee read-only monthly warning page.
- Create `assets/js/employee_warnings.js`: page initialization, API calls, table/card rendering, modal submission.
- Create `tests/employee_warnings_contract_test.php`: contract test for files, access guards, action names, self-service session binding, delete protection.
- Modify `includes/header.php`: add sidebar navigation for the warning module.

---

### Task 1: Contract Test

**Files:**
- Create: `tests/employee_warnings_contract_test.php`

- [ ] **Step 1: Write the failing contract test**

```php
<?php
$root = dirname(__DIR__);

function assert_contains_text(string $haystack, string $needle, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: {$message}\nMissing: {$needle}\n");
        exit(1);
    }
}

function assert_not_contains_text(string $haystack, string $needle, string $message): void {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, "FAIL: {$message}\nUnexpected: {$needle}\n");
        exit(1);
    }
}

function read_required(string $path): string {
    if (!is_file($path)) {
        fwrite(STDERR, "FAIL: Missing file {$path}\n");
        exit(1);
    }
    return file_get_contents($path);
}

$api = read_required($root . '/api/employee_warning_api.php');
$helper = read_required($root . '/includes/employee_warning_helpers.php');
$hrPage = read_required($root . '/employee_warnings.php');
$myPage = read_required($root . '/my_warnings.php');
$js = read_required($root . '/assets/js/employee_warnings.js');
$header = read_required($root . '/includes/header.php');

assert_contains_text($hrPage, "in_array(\$_SESSION['role'], ['admin', 'hr']", 'HR page must restrict access to admin/hr');
assert_contains_text($hrPage, 'employeeWarningMonth', 'HR page must expose a month selector');
assert_contains_text($hrPage, 'employeeWarningForm', 'HR page must expose create warning form');
assert_contains_text($hrPage, 'warningTypeForm', 'HR page must expose warning type management form');

assert_contains_text($myPage, "\$_SESSION['employee_id']", 'Employee page must use the session employee id');
assert_not_contains_text($myPage, "\$_GET['employee_id']", 'Employee page must not read employee id from URL');
assert_not_contains_text($myPage, "\$_GET['id']", 'Employee page must not read id from URL');
assert_contains_text($myPage, 'myWarningMonth', 'Employee page must expose a month selector');

foreach ([
    'monthly_summary',
    'employee_month_details',
    'create_warning',
    'get_warning_types',
    'create_warning_type',
    'update_warning_type',
    'delete_warning_type',
    'my_monthly_warnings',
] as $action) {
    assert_contains_text($api, $action, "API must expose {$action}");
}

assert_contains_text($api, "in_array(\$role, ['admin', 'hr']", 'API must gate HR/admin actions');
assert_contains_text($api, "\$_SESSION['employee_id']", 'API self-service action must use session employee id');
assert_contains_text($helper, 'CREATE TABLE IF NOT EXISTS warning_types', 'Helper must create warning_types table');
assert_contains_text($helper, 'CREATE TABLE IF NOT EXISTS employee_warnings', 'Helper must create employee_warnings table');
assert_contains_text($helper, 'employeeWarningDeleteType', 'Helper must include protected delete function');
assert_contains_text($helper, 'SELECT id FROM employee_warnings WHERE warning_type_id = ?', 'Delete must check existing warning history');

assert_contains_text($js, 'initEmployeeWarningsAdminPage', 'JS must initialize HR/admin page');
assert_contains_text($js, 'initMyWarningsPage', 'JS must initialize employee page');
assert_contains_text($header, 'employee_warnings.php', 'Sidebar must link HR/admin warning page');
assert_contains_text($header, 'my_warnings.php', 'Sidebar must link employee warning page');

echo "employee warnings contract ok\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `C:\xampp\php\php.exe tests\employee_warnings_contract_test.php`

Expected: FAIL because `api/employee_warning_api.php` does not exist yet.

- [ ] **Step 3: Commit the failing test**

Run:

```bash
git add tests/employee_warnings_contract_test.php
git commit -m "test: add employee warnings contract"
```

---

### Task 2: Helper And API

**Files:**
- Create: `includes/employee_warning_helpers.php`
- Create: `api/employee_warning_api.php`
- Test: `tests/employee_warnings_contract_test.php`

- [ ] **Step 1: Implement helper functions**

Create schema guards and query helpers:

- `employeeWarningEnsureTables(mysqli $mysqli): void`
- `employeeWarningNormalizeMonth(?string $month): string`
- `employeeWarningMonthRange(string $month): array`
- `employeeWarningFetchTypes(mysqli $mysqli): array`
- `employeeWarningSaveType(mysqli $mysqli, array $input, int $userId): array`
- `employeeWarningDeleteType(mysqli $mysqli, int $id): void`
- `employeeWarningCreateRecord(mysqli $mysqli, array $input, int $userId): void`
- `employeeWarningFetchMonthlySummary(mysqli $mysqli, string $month): array`
- `employeeWarningFetchEmployeeMonthDetails(mysqli $mysqli, int $employeeId, string $month): array`
- `employeeWarningFetchMyMonth(mysqli $mysqli, int $employeeId, string $month): array`

- [ ] **Step 2: Implement API routing**

Create `api/employee_warning_api.php` with:

- JSON helpers `sendEmployeeWarningJson()` and `sendEmployeeWarningError()`;
- login guard;
- `employeeWarningEnsureTables($mysqli)` on entry;
- GET actions: `monthly_summary`, `employee_month_details`, `get_warning_types`, `my_monthly_warnings`;
- POST actions: `create_warning`, `create_warning_type`, `update_warning_type`;
- DELETE action: `delete_warning_type`;
- HR/admin gate for all actions except `my_monthly_warnings`;
- self-service lookup through `(int)($_SESSION['employee_id'] ?? 0)`.

- [ ] **Step 3: Run contract test**

Run: `C:\xampp\php\php.exe tests\employee_warnings_contract_test.php`

Expected: still FAIL because pages, JS, and sidebar links are not created yet.

- [ ] **Step 4: Lint helper and API**

Run:

- `C:\xampp\php\php.exe -l includes\employee_warning_helpers.php`
- `C:\xampp\php\php.exe -l api\employee_warning_api.php`

Expected: no syntax errors.

---

### Task 3: HR/Admin Page

**Files:**
- Create: `employee_warnings.php`
- Create or extend: `assets/js/employee_warnings.js`
- Test: `tests/employee_warnings_contract_test.php`

- [ ] **Step 1: Create HR/admin page**

Add:

- `require_once 'includes/auth_check.php';`
- role redirect for users not in `admin` or `hr`;
- page title and header/footer includes;
- month input `id="employeeWarningMonth"`;
- summary card elements using `data-warning-summary`;
- table body `id="employeeWarningSummaryBody"`;
- detail modal `id="employeeWarningDetailModal"`;
- create form `id="employeeWarningForm"` with employee select, warning type select, incident date, and optional detail;
- warning type form `id="warningTypeForm"` and table body `id="warningTypeTableBody"`;
- script include `assets/js/employee_warnings.js`.

- [ ] **Step 2: Add admin JS initialization**

In `assets/js/employee_warnings.js`, add:

- `initEmployeeWarningsAdminPage()`;
- `loadEmployeeWarningSummary()`;
- `loadWarningTypes()`;
- `submitEmployeeWarning()`;
- `submitWarningType()`;
- `deleteWarningType(id)`;
- `openEmployeeWarningDetails(employeeId, employeeName)`;
- shared HTML escaping and month/date formatting helpers.

- [ ] **Step 3: Run test and JS check**

Run:

- `C:\xampp\php\php.exe tests\employee_warnings_contract_test.php`
- `node --check assets/js/employee_warnings.js`
- `C:\xampp\php\php.exe -l employee_warnings.php`

Expected: contract still FAIL until employee page and sidebar are added; JS/PHP syntax checks pass.

---

### Task 4: Employee Read-Only Page

**Files:**
- Create: `my_warnings.php`
- Modify: `assets/js/employee_warnings.js`
- Test: `tests/employee_warnings_contract_test.php`

- [ ] **Step 1: Create employee page**

Add:

- `require_once 'includes/auth_check.php';`
- session employee id guard;
- page title and header/footer includes;
- month input `id="myWarningMonth"`;
- summary cards for total warnings and distinct warning types;
- table body `id="myWarningDetailsBody"`;
- script include `assets/js/employee_warnings.js`.

- [ ] **Step 2: Add employee JS initialization**

In `assets/js/employee_warnings.js`, add:

- `initMyWarningsPage()`;
- `loadMyWarnings()`;
- renderer for `my_monthly_warnings` summary and details;
- empty state when the selected month has no rows.

- [ ] **Step 3: Run checks**

Run:

- `C:\xampp\php\php.exe tests\employee_warnings_contract_test.php`
- `node --check assets/js/employee_warnings.js`
- `C:\xampp\php\php.exe -l my_warnings.php`

Expected: contract still FAIL until sidebar links are added; JS/PHP syntax checks pass.

---

### Task 5: Sidebar Navigation

**Files:**
- Modify: `includes/header.php`
- Test: `tests/employee_warnings_contract_test.php`

- [ ] **Step 1: Add sidebar entry**

Add a new sidebar link outside leave/attendance:

- for `admin` and `hr`: `employee_warnings.php`;
- for all other logged-in roles: `my_warnings.php`;
- use an icon such as `fa-triangle-exclamation`;
- active state covers both `employee_warnings.php` and `my_warnings.php`.

- [ ] **Step 2: Run contract test**

Run: `C:\xampp\php\php.exe tests\employee_warnings_contract_test.php`

Expected: PASS with `employee warnings contract ok`.

- [ ] **Step 3: Lint header**

Run: `C:\xampp\php\php.exe -l includes\header.php`

Expected: no syntax errors.

---

### Task 6: Final Verification

**Files:**
- Verify all changed files.

- [ ] **Step 1: Run syntax and contract checks**

Run:

- `C:\xampp\php\php.exe -l employee_warnings.php`
- `C:\xampp\php\php.exe -l my_warnings.php`
- `C:\xampp\php\php.exe -l api\employee_warning_api.php`
- `C:\xampp\php\php.exe -l includes\employee_warning_helpers.php`
- `C:\xampp\php\php.exe -l includes\header.php`
- `node --check assets/js/employee_warnings.js`
- `C:\xampp\php\php.exe tests\employee_warnings_contract_test.php`
- `git diff --check`

Expected: all pass.

- [ ] **Step 2: Review changed files**

Run:

- `git status --short`
- `git diff --stat`
- `git diff -- employee_warnings.php my_warnings.php api/employee_warning_api.php includes/employee_warning_helpers.php includes/header.php assets/js/employee_warnings.js tests/employee_warnings_contract_test.php`

Expected: changes are limited to the warning module plus sidebar.

- [ ] **Step 3: Commit implementation**

Run:

```bash
git add employee_warnings.php my_warnings.php api/employee_warning_api.php includes/employee_warning_helpers.php includes/header.php assets/js/employee_warnings.js tests/employee_warnings_contract_test.php docs/superpowers/plans/2026-06-25-employee-warnings.md
git commit -m "feat: add employee warning records"
```

Expected: commit succeeds with only task-owned files.

---

## Self-Review

Spec coverage:

- Warning type master list: Task 2 and Task 3.
- HR/admin monthly all-employee overview: Task 2 and Task 3.
- HR/admin record modal: Task 3.
- Employee read-only monthly page: Task 4.
- No approval/status/attachments/active state: enforced by data model and out-of-scope implementation.
- Sidebar navigation: Task 5.
- Contract and syntax verification: Task 1 and Task 6.

Placeholder scan: no TODO/TBD placeholders are intentionally left in this plan.

Type consistency: action names, file names, form ids, and function names match across tasks.
