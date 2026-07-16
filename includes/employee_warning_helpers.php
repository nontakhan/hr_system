<?php

require_once __DIR__ . '/employee_warning_bulk_helpers.php';

function employeeWarningEnsureTables(mysqli $mysqli): void
{
    $mysqli->query("CREATE TABLE IF NOT EXISTS warning_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type_name VARCHAR(255) NOT NULL,
        description TEXT NULL,
        created_by INT NULL,
        updated_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_warning_types_type_name (type_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $mysqli->query("CREATE TABLE IF NOT EXISTS employee_warnings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        warning_type_id INT NOT NULL,
        warning_date DATE NOT NULL,
        detail TEXT NULL,
        source_type VARCHAR(50) NULL,
        source_key VARCHAR(100) NULL,
        source_event_date DATE NULL,
        created_by INT NULL,
        updated_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_employee_warnings_employee_month (employee_id, warning_date),
        INDEX idx_employee_warnings_type (warning_type_id),
        INDEX idx_employee_warnings_date (warning_date),
        UNIQUE KEY uq_employee_warnings_source (source_type, source_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    employeeWarningEnsureSourceColumns($mysqli);
}

function employeeWarningTrim(?string $value, int $maxLength = 255): string
{
    $value = trim((string)$value);
    if ($maxLength > 0 && mb_strlen($value, 'UTF-8') > $maxLength) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }
    return $value;
}

function employeeWarningNormalizeMonth(?string $month): string
{
    $month = trim((string)$month);
    if ($month === '') {
        return date('Y-m');
    }
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        throw new InvalidArgumentException('รูปแบบเดือนไม่ถูกต้อง');
    }
    return $month;
}

function employeeWarningNormalizeDate(?string $date, string $message): string
{
    $date = trim((string)$date);
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException($message);
    }
    return $date;
}

function employeeWarningMonthRange(string $month): array
{
    $month = employeeWarningNormalizeMonth($month);
    $start = $month . '-01';
    $end = (new DateTime($start))->modify('first day of next month')->format('Y-m-d');
    return [$start, $end];
}

function employeeWarningFetchTypes(mysqli $mysqli): array
{
    $result = $mysqli->query("SELECT id, type_name, description, created_at, updated_at FROM warning_types ORDER BY type_name ASC, id ASC");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function employeeWarningSaveType(mysqli $mysqli, array $input, int $userId): array
{
    $id = (int)($input['id'] ?? 0);
    $typeName = employeeWarningTrim($input['type_name'] ?? '', 255);
    $description = trim((string)($input['description'] ?? ''));

    if ($typeName === '') {
        throw new InvalidArgumentException('กรุณาระบุรายการใบเตือน');
    }

    if ($id > 0) {
        $stmt = $mysqli->prepare("UPDATE warning_types SET type_name = ?, description = ?, updated_by = ? WHERE id = ?");
        $stmt->bind_param('ssii', $typeName, $description, $userId, $id);
    } else {
        $stmt = $mysqli->prepare("INSERT INTO warning_types (type_name, description, created_by) VALUES (?, ?, ?)");
        $stmt->bind_param('ssi', $typeName, $description, $userId);
    }

    if (!$stmt->execute()) {
        if ($mysqli->errno === 1062 || $stmt->errno === 1062) {
            throw new InvalidArgumentException('มีรายการใบเตือนนี้แล้ว');
        }
        throw new RuntimeException($stmt->error ?: 'Cannot save warning type');
    }

    $savedId = $id > 0 ? $id : $stmt->insert_id;
    $fetch = $mysqli->prepare("SELECT id, type_name, description, created_at, updated_at FROM warning_types WHERE id = ?");
    $fetch->bind_param('i', $savedId);
    $fetch->execute();
    return $fetch->get_result()->fetch_assoc() ?: [];
}

function employeeWarningDeleteType(mysqli $mysqli, int $id): void
{
    if ($id <= 0) {
        throw new InvalidArgumentException('รายการใบเตือนไม่ถูกต้อง');
    }

    $check = $mysqli->prepare("SELECT id FROM employee_warnings WHERE warning_type_id = ? LIMIT 1");
    $check->bind_param('i', $id);
    $check->execute();
    if ($check->get_result()->fetch_assoc()) {
        throw new InvalidArgumentException('ไม่สามารถลบได้ เนื่องจากมีประวัติใบเตือนที่ใช้รายการนี้แล้ว');
    }

    $stmt = $mysqli->prepare("DELETE FROM warning_types WHERE id = ?");
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error ?: 'Cannot delete warning type');
    }
}

