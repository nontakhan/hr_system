<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

function sendJson($payload) {
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

function sendJsonError($message) {
    sendJson(['status' => 'error', 'message' => $message]);
}

try {
    if (session_status() == PHP_SESSION_NONE) session_start();
    require_once '../includes/db_connect.php';
    require_once '../includes/attendance_helpers.php';
    require_once '../includes/day_swap_helpers.php';
    require_once '../includes/leave_helpers.php';

    if (!isset($_SESSION['user_id'])) {
        sendJsonError('กรุณาเข้าสู่ระบบก่อนใช้งาน');
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');

    if ($method === 'GET') {
        if ($action === 'history') {
            sendJson(['status' => 'success', 'data' => fetchMyTimeRequests($mysqli)]);
        }

        if ($action === 'calculate') {
            $type = normalizeTimeRequestType($_GET['time_request_type'] ?? '');
            $workDate = trim((string)($_GET['work_date'] ?? ''));
            $requestTime = trim((string)($_GET['request_time'] ?? ''));
            $calculation = calculateMyTimeRequest($mysqli, $type, $workDate, $requestTime);
            sendJson(['status' => $calculation['valid'] ? 'success' : 'error', 'message' => $calculation['message'], 'data' => $calculation]);
        }

        sendJsonError('Invalid Action');
    }

    if ($method === 'POST') {
        if ($action === 'submit') {
            submitTimeRequest($mysqli);
        }

        sendJsonError('Invalid Action');
    }

    sendJsonError('Method Not Allowed');
} catch (Throwable $e) {
    error_log($e->getMessage());
    sendJsonError('System Error');
}

function normalizeTimeRequestType($value) {
    return in_array($value, ['late_arrival', 'early_departure'], true) ? $value : '';
}

function timeRequestTypeName($type) {
    return $type === 'early_departure' ? 'ขอออกก่อน' : 'ขอมาสาย';
}

function fetchMyTimeRequests(mysqli $mysqli) {
    leaveEnsureTwoStepApprovalColumns($mysqli);
    $employeeId = (int)($_SESSION['employee_id'] ?? 0);
    $stmt = $mysqli->prepare("SELECT lr.*, lt.type_name
                              FROM leave_requests lr
                              JOIN leave_types lt ON lr.leave_type_id = lt.id
                              WHERE lr.employee_id = ?
                                AND lr.request_unit = 'hour'
                              ORDER BY lr.created_at DESC
                              LIMIT 50");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function submitTimeRequest(mysqli $mysqli) {
    $employeeId = (int)($_SESSION['employee_id'] ?? 0);
    $type = normalizeTimeRequestType($_POST['time_request_type'] ?? '');
    $workDate = trim((string)($_POST['work_date'] ?? ''));
    $requestTime = trim((string)($_POST['request_time'] ?? ''));
    $reason = trim((string)($_POST['reason'] ?? ''));

    if ($type === '' || $workDate === '' || $requestTime === '' || $reason === '') {
        sendJsonError('กรุณากรอกข้อมูลให้ครบถ้วน');
    }

    $calculation = calculateMyTimeRequest($mysqli, $type, $workDate, $requestTime);
    if (!$calculation['valid']) {
        sendJsonError($calculation['message']);
    }

    $mysqli->begin_transaction();
    try {
        leaveEnsureHourlyRequestTypes($mysqli);
        leaveEnsureTwoStepApprovalColumns($mysqli);
        $leaveTypeId = fetchHourlyLeaveTypeId($mysqli, timeRequestTypeName($type));
        if ($leaveTypeId <= 0) {
            throw new RuntimeException('Time request type not found');
        }

        $payload = leaveBuildHourlyRequestPayload($type, (int)$calculation['request_minutes']);
        $requestUnit = $payload['request_unit'];
        $requestType = $payload['time_request_type'];
        $requestMinutes = (int)$payload['request_minutes'];
        $totalDays = 0.0;
        $part = 'full';

        $stmt = $mysqli->prepare("INSERT INTO leave_requests
            (employee_id, leave_type_id, start_date, end_date, start_day_part, end_day_part, request_unit, time_request_type, request_minutes, total_days, reason, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_manager')");
        $stmt->bind_param('iissssssids', $employeeId, $leaveTypeId, $workDate, $workDate, $part, $part, $requestUnit, $requestType, $requestMinutes, $totalDays, $reason);
        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error ?: 'Cannot save time request');
        }

        $mysqli->commit();
        sendJson(['status' => 'success', 'message' => 'ส่งคำขอเรียบร้อยแล้ว รอการอนุมัติ', 'data' => $calculation]);
    } catch (Throwable $e) {
        $mysqli->rollback();
        error_log($e->getMessage());
        sendJsonError('System Error');
    }
}

function fetchHourlyLeaveTypeId(mysqli $mysqli, $typeName) {
    $stmt = $mysqli->prepare("SELECT id FROM leave_types WHERE type_name = ? LIMIT 1");
    $stmt->bind_param('s', $typeName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['id'] ?? 0);
}

function calculateMyTimeRequest(mysqli $mysqli, $type, $workDate, $requestTime) {
    $employeeId = (int)($_SESSION['employee_id'] ?? 0);
    $shift = fetchEffectiveShiftForTimeRequest($mysqli, $employeeId, $workDate);
    if (!$shift) {
        return ['valid' => false, 'message' => 'ไม่พบข้อมูลกะของพนักงาน', 'request_minutes' => 0];
    }

    $calculation = attendanceCalculateTimeRequestMinutes($type, $workDate, $requestTime, $shift);
    $calculation['shift_start_time'] = $shift['start_time'] ?? null;
    $calculation['shift_end_time'] = $shift['end_time'] ?? null;
    return $calculation;
}

function fetchEffectiveShiftForTimeRequest(mysqli $mysqli, $employeeId, $workDate) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$workDate)) {
        return null;
    }

    $stmt = $mysqli->prepare("SELECT ws.start_time, ws.end_time, ws.late_tolerance_mins, ws.work_days
                              FROM employees e
                              LEFT JOIN work_shifts ws ON e.default_shift_id = ws.id
                              WHERE e.id = ?");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $shift = $stmt->get_result()->fetch_assoc();
    if (!$shift) {
        return null;
    }

    $month = substr($workDate, 0, 7);
    $overrides = fetchTimeRequestShiftOverrides($mysqli, $employeeId, $month);
    $effective = attendanceResolveShiftForDate($shift, $overrides, $workDate);
    $swapMap = attendanceBuildApprovedDaySwapMap(daySwapFetchApprovedRowsForMonth($mysqli, $employeeId, $month), $employeeId, $month);
    if (isset($swapMap[$workDate])) {
        $effective = attendanceApplyDayTypeOverride($effective, $workDate, $swapMap[$workDate]);
    }

    return $effective;
}

function fetchTimeRequestShiftOverrides(mysqli $mysqli, $employeeId, $month) {
    $start = $month . '-01';
    $end = (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');
    $stmt = $mysqli->prepare("SELECT day_of_week, start_time, end_time, late_tolerance_mins, effective_from, effective_to
                              FROM employee_shift_overrides
                              WHERE employee_id = ?
                                AND is_active = 1
                                AND effective_from <= ?
                                AND (effective_to IS NULL OR effective_to = '0000-00-00' OR effective_to >= ?)
                              ORDER BY effective_from DESC, id DESC");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('iss', $employeeId, $end, $start);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

?>
