<?php
require_once __DIR__ . '/hr_scope_helpers.php';

class EmployeeAccessDenied extends InvalidArgumentException {}

function employeeAccessDeny(): void
{
    throw new EmployeeAccessDenied('คุณไม่มีสิทธิ์จัดการข้อมูลนี้ กรุณาติดต่อผู้ดูแลระบบหากต้องการปรับขอบเขตสิทธิ์');
}

function employeeAccessRefresh(mysqli $mysqli): void
{
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $stmt = $mysqli->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $role = $stmt->get_result()->fetch_assoc()['role'] ?? '';
    $stmt->close();
    $_SESSION['role'] = $role;
    if (!in_array($role, ['admin', 'hr'], true)) employeeAccessDeny();
    hrScopeRefreshSession($mysqli);
}

function employeeAccessRequireEmployee(mysqli $mysqli, int $employeeId): array
{
    $scope = hrScopeBuildEmployeeWhereClause($_SESSION['role'] ?? '', hrScopeCurrentSessionScopes(), 'e');
    $stmt = $mysqli->prepare('SELECT e.id, e.company_id, e.branch_id FROM employees e WHERE e.id = ?' . $scope['sql']);
    hrScopeBindParams($stmt, 'i' . $scope['types'], array_merge([$employeeId], $scope['params']));
    $stmt->execute();
    $employee = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$employee) employeeAccessDeny();
    return $employee;
}

function employeeAccessRequireAssignment(mysqli $mysqli, int $companyId, int $branchId): void
{
    $stmt = $mysqli->prepare('SELECT id FROM branches WHERE id = ? AND company_id = ?');
    $stmt->bind_param('ii', $branchId, $companyId);
    $stmt->execute();
    $valid = $stmt->get_result()->num_rows === 1;
    $stmt->close();
    if (!$valid) employeeAccessDeny();
    if (($_SESSION['role'] ?? '') === 'admin') return;
    $scope = hrScopeCurrentSessionScopes();
    if (($_SESSION['role'] ?? '') !== 'hr' || (!in_array($companyId, $scope['company_ids'], true) && !in_array($branchId, $scope['branch_ids'], true))) employeeAccessDeny();
}

function employeeAccessCanManageAccount(?string $targetRole): bool
{
    return ($_SESSION['role'] ?? '') === 'admin'
        || (($_SESSION['role'] ?? '') === 'hr' && ($targetRole === null || $targetRole === 'employee'));
}

// Legacy data may contain more than one account for one employee.
function employeeAccessCanManageEmployeeAccounts(mysqli $mysqli, int $employeeId): bool
{
    if (($_SESSION['role'] ?? '') === 'admin') return true;
    if (!employeeAccessCanManageAccount(null)) return false;
    $stmt = $mysqli->prepare('SELECT role FROM users WHERE employee_id = ?');
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $roles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($roles as $row) {
        if (!employeeAccessCanManageAccount($row['role'])) return false;
    }
    return true;
}

function employeeAccessValidateAccount(mysqli $mysqli, int $employeeId, array $data): void
{
    if (isset($data['role']) && !in_array($data['role'], ['employee', 'manager', 'hr', 'admin'], true)) employeeAccessDeny();
    if (($_SESSION['role'] ?? '') === 'admin') return;
    if (!empty($data['hr_company_ids']) || !empty($data['hr_branch_ids'])) employeeAccessDeny();
    if (isset($data['role']) && $data['role'] !== 'employee') employeeAccessDeny();
    if (!employeeAccessCanManageEmployeeAccounts($mysqli, $employeeId) &&
        (!empty($data['username']) || !empty($data['password']) || array_key_exists('role', $data))) employeeAccessDeny();
}

