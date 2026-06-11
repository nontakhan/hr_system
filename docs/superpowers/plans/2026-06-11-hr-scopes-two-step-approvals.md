# HR Scopes and Two-Step Approvals Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add selectable HR company/branch scopes and change leave/day-swap approvals to manager-first, HR-final approval.

**Architecture:** Keep `users.role` as the main role and add a shared `includes/hr_scope_helpers.php` module for HR scope storage, session refresh, and employee-scope filtering. Convert request status flow from single `pending` to `pending_manager -> pending_hr -> approved`, while preserving `approved/rejected/cancelled` compatibility for existing reports.

**Tech Stack:** PHP 8 on XAMPP, MySQLi, Bootstrap forms, vanilla JavaScript, existing focused PHP/Node tests.

---

## File Map

- Create: `includes/hr_scope_helpers.php`
  - Owns `user_hr_scopes` table ensure logic, scope fetch/save helpers, scope-aware employee predicates, and reusable SQL fragments.
- Create: `tests/hr_scope_helpers_test.php`
  - Unit-style test for pure scope helper behavior and SQL fragment decisions.
- Modify: `api/login_process.php`
  - Load HR scope arrays into the session at login.
- Modify: `includes/auth_check.php`
  - Refresh HR scope arrays for authenticated requests.
- Modify: `employee_add.php`
  - Add company and branch multi-select fields in the User Account section.
- Modify: `employee_edit.php`
  - Load existing HR scopes and add editable company/branch multi-select fields.
- Modify: `assets/js/employee.js`
  - Toggle the HR scope controls by role and submit multi-select values normally with `FormData`.
- Modify: `api/employee_api.php`
  - Ensure/save HR scopes during create/update and use scope-aware filters for employee listing.
- Modify: `api/leave_request_api.php`
  - Insert new leave requests as `pending_manager`.
- Modify: `api/leave_history_api.php`
  - Allow cancellation while status is `pending` or `pending_manager`; keep `pending_hr` non-cancellable unless explicitly changed later.
- Modify: `api/leave_approval_api.php`
  - Add schema ensure for manager/HR approver columns and implement stage-aware approval lists/actions.
- Modify: `assets/js/leave_approval.js`
  - Display current approval stage and status labels.
- Modify: `assets/js/my_leaves.js`
  - Render `pending_manager` and `pending_hr` labels.
- Modify: `includes/day_swap_helpers.php`
  - Ensure new day-swap statuses/approver columns exist.
- Modify: `api/day_swap_api.php`
  - Insert requests as `pending_manager`, scope HR approval with HR helpers, and implement stage-aware actions.
- Modify: `assets/js/day_swap.js`
  - Display current approval stage and status labels.
- Modify: `includes/header.php`, `leave_approvals.php`, `day_swap_approvals.php`, `employees.php`, `attendance.php`, `attendance_import.php`, `leave_types.php`, `shifts.php`, `company_holidays.php`
  - Replace direct role checks where HR menu/page access depends on role only, and keep admin full access.

---

### Task 1: Shared HR Scope Helpers

**Files:**
- Create: `includes/hr_scope_helpers.php`
- Create: `tests/hr_scope_helpers_test.php`

- [ ] **Step 1: Write the failing helper test**

Create `tests/hr_scope_helpers_test.php`:

```php
<?php
require_once __DIR__ . '/../includes/hr_scope_helpers.php';

function assertHrScopeSame($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$scopes = hrScopeNormalizeRows([
    ['scope_type' => 'company', 'scope_id' => '2'],
    ['scope_type' => 'branch', 'scope_id' => '7'],
    ['scope_type' => 'company', 'scope_id' => '2'],
    ['scope_type' => 'branch', 'scope_id' => '0'],
    ['scope_type' => 'bad', 'scope_id' => '9'],
]);

assertHrScopeSame([2], $scopes['company_ids'], 'Company scopes should be unique positive ints.');
assertHrScopeSame([7], $scopes['branch_ids'], 'Branch scopes should be unique positive ints.');
assertHrScopeSame(true, hrScopeHasAnyScope($scopes), 'Combined scopes should be detected.');

$empty = hrScopeNormalizeRows([]);
assertHrScopeSame(false, hrScopeHasAnyScope($empty), 'Empty scopes should not grant access.');

$adminClause = hrScopeBuildEmployeeWhereClause('admin', [], 'e');
assertHrScopeSame('', $adminClause['sql'], 'Admin should not need an employee scope SQL clause.');
assertHrScopeSame([], $adminClause['params'], 'Admin scope SQL should not bind params.');

$hrClause = hrScopeBuildEmployeeWhereClause('hr', ['company_ids' => [2], 'branch_ids' => [7, 8]], 'e');
assertHrScopeSame(' AND (e.company_id IN (?) OR e.branch_id IN (?,?)) ', $hrClause['sql'], 'HR scope clause should combine company and branch scopes.');
assertHrScopeSame('iii', $hrClause['types'], 'HR scope clause should bind integer params.');
assertHrScopeSame([2, 7, 8], $hrClause['params'], 'HR scope params should be company IDs then branch IDs.');

$noScopeClause = hrScopeBuildEmployeeWhereClause('hr', ['company_ids' => [], 'branch_ids' => []], 'e');
assertHrScopeSame(' AND 1=0 ', $noScopeClause['sql'], 'HR with no scopes should see no employee rows.');

echo "hr_scope_helpers_test passed\n";
```

- [ ] **Step 2: Run the failing test**

