<?php

require_once __DIR__ . '/proxy_request_helpers.php';

function trainingRequestEnsureTable(mysqli $mysqli): void
{
    trainingRequestEnsureActivityTypesTable($mysqli);

    $mysqli->query("CREATE TABLE IF NOT EXISTS training_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        activity_type_id INT NULL,
        course_name VARCHAR(255) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        start_day_part ENUM('full','morning','afternoon') NOT NULL DEFAULT 'full',
        end_day_part ENUM('full','morning','afternoon') NOT NULL DEFAULT 'full',
        location VARCHAR(255) NULL,
        objective TEXT NOT NULL,
        attachment_path VARCHAR(255) NULL,
        status ENUM('pending','pending_manager','pending_hr','approved','rejected','cancelled') NOT NULL DEFAULT 'pending_manager',
        manager_approver_id INT NULL,
        manager_approval_date DATETIME NULL,
        hr_approver_id INT NULL,
        hr_approval_date DATETIME NULL,
        approver_id INT NULL,
        approval_date DATETIME NULL,
        rejection_reason TEXT NULL,
        training_record_id INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_training_requests_employee (employee_id),
        INDEX idx_training_requests_activity_type (activity_type_id),
        INDEX idx_training_requests_status (status),
        INDEX idx_training_requests_dates (start_date, end_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    trainingRequestEnsureActivityColumns($mysqli);
    proxyRequestEnsureAuditColumns($mysqli, 'training_requests');
}

function trainingRequestEnsureActivityTypesTable(mysqli $mysqli): void
{
    $mysqli->query("CREATE TABLE IF NOT EXISTS activity_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type_name VARCHAR(255) NOT NULL,
        description TEXT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_activity_types_name (type_name),
        INDEX idx_activity_types_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $result = $mysqli->query("SELECT COUNT(*) AS total FROM activity_types");
    $row = $result ? $result->fetch_assoc() : ['total' => 0];
    if ((int)($row['total'] ?? 0) > 0) {
        return;
    }

    $defaults = [
        ['ไปอบรม', 'กิจกรรมฝึกอบรมหรือพัฒนาความรู้'],
        ['สัมมนา', 'เข้าร่วมสัมมนาหรือประชุมภายนอก'],
        ['งานบุญ', 'กิจกรรมงานบุญหรืองานสังคมขององค์กร'],
    ];
    $stmt = $mysqli->prepare("INSERT INTO activity_types (type_name, description, is_active) VALUES (?, ?, 1)");
    foreach ($defaults as $item) {
        $stmt->bind_param('ss', $item[0], $item[1]);
        $stmt->execute();
    }
}

function trainingRequestEnsureActivityColumns(mysqli $mysqli): void
{
    $columns = [];
    $result = $mysqli->query("SHOW COLUMNS FROM training_requests");
    while ($result && ($row = $result->fetch_assoc())) {
        $columns[$row['Field']] = true;
    }

    if (!isset($columns['activity_type_id'])) {
        $mysqli->query("ALTER TABLE training_requests ADD COLUMN activity_type_id INT NULL AFTER employee_id");
        $mysqli->query("ALTER TABLE training_requests ADD INDEX idx_training_requests_activity_type (activity_type_id)");
    }
    if (!isset($columns['start_day_part'])) {
        $mysqli->query("ALTER TABLE training_requests ADD COLUMN start_day_part ENUM('full','morning','afternoon') NOT NULL DEFAULT 'full' AFTER end_date");
    }
    if (!isset($columns['end_day_part'])) {
        $mysqli->query("ALTER TABLE training_requests ADD COLUMN end_day_part ENUM('full','morning','afternoon') NOT NULL DEFAULT 'full' AFTER start_day_part");
    }
}

function trainingRequestFetchActiveActivityTypes(mysqli $mysqli): array
{
    trainingRequestEnsureActivityTypesTable($mysqli);
    $result = $mysqli->query("SELECT id, type_name, description, is_active
                              FROM activity_types
                              WHERE is_active = 1
                              ORDER BY type_name ASC, id ASC");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function trainingRequestFetchActivityTypes(mysqli $mysqli): array
{
    trainingRequestEnsureActivityTypesTable($mysqli);
    $result = $mysqli->query("SELECT id, type_name, description, is_active, created_at, updated_at
                              FROM activity_types
                              ORDER BY is_active DESC, type_name ASC, id ASC");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function trainingRequestActivityTypeExists(mysqli $mysqli, int $activityTypeId): bool
{
    if ($activityTypeId <= 0) {
        return false;
    }
    trainingRequestEnsureActivityTypesTable($mysqli);
    $stmt = $mysqli->prepare("SELECT id FROM activity_types WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param('i', $activityTypeId);
    $stmt->execute();
    return $stmt->get_result()->num_rows === 1;
}

function trainingRequestCreateHistoryRecord(mysqli $mysqli, array $request, int $approverId): int
{
    if (!function_exists('ensureEmployeeTrainingRecordsTable')) {
        require_once __DIR__ . '/employee_training_helpers.php';
    }
    ensureEmployeeTrainingRecordsTable($mysqli);

    $employeeId = (int)($request['employee_id'] ?? 0);
    $trainingDate = (string)($request['start_date'] ?? date('Y-m-d'));
    $courseName = trim((string)($request['course_name'] ?? ''));
    $attachmentPath = trim((string)($request['attachment_path'] ?? ''));
    $notes = trainingRequestBuildHistoryNotes($request);
    $resultStatus = 'อนุมัติให้เข้าร่วม';
    $certificateExpiryDate = null;

    $stmt = $mysqli->prepare("INSERT INTO employee_training_records
        (employee_id, training_date, course_name, result_status, certificate_expiry_date, attachment_path, notes, created_by, updated_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        'issssssii',
        $employeeId,
        $trainingDate,
        $courseName,
        $resultStatus,
        $certificateExpiryDate,
        $attachmentPath,
        $notes,
        $approverId,
        $approverId
    );
    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error ?: 'Cannot create training history');
    }
    return (int)$stmt->insert_id;
}

function trainingRequestBuildHistoryNotes(array $request): string
{
    $lines = [];
    $activityTypeName = trim((string)($request['activity_type_name'] ?? ''));
    $lines[] = 'สร้างจากคำขอกิจกรรม #' . (int)($request['id'] ?? 0);
    if ($activityTypeName !== '') {
        $lines[] = 'ประเภทกิจกรรม: ' . $activityTypeName;
    }
    $lines[] = 'ช่วงกิจกรรม: ' . trainingRequestFormatDateRangeForNotes($request);

    $location = trim((string)($request['location'] ?? ''));
    if ($location !== '') {
        $lines[] = 'สถานที่/รูปแบบ: ' . $location;
    }

    $objective = trim((string)($request['objective'] ?? ''));
    if ($objective !== '') {
        $lines[] = 'วัตถุประสงค์: ' . $objective;
    }

    return implode("\n", $lines);
}

function trainingRequestFormatDateRangeForNotes(array $request): string
{
    $startDate = (string)($request['start_date'] ?? '-');
    $endDate = (string)($request['end_date'] ?? '-');
    $startPart = trainingRequestDayPartLabel($request['start_day_part'] ?? 'full');
    $endPart = trainingRequestDayPartLabel($request['end_day_part'] ?? 'full');
    if ($startDate === $endDate) {
        return $startPart === $endPart ? "{$startDate} ({$startPart})" : "{$startDate} ({$startPart}-{$endPart})";
    }
    return "{$startDate} ({$startPart}) ถึง {$endDate} ({$endPart})";
}

function trainingRequestDayPartLabel(string $value): string
{
    $value = trainingRequestNormalizeDayPart($value);
    if ($value === 'morning') {
        return 'ครึ่งวันเช้า';
    }
    if ($value === 'afternoon') {
        return 'ครึ่งวันบ่าย';
    }
    return 'เต็มวัน';
}

function trainingRequestApprovalQuery(string $type, string $role, array $scopes): string
{
    $sql = "SELECT tr.*,
                   CONCAT_WS(' ', e.first_name_th, e.last_name_th) AS employee_name,
                   e.citizen_id AS employee_code,
                   e.profile_img_url AS employee_profile_img_url,
                   e.supervisor_id,
                   CONCAT_WS(' ', ae.first_name_th, ae.last_name_th) AS approver_name,
                   CONCAT_WS(' ', pce.first_name_th, pce.last_name_th) AS proxy_creator_name,
                   at.type_name AS activity_type_name
            FROM training_requests tr
            JOIN employees e ON tr.employee_id = e.id
            LEFT JOIN activity_types at ON tr.activity_type_id = at.id
            LEFT JOIN employees ae ON tr.approver_id = ae.id
            LEFT JOIN employees pce ON tr.created_by_employee_id = pce.id
            WHERE 1=1";

    if ($role === 'hr') {
        $scopeClause = hrScopeBuildEmployeeWhereClause($role, $scopes, 'e');
        $sql .= $scopeClause['sql'];
    } elseif ($role !== 'admin') {
        $sql .= " AND e.supervisor_id = ?";
    }

    if ($type === 'pending') {
        if ($role === 'hr') {
            $sql .= " AND tr.status = 'pending_hr'";
        } elseif ($role === 'admin') {
            $sql .= " AND tr.status IN ('pending','pending_manager','pending_hr')";
        } else {
            $sql .= " AND tr.status IN ('pending','pending_manager')";
        }
    } else {
        $sql .= " AND tr.status IN ('approved','rejected')";
    }

    $sql .= " ORDER BY tr.created_at DESC";
    return $sql;
}

function trainingRequestFetchApprovableRequest(mysqli $mysqli, int $requestId, string $role, int $employeeId, array $scopes): ?array
{
    $sql = "SELECT tr.*, e.supervisor_id,
                   at.type_name AS activity_type_name
            FROM training_requests tr
            JOIN employees e ON tr.employee_id = e.id
            LEFT JOIN activity_types at ON tr.activity_type_id = at.id
            WHERE tr.id = ?";
    $types = 'i';
    $params = [$requestId];

    if ($role === 'hr') {
        $scopeClause = hrScopeBuildEmployeeWhereClause($role, $scopes, 'e');
        $sql .= $scopeClause['sql'];
        $types .= $scopeClause['types'];
        $params = array_merge($params, $scopeClause['params']);
    } elseif ($role !== 'admin') {
        $sql .= " AND e.supervisor_id = ?";
        $types .= 'i';
        $params[] = $employeeId;
    }

    $stmt = $mysqli->prepare($sql);
    hrScopeBindParams($stmt, $types, $params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function trainingRequestNormalizeDate(string $value, string $message): string
{
    $value = trim($value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        throw new InvalidArgumentException($message);
    }
    return $value;
}

function trainingRequestNormalizeDayPart(string $value): string
{
    $value = trim($value);
    return in_array($value, ['full', 'morning', 'afternoon'], true) ? $value : 'full';
}

function trainingRequestTrim(string $value, int $maxLength): string
{
    $value = trim($value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }
    return substr($value, 0, $maxLength);
}
?>
