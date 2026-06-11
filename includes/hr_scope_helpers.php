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