Run:

```powershell
C:\xampp\php\php.exe tests\hr_scope_helpers_test.php
```

Expected: fail because `includes/hr_scope_helpers.php` does not exist.

- [ ] **Step 3: Implement the helper module**

Create `includes/hr_scope_helpers.php`:

```php
<?php

function hrScopeEnsureTable(mysqli $mysqli) {
    $mysqli->query("CREATE TABLE IF NOT EXISTS user_hr_scopes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        scope_type ENUM('company','branch') NOT NULL,
        scope_id INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_hr_scope (user_id, scope_type, scope_id),
        INDEX idx_user_hr_scope_user (user_id),
        INDEX idx_user_hr_scope_lookup (scope_type, scope_id),
        CONSTRAINT fk_user_hr_scopes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function hrScopeNormalizeRows(array $rows) {
    $scopes = ['company_ids' => [], 'branch_ids' => []];
    foreach ($rows as $row) {
        $type = (string)($row['scope_type'] ?? '');
        $id = (int)($row['scope_id'] ?? 0);
        if ($id <= 0) continue;
        if ($type === 'company' && !in_array($id, $scopes['company_ids'], true)) {
            $scopes['company_ids'][] = $id;
        }
        if ($type === 'branch' && !in_array($id, $scopes['branch_ids'], true)) {
            $scopes['branch_ids'][] = $id;
        }
    }
    sort($scopes['company_ids']);
    sort($scopes['branch_ids']);
    return $scopes;
}

function hrScopeHasAnyScope(array $scopes) {
    return !empty($scopes['company_ids']) || !empty($scopes['branch_ids']);
}

function hrScopeFetchForUser(mysqli $mysqli, $userId) {
    hrScopeEnsureTable($mysqli);
    $stmt = $mysqli->prepare("SELECT scope_type, scope_id FROM user_hr_scopes WHERE user_id = ? ORDER BY scope_type, scope_id");
    $userId = (int)$userId;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return hrScopeNormalizeRows($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

function hrScopeRefreshSession(mysqli $mysqli) {
    if (empty($_SESSION['user_id'])) return;
    $scopes = hrScopeFetchForUser($mysqli, (int)$_SESSION['user_id']);
    $_SESSION['hr_company_ids'] = $scopes['company_ids'];
    $_SESSION['hr_branch_ids'] = $scopes['branch_ids'];
}

function hrScopeCurrentSessionScopes() {
    return [
        'company_ids' => array_values(array_filter(array_map('intval', $_SESSION['hr_company_ids'] ?? []))),
        'branch_ids' => array_values(array_filter(array_map('intval', $_SESSION['hr_branch_ids'] ?? []))),
    ];
}

function hrScopeBuildEmployeeWhereClause($role, array $scopes, $employeeAlias = 'e') {
    if ($role === 'admin') {
        return ['sql' => '', 'types' => '', 'params' => []];
    }
    if ($role !== 'hr') {
        return ['sql' => ' AND 1=0 ', 'types' => '', 'params' => []];
    }

    $companyIds = array_values(array_unique(array_filter(array_map('intval', $scopes['company_ids'] ?? []))));
    $branchIds = array_values(array_unique(array_filter(array_map('intval', $scopes['branch_ids'] ?? []))));
    if (!$companyIds && !$branchIds) {
        return ['sql' => ' AND 1=0 ', 'types' => '', 'params' => []];
    }

    $parts = [];
    $params = [];
    if ($companyIds) {
        $parts[] = "{$employeeAlias}.company_id IN (" . implode(',', array_fill(0, count($companyIds), '?')) . ")";
        $params = array_merge($params, $companyIds);
    }
    if ($branchIds) {
        $parts[] = "{$employeeAlias}.branch_id IN (" . implode(',', array_fill(0, count($branchIds), '?')) . ")";
        $params = array_merge($params, $branchIds);
    }

    return [
        'sql' => ' AND (' . implode(' OR ', $parts) . ') ',
        'types' => str_repeat('i', count($params)),
        'params' => $params,
    ];
}

function hrScopeBindParams(mysqli_stmt $stmt, $types, array $params) {
    if ($types === '' || !$params) return;
    $refs = [$types];
    foreach ($params as $index => $value) {
        $refs[] = &$params[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}
```

- [ ] **Step 4: Run the helper test**

Run:

```powershell
C:\xampp\php\php.exe tests\hr_scope_helpers_test.php
```

Expected: `hr_scope_helpers_test passed`.

- [ ] **Step 5: Commit**

```powershell
git add includes\hr_scope_helpers.php tests\hr_scope_helpers_test.php
git commit -m "Add HR scope helper foundation"
```

---

### Task 2: Login and Auth Scope Refresh

**Files:**
- Modify: `api/login_process.php`
- Modify: `includes/auth_check.php`

- [ ] **Step 1: Include and refresh HR scopes at login**

In `api/login_process.php`, after `require_once '../includes/db_connect.php';`, add:

```php
require_once '../includes/hr_scope_helpers.php';
```

After setting `$_SESSION['company_id'] = $user['company_id'];`, add:

```php
hrScopeRefreshSession($mysqli);
```

- [ ] **Step 2: Refresh scopes during auth checks**

In `includes/auth_check.php`, after `require_once __DIR__ . '/db_connect.php';`, add:

```php
require_once __DIR__ . '/hr_scope_helpers.php';
hrScopeRefreshSession($mysqli);
```

