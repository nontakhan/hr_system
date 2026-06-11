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
    require_once '../includes/leave_helpers.php';

    if (!isset($_SESSION['user_id'])) sendJsonError('Login Required');

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');
    $role = $_SESSION['role'];
    $canManage = in_array($role, ['admin', 'hr'], true);

    if ($method === 'GET') {
        if ($action === 'employees') {
            if (!$canManage) sendJsonError('Access Denied');
            $companyId = (int)($_SESSION['company_id'] ?? 0);
            $sql = "SELECT id, citizen_id, first_name_th, last_name_th
                    FROM employees
                    WHERE status IN ('active', 'probation')";
            if ($role === 'hr') {
                $sql .= " AND company_id = ?";
                $sql .= " ORDER BY first_name_th, last_name_th";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param('i', $companyId);
            } else {
                $sql .= " ORDER BY first_name_th, last_name_th";
                $stmt = $mysqli->prepare($sql);
            }
            $stmt->execute();
            sendJson(['status' => 'success', 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        }

        if ($action === 'months') {
            $employeeId = resolveAttendanceEmployeeId($canManage);
            $stmt = $mysqli->prepare("SELECT DISTINCT import_month FROM attendance_records WHERE employee_id = ? ORDER BY import_month DESC");
            $stmt->bind_param('i', $employeeId);
            $stmt->execute();
            sendJson(['status' => 'success', 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        }

        if ($action === 'report') {
            $employeeId = resolveAttendanceEmployeeId($canManage);
            $month = $_GET['month'] ?? date('Y-m');
            if (!preg_match('/^\d{4}-\d{2}$/', $month)) sendJsonError('Invalid month');

            $employee = fetchAttendanceEmployee($mysqli, $employeeId);
            if (!$employee) sendJsonError('Employee not found');

            $rows = buildMonthlyAttendanceReport($mysqli, $employee, $month);
            sendJson(['status' => 'success', 'employee' => $employee, 'month' => $month, 'data' => $rows]);
        }

        if ($action === 'import_summary') {
            if (!$canManage) sendJsonError('Access Denied');
            sendJson(['status' => 'success', 'data' => fetchAttendanceImportSummary($mysqli)]);
        }

        sendJsonError('Invalid Action');
    }

    if ($method === 'POST') {
        if ($action !== 'import') sendJsonError('Invalid Action');
        if (!$canManage) sendJsonError('Access Denied');

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            sendJsonError('กรุณาเลือกไฟล์ CSV');
        }

        $result = importAttendanceCsv($mysqli, $_FILES['csv_file']['tmp_name'], $_FILES['csv_file']['name']);
        sendJson(['status' => 'success'] + $result);
    }

    sendJsonError('Method Not Allowed');
} catch (Throwable $e) {
    error_log($e->getMessage());
    sendJsonError('System Error');
}

function resolveAttendanceEmployeeId($canManage) {
    if ($canManage && isset($_GET['employee_id']) && (int)$_GET['employee_id'] > 0) {
        return (int)$_GET['employee_id'];
    }
    return (int)($_SESSION['employee_id'] ?? 0);
}

function fetchAttendanceEmployee($mysqli, $employeeId) {
    $stmt = $mysqli->prepare("SELECT e.id, e.citizen_id, e.first_name_th, e.last_name_th,
                                     ws.start_time, ws.end_time, ws.late_tolerance_mins, ws.work_days
                              FROM employees e
                              LEFT JOIN work_shifts ws ON e.default_shift_id = ws.id
                              WHERE e.id = ?");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function importAttendanceCsv($mysqli, $filePath, $sourceFile) {
    $rows = attendanceReadCsvRows($filePath);
    $inserted = 0;
    $skipped = 0;
    $unmatched = 0;

    $employeeStmt = $mysqli->prepare("SELECT id FROM employees WHERE citizen_id = ? LIMIT 1");
    $insertStmt = $mysqli->prepare("INSERT IGNORE INTO attendance_records
        (employee_id, citizen_id, work_date, check_in, check_out, import_month, source_file)
        VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($rows as $row) {
        $importMonth = attendanceImportMonthFromWorkDate($row['work_date']);
        if ($importMonth === null) {
            $skipped++;
            continue;
        }

        $employeeStmt->bind_param('s', $row['citizen_id']);
        $employeeStmt->execute();
        $employee = $employeeStmt->get_result()->fetch_assoc();
        if (!$employee) {
            $unmatched++;
            continue;
        }

        $employeeId = (int)$employee['id'];
        $insertStmt->bind_param(
            'issssss',
            $employeeId,
            $row['citizen_id'],
            $row['work_date'],
            $row['check_in'],
            $row['check_out'],
            $importMonth,
            $sourceFile
        );
        $insertStmt->execute();
        if ($insertStmt->affected_rows > 0) {
            $inserted++;
        } else {
            $skipped++;
        }
    }

    return [
        'message' => 'นำเข้าไฟล์สำเร็จ',
        'inserted' => $inserted,
        'skipped' => $skipped,
        'unmatched' => $unmatched,
    ];
}

function buildMonthlyAttendanceReport($mysqli, array $employee, $month) {
    $start = new DateTimeImmutable($month . '-01');
    $end = $start->modify('last day of this month');
    $records = [];

    $stmt = $mysqli->prepare("SELECT work_date, check_in, check_out FROM attendance_records WHERE employee_id = ? AND import_month = ?");
    $stmt->bind_param('is', $employee['id'], $month);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $records[$row['work_date']] = $row;
    }

    $shift = [
        'start_time' => $employee['start_time'],
        'end_time' => $employee['end_time'],
        'late_tolerance_mins' => $employee['late_tolerance_mins'],
        'work_days' => $employee['work_days'],
    ];
    $shiftOverrides = fetchEmployeeShiftOverridesForMonth($mysqli, (int)$employee['id'], $month);
    $holidays = fetchCompanyHolidaysForMonth($mysqli, $month);
    $leaves = fetchApprovedLeavesForMonth($mysqli, (int)$employee['id'], $month);
    $hourlyRequests = fetchApprovedHourlyRequestsForMonth($mysqli, (int)$employee['id'], $month);

    $rows = [];
    for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
        $workDate = $date->format('Y-m-d');
        $record = $records[$workDate] ?? ['check_in' => null, 'check_out' => null];
        $effectiveShift = attendanceResolveShiftForDate($shift, $shiftOverrides, $workDate);
        $status = attendanceEvaluateStatus($workDate, $record['check_in'], $record['check_out'], $effectiveShift, $holidays, $leaves);
        $rows[] = [
            'work_date' => $workDate,
            'day_name' => $date->format('D'),
            'check_in' => $record['check_in'],
            'check_out' => $record['check_out'],
            'status' => $status['status'],
            'status_label' => $status['label'],
            'is_late' => $status['is_late'],
            'holiday_name' => $status['holiday_name'],
            'leave_name' => $status['leave_name'],
            'hourly_requests' => $hourlyRequests[$workDate] ?? [],
        ];
    }

    return $rows;
}

function fetchEmployeeShiftOverridesForMonth($mysqli, $employeeId, $month) {
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

function fetchApprovedLeavesForMonth($mysqli, $employeeId, $month) {
    $start = $month . '-01';
    $end = (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');
    leaveEnsureRequestPartColumns($mysqli);
    $stmt = $mysqli->prepare("SELECT lr.start_date, lr.end_date, lt.type_name
                              FROM leave_requests lr
                              JOIN leave_types lt ON lr.leave_type_id = lt.id
                              WHERE lr.employee_id = ?
                                AND lr.status = 'approved'
                                AND lr.request_unit = 'day'
                                AND lr.start_date <= ?
                                AND lr.end_date >= ?
                              ORDER BY lr.start_date");
    $stmt->bind_param('iss', $employeeId, $end, $start);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    return attendanceBuildApprovedLeaveMap($rows, $month);
}

function fetchApprovedHourlyRequestsForMonth($mysqli, $employeeId, $month) {
    leaveEnsureRequestPartColumns($mysqli);
    $start = $month . '-01';
    $end = (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');
    $stmt = $mysqli->prepare("SELECT lr.start_date, lr.request_unit, lr.time_request_type, lr.request_minutes
                              FROM leave_requests lr
                              WHERE lr.employee_id = ?
                                AND lr.status = 'approved'
                                AND lr.request_unit = 'hour'
                                AND lr.start_date BETWEEN ? AND ?
                              ORDER BY lr.start_date, lr.id");
    $stmt->bind_param('iss', $employeeId, $start, $end);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    return attendanceBuildApprovedHourlyRequestMap($rows, $month);
}

function fetchAttendanceImportSummary($mysqli) {
    $currentMonth = date('Y-m');
    $oldestMonth = (new DateTimeImmutable($currentMonth . '-01'))->modify('-5 months')->format('Y-m');
    $stmt = $mysqli->prepare("SELECT import_month,
                                     COUNT(*) AS record_count,
                                     COUNT(DISTINCT employee_id) AS employee_count,
                                     MAX(work_date) AS latest_work_date
                              FROM attendance_records
                              WHERE import_month BETWEEN ? AND ?
                              GROUP BY import_month
                              ORDER BY import_month DESC");
    $stmt->bind_param('ss', $oldestMonth, $currentMonth);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    return attendanceBuildImportSummaryMonths($rows, date('Y-m-d'), 6);
}

function fetchCompanyHolidaysForMonth($mysqli, $month) {
    $start = $month . '-01';
    $end = (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');
    $stmt = $mysqli->prepare("SELECT holiday_date, holiday_name FROM company_holidays WHERE holiday_date BETWEEN ? AND ? ORDER BY holiday_date");
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $res = $stmt->get_result();

    $holidays = [];
    while ($row = $res->fetch_assoc()) {
        $holidays[$row['holiday_date']] = $row['holiday_name'];
    }
    return $holidays;
}
