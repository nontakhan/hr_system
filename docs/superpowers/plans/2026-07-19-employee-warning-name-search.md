# Employee Warning All-Month Name Search Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add scoped partial-name search across all warning months while preserving the existing monthly view and edit/delete workflow.

**Architecture:** Add two read-only scoped helper queries and API actions: aggregated employee search and complete employee warning history. Add an explicit UI mode (`month` or `history`) so detail refresh after mutations uses the correct endpoint, while search controls switch the summary table without changing monthly cards.

**Tech Stack:** PHP 8 / mysqli, MySQL/MariaDB, vanilla JavaScript, Bootstrap 5, SweetAlert2, PHP and Node contract tests.

## Global Constraints

- Require `admin` or `hr` for search and all-history detail.
- Apply current HR scope to both queries.
- Search partial Thai names across all warning dates with a prepared `LIKE` value.
- Keep summary cards month-specific in search mode.
- Preserve current edit/delete behavior and sourced-warning provenance.
- Do not commit unless explicitly requested.

---

### Task 1: Scoped All-Month Search API

**Files:**
- Modify: `tests/employee_warnings_contract_test.php`
- Modify: `includes/employee_warning_helpers.php`
- Modify: `api/employee_warning_api.php`

**Interfaces:**
- Produces: `employeeWarningNormalizeSearch(?string $term): string`
- Produces: `employeeWarningSearchByName(mysqli $mysqli, string $term, string $role, array $scopes): array`
- Produces: `employeeWarningFetchEmployeeHistory(mysqli $mysqli, int $employeeId, string $role, array $scopes): array`

- [ ] Add failing contract assertions for `search_employee_warnings`, `employee_warning_history`, the three helper functions, `LIKE ?`, and scope use.
- [ ] Run `C:\xampp\php\php.exe tests\employee_warnings_contract_test.php`; expect failure on the first missing search action/helper.
- [ ] Implement term trim, non-empty validation, 100-character cap, and `%term%` prepared matching over `CONCAT_WS(' ', e.first_name_th, e.last_name_th)`.
- [ ] Implement all-date grouped results with the same columns as monthly summary and `employeeWarningEmployeeScopeClause($role, $scopes, 'e')`.
- [ ] Refactor detail selection into a shared range-optional query or implement the history helper with the identical selected fields and descending order; apply employee ID and scope parameters.
- [ ] Route both GET actions through the existing HR gate and return the standard success envelope.
- [ ] Run the contract plus PHP lint for helper/API; expect all exit 0.

### Task 2: Search and History UI State

**Files:**
- Modify: `tests/employee_warnings_edit_delete_ui_test.js`
- Modify: `employee_warnings.php`
- Modify: `assets/js/employee_warnings.js`

**Interfaces:**
- Produces: search controls `employeeWarningSearchForm`, `employeeWarningSearchName`, and `clearEmployeeWarningSearchBtn`.
- Produces: `searchEmployeeWarnings(event)`, `clearEmployeeWarningSearch()`, and detail context `{ employeeId, employeeName, mode }`.

- [ ] Add failing UI assertions for the three control IDs, both API action strings, a history-mode context, and clear/search handlers.
- [ ] Run `node tests\employee_warnings_edit_delete_ui_test.js`; expect failure at the first missing search control.
- [ ] Add a compact Bootstrap input group above the monthly table plus an initially hidden Clear Search button and a label hook that identifies current mode.
- [ ] Implement non-empty client validation, fetch all-month search rows, reuse the escaped summary row renderer, and set row detail buttons to history mode.
- [ ] Extend `openEmployeeWarningDetails(employeeId, employeeName, mode = 'month')` to choose `employee_warning_history` for history mode and the existing endpoint for month mode.
- [ ] Make edit/delete refresh call the current context mode; make Clear Search restore labels, hide itself, empty the input, and call monthly summary loading.
- [ ] Run the UI contract, JS syntax check, PHP page lint, and PHP warning contract; expect all pass.

### Task 3: Fresh Regression Evidence

**Files:**
- Review task-owned warning files and docs only.

**Interfaces:**
- Produces verified, unstaged workspace changes.

- [ ] Run `employee_warnings_contract_test.php`, both bulk warning PHP tests, the edit/delete UI test, and both bulk warning Node tests.
- [ ] Run PHP lint on page/API/helper and `node --check assets\js\employee_warnings.js`.
- [ ] Run `git diff --check`, inspect task-file diff, and run `git status --short`; expect no whitespace errors and no unrelated edits.