- [ ] **Step 3: Syntax check**

Run:

```powershell
C:\xampp\php\php.exe -l api\login_process.php
C:\xampp\php\php.exe -l includes\auth_check.php
```

Expected: both say `No syntax errors detected`.

- [ ] **Step 4: Commit**

```powershell
git add api\login_process.php includes\auth_check.php
git commit -m "Load HR scopes into sessions"
```

---

### Task 3: Account Form Scope UI and Persistence

**Files:**
- Modify: `employee_add.php`
- Modify: `employee_edit.php`
- Modify: `assets/js/employee.js`
- Modify: `api/employee_api.php`

- [ ] **Step 1: Add scope data queries to employee add/edit pages**

In both `employee_add.php` and `employee_edit.php`, require the helper after DB connect:

```php
require_once 'includes/hr_scope_helpers.php';
```

In the existing master data query block, add:

```php
@$hrCompanies = $mysqli->query("SELECT id, company_name_th FROM companies ORDER BY company_name_th")->fetch_all(MYSQLI_ASSOC);
@$hrBranches = $mysqli->query("SELECT b.id, b.branch_name_th, b.company_id, c.company_name_th
                               FROM branches b
                               JOIN companies c ON b.company_id = c.id
                               ORDER BY c.company_name_th, b.branch_name_th")->fetch_all(MYSQLI_ASSOC);
```

In `employee_edit.php`, after `$emp` is loaded, add:

```php
$hrScopes = !empty($emp['user_id']) ? hrScopeFetchForUser($mysqli, (int)$emp['user_id']) : ['company_ids' => [], 'branch_ids' => []];
```

Also change the employee edit SELECT to include `u.id AS user_id`:

```php
$sql = "SELECT e.*, u.id AS user_id, u.username, u.role
        FROM employees e
        LEFT JOIN users u ON e.id = u.employee_id
        WHERE e.id = ?";
```

- [ ] **Step 2: Add the HR scope controls in both account forms**

Under the role select in the User Account card, add:

```php
<div class="col-12 hr-scope-section" style="display: none;">
    <div class="border rounded p-3 bg-light">
        <div class="fw-semibold mb-2">ขอบเขตสิทธิ์ HR</div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">HR บริษัท</label>
                <select name="hr_company_ids[]" class="form-select" multiple size="6">
                    <?php foreach ($hrCompanies as $company): ?>
                        <option value="<?php echo (int)$company['id']; ?>">
                            <?php echo htmlspecialchars($company['company_name_th']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">เลือกได้มากกว่า 1 บริษัท</small>
            </div>
            <div class="col-md-6">
                <label class="form-label">HR สาขา</label>
                <select name="hr_branch_ids[]" class="form-select" multiple size="6">
                    <?php foreach ($hrBranches as $branch): ?>
                        <option value="<?php echo (int)$branch['id']; ?>" data-company-id="<?php echo (int)$branch['company_id']; ?>">
                            <?php echo htmlspecialchars($branch['company_name_th'] . ' - ' . $branch['branch_name_th']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">เลือกได้มากกว่า 1 สาขา</small>
            </div>
        </div>
    </div>
</div>
```

For `employee_edit.php`, add selected state:

```php
<?php echo in_array((int)$company['id'], $hrScopes['company_ids'], true) ? 'selected' : ''; ?>
<?php echo in_array((int)$branch['id'], $hrScopes['branch_ids'], true) ? 'selected' : ''; ?>
```

- [ ] **Step 3: Toggle HR scope controls in JavaScript**

In `assets/js/employee.js`, inside `DOMContentLoaded`, add:

```javascript
document.querySelectorAll('select[name="role"]').forEach(roleSelect => {
    const toggleHrScopes = () => {
        const form = roleSelect.closest('form');
        const section = form ? form.querySelector('.hr-scope-section') : null;
        if (!section) return;
        const visible = roleSelect.value === 'hr' || roleSelect.value === 'admin';
        section.style.display = visible ? '' : 'none';
        section.querySelectorAll('select').forEach(select => {
            select.disabled = !visible;
        });
    };
    roleSelect.addEventListener('change', toggleHrScopes);
    toggleHrScopes();
});
```

- [ ] **Step 4: Persist scopes in employee API**

In `api/employee_api.php`, require the helper:

```php
require_once '../includes/hr_scope_helpers.php';
```

Add helper functions:

```php
function normalizePostedIds($value) {
    $values = is_array($value) ? $value : [$value];
    return array_values(array_unique(array_filter(array_map('intval', $values))));
}

function validateIdsExist(mysqli $mysqli, $table, array $ids) {
    if (!$ids) return true;
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM {$table} WHERE id IN ({$placeholders})");
    hrScopeBindParams($stmt, str_repeat('i', count($ids)), $ids);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)$row['total'] === count($ids);
}

function syncUserHrScopes(mysqli $mysqli, $userId, $role, array $data) {
    hrScopeEnsureTable($mysqli);
    $userId = (int)$userId;
    $delete = $mysqli->prepare("DELETE FROM user_hr_scopes WHERE user_id = ?");
    $delete->bind_param('i', $userId);
    $delete->execute();

    if (!in_array($role, ['hr', 'admin'], true)) {
        return;
    }

    $companyIds = normalizePostedIds($data['hr_company_ids'] ?? []);
    $branchIds = normalizePostedIds($data['hr_branch_ids'] ?? []);
    if (!validateIdsExist($mysqli, 'companies', $companyIds)) {
        throw new InvalidArgumentException('บริษัท HR ที่เลือกไม่ถูกต้อง');
    }
    if (!validateIdsExist($mysqli, 'branches', $branchIds)) {
        throw new InvalidArgumentException('สาขา HR ที่เลือกไม่ถูกต้อง');
    }

    $insert = $mysqli->prepare("INSERT INTO user_hr_scopes (user_id, scope_type, scope_id) VALUES (?, ?, ?)");
    foreach ($companyIds as $id) {
        $type = 'company';
        $insert->bind_param('isi', $userId, $type, $id);
        $insert->execute();
    }
    foreach ($branchIds as $id) {
        $type = 'branch';
        $insert->bind_param('isi', $userId, $type, $id);
        $insert->execute();
    }
}
```