function employeeWarningCreateRecord(mysqli $mysqli, array $input, int $userId): void
{
    $employeeId = (int)($input['employee_id'] ?? 0);
    $warningTypeId = (int)($input['warning_type_id'] ?? 0);
    $warningDate = employeeWarningNormalizeDate($input['warning_date'] ?? '', 'กรุณาระบุวันที่เกิดเหตุ');
    $detail = trim((string)($input['detail'] ?? ''));

    if ($employeeId <= 0) {
        throw new InvalidArgumentException('กรุณาเลือกพนักงาน');
    }
    if ($warningTypeId <= 0) {
        throw new InvalidArgumentException('กรุณาเลือกรายการใบเตือน');
    }

    $employee = $mysqli->prepare("SELECT id FROM employees WHERE id = ? LIMIT 1");
    $employee->bind_param('i', $employeeId);
    $employee->execute();
    if (!$employee->get_result()->fetch_assoc()) {
        throw new InvalidArgumentException('ไม่พบข้อมูลพนักงาน');
    }

    $type = $mysqli->prepare("SELECT id FROM warning_types WHERE id = ? LIMIT 1");
    $type->bind_param('i', $warningTypeId);
    $type->execute();
    if (!$type->get_result()->fetch_assoc()) {
        throw new InvalidArgumentException('ไม่พบรายการใบเตือน');
    }

    $stmt = $mysqli->prepare("INSERT INTO employee_warnings (employee_id, warning_type_id, warning_date, detail, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('iissi', $employeeId, $warningTypeId, $warningDate, $detail, $userId);
    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error ?: 'Cannot save employee warning');
    }
}

function employeeWarningEmployeeScopeClause(string $role, array $scopes, string $alias = 'e'): array
{
    if ($role === 'hr' && function_exists('hrScopeBuildEmployeeWhereClause')) {
        return hrScopeBuildEmployeeWhereClause($role, $scopes, $alias);
    }
    return ['sql' => '', 'types' => '', 'params' => []];
}

function employeeWarningBindParams(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '') {
        return;
    }

    $refs = [];
    foreach ($params as $index => $_) {
        $refs[$index] = &$params[$index];
    }
    $stmt->bind_param($types, ...$refs);
}