function employeeAccessAuthorizeAction(mysqli $mysqli, string $method, string $action, array $data): void
{
    if ($method === 'DELETE') {
        if (($_SESSION['role'] ?? '') !== 'admin') employeeAccessDeny();
        return;
    }
    if ($method === 'GET') {
        if (in_array($action, ['get_history', 'training_history'], true)) employeeAccessRequireEmployee($mysqli, (int)($data['employee_id'] ?? 0));
        return;
    }
    if ($method !== 'POST') employeeAccessDeny();
    $idKey = in_array($action, ['update_employee', 'update_profile_image'], true) ? 'id' : 'employee_id';
    $employeeId = (int)($data[$idKey] ?? 0);
    $employee = null;
    if ($action !== 'create_employee') $employee = employeeAccessRequireEmployee($mysqli, $employeeId);
    if (in_array($action, ['create_employee', 'update_employee'], true)) {
        employeeAccessRequireAssignment($mysqli, (int)($data['company_id'] ?? 0), (int)($data['branch_id'] ?? 0));
        employeeAccessValidateAccount($mysqli, $action === 'create_employee' ? 0 : $employeeId, $data);
    }
    if (in_array($action, ['transfer_employee', 'update_transfer_history'], true)) {
        $companyId = (int)($data['new_company_id'] ?? 0) ?: (int)$employee['company_id'];
        $branchId = (int)($data['new_branch_id'] ?? 0) ?: (int)$employee['branch_id'];
        employeeAccessRequireAssignment($mysqli, $companyId, $branchId);
    }
}

function employeeAccessRequirePage(mysqli $mysqli, ?int $employeeId = null, bool $requireAssignmentScope = false): void
{
    try {
        employeeAccessRefresh($mysqli);
        if ($requireAssignmentScope && $_SESSION['role'] === 'hr' && !hrScopeHasAnyScope(hrScopeCurrentSessionScopes())) employeeAccessDeny();
        if ($employeeId !== null) employeeAccessRequireEmployee($mysqli, $employeeId);
    } catch (EmployeeAccessDenied $error) {
        http_response_code(403);
        $page_title = 'ไม่มีสิทธิ์เข้าถึงข้อมูล';
        require __DIR__ . '/header.php';
        echo '<div class="alert alert-warning" role="alert"><h1 class="h5">ไม่มีสิทธิ์เข้าถึงข้อมูลนี้</h1><p>ข้อมูลนี้อยู่นอกขอบเขตที่คุณดูแล กรุณาติดต่อผู้ดูแลระบบหากต้องการปรับสิทธิ์</p><a class="btn btn-outline-dark" href="dashboard.php">กลับหน้าหลัก</a></div>';
        require __DIR__ . '/footer.php';
        exit;
    }
}

function employeeAccessFormOptions(mysqli $mysqli, int $excludeEmployeeId = 0, int $currentSupervisorId = 0): array
{
    $role = $_SESSION['role'] ?? '';
    $scopes = hrScopeCurrentSessionScopes();
    $scope = hrScopeBuildEmployeeWhereClause($role, $scopes, 'b');
    $branchSql = str_replace('b.branch_id', 'b.id', $scope['sql']);
    $stmt = $mysqli->prepare('SELECT b.id, b.company_id, b.branch_name_th, c.company_name_th FROM branches b JOIN companies c ON c.id=b.company_id WHERE 1=1' . $branchSql . ' ORDER BY c.company_name_th, b.branch_name_th');
    hrScopeBindParams($stmt, $scope['types'], $scope['params']);
    $stmt->execute();
    $branches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $companies = [];
    $companyIds = array_values(array_unique(array_merge($scopes['company_ids'], array_map('intval', array_column($branches, 'company_id')))));
    if ($role === 'admin') {
        $companies = $mysqli->query('SELECT id, company_name_th FROM companies ORDER BY company_name_th')->fetch_all(MYSQLI_ASSOC);
    } elseif ($companyIds) {
        $stmt = $mysqli->prepare('SELECT id, company_name_th FROM companies WHERE id IN (' . implode(',', array_fill(0, count($companyIds), '?')) . ') ORDER BY company_name_th');
        hrScopeBindParams($stmt, str_repeat('i', count($companyIds)), $companyIds);
        $stmt->execute();
        $companies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    $scope = hrScopeBuildEmployeeWhereClause($role, $scopes, 'e');
    $stmt = $mysqli->prepare("SELECT e.id, e.first_name_th, e.last_name_th FROM employees e WHERE e.status = 'active' AND e.id <> ?" . $scope['sql'] . ' ORDER BY e.first_name_th');
    hrScopeBindParams($stmt, 'i' . $scope['types'], array_merge([$excludeEmployeeId], $scope['params']));
    $stmt->execute();
    $supervisors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    if ($currentSupervisorId > 0 && !in_array($currentSupervisorId, array_map('intval', array_column($supervisors, 'id')), true)) {
        $supervisors[] = ['id' => $currentSupervisorId, 'first_name_th' => 'หัวหน้างานเดิม', 'last_name_th' => '(คงค่าเดิม)'];
    }
    return ['companies' => $companies, 'branches' => $branches, 'supervisors' => $supervisors];
}