After creating a user in `createEmployee()`, call:

```php
syncUserHrScopes($mysqli, $mysqli->insert_id, $role, $data);
```

For existing user update, after updating `users`, call:

```php
syncUserHrScopes($mysqli, (int)$user_exists['id'], $role, $data);
```

For newly inserted user in `updateEmployee()`, call:

```php
syncUserHrScopes($mysqli, $mysqli->insert_id, $role, $data);
```

- [ ] **Step 5: Syntax and JS checks**

Run:

```powershell
C:\xampp\php\php.exe -l employee_add.php
C:\xampp\php\php.exe -l employee_edit.php
C:\xampp\php\php.exe -l api\employee_api.php
node --check assets\js\employee.js
```

Expected: all pass.

- [ ] **Step 6: Commit**

```powershell
git add employee_add.php employee_edit.php assets\js\employee.js api\employee_api.php
git commit -m "Add HR scope account management"
```

---

### Task 4: Scope-Aware Employee Visibility

**Files:**
- Modify: `api/employee_api.php`
- Modify: `employees.php`

- [ ] **Step 1: Update employee listing filters**

In `getAllEmployees()`, replace current HR `company_id` logic with:

```php
$role = $_SESSION['role'];
$scopes = hrScopeCurrentSessionScopes();
$scopeClause = hrScopeBuildEmployeeWhereClause($role, $scopes, 'e');

if ($role === 'hr') {
    $sql .= $scopeClause['sql'];
    if ($filter_branch_id > 0) {
        $sql .= " AND e.branch_id = ? ";
        $scopeClause['types'] .= 'i';
        $scopeClause['params'][] = $filter_branch_id;
    }
    $sql .= " ORDER BY e.id DESC";
    $stmt = $mysqli->prepare($sql);
    hrScopeBindParams($stmt, $scopeClause['types'], $scopeClause['params']);
} elseif ($filter_branch_id > 0) {
    $sql .= " AND e.branch_id = ? ORDER BY e.id DESC";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $filter_branch_id);
} else {
    $sql .= " ORDER BY e.id DESC";
    $stmt = $mysqli->prepare($sql);
}
```

- [ ] **Step 2: Update branch filter options**

In `employees.php`, require helper:

```php
require_once 'includes/hr_scope_helpers.php';
```

For HR branch query, replace company-only filtering with a scope clause against branch/company:

```php
if ($_SESSION['role'] === 'hr') {
    $scopes = hrScopeCurrentSessionScopes();
    $conditions = [];
    if (!empty($scopes['company_ids'])) {
        $conditions[] = "b.company_id IN (" . implode(',', array_map('intval', $scopes['company_ids'])) . ")";
    }
    if (!empty($scopes['branch_ids'])) {
        $conditions[] = "b.id IN (" . implode(',', array_map('intval', $scopes['branch_ids'])) . ")";
    }
    $sql_branch .= $conditions ? " WHERE (" . implode(' OR ', $conditions) . ")" : " WHERE 1=0";
}
```

- [ ] **Step 3: Checks**

Run:

```powershell
C:\xampp\php\php.exe -l api\employee_api.php
C:\xampp\php\php.exe -l employees.php
C:\xampp\php\php.exe tests\hr_scope_helpers_test.php
```

Expected: syntax passes and helper test passes.

- [ ] **Step 4: Commit**

```powershell
git add api\employee_api.php employees.php
git commit -m "Apply HR scopes to employee visibility"
```

---

### Task 5: Leave Two-Step Approval Backend

**Files:**
- Modify: `api/leave_request_api.php`
- Modify: `api/leave_history_api.php`
- Modify: `api/leave_approval_api.php`

- [ ] **Step 1: Add leave approval schema ensure function**

In `api/leave_approval_api.php`, add:

```php
require_once '../includes/hr_scope_helpers.php';

function leaveApprovalEnsureTwoStepColumns(mysqli $mysqli) {
    leaveEnsureRequestPartColumns($mysqli);
    $columns = [];
    $result = $mysqli->query("SHOW COLUMNS FROM leave_requests");
    while ($row = $result->fetch_assoc()) {
        $columns[$row['Field']] = true;
    }
    if (empty($columns['manager_approver_id'])) {
        $mysqli->query("ALTER TABLE leave_requests ADD COLUMN manager_approver_id INT NULL AFTER approver_id");
    }
    if (empty($columns['manager_approval_date'])) {
        $mysqli->query("ALTER TABLE leave_requests ADD COLUMN manager_approval_date DATETIME NULL AFTER manager_approver_id");
    }
    if (empty($columns['hr_approver_id'])) {
        $mysqli->query("ALTER TABLE leave_requests ADD COLUMN hr_approver_id INT NULL AFTER manager_approval_date");
    }
    if (empty($columns['hr_approval_date'])) {
        $mysqli->query("ALTER TABLE leave_requests ADD COLUMN hr_approval_date DATETIME NULL AFTER hr_approver_id");
    }
}
```

