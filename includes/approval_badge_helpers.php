<?php

function approvalBadgePendingStagesForRole($role) {
    if ($role === 'admin') {
        return ['pending_hr', 'pending_cancel_hr'];
    }
    if ($role === 'hr') {
        return ['pending_hr', 'pending_cancel_hr'];
    }
    if ($role === 'manager') {
        return ['pending', 'pending_manager'];
    }
    return [];
}

function approvalBadgeNormalizeCounts(array $counts) {
    $normalized = [
        'leave' => max(0, (int)($counts['leave'] ?? 0)),
        'time_request' => max(0, (int)($counts['time_request'] ?? 0)),
        'day_swap' => max(0, (int)($counts['day_swap'] ?? 0)),
    ];
    $normalized['total'] = $normalized['leave'] + $normalized['time_request'] + $normalized['day_swap'];
    return $normalized;
}

function approvalBadgeFetchCounts(mysqli $mysqli, $role, $employeeId, array $scopes) {
    $stages = approvalBadgePendingStagesForRole($role);
    if (!$stages) {
        return approvalBadgeNormalizeCounts([]);
    }

    if (function_exists('leaveEnsureTwoStepApprovalColumns')) {
        leaveEnsureTwoStepApprovalColumns($mysqli);
    }
    if (function_exists('daySwapEnsureTable')) {
        daySwapEnsureTable($mysqli);
    }

    return approvalBadgeNormalizeCounts([
        'leave' => approvalBadgeCountLeaveRequests($mysqli, $role, (int)$employeeId, $scopes, 'day', $stages),
        'time_request' => approvalBadgeCountLeaveRequests($mysqli, $role, (int)$employeeId, $scopes, 'hour', $stages),
        'day_swap' => approvalBadgeCountDaySwapRequests($mysqli, $role, (int)$employeeId, $scopes, $stages),
    ]);
}

function approvalBadgeCountLeaveRequests(mysqli $mysqli, $role, $employeeId, array $scopes, $requestUnit, array $stages) {
    $stageList = approvalBadgeSqlStringList($stages);
    $sql = "SELECT COUNT(*) AS total
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.id
            WHERE lr.status IN ($stageList)
              AND lr.request_unit = ?";
    $types = 's';
    $params = [$requestUnit === 'hour' ? 'hour' : 'day'];

    approvalBadgeAppendRoleScope($sql, $types, $params, $role, $employeeId, $scopes, 'e');
    return approvalBadgeFetchTotal($mysqli, $sql, $types, $params);
}

function approvalBadgeCountDaySwapRequests(mysqli $mysqli, $role, $employeeId, array $scopes, array $stages) {
    $stageList = approvalBadgeSqlStringList($stages);
    $sql = "SELECT COUNT(*) AS total
            FROM day_swap_requests dsr
            JOIN employees re ON dsr.requester_employee_id = re.id
            WHERE dsr.status IN ($stageList)";
    $types = '';
    $params = [];

    approvalBadgeAppendRoleScope($sql, $types, $params, $role, $employeeId, $scopes, 're');
    return approvalBadgeFetchTotal($mysqli, $sql, $types, $params);
}

function approvalBadgeAppendRoleScope(&$sql, &$types, array &$params, $role, $employeeId, array $scopes, $employeeAlias) {
    if ($role === 'admin') {
        return;
    }
    if ($role === 'hr') {
        $scopeClause = hrScopeBuildEmployeeWhereClause($role, $scopes, $employeeAlias);
        $sql .= $scopeClause['sql'];
        $types .= $scopeClause['types'];
        $params = array_merge($params, $scopeClause['params']);
        return;
    }

    $sql .= " AND {$employeeAlias}.supervisor_id = ?";
    $types .= 'i';
    $params[] = (int)$employeeId;
}

function approvalBadgeFetchTotal(mysqli $mysqli, $sql, $types, array $params) {
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    hrScopeBindParams($stmt, $types, $params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['total'] ?? 0);
}

function approvalBadgeSqlStringList(array $values) {
    $safe = [];
    foreach ($values as $value) {
        $safe[] = "'" . str_replace("'", "''", (string)$value) . "'";
    }
    return implode(',', $safe);
}