function employeeWarningFetchEmployees(mysqli $mysqli, string $role, array $scopes): array
{
    $scopeClause = employeeWarningEmployeeScopeClause($role, $scopes, 'e');
    $sql = "SELECT e.id,
                   e.citizen_id,
                   CONCAT_WS(' ', e.first_name_th, e.last_name_th) AS employee_name,
                   c.company_name_th,
                   b.branch_name_th,
                   d.dept_name_th,
                   p.position_name_th
            FROM employees e
            LEFT JOIN companies c ON e.company_id = c.id
            LEFT JOIN branches b ON e.branch_id = b.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE e.status IN ('active', 'probation')" . $scopeClause['sql'] . "
            ORDER BY e.first_name_th, e.last_name_th";
    $stmt = $mysqli->prepare($sql);
    if ($scopeClause['types'] !== '' && function_exists('hrScopeBindParams')) {
        hrScopeBindParams($stmt, $scopeClause['types'], $scopeClause['params']);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function employeeWarningFetchMonthlySummary(mysqli $mysqli, string $month, string $role = 'admin', array $scopes = []): array
{
    [$start, $end] = employeeWarningMonthRange($month);
    $scopeClause = employeeWarningEmployeeScopeClause($role, $scopes, 'e');
    $sql = "SELECT e.id AS employee_id,
                   e.citizen_id,
                   CONCAT_WS(' ', e.first_name_th, e.last_name_th) AS employee_name,
                   c.company_name_th,
                   b.branch_name_th,
                   d.dept_name_th,
                   p.position_name_th,
                   COUNT(ew.id) AS warning_count,
                   GROUP_CONCAT(DISTINCT wt.type_name ORDER BY wt.type_name SEPARATOR ', ') AS warning_types
            FROM employee_warnings ew
            JOIN employees e ON ew.employee_id = e.id
            JOIN warning_types wt ON ew.warning_type_id = wt.id
            LEFT JOIN companies c ON e.company_id = c.id
            LEFT JOIN branches b ON e.branch_id = b.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE ew.warning_date >= ? AND ew.warning_date < ?" . $scopeClause['sql'] . "
            GROUP BY e.id, e.citizen_id, e.first_name_th, e.last_name_th, c.company_name_th, b.branch_name_th, d.dept_name_th, p.position_name_th
            ORDER BY warning_count DESC, e.first_name_th, e.last_name_th";
    $stmt = $mysqli->prepare($sql);
    $types = 'ss' . $scopeClause['types'];
    $params = array_merge([$start, $end], $scopeClause['params']);
    employeeWarningBindParams($stmt, $types, $params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $topType = employeeWarningFetchTopType($mysqli, $start, $end, $role, $scopes);
    $totalWarnings = 0;
    $typeSet = [];
    foreach ($rows as $row) {
        $totalWarnings += (int)$row['warning_count'];
        foreach (array_filter(array_map('trim', explode(',', (string)$row['warning_types']))) as $name) {
            $typeSet[$name] = true;
        }
    }

    return [
        'summary' => [
            'total_warnings' => $totalWarnings,
            'employee_count' => count($rows),
            'distinct_type_count' => count($typeSet),
            'top_warning_type' => $topType['type_name'] ?? '-',
            'top_warning_count' => isset($topType['warning_count']) ? (int)$topType['warning_count'] : 0,
        ],
        'rows' => $rows,
    ];
}

function employeeWarningFetchTopType(mysqli $mysqli, string $start, string $end, string $role, array $scopes): ?array
{
    $scopeClause = employeeWarningEmployeeScopeClause($role, $scopes, 'e');
    $sql = "SELECT wt.type_name, COUNT(ew.id) AS warning_count
            FROM employee_warnings ew
            JOIN warning_types wt ON ew.warning_type_id = wt.id
            JOIN employees e ON ew.employee_id = e.id
            WHERE ew.warning_date >= ? AND ew.warning_date < ?" . $scopeClause['sql'] . "
            GROUP BY wt.id, wt.type_name
            ORDER BY warning_count DESC, wt.type_name
            LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    $types = 'ss' . $scopeClause['types'];
    $params = array_merge([$start, $end], $scopeClause['params']);
    employeeWarningBindParams($stmt, $types, $params);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function employeeWarningFetchEmployeeMonthDetails(mysqli $mysqli, int $employeeId, string $month, string $role = 'admin', array $scopes = []): array
{
    [$start, $end] = employeeWarningMonthRange($month);
    $scopeClause = employeeWarningEmployeeScopeClause($role, $scopes, 'e');
    $stmt = $mysqli->prepare("SELECT ew.id,
                                     ew.warning_date,
                                     ew.detail,
                                     ew.created_at,
                                     wt.type_name,
                                     CONCAT_WS(' ', ce.first_name_th, ce.last_name_th) AS created_by_name
                              FROM employee_warnings ew
                              JOIN employees e ON ew.employee_id = e.id
                              JOIN warning_types wt ON ew.warning_type_id = wt.id
                              LEFT JOIN users u ON ew.created_by = u.id
                              LEFT JOIN employees ce ON u.employee_id = ce.id
                              WHERE ew.employee_id = ? AND ew.warning_date >= ? AND ew.warning_date < ?" . $scopeClause['sql'] . "
                              ORDER BY ew.warning_date DESC, ew.id DESC");
    $types = 'iss' . $scopeClause['types'];
    $params = array_merge([$employeeId, $start, $end], $scopeClause['params']);
    employeeWarningBindParams($stmt, $types, $params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function employeeWarningFetchMyMonth(mysqli $mysqli, int $employeeId, string $month): array
{
    if ($employeeId <= 0) {
        throw new InvalidArgumentException('ไม่พบข้อมูลพนักงานของผู้ใช้งาน');
    }

    $rows = employeeWarningFetchEmployeeMonthDetails($mysqli, $employeeId, $month);
    $types = [];
    foreach ($rows as $row) {
        if (!empty($row['type_name'])) {
            $types[$row['type_name']] = true;
        }
    }
    return [
        'summary' => [
            'total_warnings' => count($rows),
            'distinct_type_count' => count($types),
        ],
        'rows' => $rows,
    ];
}
?>