Call it after login check:

```php
leaveApprovalEnsureTwoStepColumns($mysqli);
```

- [ ] **Step 2: Submit leave as pending_manager**

In `api/leave_request_api.php`, change the INSERT status from `'pending'` to `'pending_manager'`:

```php
$sql = "INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, start_day_part, end_day_part, request_unit, time_request_type, request_minutes, total_days, reason, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_manager')";
```

- [ ] **Step 3: Keep old pending cancellable**

In `api/leave_history_api.php`, change the cancel check to:

```php
$check = $mysqli->prepare("SELECT id FROM leave_requests WHERE id = ? AND employee_id = ? AND status IN ('pending','pending_manager')");
```

- [ ] **Step 4: Replace leave pending/history query filters**

In `api/leave_approval_api.php`, build role filters:

```php
$scopeClause = hrScopeBuildEmployeeWhereClause($my_role, hrScopeCurrentSessionScopes(), 'e');

if ($my_role === 'admin') {
    // no extra filter
} elseif ($my_role === 'hr') {
    $sql .= $scopeClause['sql'];
} else {
    $sql .= " AND e.supervisor_id = ? ";
}

if ($type === 'pending') {
    if ($my_role === 'hr') {
        $sql .= " AND lr.status = 'pending_hr' ";
    } elseif ($my_role === 'admin') {
        $sql .= " AND lr.status IN ('pending','pending_manager','pending_hr') ";
    } else {
        $sql .= " AND lr.status IN ('pending','pending_manager') ";
    }
} else {
    $sql .= " AND lr.status IN ('pending_hr','approved','rejected') ";
}
```

Bind params by merging manager params or HR scope params:

```php
$types = '';
$params = [];
if ($my_role === 'hr') {
    $types .= $scopeClause['types'];
    $params = array_merge($params, $scopeClause['params']);
} elseif ($my_role !== 'admin') {
    $types .= 'i';
    $params[] = $my_emp_id;
}
$stmt = $mysqli->prepare($sql);
hrScopeBindParams($stmt, $types, $params);
$stmt->execute();
```

- [ ] **Step 5: Replace leave approval POST action**

In the POST approval block, determine stage:

```php
$auth_sql = "SELECT lr.id, lr.status, e.supervisor_id
             FROM leave_requests lr
             JOIN employees e ON lr.employee_id = e.id
             WHERE lr.id = ?";
$scopeClause = hrScopeBuildEmployeeWhereClause($my_role, hrScopeCurrentSessionScopes(), 'e');
if ($my_role === 'hr') {
    $auth_sql .= $scopeClause['sql'];
} elseif ($my_role !== 'admin') {
    $auth_sql .= " AND e.supervisor_id = ?";
}

$types = 'i';
$params = [$req_id];
if ($my_role === 'hr') {
    $types .= $scopeClause['types'];
    $params = array_merge($params, $scopeClause['params']);
} elseif ($my_role !== 'admin') {
    $types .= 'i';
    $params[] = $my_emp_id;
}

$auth_stmt = $mysqli->prepare($auth_sql);
hrScopeBindParams($auth_stmt, $types, $params);
$auth_stmt->execute();
$request = $auth_stmt->get_result()->fetch_assoc();
if (!$request) sendJsonError('Access Denied');

$currentStatus = $request['status'] === 'pending' ? 'pending_manager' : $request['status'];
$now = date('Y-m-d H:i:s');

if ($currentStatus === 'pending_manager') {
    if (!($my_role === 'admin' || (int)$request['supervisor_id'] === $my_emp_id)) sendJsonError('Access Denied');
    if ($action === 'approve') {
        $stmt = $mysqli->prepare("UPDATE leave_requests
                                  SET status = 'pending_hr', manager_approver_id = ?, manager_approval_date = ?
                                  WHERE id = ? AND status IN ('pending','pending_manager')");
        $stmt->bind_param('isi', $my_emp_id, $now, $req_id);
    } else {
        $stmt = $mysqli->prepare("UPDATE leave_requests
                                  SET status = 'rejected', manager_approver_id = ?, manager_approval_date = ?, approver_id = ?, approval_date = ?, rejection_reason = ?
                                  WHERE id = ? AND status IN ('pending','pending_manager')");
        $stmt->bind_param('isissi', $my_emp_id, $now, $my_emp_id, $now, $reason, $req_id);
    }
} elseif ($currentStatus === 'pending_hr') {
    if (!in_array($my_role, ['admin', 'hr'], true)) sendJsonError('Access Denied');
    $newStatus = $action === 'approve' ? 'approved' : 'rejected';
    $stmt = $mysqli->prepare("UPDATE leave_requests
                              SET status = ?, hr_approver_id = ?, hr_approval_date = ?, approver_id = ?, approval_date = ?, rejection_reason = ?
                              WHERE id = ? AND status = 'pending_hr'");
    $stmt->bind_param('sisissi', $newStatus, $my_emp_id, $now, $my_emp_id, $now, $reason, $req_id);
} else {
    sendJsonError('Request was already processed');
}
```

Then keep the existing execute/affected rows success handling.

- [ ] **Step 6: Checks**

Run:

