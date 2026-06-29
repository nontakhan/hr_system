<?php

function proxyRequestRoleCanCreate($role) {
    return in_array((string)$role, ['admin', 'hr'], true);
}

function proxyRequestRequireAccess() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user_id']) || !proxyRequestRoleCanCreate($_SESSION['role'] ?? '')) {
        throw new RuntimeException('Access Denied');
    }
}

function proxyRequestBuildAuditPayload($proxyNote = '') {
    return [
        'created_by_user_id' => (int)($_SESSION['user_id'] ?? 0),
        'created_by_employee_id' => (int)($_SESSION['employee_id'] ?? 0) ?: null,
        'created_by_role' => (string)($_SESSION['role'] ?? ''),
        'created_via' => 'admin_proxy',
        'proxy_note' => trim((string)$proxyNote),
    ];
}

function proxyRequestCreatorLabel(array $row) {
    if (($row['created_via'] ?? 'admin_proxy') !== 'admin_proxy') {
        return '';
    }
    $name = trim((string)($row['proxy_creator_name'] ?? ''));
    if ($name === '') {
        $name = trim((string)($row['created_by_role'] ?? ''));
    }
    return $name === '' ? '' : 'Created by HR/Admin: ' . $name;
}

function proxyRequestEnsureAuditColumns(mysqli $mysqli, $tableName) {
    $allowed = ['leave_requests', 'day_swap_requests', 'training_requests'];
    if (!in_array($tableName, $allowed, true)) {
        throw new InvalidArgumentException('Invalid proxy audit table');
    }

    $columns = [];
    $result = $mysqli->query("SHOW COLUMNS FROM {$tableName}");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = $row;
        }
    }

    $afterColumn = isset($columns['rejection_reason']) ? 'rejection_reason' : null;
    proxyRequestAddColumnIfMissing($mysqli, $tableName, $columns, 'created_by_user_id', 'INT NULL', $afterColumn);
    proxyRequestAddColumnIfMissing($mysqli, $tableName, $columns, 'created_by_employee_id', 'INT NULL', 'created_by_user_id');
    proxyRequestAddColumnIfMissing($mysqli, $tableName, $columns, 'created_by_role', 'VARCHAR(30) NULL', 'created_by_employee_id');
    proxyRequestAddColumnIfMissing($mysqli, $tableName, $columns, 'created_via', "ENUM('self_service','admin_proxy') NOT NULL DEFAULT 'self_service'", 'created_by_role');
    proxyRequestAddColumnIfMissing($mysqli, $tableName, $columns, 'proxy_note', 'TEXT NULL', 'created_via');
}

function proxyRequestAddColumnIfMissing(mysqli $mysqli, $tableName, array &$columns, $columnName, $definition, $afterColumn = null) {
    if (isset($columns[$columnName])) {
        return;
    }

    $afterSql = ($afterColumn !== null && isset($columns[$afterColumn])) ? " AFTER {$afterColumn}" : '';
    $mysqli->query("ALTER TABLE {$tableName} ADD COLUMN {$columnName} {$definition}{$afterSql}");
    $columns[$columnName] = true;
}
