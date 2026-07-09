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
            $typeFilter = normalizeTimeRequestHistoryFilter($_GET['time_request_type'] ?? 'late_early');
            sendJson(['status' => 'success', 'data' => fetchMyTimeRequests($mysqli, $typeFilter)]);
        }

        if ($action === 'calculate') {
            $type = normalizeTimeRequestType($_GET['time_request_type'] ?? '');
            $workDate = normalizeGregorianDateInput($_GET['work_date'] ?? '');
            $requestTime = trim((string)($_GET['request_time'] ?? ''));
            $overtimeStartTime = trim((string)($_GET['overtime_start_time'] ?? ''));
            $overtimeEndTime = trim((string)($_GET['overtime_end_time'] ?? ''));
            $calculation = $type === 'overtime_after_work'
                ? calculateMyOvertimeRequest($mysqli, $workDate, $overtimeStartTime, $overtimeEndTime)
                : calculateMyTimeRequest($mysqli, $type, $workDate, $requestTime);
            sendJson(['status' => $calculation['valid'] ? 'success' : 'error', 'message' => $calculation['message'], 'data' => $calculation]);
        }

        if ($action === 'work_date_context') {
            $workDate = normalizeGregorianDateInput($_GET['work_date'] ?? '');
            $context = fetchMyWorkDateContext($mysqli, $workDate);
            sendJson(['status' => $context['valid'] ? 'success' : 'error', 'message' => $context['message'], 'data' => $context]);
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
    return in_array($value, ['late_arrival', 'early_departure', 'overtime_after_work'], true) ? $value : '';
}

function normalizeTimeRequestHistoryFilter($value) {
    return $value === 'overtime_after_work' ? 'overtime_after_work' : 'late_early';
}

function timeRequestTypeName($type) {
    if ($type === 'overtime_after_work') {
        return 'OT หลังเลิกงาน';
    }
    return $type === 'early_departure' ? 'ขอออกก่อน' : 'ขอมาสาย';
}

function fetchMyTimeRequests(mysqli $mysqli, $typeFilter = 'late_early') {
    leaveEnsureTwoStepApprovalColumns($mysqli);
    $employeeId = (int)($_SESSION['employee_id'] ?? 0);
    $typeSql = $typeFilter === 'overtime_after_work'
        ? " AND lr.time_request_type = 'overtime_after_work'"
        : " AND lr.time_request_type IN ('late_arrival','early_departure')";
    $stmt = $mysqli->prepare("SELECT lr.*, lr.created_via, lr.created_by_role, lr.proxy_note, lt.type_name,
                                     CONCAT_WS(' ', pce.first_name_th, pce.last_name_th) AS proxy_creator_name
                              FROM leave_requests lr
                              JOIN leave_types lt ON lr.leave_type_id = lt.id
                              LEFT JOIN employees pce ON lr.created_by_employee_id = pce.id
                              WHERE lr.employee_id = ?
                                AND lr.request_unit = 'hour'
                                {$typeSql}
                              ORDER BY lr.created_at DESC
                              LIMIT 50");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function submitTimeRequest(mysqli $mysqli) {
    $employeeId = (int)($_SESSION['employee_id'] ?? 0);
    $type = normalizeTimeRequestType($_POST['time_request_type'] ?? '');
    $workDate = normalizeGregorianDateInput($_POST['work_date'] ?? '');
    $requestTime = trim((string)($_POST['request_time'] ?? ''));
    $overtimeStartTime = trim((string)($_POST['overtime_start_time'] ?? ''));
    $overtimeEndTime = trim((string)($_POST['overtime_end_time'] ?? ''));
    $reason = trim((string)($_POST['reason'] ?? ''));

    if ($type === '' || $workDate === '' || $reason === '' || ($type !== 'overtime_after_work' && $requestTime === '') || ($type === 'overtime_after_work' && ($overtimeStartTime === '' || $overtimeEndTime === ''))) {
        sendJsonError('กรุณากรอกข้อมูลให้ครบถ้วน');
    }

    $calculation = $type === 'overtime_after_work'
        ? calculateMyOvertimeRequest($mysqli, $workDate, $overtimeStartTime, $overtimeEndTime)
        : calculateMyTimeRequest($mysqli, $type, $workDate, $requestTime);
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
        $requestStartTime = $type === 'overtime_after_work' ? ($calculation['request_start_time'] ?? null) : null;
        $requestEndTime = $type === 'overtime_after_work' ? ($calculation['request_end_time'] ?? null) : null;
        $totalDays = 0.0;
        $part = 'full';

        $stmt = $mysqli->prepare("INSERT INTO leave_requests
            (employee_id, leave_type_id, start_date, end_date, start_day_part, end_day_part, request_unit, time_request_type, request_minutes, request_start_time, request_end_time, total_days, reason, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_manager')");
        $stmt->bind_param('iissssssissds', $employeeId, $leaveTypeId, $workDate, $workDate, $part, $part, $requestUnit, $requestType, $requestMinutes, $requestStartTime, $requestEndTime, $totalDays, $reason);
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
    $workDate = normalizeGregorianDateInput($workDate);
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

function calculateMyOvertimeRequest(mysqli $mysqli, $workDate, $startTime, $endTime) {
    $workDate = normalizeGregorianDateInput($workDate);
    return attendanceCalculateOvertimeWindowMinutes($workDate, $startTime, $endTime);
}

function fetchMyWorkDateContext(mysqli $mysqli, $workDate) {
    $workDate = normalizeGregorianDateInput($workDate);
    $employeeId = (int)($_SESSION['employee_id'] ?? 0);
    $shift = fetchEffectiveShiftForTimeRequest($mysqli, $employeeId, $workDate);
    if (!$shift) {
        return [
            'valid' => false,
            'message' => 'ไม่พบข้อมูลกะของพนักงาน',
            'day_type' => 'unknown',
            'day_type_label' => 'ไม่พบข้อมูล',
            'shift_start_time' => null,
            'shift_end_time' => null,
            'holiday_name' => null,
        ];
    }

    $holidays = leaveFetchCompanyHolidays($mysqli, $workDate, $workDate);
    return attendanceBuildWorkDateContext($workDate, $shift, $holidays[$workDate] ?? null);
}

function fetchEffectiveShiftForTimeRequest(mysqli $mysqli, $employeeId, $workDate) {
    $workDate = normalizeGregorianDateInput($workDate);
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