```powershell
C:\xampp\php\php.exe -l api\leave_request_api.php
C:\xampp\php\php.exe -l api\leave_history_api.php
C:\xampp\php\php.exe -l api\leave_approval_api.php
C:\xampp\php\php.exe tests\leave_helpers_test.php
```

Expected: all pass.

- [ ] **Step 7: Commit**

```powershell
git add api\leave_request_api.php api\leave_history_api.php api\leave_approval_api.php
git commit -m "Add two-step leave approval backend"
```

---

### Task 6: Leave Approval UI Status Labels

**Files:**
- Modify: `assets/js/leave_approval.js`
- Modify: `assets/js/my_leaves.js`

- [ ] **Step 1: Add leave status label helper**

In both JS files, add:

```javascript
function renderLeaveStatusBadge(status) {
    const map = {
        pending: ['รอหัวหน้างานอนุมัติ', 'warning text-dark'],
        pending_manager: ['รอหัวหน้างานอนุมัติ', 'warning text-dark'],
        pending_hr: ['รอ HR อนุมัติ', 'info text-dark'],
        approved: ['อนุมัติแล้ว', 'success'],
        rejected: ['ไม่อนุมัติ', 'danger'],
        cancelled: ['ยกเลิก', 'secondary'],
    };
    const item = map[status] || [status || '-', 'secondary'];
    return `<span class="badge bg-${item[1]}">${item[0]}</span>`;
}
```

- [ ] **Step 2: Use helper in approval history**

In `assets/js/leave_approval.js`, replace the old `statusBadge` ternary with:

```javascript
const statusBadge = renderLeaveStatusBadge(item.status);
```

In pending rows, add a small stage label under the employee code:

```javascript
<small class="text-muted">${item.employee_code}</small>
<div class="mt-1">${renderLeaveStatusBadge(item.status)}</div>
```

- [ ] **Step 3: Use helper in my leaves**

In `assets/js/my_leaves.js`, replace status rendering for pending/approved/rejected with `renderLeaveStatusBadge(item.status)` while keeping the cancel button visible only for `pending` or `pending_manager`.

Use:

```javascript
const canCancel = item.status === 'pending' || item.status === 'pending_manager';
```

- [ ] **Step 4: JS checks**

Run:

```powershell
node --check assets\js\leave_approval.js
node --check assets\js\my_leaves.js
```

Expected: both pass.

- [ ] **Step 5: Commit**

```powershell
git add assets\js\leave_approval.js assets\js\my_leaves.js
git commit -m "Show two-step leave approval states"
```

---

### Task 7: Day Swap Two-Step Approval Backend

**Files:**
- Modify: `includes/day_swap_helpers.php`
- Modify: `api/day_swap_api.php`

- [ ] **Step 1: Ensure day swap columns and status enum**

In `includes/day_swap_helpers.php`, update `daySwapEnsureTable()` table definition status enum to:

```sql
status ENUM('pending','pending_manager','pending_hr','approved','rejected','cancelled') NOT NULL DEFAULT 'pending_manager'
```

After the create table query, add column checks:

```php
$columns = [];
$result = $mysqli->query("SHOW COLUMNS FROM day_swap_requests");
while ($row = $result->fetch_assoc()) {
    $columns[$row['Field']] = $row;
}
if (!empty($columns['status']) && strpos($columns['status']['Type'], 'pending_manager') === false) {
    $mysqli->query("ALTER TABLE day_swap_requests MODIFY status ENUM('pending','pending_manager','pending_hr','approved','rejected','cancelled') NOT NULL DEFAULT 'pending_manager'");
}
if (empty($columns['manager_approver_id'])) {
    $mysqli->query("ALTER TABLE day_swap_requests ADD COLUMN manager_approver_id INT NULL AFTER approver_id");
}
if (empty($columns['manager_approval_date'])) {
    $mysqli->query("ALTER TABLE day_swap_requests ADD COLUMN manager_approval_date DATETIME NULL AFTER manager_approver_id");
}
if (empty($columns['hr_approver_id'])) {
    $mysqli->query("ALTER TABLE day_swap_requests ADD COLUMN hr_approver_id INT NULL AFTER manager_approval_date");
}
if (empty($columns['hr_approval_date'])) {
    $mysqli->query("ALTER TABLE day_swap_requests ADD COLUMN hr_approval_date DATETIME NULL AFTER hr_approver_id");
}
```

- [ ] **Step 2: Submit day swaps as pending_manager**

In `api/day_swap_api.php`, change insert:

```php
$stmt = $mysqli->prepare("INSERT INTO day_swap_requests
    (requester_employee_id, target_employee_id, requester_date, target_date, reason, status)
    VALUES (?, ?, ?, ?, ?, 'pending_manager')");
```

- [ ] **Step 3: Include HR helper and scope pending query**

At top of `api/day_swap_api.php`, add:

```php
require_once '../includes/hr_scope_helpers.php';
```

Replace HR company filtering in `daySwapApprovalQuery()` with an employee scope clause. Change signature to:

```php
function daySwapApprovalQuery($type, $role, array $scopes) {
```

Inside function:

```php
$scopeClause = hrScopeBuildEmployeeWhereClause($role, $scopes, 're');
if ($role === 'hr') {
    $sql .= $scopeClause['sql'];
} elseif ($role !== 'admin') {
    $sql .= " AND re.supervisor_id = ?";
}

if ($type === 'pending') {
    if ($role === 'hr') {
        $sql .= " AND dsr.status = 'pending_hr'";
    } elseif ($role === 'admin') {
        $sql .= " AND dsr.status IN ('pending','pending_manager','pending_hr')";
    } else {
        $sql .= " AND dsr.status IN ('pending','pending_manager')";
    }
} else {
    $sql .= " AND dsr.status IN ('pending_hr','approved','rejected')";
}
```

