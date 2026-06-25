<?php

function trainingRequestEnsureTable(mysqli $mysqli): void
{
    $mysqli->query("CREATE TABLE IF NOT EXISTS training_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        course_name VARCHAR(255) NOT NULL,
        provider VARCHAR(255) NULL,
        training_type VARCHAR(100) NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        location VARCHAR(255) NULL,
        estimated_cost DECIMAL(10,2) NULL,
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
        INDEX idx_training_requests_status (status),
        INDEX idx_training_requests_dates (start_date, end_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
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
    $provider = trim((string)($request['provider'] ?? ''));
    $trainingType = trim((string)($request['training_type'] ?? ''));
    $attachmentPath = trim((string)($request['attachment_path'] ?? ''));
    $notes = trainingRequestBuildHistoryNotes($request);
    $resultStatus = 'อนุมัติให้เข้าร่วม';
    $certificateExpiryDate = null;

    $stmt = $mysqli->prepare("INSERT INTO employee_training_records
        (employee_id, training_date, course_name, provider, training_type, result_status, certificate_expiry_date, attachment_path, notes, created_by, updated_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        'issssssssii',
        $employeeId,
        $trainingDate,
        $courseName,
        $provider,
        $trainingType,
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
    $lines[] = 'สร้างจากคำขออบรม #' . (int)($request['id'] ?? 0);
    $lines[] = 'ช่วงอบรม: ' . (string)($request['start_date'] ?? '-') . ' ถึง ' . (string)($request['end_date'] ?? '-');

    $location = trim((string)($request['location'] ?? ''));
    if ($location !== '') {
        $lines[] = 'สถานที่/รูปแบบ: ' . $location;
    }

    $cost = $request['estimated_cost'] ?? null;
    if ($cost !== null && $cost !== '' && (float)$cost > 0) {
        $lines[] = 'ค่าใช้จ่ายโดยประมาณ: ' . number_format((float)$cost, 2);
    }

    $objective = trim((string)($request['objective'] ?? ''));
    if ($objective !== '') {
        $lines[] = 'วัตถุประสงค์: ' . $objective;
    }

    return implode("\n", $lines);
}

function trainingRequestApprovalQuery(string $type, string $role, array $scopes): string
{
    $sql = "SELECT tr.*,
                   CONCAT_WS(' ', e.first_name_th, e.last_name_th) AS employee_name,
                   e.citizen_id AS employee_code,
                   e.supervisor_id,
                   CONCAT_WS(' ', ae.first_name_th, ae.last_name_th) AS approver_name
            FROM training_requests tr
            JOIN employees e ON tr.employee_id = e.id
            LEFT JOIN employees ae ON tr.approver_id = ae.id
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
    $sql = "SELECT tr.*, e.supervisor_id
            FROM training_requests tr
            JOIN employees e ON tr.employee_id = e.id
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

function trainingRequestTrim(string $value, int $maxLength): string
{
    $value = trim($value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }
    return substr($value, 0, $maxLength);
}
?>
