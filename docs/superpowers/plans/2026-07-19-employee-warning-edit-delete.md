# Employee Warning Edit and Delete Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let admin and HR users edit or delete any employee warning from the monthly detail modal without weakening HR scope enforcement or losing bulk-warning provenance.

**Architecture:** Extend the existing warning helper with scoped mutation functions, route two new API actions to them, and reuse the existing create modal as an edit form. The detail response supplies stable IDs and editable values; UI actions refresh both the monthly summary and the open detail modal.

**Tech Stack:** PHP 8 / mysqli, MySQL/MariaDB, vanilla JavaScript, Bootstrap 5, SweetAlert2, focused PHP/Node contract tests.

## Global Constraints

- Require the existing `admin` or `hr` role gate for all mutations.
- For HR, verify both the existing warning owner and the newly selected employee are inside current HR scope.
- Preserve `source_type`, `source_key`, and `source_event_date` during update.
- Deleting a sourced warning permits its source event to be warned again later.
- Persist dates as Gregorian `YYYY-MM-DD` and retain the existing Thai Bootstrap/Sarabun UI.
- Do not commit unless the user explicitly requests it; keep unrelated worktree changes untouched.

## File Structure

- Modify `tests/employee_warnings_contract_test.php`: static regression contract for UI, API routes, scoped helper calls, and provenance-safe update SQL.
- Create `tests/employee_warnings_edit_delete_ui_test.js`: focused JavaScript contract for edit/delete controls and modal state.
- Modify `includes/employee_warning_helpers.php`: shared input validation plus scoped update/delete functions.
- Modify `api/employee_warning_api.php`: expose `update_warning` and `delete_warning` actions.
- Modify `employee_warnings.php`: edit-mode hidden ID, stable modal labels, actions column.
- Modify `assets/js/employee_warnings.js`: detail state, edit-mode population, delete confirmation, and refresh behavior.

---

### Task 1: Lock the Server-Side Mutation Contract

**Files:**
- Modify: `tests/employee_warnings_contract_test.php`
- Modify: `includes/employee_warning_helpers.php`
- Modify: `api/employee_warning_api.php`

**Interfaces:**
- Produces: `employeeWarningUpdateRecord(mysqli $mysqli, array $input, int $userId, string $role, array $scopes): void`
- Produces: `employeeWarningDeleteRecord(mysqli $mysqli, int $id, string $role, array $scopes): void`
- Consumes: `employeeWarningEmployeeScopeClause()` and `employeeWarningBindParams()`.

- [ ] **Step 1: Add failing server contract assertions**

Add `update_warning` and `delete_warning` to the API action list. Assert that the helper contains both mutation function names, `updated_by = ?`, a scoped lookup joined to `employees e`, and an update statement that changes only `employee_id`, `warning_type_id`, `warning_date`, `detail`, and `updated_by`—never any `source_*` column.

- [ ] **Step 2: Run the contract and verify RED**

Run: `C:\xampp\php\php.exe tests\employee_warnings_contract_test.php`

Expected: FAIL because `update_warning` and the record mutation helpers do not exist.

- [ ] **Step 3: Extract reusable create/update validation**

In `includes/employee_warning_helpers.php`, introduce a focused validator returning:

```php
function employeeWarningValidateRecordInput(mysqli $mysqli, array $input): array
{
    // Return [$employeeId, $warningTypeId, $warningDate, $detail]
    // using the existing create validation and exact Thai errors.
}
```

Refactor `employeeWarningCreateRecord()` to consume this tuple without changing its INSERT behavior.

- [ ] **Step 4: Add scoped target lookup and mutations**

Implement a private/shared scoped lookup using `employeeWarningEmployeeScopeClause($role, $scopes, 'e')`, binding the warning ID before scope parameters. `employeeWarningUpdateRecord()` must first authorize the old row, validate the new employee through the same scoped employee query, then execute:

```sql
UPDATE employee_warnings
SET employee_id = ?, warning_type_id = ?, warning_date = ?, detail = ?, updated_by = ?
WHERE id = ?
```

`employeeWarningDeleteRecord()` authorizes the existing row and executes `DELETE FROM employee_warnings WHERE id = ?`. Both reject missing/out-of-scope rows with the same Thai invalid/not-found message.

- [ ] **Step 5: Route API actions**

Under POST, call `employeeWarningUpdateRecord($mysqli, $input, $userId, $role, $scopes)` for `update_warning`. Under DELETE, call `employeeWarningDeleteRecord($mysqli, (int)($input['id'] ?? 0), $role, $scopes)` for `delete_warning`. Return the existing success JSON envelope with Thai messages.

- [ ] **Step 6: Verify GREEN and syntax**

Run:

```powershell
C:\xampp\php\php.exe tests\employee_warnings_contract_test.php
C:\xampp\php\php.exe -l includes\employee_warning_helpers.php
C:\xampp\php\php.exe -l api\employee_warning_api.php
```

Expected: contract prints `employee warnings contract ok`; both syntax checks report no errors.

### Task 2: Lock and Implement Detail Modal Actions

**Files:**
- Create: `tests/employee_warnings_edit_delete_ui_test.js`
- Modify: `employee_warnings.php`
- Modify: `assets/js/employee_warnings.js`
- Modify: `includes/employee_warning_helpers.php`

