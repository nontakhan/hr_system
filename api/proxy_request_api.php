<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

const PROXY_REQUEST_CREATED_VIA = 'admin_proxy';

function proxyRequestJson($payload) {
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

function proxyRequestError($message) {
    proxyRequestJson(['status' => 'error', 'message' => $message]);
}

try {
    if (session_status() === PHP_SESSION_NONE) session_start();
    require_once '../includes/db_connect.php';
    require_once '../includes/proxy_request_helpers.php';
    require_once '../includes/hr_scope_helpers.php';
    require_once '../includes/leave_helpers.php';
    require_once '../includes/day_swap_helpers.php';
    require_once '../includes/training_request_helpers.php';
    require_once '../includes/attendance_helpers.php';
    require_once '../includes/upload_security.php';

    proxyRequestRequireAccess();
    leaveEnsureTwoStepApprovalColumns($mysqli);
    leaveEnsureHourlyRequestTypes($mysqli);
    daySwapEnsureTable($mysqli);
    trainingRequestEnsureTable($mysqli);

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');

    if ($method === 'GET') {
        if ($action === 'employees') proxyRequestJson(['status' => 'success', 'data' => proxyRequestFetchEmployees($mysqli)]);
        if ($action === 'leave_types') proxyRequestJson(['status' => 'success', 'data' => proxyRequestFetchLeaveTypes($mysqli)]);
        if ($action === 'day_swap_holidays') proxyRequestJson(['status' => 'success', 'data' => proxyRequestFetchDaySwapHolidays($mysqli)]);
        if ($action === 'calculate_leave') proxyRequestJson(['status' => 'success', 'data' => proxyRequestCalculateLeave($mysqli)]);
        if ($action === 'calculate_time_request') proxyRequestJson(['status' => 'success', 'data' => proxyRequestCalculateTimeRequest($mysqli)]);
        proxyRequestError('Invalid Action');
    }

    if ($method === 'POST') {
        if ($action === 'create_leave') proxyRequestCreateLeave($mysqli);
        if ($action === 'create_late_early') proxyRequestCreateTimeRequest($mysqli, false);
        if ($action === 'create_overtime') proxyRequestCreateTimeRequest($mysqli, true);
        if ($action === 'create_day_swap') proxyRequestCreateDaySwap($mysqli);
        if ($action === 'create_training') proxyRequestCreateTraining($mysqli);
        proxyRequestError('Invalid Action');
    }

    proxyRequestError('Method Not Allowed');
} catch (Throwable $e) {
    error_log($e->getMessage());
    proxyRequestError($e instanceof InvalidArgumentException ? $e->getMessage() : 'System Error');
}

function proxyRequestCurrentRole() {
    return (string)($_SESSION['role'] ?? '');
}

function proxyRequestCurrentEmployeeId() {
    return (int)($_SESSION['employee_id'] ?? 0);
}

function proxyRequestCanAccessEmployee(mysqli $mysqli, int $employeeId): bool {
    $role = proxyRequestCurrentRole();
    if ($role === 'admin') {
        $stmt = $mysqli->prepare("SELECT id FROM employees WHERE id = ? AND status IN ('active', 'probation')");
        $stmt->bind_param('i', $employeeId);
        $stmt->execute();
        return $stmt->get_result()->num_rows === 1;
    }

    $sql = "SELECT e.id FROM employees e WHERE e.id = ? AND e.status IN ('active', 'probation')";
    $types = 'i';
    $params = [$employeeId];
    $scopeClause = hrScopeBuildEmployeeWhereClause($role, hrScopeCurrentSessionScopes(), 'e');
    $sql .= $scopeClause['sql'];
    $types .= $scopeClause['types'];
    $params = array_merge($params, $scopeClause['params']);

    $stmt = $mysqli->prepare($sql);
    hrScopeBindParams($stmt, $types, $params);
    $stmt->execute();
    return $stmt->get_result()->num_rows === 1;
}

function proxyRequestRequireEmployee(mysqli $mysqli, int $employeeId): void {
    if ($employeeId <= 0 || !proxyRequestCanAccessEmployee($mysqli, $employeeId)) {
        throw new InvalidArgumentException('Access Denied');
    }
}

function proxyRequestFetchEmployees(mysqli $mysqli): array {
    $role = proxyRequestCurrentRole();
    $sql = "SELECT e.id, e.citizen_id, e.first_name_th, e.last_name_th
            FROM employees e
            WHERE e.status IN ('active', 'probation')";
    $types = '';
    $params = [];
    if ($role === 'hr') {
        $scopeClause = hrScopeBuildEmployeeWhereClause($role, hrScopeCurrentSessionScopes(), 'e');
        $sql .= $scopeClause['sql'];
        $types .= $scopeClause['types'];
        $params = array_merge($params, $scopeClause['params']);
    }
    $sql .= " ORDER BY e.first_name_th, e.last_name_th";
    $stmt = $mysqli->prepare($sql);
    if ($types !== '') {
        hrScopeBindParams($stmt, $types, $params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function proxyRequestFetchLeaveTypes(mysqli $mysqli): array {
    leaveEnsureHourlyRequestTypes($mysqli);
    leaveEnsureLeaveTypeCalculationColumns($mysqli);
    $result = $mysqli->query("SELECT id, type_name, days_per_year, requires_file, calculation_unit, hours_per_day, hour_full_day_threshold, vacation_min_months_before_leave
                              FROM leave_types
                              ORDER BY id ASC");
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    return array_values(array_filter($rows, function ($row) {
        return leaveDetectHourlyRequestType($row['type_name'] ?? '') === null
            && ($row['time_request_type'] ?? '') !== 'overtime_after_work';
    }));
}

function proxyRequestFetchDaySwapHolidays(mysqli $mysqli): array {
    $employeeId = (int)($_GET['employee_id'] ?? 0);
    $month = trim((string)($_GET['month'] ?? date('Y-m')));
    proxyRequestRequireEmployee($mysqli, $employeeId);
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        throw new InvalidArgumentException('Invalid month');
    }
    return daySwapBuildHolidayOptions($mysqli, $employeeId, $month);
}

function proxyRequestCalculateLeave(mysqli $mysqli): array {
    $employeeId = (int)($_GET['employee_id'] ?? 0);
    proxyRequestRequireEmployee($mysqli, $employeeId);
    $start = trim((string)($_GET['start_date'] ?? ''));
    $end = trim((string)($_GET['end_date'] ?? ''));
    return leaveBuildDateSummary(
        $start,
        $end,
        $_GET['start_day_part'] ?? 'full',
        $_GET['end_day_part'] ?? 'full',
        leaveFetchEmployeeWorkDays($mysqli, $employeeId),
        leaveFetchCompanyHolidays($mysqli, $start, $end)
    );
}

function proxyRequestCalculateTimeRequest(mysqli $mysqli): array {
    $employeeId = (int)($_GET['employee_id'] ?? 0);
    proxyRequestRequireEmployee($mysqli, $employeeId);
    return proxyRequestCalculateTimeRequestFromValues(
        $mysqli,
        $employeeId,
        proxyRequestNormalizeTimeType($_GET['time_request_type'] ?? ''),
        trim((string)($_GET['work_date'] ?? '')),
        trim((string)($_GET['request_time'] ?? '')),
        (int)($_GET['overtime_minutes'] ?? 0)
    );
}

function proxyRequestNormalizeTimeType($value) {
    return in_array($value, ['late_arrival', 'early_departure', 'overtime_after_work'], true) ? $value : '';
}

function proxyRequestTimeTypeName($type) {
    if ($type === 'overtime_after_work') return 'OT หลังเลิกงาน';
    return $type === 'early_departure' ? 'ขอออกก่อน' : 'ขอมาสาย';
}

function proxyRequestFetchEffectiveShift(mysqli $mysqli, int $employeeId, string $workDate): ?array {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) return null;
    $stmt = $mysqli->prepare("SELECT ws.start_time, ws.end_time, ws.late_tolerance_mins, ws.work_days
                              FROM employees e
                              LEFT JOIN work_shifts ws ON e.default_shift_id = ws.id
                              WHERE e.id = ?");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $shift = $stmt->get_result()->fetch_assoc();
    if (!$shift) return null;
    $month = substr($workDate, 0, 7);
    $overrides = daySwapFetchShiftOverridesForMonth($mysqli, $employeeId, $month);
    $effective = attendanceResolveShiftForDate($shift, $overrides, $workDate);
    $swapMap = attendanceBuildApprovedDaySwapMap(daySwapFetchApprovedRowsForMonth($mysqli, $employeeId, $month), $employeeId, $month);
    return isset($swapMap[$workDate]) ? attendanceApplyDayTypeOverride($effective, $workDate, $swapMap[$workDate]) : $effective;
}

function proxyRequestCalculateTimeRequestFromValues(mysqli $mysqli, int $employeeId, string $type, string $workDate, string $requestTime, int $overtimeMinutes): array {
    $shift = proxyRequestFetchEffectiveShift($mysqli, $employeeId, $workDate);
    if (!$shift) return ['valid' => false, 'message' => 'ไม่พบข้อมูลกะของพนักงาน', 'request_minutes' => 0];
    if ($type === 'overtime_after_work') {
        if ($overtimeMinutes < 1 || $overtimeMinutes > 480) return ['valid' => false, 'message' => 'จำนวน OT ต้องอยู่ระหว่าง 1-480 นาที', 'request_minutes' => $overtimeMinutes];
        return ['valid' => true, 'message' => '', 'request_minutes' => $overtimeMinutes, 'shift_start_time' => $shift['start_time'] ?? null, 'shift_end_time' => $shift['end_time'] ?? null];
    }
    $calculation = attendanceCalculateTimeRequestMinutes($type, $workDate, $requestTime, $shift);
    $calculation['shift_start_time'] = $shift['start_time'] ?? null;
    $calculation['shift_end_time'] = $shift['end_time'] ?? null;
    return $calculation;
}

function proxyRequestFetchHourlyLeaveTypeId(mysqli $mysqli, string $typeName): int {
    $stmt = $mysqli->prepare("SELECT id FROM leave_types WHERE type_name = ? LIMIT 1");
    $stmt->bind_param('s', $typeName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['id'] ?? 0);
}

function proxyRequestCreateLeave(mysqli $mysqli): void {
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    proxyRequestRequireEmployee($mysqli, $employeeId);
    $typeId = (int)($_POST['leave_type_id'] ?? 0);
    $start = trim((string)($_POST['start_date'] ?? ''));
    $end = trim((string)($_POST['end_date'] ?? ''));
    $startPart = leaveNormalizeDayPart($_POST['start_day_part'] ?? 'full');
    $endPart = leaveNormalizeDayPart($_POST['end_day_part'] ?? 'full');
    $reason = trim((string)($_POST['reason'] ?? ''));
    if ($typeId <= 0 || $start === '' || $end === '' || $reason === '') {
        throw new InvalidArgumentException('กรุณากรอกข้อมูลให้ครบถ้วน');
    }
    if ($end < $start) {
        throw new InvalidArgumentException('วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่ม');
    }

    $summary = leaveBuildDateSummary($start, $end, $startPart, $endPart, leaveFetchEmployeeWorkDays($mysqli, $employeeId), leaveFetchCompanyHolidays($mysqli, $start, $end));
    if (!$summary['valid']) {
        throw new InvalidArgumentException($summary['message']);
    }
    $requestedDates = array_column($summary['included_dates'] ?? [], 'date');
    $conflicts = leaveFetchConflictingLeaveDates($mysqli, $employeeId, $start, $end, $requestedDates);
    if ($conflicts) {
        throw new InvalidArgumentException('มีใบลาในวันที่เลือกอยู่แล้ว: ' . implode(', ', $conflicts));
    }

    $audit = proxyRequestBuildAuditPayload($_POST['proxy_note'] ?? '');
    $now = date('Y-m-d H:i:s');
    $totalDays = (float)$summary['total_days'];
    $requestUnit = 'day';
    $timeType = null;
    $requestMinutes = 0;
    $approverEmployeeId = proxyRequestCurrentEmployeeId() ?: null;

    $mysqli->begin_transaction();
    try {
        $stmt = $mysqli->prepare("INSERT INTO leave_requests
            (employee_id, leave_type_id, start_date, end_date, start_day_part, end_day_part, request_unit, time_request_type, request_minutes, total_days, reason, status, approver_id, approval_date, manager_approver_id, manager_approval_date, hr_approver_id, hr_approval_date, created_by_user_id, created_by_employee_id, created_by_role, created_via, proxy_note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iissssssidsisisisiisss', $employeeId, $typeId, $start, $end, $startPart, $endPart, $requestUnit, $timeType, $requestMinutes, $totalDays, $reason, $approverEmployeeId, $now, $approverEmployeeId, $now, $approverEmployeeId, $now, $audit['created_by_user_id'], $audit['created_by_employee_id'], $audit['created_by_role'], $audit['created_via'], $audit['proxy_note']);
        if (!$stmt->execute()) throw new RuntimeException($stmt->error ?: 'Cannot save proxy leave request');
        $mysqli->commit();
        proxyRequestJson(['status' => 'success', 'message' => 'บันทึกและอนุมัติรายการเรียบร้อยแล้ว', 'data' => $summary]);
    } catch (Throwable $e) {
        $mysqli->rollback();
        throw $e;
    }
}

function proxyRequestCreateTimeRequest(mysqli $mysqli, bool $isOvertime): void {
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    proxyRequestRequireEmployee($mysqli, $employeeId);
    $type = proxyRequestNormalizeTimeType($_POST['time_request_type'] ?? '');
    if ($isOvertime) $type = 'overtime_after_work';
    $workDate = trim((string)($_POST['work_date'] ?? ''));
    $requestTime = trim((string)($_POST['request_time'] ?? ''));
    $overtimeMinutes = (int)($_POST['overtime_minutes'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? ''));
    if ($type === '' || $workDate === '' || $reason === '' || (!$isOvertime && $requestTime === '')) {
        throw new InvalidArgumentException('กรุณากรอกข้อมูลให้ครบถ้วน');
    }
    $calc = proxyRequestCalculateTimeRequestFromValues($mysqli, $employeeId, $type, $workDate, $requestTime, $overtimeMinutes);
    if (!$calc['valid']) throw new InvalidArgumentException($calc['message']);
    $leaveTypeId = proxyRequestFetchHourlyLeaveTypeId($mysqli, proxyRequestTimeTypeName($type));
    if ($leaveTypeId <= 0) throw new RuntimeException('Time request type not found');

    $payload = leaveBuildHourlyRequestPayload($type, (int)$calc['request_minutes']);
    $audit = proxyRequestBuildAuditPayload($_POST['proxy_note'] ?? '');
    $now = date('Y-m-d H:i:s');
    $part = 'full';
    $totalDays = 0.0;
    $approverEmployeeId = proxyRequestCurrentEmployeeId() ?: null;

    $stmt = $mysqli->prepare("INSERT INTO leave_requests
        (employee_id, leave_type_id, start_date, end_date, start_day_part, end_day_part, request_unit, time_request_type, request_minutes, approved_request_minutes, total_days, reason, status, approver_id, approval_date, manager_approver_id, manager_approval_date, hr_approver_id, hr_approval_date, created_by_user_id, created_by_employee_id, created_by_role, created_via, proxy_note)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iissssssiidsisisisiisss', $employeeId, $leaveTypeId, $workDate, $workDate, $part, $part, $payload['request_unit'], $payload['time_request_type'], $payload['request_minutes'], $payload['request_minutes'], $totalDays, $reason, $approverEmployeeId, $now, $approverEmployeeId, $now, $approverEmployeeId, $now, $audit['created_by_user_id'], $audit['created_by_employee_id'], $audit['created_by_role'], $audit['created_via'], $audit['proxy_note']);
    if (!$stmt->execute()) throw new RuntimeException($stmt->error ?: 'Cannot save proxy time request');
    proxyRequestJson(['status' => 'success', 'message' => 'บันทึกและอนุมัติรายการเรียบร้อยแล้ว', 'data' => $calc]);
}

function proxyRequestCreateDaySwap(mysqli $mysqli): void {
    $requesterId = (int)($_POST['requester_employee_id'] ?? $_POST['employee_id'] ?? 0);
    $targetId = (int)($_POST['target_employee_id'] ?? 0);
    proxyRequestRequireEmployee($mysqli, $requesterId);
    proxyRequestRequireEmployee($mysqli, $targetId);
    $requesterDate = trim((string)($_POST['requester_date'] ?? ''));
    $targetDate = trim((string)($_POST['target_date'] ?? ''));
    $reason = trim((string)($_POST['reason'] ?? ''));
    if ($targetId <= 0 || $targetId === $requesterId || $reason === '') throw new InvalidArgumentException('กรุณากรอกข้อมูลให้ครบถ้วน');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $requesterDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) throw new InvalidArgumentException('กรุณาเลือกวันที่ให้ครบ');
    if (!daySwapDateIsSelectableHoliday($mysqli, $requesterId, $requesterDate, substr($requesterDate, 0, 7))) throw new InvalidArgumentException('วันที่ของพนักงานไม่ใช่วันหยุดที่เลือกได้');
    if (!daySwapDateIsSelectableHoliday($mysqli, $targetId, $targetDate, substr($targetDate, 0, 7))) throw new InvalidArgumentException('วันที่ของพนักงานคู่สลับไม่ใช่วันหยุดที่เลือกได้');
    if (daySwapHasPendingOrApprovedConflict($mysqli, $requesterId, $targetId, $requesterDate, $targetDate)) throw new InvalidArgumentException('มีคำขอสลับวันหยุดของวันที่เลือกอยู่แล้ว');

    $audit = proxyRequestBuildAuditPayload($_POST['proxy_note'] ?? '');
    $now = date('Y-m-d H:i:s');
    $approverEmployeeId = proxyRequestCurrentEmployeeId() ?: null;
    $stmt = $mysqli->prepare("INSERT INTO day_swap_requests
        (requester_employee_id, target_employee_id, requester_date, target_date, reason, status, approver_id, approval_date, manager_approver_id, manager_approval_date, hr_approver_id, hr_approval_date, created_by_user_id, created_by_employee_id, created_by_role, created_via, proxy_note)
        VALUES (?, ?, ?, ?, ?, 'approved', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iisssisisisiisss', $requesterId, $targetId, $requesterDate, $targetDate, $reason, $approverEmployeeId, $now, $approverEmployeeId, $now, $approverEmployeeId, $now, $audit['created_by_user_id'], $audit['created_by_employee_id'], $audit['created_by_role'], $audit['created_via'], $audit['proxy_note']);
    if (!$stmt->execute()) throw new RuntimeException($stmt->error ?: 'Cannot save proxy day swap request');
    proxyRequestJson(['status' => 'success', 'message' => 'บันทึกและอนุมัติรายการเรียบร้อยแล้ว']);
}

function proxyRequestCreateTraining(mysqli $mysqli): void {
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    proxyRequestRequireEmployee($mysqli, $employeeId);
    $courseName = trainingRequestTrim((string)($_POST['course_name'] ?? ''), 255);
    $provider = trainingRequestTrim((string)($_POST['provider'] ?? ''), 255);
    $trainingType = trainingRequestTrim((string)($_POST['training_type'] ?? ''), 100);
    $startDate = trainingRequestNormalizeDate((string)($_POST['start_date'] ?? ''), 'กรุณาระบุวันที่เริ่มอบรม');
    $endDate = trainingRequestNormalizeDate((string)($_POST['end_date'] ?? ''), 'กรุณาระบุวันที่สิ้นสุดอบรม');
    $location = trainingRequestTrim((string)($_POST['location'] ?? ''), 255);
    $objective = trim((string)($_POST['objective'] ?? ''));
    $estimatedCost = trim((string)($_POST['estimated_cost'] ?? ''));
    if ($courseName === '' || $objective === '') throw new InvalidArgumentException('กรุณากรอกข้อมูลให้ครบถ้วน');
    if ($endDate < $startDate) throw new InvalidArgumentException('วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มอบรม');
    $cost = $estimatedCost === '' ? null : max(0, (float)$estimatedCost);
    $attachmentPath = '';
    if (isset($_FILES['attachment']) && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $attachmentPath = saveEmployeeTrainingAttachment($_FILES['attachment'], $employeeId);
    }

    $audit = proxyRequestBuildAuditPayload($_POST['proxy_note'] ?? '');
    $now = date('Y-m-d H:i:s');
    $approverEmployeeId = proxyRequestCurrentEmployeeId() ?: null;
    $mysqli->begin_transaction();
    try {
        $stmt = $mysqli->prepare("INSERT INTO training_requests
            (employee_id, course_name, provider, training_type, start_date, end_date, location, estimated_cost, objective, attachment_path, status, manager_approver_id, manager_approval_date, hr_approver_id, hr_approval_date, approver_id, approval_date, created_by_user_id, created_by_employee_id, created_by_role, created_via, proxy_note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('issssssdssisisiisssss', $employeeId, $courseName, $provider, $trainingType, $startDate, $endDate, $location, $cost, $objective, $attachmentPath, $approverEmployeeId, $now, $approverEmployeeId, $now, $approverEmployeeId, $now, $audit['created_by_user_id'], $audit['created_by_employee_id'], $audit['created_by_role'], $audit['created_via'], $audit['proxy_note']);
        if (!$stmt->execute()) throw new RuntimeException($stmt->error ?: 'Cannot save proxy training request');
        $requestId = (int)$stmt->insert_id;
        $request = [
            'id' => $requestId,
            'employee_id' => $employeeId,
            'course_name' => $courseName,
            'provider' => $provider,
            'training_type' => $trainingType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'location' => $location,
            'estimated_cost' => $cost,
            'objective' => $objective,
            'attachment_path' => $attachmentPath,
        ];
        $recordId = trainingRequestCreateHistoryRecord($mysqli, $request, (int)$approverEmployeeId);
        $update = $mysqli->prepare("UPDATE training_requests SET training_record_id = ? WHERE id = ?");
        $update->bind_param('ii', $recordId, $requestId);
        $update->execute();
        $mysqli->commit();
        proxyRequestJson(['status' => 'success', 'message' => 'บันทึกและอนุมัติรายการเรียบร้อยแล้ว']);
    } catch (Throwable $e) {
        $mysqli->rollback();
        throw $e;
    }
}