When calling:

```php
$scopes = hrScopeCurrentSessionScopes();
$sql = daySwapApprovalQuery($action, $myRole, $scopes);
$stmt = $mysqli->prepare($sql);
if ($myRole === 'hr') {
    $scopeClause = hrScopeBuildEmployeeWhereClause($myRole, $scopes, 're');
    hrScopeBindParams($stmt, $scopeClause['types'], $scopeClause['params']);
} elseif ($myRole !== 'admin') {
    $stmt->bind_param('i', $myEmployeeId);
}
```

- [ ] **Step 4: Replace day swap approval action**

Update `daySwapCanApproveRequest()` to return row data instead of boolean:

```php
function daySwapFetchApprovableRequest($mysqli, $requestId, $role, $myEmployeeId, array $scopes) {
    $sql = "SELECT dsr.id, dsr.status, re.supervisor_id
            FROM day_swap_requests dsr
            JOIN employees re ON dsr.requester_employee_id = re.id
            WHERE dsr.id = ?";
    $scopeClause = hrScopeBuildEmployeeWhereClause($role, $scopes, 're');
    $types = 'i';
    $params = [$requestId];
    if ($role === 'hr') {
        $sql .= $scopeClause['sql'];
        $types .= $scopeClause['types'];
        $params = array_merge($params, $scopeClause['params']);
    } elseif ($role !== 'admin') {
        $sql .= " AND re.supervisor_id = ?";
        $types .= 'i';
        $params[] = $myEmployeeId;
    }
    $stmt = $mysqli->prepare($sql);
    hrScopeBindParams($stmt, $types, $params);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
```

In POST approve/reject:

```php
$request = daySwapFetchApprovableRequest($mysqli, $requestId, $myRole, $myEmployeeId, hrScopeCurrentSessionScopes());
if (!$request) sendDaySwapError('Access Denied');
$currentStatus = $request['status'] === 'pending' ? 'pending_manager' : $request['status'];
$now = date('Y-m-d H:i:s');

if ($currentStatus === 'pending_manager') {
    if (!($myRole === 'admin' || (int)$request['supervisor_id'] === $myEmployeeId)) sendDaySwapError('Access Denied');
    if ($postAction === 'approve') {
        $stmt = $mysqli->prepare("UPDATE day_swap_requests
                                  SET status = 'pending_hr', manager_approver_id = ?, manager_approval_date = ?
                                  WHERE id = ? AND status IN ('pending','pending_manager')");
        $stmt->bind_param('isi', $myEmployeeId, $now, $requestId);
    } else {
        $stmt = $mysqli->prepare("UPDATE day_swap_requests
                                  SET status = 'rejected', manager_approver_id = ?, manager_approval_date = ?, approver_id = ?, approval_date = ?, rejection_reason = ?
                                  WHERE id = ? AND status IN ('pending','pending_manager')");
        $stmt->bind_param('isissi', $myEmployeeId, $now, $myEmployeeId, $now, $reason, $requestId);
    }
} elseif ($currentStatus === 'pending_hr') {
    if (!in_array($myRole, ['admin', 'hr'], true)) sendDaySwapError('Access Denied');
    $newStatus = $postAction === 'approve' ? 'approved' : 'rejected';
    $stmt = $mysqli->prepare("UPDATE day_swap_requests
                              SET status = ?, hr_approver_id = ?, hr_approval_date = ?, approver_id = ?, approval_date = ?, rejection_reason = ?
                              WHERE id = ? AND status = 'pending_hr'");
    $stmt->bind_param('sisissi', $newStatus, $myEmployeeId, $now, $myEmployeeId, $now, $reason, $requestId);
} else {
    sendDaySwapError('Request was already processed');
}
```

- [ ] **Step 5: Checks**

Run:

```powershell
C:\xampp\php\php.exe -l includes\day_swap_helpers.php
C:\xampp\php\php.exe -l api\day_swap_api.php
C:\xampp\php\php.exe tests\attendance_helpers_test.php
node tests\day_swap_calendar_test.js
```

Expected: all pass.

- [ ] **Step 6: Commit**

```powershell
git add includes\day_swap_helpers.php api\day_swap_api.php
git commit -m "Add two-step day swap approval backend"
```

---

### Task 8: Day Swap UI Status Labels

**Files:**
- Modify: `assets/js/day_swap.js`

- [ ] **Step 1: Update day swap status labels**

Replace `renderDaySwapStatus(status)` map with:

```javascript
const map = {
    pending: ['รอหัวหน้างานอนุมัติ', 'warning text-dark'],
    pending_manager: ['รอหัวหน้างานอนุมัติ', 'warning text-dark'],
    pending_hr: ['รอ HR อนุมัติ', 'info text-dark'],
    approved: ['อนุมัติแล้ว', 'success'],
    rejected: ['ไม่อนุมัติ', 'danger'],
    cancelled: ['ยกเลิก', 'secondary'],
};
```

In pending approval rows, add `renderDaySwapStatus(item.status)` under requester code.

- [ ] **Step 2: JS checks**

Run:

```powershell
node --check assets\js\day_swap.js
node tests\day_swap_calendar_test.js
```