**Interfaces:**
- Consumes detail rows containing `id`, `employee_id`, `warning_type_id`, `warning_date`, `detail`, `type_name`, and `created_by_name`.
- Produces: `resetEmployeeWarningForm()`, `editEmployeeWarning(row)`, `deleteEmployeeWarning(id)`, and refreshed `openEmployeeWarningDetails(employeeId, employeeName)` behavior.

- [ ] **Step 1: Write the failing UI contract test**

Create a Node test that reads the page, JavaScript, and helper sources and asserts:

```js
includes(page, 'id="employeeWarningId"');
includes(page, 'id="employeeWarningModalTitle"');
includes(page, 'id="employeeWarningSubmitLabel"');
includes(page, 'จัดการ');
includes(script, 'btn-employee-warning-edit');
includes(script, 'btn-employee-warning-delete');
includes(script, "data.action = data.id ? 'update_warning' : 'create_warning'");
includes(script, "method: 'DELETE'");
includes(script, "action: 'delete_warning'");
includes(helper, 'ew.warning_type_id');
includes(helper, 'ew.employee_id');
```

Use small `includes()`/failure helpers matching existing Node contract tests.

- [ ] **Step 2: Run the UI test and verify RED**

Run: `node tests\employee_warnings_edit_delete_ui_test.js`

Expected: FAIL at the missing hidden warning ID or missing action controls.

- [ ] **Step 3: Expose edit values and modal hooks**

Add `ew.employee_id` and `ew.warning_type_id` to `employeeWarningFetchEmployeeMonthDetails()`. In `employee_warnings.php`, add hidden `id`, IDs for modal title/submit label, an actions header, and update empty/loading `colspan` values from 4 to 5.

- [ ] **Step 4: Add explicit modal mode management**

Add `resetEmployeeWarningForm()` to clear the hidden ID, restore the title “เพิ่มใบเตือนพนักงาน”, restore the submit label “บันทึกใบเตือน”, reset the date to `todayDate()`, and clear editable fields. Bind it to the Add button and the modal hidden event so stale edit state cannot leak into creation.

- [ ] **Step 5: Render safe row actions**

Store detail rows in page state keyed by numeric warning ID. Render Edit/Delete buttons with numeric `data-id` attributes, and use delegated click handling on `employeeWarningDetailBody`. Do not serialize raw details into HTML attributes.

- [ ] **Step 6: Implement edit submission**

`editEmployeeWarning(row)` fills all four editable values, changes title/submit text, hides the detail modal, and shows the form modal. `submitEmployeeWarning()` chooses `update_warning` when hidden ID exists and otherwise `create_warning`. On success, reset/hide the form, refresh monthly summary, and reopen/refresh the prior detail context when present.

- [ ] **Step 7: Implement confirmed deletion**

`deleteEmployeeWarning(id)` uses SweetAlert2 with destructive confirmation text, sends:

```js
{
    method: 'DELETE',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'delete_warning', id })
}
```

On success, show the Thai success alert, refresh the monthly summary, and reload the open employee detail list. On failure, keep the modal usable and show the returned message.

- [ ] **Step 8: Verify GREEN and syntax**

Run:

```powershell
node tests\employee_warnings_edit_delete_ui_test.js
node --check assets\js\employee_warnings.js
C:\xampp\php\php.exe tests\employee_warnings_contract_test.php
C:\xampp\php\php.exe -l employee_warnings.php
C:\xampp\php\php.exe -l includes\employee_warning_helpers.php
```

Expected: both focused tests pass and all three syntax checks exit 0.

### Task 3: Final Regression and Worktree Review

**Files:**
- Review all task-owned files only.

**Interfaces:**
- Consumes the complete edit/delete workflow.
- Produces fresh completion evidence without staging or committing files.

- [ ] **Step 1: Run warning-related regression tests**

Run:

```powershell
C:\xampp\php\php.exe tests\employee_warnings_contract_test.php
C:\xampp\php\php.exe tests\employee_warning_bulk_api_contract_test.php
C:\xampp\php\php.exe tests\employee_warning_bulk_helpers_test.php
node tests\employee_warnings_edit_delete_ui_test.js
node tests\bulk_employee_warnings_ui_test.js
node tests\bulk_employee_warnings_pagination_test.js
```

Expected: every command exits 0 with its pass message.

- [ ] **Step 2: Run complete changed-file syntax checks**

Run PHP lint for `employee_warnings.php`, `api/employee_warning_api.php`, and `includes/employee_warning_helpers.php`, then run `node --check assets\js\employee_warnings.js`.

Expected: all exit 0 with no syntax errors.

- [ ] **Step 3: Inspect patch hygiene**

Run:

```powershell
git diff --check
git diff -- employee_warnings.php assets\js\employee_warnings.js api\employee_warning_api.php includes\employee_warning_helpers.php tests\employee_warnings_contract_test.php tests\employee_warnings_edit_delete_ui_test.js docs\superpowers\specs\2026-07-19-employee-warning-edit-delete-design.md docs\superpowers\plans\2026-07-19-employee-warning-edit-delete.md
git status --short
```

Expected: no whitespace errors; diff contains only approved warning workflow changes; unrelated pre-existing files remain untouched and unstaged.