Expected: both pass.

- [ ] **Step 3: Commit**

```powershell
git add assets\js\day_swap.js
git commit -m "Show two-step day swap approval states"
```

---

### Task 9: Page Access and Menu Role Checks

**Files:**
- Modify: `includes/header.php`
- Modify: `leave_approvals.php`
- Modify: `day_swap_approvals.php`
- Modify: `attendance.php`
- Modify: `attendance_import.php`
- Modify: `leave_types.php`
- Modify: `shifts.php`
- Modify: `company_holidays.php`

- [ ] **Step 1: Add small access helpers in header/auth consumers**

Use direct role checks for now:

```php
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$isHr = ($_SESSION['role'] ?? '') === 'hr';
$isManager = ($_SESSION['role'] ?? '') === 'manager';
```

For approval pages and sidebar approval links:

```php
if (!in_array($_SESSION['role'], ['manager', 'hr', 'admin'], true)) { ... }
```

Keep this behavior because HR with no scopes can open the page but API returns no scoped rows.

For admin/HR maintenance pages:

```php
if (!in_array($_SESSION['role'], ['admin', 'hr'], true)) { ... }
```

Keep existing behavior unless the page edits global settings. If the page is global company holidays, leave types, or shifts and business wants HR scoped edit restrictions later, defer that to a separate spec.

- [ ] **Step 2: Verify no accidental role regression**

Run:

```powershell
rg -n "\$_SESSION\['role'\].*hr|in_array\(\$_SESSION\['role'\]" includes *.php api
```

Expected: all checks are either unchanged global role gates or replaced by scope-aware API filtering where data visibility matters.

- [ ] **Step 3: Syntax checks**

Run:

```powershell
C:\xampp\php\php.exe -l includes\header.php
C:\xampp\php\php.exe -l leave_approvals.php
C:\xampp\php\php.exe -l day_swap_approvals.php
C:\xampp\php\php.exe -l attendance.php
C:\xampp\php\php.exe -l attendance_import.php
C:\xampp\php\php.exe -l leave_types.php
C:\xampp\php\php.exe -l shifts.php
C:\xampp\php\php.exe -l company_holidays.php
```

Expected: all pass.

- [ ] **Step 4: Commit**

```powershell
git add includes\header.php leave_approvals.php day_swap_approvals.php attendance.php attendance_import.php leave_types.php shifts.php company_holidays.php
git commit -m "Review HR access gates for scoped permissions"
```

---

### Task 10: Final Verification

**Files:**
- No new code unless verification finds a defect.

- [ ] **Step 1: Run PHP lint on touched PHP files**

Run:

```powershell
C:\xampp\php\php.exe -l includes\hr_scope_helpers.php
C:\xampp\php\php.exe -l includes\auth_check.php
C:\xampp\php\php.exe -l api\login_process.php
C:\xampp\php\php.exe -l employee_add.php
C:\xampp\php\php.exe -l employee_edit.php
C:\xampp\php\php.exe -l employees.php
C:\xampp\php\php.exe -l api\employee_api.php
C:\xampp\php\php.exe -l api\leave_request_api.php
C:\xampp\php\php.exe -l api\leave_history_api.php
C:\xampp\php\php.exe -l api\leave_approval_api.php
C:\xampp\php\php.exe -l includes\day_swap_helpers.php
C:\xampp\php\php.exe -l api\day_swap_api.php
```

Expected: all say `No syntax errors detected`.

- [ ] **Step 2: Run focused PHP tests**

Run:

```powershell
C:\xampp\php\php.exe tests\hr_scope_helpers_test.php
C:\xampp\php\php.exe tests\leave_helpers_test.php
C:\xampp\php\php.exe tests\attendance_helpers_test.php
```

Expected: all pass.

- [ ] **Step 3: Run focused JS checks/tests**

Run:

```powershell
node --check assets\js\employee.js
node --check assets\js\leave_approval.js
node --check assets\js\my_leaves.js
node --check assets\js\day_swap.js
node tests\day_swap_calendar_test.js
node tests\leave_request_icon_ui_test.js
```

Expected: all pass.

- [ ] **Step 4: Run diff hygiene checks**

Run:

```powershell
git diff --check
git status --short
```

Expected: no whitespace errors; status shows only intended files if there are uncommitted fixes.

- [ ] **Step 5: Commit any verification fixes**

If verification required fixes:

```powershell
git add <fixed-files>
git commit -m "Fix HR scope approval verification issues"
```

If no fixes are needed, do not create an empty commit.

---

## Self-Review

Spec coverage:

- HR company scopes selected manually: covered by Tasks 1, 3, and 4.
- HR branch scopes selected manually: covered by Tasks 1, 3, and 4.
- One user can hold both HR company and HR branch scopes: covered by helper normalization and form persistence in Tasks 1 and 3.
- Admin sees all and can act on current approval stage: covered by Tasks 5 and 7.
- Manager first, HR second: covered by Tasks 5 and 7.
- HR sees only requests after manager approval: covered by pending query filters in Tasks 5 and 7.
- Attendance/report only uses `approved`: preserved because Tasks 5 and 7 do not alter attendance fetches beyond final status semantics.

Placeholder scan:

- No `TODO`, `TBD`, or open-ended implementation placeholders remain.

Type consistency:

- Scope arrays use `company_ids` and `branch_ids` consistently.
- Canonical request statuses are `pending_manager`, `pending_hr`, `approved`, `rejected`, and `cancelled`.
