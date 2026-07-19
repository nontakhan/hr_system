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
    require_once '../includes/day_swap_helpers.php';
    require_once '../includes/hr_scope_helpers.php';
    require_once '../includes/employee_shift_assignment_helpers.php';
    require_once '../includes/training_request_helpers.php';
    require_once '../includes/employee_warning_helpers.php';
    require_once '../includes/employee_request_attendance_report_helpers.php';

    if (!isset($_SESSION['user_id'])) sendJsonError('Login Required');

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');
    $role = $_SESSION['role'];
    $canManage = in_array($role, ['admin', 'hr'], true);

    if ($method === 'GET') {
        if ($action === 'employees') {
            if (!$canManage) sendJsonError('Access Denied');
            $sql = "SELECT id, citizen_id, first_name_th, last_name_th
                    FROM employees e
                    WHERE status IN ('active', 'probation')";
            if ($role === 'hr') {
                $scopeClause = hrScopeBuildEmployeeWhereClause($role, hrScopeCurrentSessionScopes(), 'e');
                $sql .= $scopeClause['sql'];
                $sql .= " ORDER BY first_name_th, last_name_th";
                $stmt = $mysqli->prepare($sql);
                hrScopeBindParams($stmt, $scopeClause['types'], $scopeClause['params']);
            } else {
                $sql .= " ORDER BY first_name_th, last_name_th";
                $stmt = $mysqli->prepare($sql);
            }
            $stmt->execute();
            sendJson(['status' => 'success', 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        }

        if ($action === 'months') {
            $employeeId = resolveAttendanceEmployeeId($mysqli, $canManage);
            $stmt = $mysqli->prepare("SELECT DISTINCT import_month FROM attendance_records WHERE employee_id = ? ORDER BY import_month DESC");
            $stmt->bind_param('i', $employeeId);
            $stmt->execute();
            sendJson(['status' => 'success', 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        }

        if ($action === 'report') {
            $employeeId = resolveAttendanceEmployeeId($mysqli, $canManage);
            $month = $_GET['month'] ?? date('Y-m');
            if (!preg_match('/^\d{4}-\d{2}$/', $month)) sendJsonError('Invalid month');

            $employee = fetchAttendanceEmployee($mysqli, $employeeId);
            if (!$employee) sendJsonError('Employee not found');

            $rows = buildMonthlyAttendanceReport($mysqli, $employee, $month);
            sendJson(['status' => 'success', 'employee' => $employee, 'month' => $month, 'data' => $rows]);
        }

        if ($action === 'report_range') {
            $employeeId = resolveAttendanceEmployeeId($mysqli, $canManage);
            $startMonth = $_GET['start_month'] ?? date('Y-m');
            $endMonth = $_GET['end_month'] ?? $startMonth;
            if (!isValidAttendanceMonthRange($startMonth, $endMonth)) {
                sendJsonError('Invalid month range');
            }

            $employee = fetchAttendanceEmployee($mysqli, $employeeId);
            if (!$employee) sendJsonError('Employee not found');

            $rows = buildAttendanceReportRange($mysqli, $employee, $startMonth, $endMonth);
            sendJson([
                'status' => 'success',
                'employee' => $employee,
                'month' => $startMonth,
                'start_month' => $startMonth,
                'end_month' => $endMonth,
                'months' => buildAttendanceMonthRange($startMonth, $endMonth),
                'data' => $rows,
            ]);
        }

        if ($action === 'missing_scan_report') {
            if (!$canManage) sendJsonError('Access Denied');
            $month = $_GET['month'] ?? date('Y-m');
            if (!preg_match('/^\d{4}-\d{2}$/', $month)) sendJsonError('Invalid month');

            $missingType = attendanceNormalizeMissingScanType($_GET['missing_type'] ?? 'all');
            $employees = fetchAttendanceMissingScanEmployees($mysqli, $role, $_GET);
            $rows = buildAttendanceMissingScanReport($mysqli, $employees, $month, $missingType);
            employeeWarningEnsureTables($mysqli);
            $rows = employeeWarningAnnotateReportRows(
                $mysqli,
                $rows,
                EMPLOYEE_WARNING_SOURCE_ATTENDANCE_MISSING,
                fn(array $row): array => [
                    'employee_id' => $row['employee_id'] ?? 0,
                    'work_date' => $row['work_date'] ?? '',
                ]
            );
            sendJson([
                'status' => 'success',
                'month' => $month,
                'missing_type' => $missingType,
                'summary' => attendanceCountMissingScanRows($rows),
                'data' => $rows,
            ]);
        }

        if ($action === 'late_early_report') {
            if (!$canManage) sendJsonError('Access Denied');
            $month = $_GET['month'] ?? date('Y-m');
            if (!preg_match('/^\d{4}-\d{2}$/', $month)) sendJsonError('Invalid month');

            $incidentType = attendanceNormalizeLateEarlyIncidentType($_GET['incident_type'] ?? 'all');
            $employees = fetchAttendanceMissingScanEmployees($mysqli, $role, $_GET);
            $rows = buildAttendanceLateEarlyReport($mysqli, $employees, $month, $incidentType);
            employeeWarningEnsureTables($mysqli);
            $rows = employeeWarningAnnotateReportRows(
                $mysqli,
                $rows,
                EMPLOYEE_WARNING_SOURCE_ATTENDANCE_LATE_EARLY,
                fn(array $row): array => [
                    'employee_id' => $row['employee_id'] ?? 0,
                    'work_date' => $row['work_date'] ?? '',
                ]
            );
            sendJson([
                'status' => 'success',
                'month' => $month,
                'incident_type' => $incidentType,
                'summary' => attendanceCountLateEarlyRows($rows),
                'data' => $rows,
            ]);
        }

        if ($action === 'employee_request_attendance_report') {
            if (!$canManage) sendJsonError('Access Denied');
            $employeeId = (int)($_GET['employee_id'] ?? 0);
            $month = $_GET['month'] ?? '';
            if ($employeeId <= 0 || !preg_match('/^\d{4}-\d{2}$/', $month)) {
                sendJsonError('กรุณาเลือกพนักงานและเดือน');
            }
            $employee = fetchEmployeeRequestAttendanceReportEmployee($mysqli, $role, $employeeId);
            if (!$employee) sendJsonError('ไม่พบพนักงานในขอบเขตที่รับผิดชอบ');
            sendJson([
                'status' => 'success',
                'month' => $month,
                'employee' => [
                    'id' => (int)$employee['id'],
                    'full_name' => trim(($employee['first_name_th'] ?? '') . ' ' . ($employee['last_name_th'] ?? '')),
                    'position_name' => $employee['position_name_th'] ?? '',
                    'company_name' => $employee['company_name_th'] ?? '',
                    'branch_name' => $employee['branch_name_th'] ?? '',
                ],
            ] + buildEmployeeRequestAttendanceReport($mysqli, $employee, $month));
        }

        if ($action === 'import_summary') {
            if (!$canManage) sendJsonError('Access Denied');
            sendJson(['status' => 'success', 'data' => fetchAttendanceImportSummary($mysqli, $role)]);
        }

        if ($action === 'import_summary_detail') {
            if (!$canManage) sendJsonError('Access Denied');
            $month = $_GET['month'] ?? '';
            if (!preg_match('/^\d{4}-\d{2}$/', $month)) sendJsonError('Invalid month');
            sendJson(['status' => 'success', 'month' => $month, 'data' => fetchAttendanceImportSummaryEmployees($mysqli, $month, $role)]);
        }

        if ($action === 'adjustment_filter_options') {
            if (!$canManage) sendJsonError('Access Denied');
            sendJson(['status' => 'success', 'data' => fetchAttendanceAdjustmentFilterOptions($mysqli, $role)]);
        }

        if ($action === 'adjustment_employees') {
            if (!$canManage) sendJsonError('Access Denied');
            $workDate = $_GET['work_date'] ?? date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) sendJsonError('Invalid date');
            sendJson([
                'status' => 'success',
                'work_date' => $workDate,
                'data' => fetchAttendanceAdjustmentEmployees($mysqli, $role, $workDate, $_GET),
            ]);
        }

        sendJsonError('Invalid Action');
    }

    if ($method === 'POST' && in_array($action, ['save_adjustment', 'save_bulk_adjustments'], true)) {
        if (!$canManage) sendJsonError('Access Denied');

        if ($action === 'save_adjustment') {
            $saved = saveAttendanceAdjustment($mysqli, $role, $_POST);
            sendJson(['status' => 'success', 'saved' => $saved]);
        }

        if ($action === 'save_bulk_adjustments') {
            $saved = saveBulkAttendanceAdjustments($mysqli, $role, $_POST);
            sendJson(['status' => 'success', 'saved' => $saved]);
        }
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
    error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    sendJsonError('System Error');
}

function resolveAttendanceEmployeeId($mysqli, $canManage) {
    if ($canManage && isset($_GET['employee_id']) && (int)$_GET['employee_id'] > 0) {
        $employeeId = (int)$_GET['employee_id'];
        if (!attendanceCanViewEmployee($mysqli, $employeeId)) {
            sendJsonError('Access Denied');
        }
        return $employeeId;
    }
    return (int)($_SESSION['employee_id'] ?? 0);
}

function attendanceCanViewEmployee($mysqli, $employeeId) {
    $role = $_SESSION['role'] ?? 'employee';
    if ($role === 'admin') return true;
    if ($role !== 'hr') {
        return $employeeId === (int)($_SESSION['employee_id'] ?? 0);
    }

    $scopeClause = hrScopeBuildEmployeeWhereClause($role, hrScopeCurrentSessionScopes(), 'e');
    $sql = "SELECT e.id FROM employees e WHERE e.id = ?" . $scopeClause['sql'] . " LIMIT 1";
    $types = 'i' . $scopeClause['types'];
    $params = array_merge([$employeeId], $scopeClause['params']);
    $stmt = $mysqli->prepare($sql);
    hrScopeBindParams($stmt, $types, $params);
    $stmt->execute();
    return $stmt->get_result()->num_rows === 1;
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
    $updated = 0;
    $skipped = 0;
    $unmatched = 0;
    $candidates = [];
    $employeeIds = [];
    $minWorkDate = null;
    $maxWorkDate = null;

    $employeeRes = $mysqli->query("SELECT id, citizen_id FROM employees WHERE citizen_id IS NOT NULL AND citizen_id <> ''");
    $employeeMap = $employeeRes ? attendanceBuildEmployeeIdMap($employeeRes->fetch_all(MYSQLI_ASSOC)) : [];

    foreach ($rows as $row) {
        $importMonth = attendanceImportMonthFromWorkDate($row['work_date']);
        if ($importMonth === null) {
            $skipped++;
            continue;
        }

        $employeeId = $employeeMap[$row['citizen_id']] ?? 0;
        if ($employeeId <= 0) {
            $unmatched++;
            continue;
        }

        $employeeIds[$employeeId] = true;
        $minWorkDate = $minWorkDate === null || $row['work_date'] < $minWorkDate ? $row['work_date'] : $minWorkDate;
        $maxWorkDate = $maxWorkDate === null || $row['work_date'] > $maxWorkDate ? $row['work_date'] : $maxWorkDate;
        $candidates[] = [
            'employee_id' => $employeeId,
            'citizen_id' => $row['citizen_id'],
            'work_date' => $row['work_date'],
            'check_in' => $row['check_in'],
            'check_out' => $row['check_out'],
            'import_month' => $importMonth,
            'source_file' => $sourceFile,
        ];
    }

    $existingMap = fetchAttendanceExistingRecordMap($mysqli, array_keys($employeeIds), $minWorkDate, $maxWorkDate);
    $pendingMap = $existingMap;
    $writeRows = [];
    foreach ($candidates as $row) {
        $key = $row['employee_id'] . '|' . $row['work_date'];
        if (!isset($pendingMap[$key])) {
            $inserted++;
            $writeRows[] = $row;
            $pendingMap[$key] = [
                'check_in' => $row['check_in'],
                'check_out' => $row['check_out'],
            ];
            continue;
        }

        if (attendanceExistingRecordNeedsFill($pendingMap[$key], $row)) {
            $updated++;
            $writeRows[] = $row;
            $pendingMap[$key]['check_in'] = $pendingMap[$key]['check_in'] ?? $row['check_in'];
            $pendingMap[$key]['check_out'] = $pendingMap[$key]['check_out'] ?? $row['check_out'];
        } else {
            $skipped++;
        }
    }

    if (!empty($writeRows)) {
        $mysqli->begin_transaction();
        try {
            foreach (array_chunk($writeRows, 250) as $batchRows) {
                executeAttendanceImportBatch($mysqli, $batchRows);
            }
            $mysqli->commit();
        } catch (Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }
    }

    return [
        'message' => 'นำเข้าไฟล์สำเร็จ',
        'inserted' => $inserted,
        'updated' => $updated,
        'skipped' => $skipped,
        'unmatched' => $unmatched,
    ];
}

function fetchAttendanceExistingRecordMap($mysqli, array $employeeIds, $minWorkDate, $maxWorkDate) {
    $employeeIds = array_values(array_filter(array_map('intval', $employeeIds)));
    if (empty($employeeIds) || $minWorkDate === null || $maxWorkDate === null) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
    $sql = "SELECT employee_id, work_date, check_in, check_out
            FROM attendance_records
            WHERE employee_id IN ({$placeholders})
              AND work_date BETWEEN ? AND ?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($mysqli->error);
    }

    $types = str_repeat('i', count($employeeIds)) . 'ss';
    $params = array_merge($employeeIds, [$minWorkDate, $maxWorkDate]);
    bindMysqliParams($stmt, $types, $params);
    $stmt->execute();
    return attendanceBuildExistingRecordMap($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

function executeAttendanceImportBatch($mysqli, array $rows) {
    $stmt = $mysqli->prepare(attendanceBuildImportBatchUpsertSql(count($rows)));
    if (!$stmt) {
        throw new RuntimeException($mysqli->error);
    }

    $types = str_repeat('issssss', count($rows));
    $params = [];
    foreach ($rows as $row) {
        $params[] = (int)$row['employee_id'];
        $params[] = $row['citizen_id'];
        $params[] = $row['work_date'];
        $params[] = $row['check_in'];
        $params[] = $row['check_out'];
        $params[] = $row['import_month'];
        $params[] = $row['source_file'];
    }

    bindMysqliParams($stmt, $types, $params);
    $stmt->execute();
    if ($stmt->errno) {
        throw new RuntimeException($stmt->error);
    }
}

function bindMysqliParams($stmt, $types, array $params) {
    $values = array_merge([$types], $params);
    $refs = [];
    foreach ($values as $key => $value) {
        $refs[$key] = &$values[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function attendanceEnsureOverrideTable(mysqli $mysqli) {
    $sql = "CREATE TABLE IF NOT EXISTS attendance_record_overrides (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        work_date DATE NOT NULL,
        override_check_in TIME NULL,
        override_check_out TIME NULL,
        reason TEXT NOT NULL,
        created_by INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_by INT NULL,
        updated_at DATETIME NULL,
        UNIQUE KEY uniq_attendance_override_employee_date (employee_id, work_date),
        KEY idx_attendance_override_work_date (work_date),
        KEY idx_attendance_override_created_by (created_by)
    )";
    if (!$mysqli->query($sql)) {
        throw new Exception('Create attendance override table failed: ' . $mysqli->error);
    }
}

function fetchAttendanceOverridesForMonth(mysqli $mysqli, $employeeId, $month) {
    attendanceEnsureOverrideTable($mysqli);
    $stmt = $mysqli->prepare("SELECT aro.employee_id, aro.work_date, aro.override_check_in, aro.override_check_out,
                                     aro.reason, aro.created_at, aro.updated_at,
                                     TRIM(CONCAT(COALESCE(c.first_name_th, ''), ' ', COALESCE(c.last_name_th, ''))) AS created_by_name,
                                     TRIM(CONCAT(COALESCE(u.first_name_th, ''), ' ', COALESCE(u.last_name_th, ''))) AS updated_by_name
                              FROM attendance_record_overrides aro
                              LEFT JOIN users cu ON aro.created_by = cu.id
                              LEFT JOIN employees c ON cu.employee_id = c.id
                              LEFT JOIN users uu ON aro.updated_by = uu.id
                              LEFT JOIN employees u ON uu.employee_id = u.id
                              WHERE aro.employee_id = ? AND DATE_FORMAT(aro.work_date, '%Y-%m') = ?");
    $stmt->bind_param('is', $employeeId, $month);
    $stmt->execute();
    return attendanceBuildOverrideMap($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
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
    $overrideMap = fetchAttendanceOverridesForMonth($mysqli, (int)$employee['id'], $month);

    $shift = [
        'start_time' => $employee['start_time'],
        'end_time' => $employee['end_time'],
        'late_tolerance_mins' => $employee['late_tolerance_mins'],
        'work_days' => $employee['work_days'],
    ];
    $shiftAssignments = employeeShiftAssignmentsFetchForMonth($mysqli, (int)$employee['id'], $month);
    $shiftOverrides = fetchEmployeeShiftOverridesForMonth($mysqli, (int)$employee['id'], $month);
    $holidays = fetchCompanyHolidaysForMonth($mysqli, $month);
    $leaves = fetchApprovedLeavesForMonth($mysqli, (int)$employee['id'], $month);
    $trainings = fetchApprovedTrainingRequestsForMonth($mysqli, (int)$employee['id'], $month);
    $hourlyRequests = fetchApprovedHourlyRequestsForMonth($mysqli, (int)$employee['id'], $month);
    $daySwaps = attendanceBuildApprovedDaySwapMap(fetchApprovedDaySwapsForMonth($mysqli, (int)$employee['id'], $month), (int)$employee['id'], $month);

    $rows = [];
    for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
        $workDate = $date->format('Y-m-d');
        $rawRecord = $records[$workDate] ?? ['check_in' => null, 'check_out' => null];
        $record = attendanceApplyRecordOverride($rawRecord, $overrideMap[$workDate] ?? null);
        $assignmentShift = employeeShiftAssignmentsResolveForDate($shiftAssignments, $shift, $workDate);
        $effectiveShift = attendanceResolveShiftForDate($assignmentShift, $shiftOverrides, $workDate);
        if (isset($daySwaps[$workDate])) {
            $effectiveShift = attendanceApplyDayTypeOverride($effectiveShift, $workDate, $daySwaps[$workDate]);
        }
        $status = attendanceEvaluateStatus($workDate, $record['check_in'], $record['check_out'], $effectiveShift, $holidays, $leaves, $trainings);
        $rows[] = [
            'work_date' => $workDate,
            'day_name' => $date->format('D'),
            'check_in' => $record['check_in'],
            'check_out' => $record['check_out'],
            'raw_check_in' => $rawRecord['check_in'],
            'raw_check_out' => $rawRecord['check_out'],
            'status' => $status['status'],
            'status_label' => $status['label'],
            'is_late' => $status['is_late'],
            'holiday_name' => $status['holiday_name'],
            'leave_name' => $status['leave_name'],
            'training_name' => $status['training_name'],
            'day_swap_type' => $daySwaps[$workDate] ?? null,
            'hourly_requests' => $hourlyRequests[$workDate] ?? [],
            'has_override' => $record['has_override'],
            'override_check_in' => $record['override_check_in'],
            'override_check_out' => $record['override_check_out'],
            'override_reason' => $record['override_reason'],
            'override_created_by_name' => $record['override_created_by_name'],
            'override_updated_by_name' => $record['override_updated_by_name'],
            'override_created_at' => $record['override_created_at'],
            'override_updated_at' => $record['override_updated_at'],
        ];
    }

    return $rows;
}

function fetchEmployeeRequestAttendanceReportEmployee(mysqli $mysqli, $role, $employeeId) {
    $scope = hrScopeBuildEmployeeWhereClause($role, hrScopeCurrentSessionScopes(), 'e');
    $sql = "SELECT e.id, e.citizen_id, e.first_name_th, e.last_name_th, e.company_id, e.branch_id,
                   p.position_name_th, c.company_name_th, b.branch_name_th,
                   ws.start_time, ws.end_time, ws.late_tolerance_mins, ws.work_days
            FROM employees e
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN companies c ON e.company_id = c.id
            LEFT JOIN branches b ON e.branch_id = b.id
            LEFT JOIN work_shifts ws ON e.default_shift_id = ws.id
            WHERE e.id = ? AND e.status IN ('active', 'probation')" . $scope['sql'] . " LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    hrScopeBindParams($stmt, 'i' . $scope['types'], array_merge([(int)$employeeId], $scope['params']));
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function buildEmployeeRequestAttendanceReport(mysqli $mysqli, array $employee, $month) {
    $employeeId = (int)$employee['id'];
    $startDate = $month . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));
    $events = [];

    $records = fetchAttendanceRecordsForEmployeesMonth($mysqli, [$employeeId], $month);
    $overrides = fetchAttendanceOverridesForEmployeesMonth($mysqli, [$employeeId], $startDate, $endDate);
    $assignments = fetchShiftAssignmentsForEmployeesMonth($mysqli, [$employeeId], $startDate, $endDate);
    $shiftOverrides = fetchShiftOverridesForEmployeesMonth($mysqli, [$employeeId], $startDate, $endDate);
    $holidays = fetchCompanyHolidaysForMonth($mysqli, $month);
    $leaveMap = fetchApprovedLeaveMapForEmployeesMonth($mysqli, [$employeeId], $month, $startDate, $endDate);
    $trainingMap = fetchApprovedTrainingMapForEmployeesMonth($mysqli, [$employeeId], $month, $startDate, $endDate);
    $daySwapMap = fetchApprovedDaySwapMapForEmployeesMonth($mysqli, [$employeeId], $month, $startDate, $endDate);
    $baseShift = [
        'start_time' => $employee['start_time'] ?? null,
        'end_time' => $employee['end_time'] ?? null,
        'late_tolerance_mins' => $employee['late_tolerance_mins'] ?? 0,
        'work_days' => $employee['work_days'] ?? '',
    ];
    $scannerByDate = [];
    $scannerDates = array_values(array_unique(array_merge(
        array_keys($records[$employeeId] ?? []),
        array_keys($overrides[$employeeId] ?? [])
    )));
    sort($scannerDates);
    foreach ($scannerDates as $workDate) {
        $rawRecord = $records[$employeeId][$workDate] ?? ['check_in' => null, 'check_out' => null];
        $record = attendanceApplyRecordOverride($rawRecord, $overrides[$employeeId][$workDate] ?? null);
        $effectiveShift = employeeShiftAssignmentsResolveForDate($assignments[$employeeId] ?? [], $baseShift, $workDate);
        $effectiveShift = attendanceResolveShiftForDate($effectiveShift, $shiftOverrides[$employeeId] ?? [], $workDate);
        if (isset($daySwapMap[$employeeId][$workDate])) {
            $effectiveShift = attendanceApplyDayTypeOverride($effectiveShift, $workDate, $daySwapMap[$employeeId][$workDate]);
        }
        $status = attendanceEvaluateStatus($workDate, $record['check_in'], $record['check_out'], $effectiveShift, $holidays, $leaveMap[$employeeId] ?? [], $trainingMap[$employeeId] ?? []);
        if (in_array($status['status'], ['holiday', 'leave', 'training'], true)) continue;
        $scanner = employeeRequestReportCalculateScannerEvents($workDate, $record['check_in'], $record['check_out'], $effectiveShift);
        $scannerByDate[$workDate] = $scanner;
        $shiftStart = substr((string)($effectiveShift['start_time'] ?? ''), 0, 5);
        $shiftEnd = substr((string)($effectiveShift['end_time'] ?? ''), 0, 5);
        $checkIn = substr((string)($record['check_in'] ?? ''), 0, 5);
        $checkOut = substr((string)($record['check_out'] ?? ''), 0, 5);
        if ($scanner['late_minutes'] > 0) {
            $events[] = employeeRequestReportBuildEvent("scanner:late:{$workDate}", $workDate, 'actual_late', 'scanner', "{$shiftStart} / {$checkIn}", $scanner['late_minutes'], 'minute', 'เวลาเข้างานตามกะ / เวลาสแกนเข้า', 'ข้อมูลลงเวลา');
        }
        if ($scanner['early_minutes'] > 0) {
            $events[] = employeeRequestReportBuildEvent("scanner:early:{$workDate}", $workDate, 'actual_early', 'scanner', "{$checkOut} / {$shiftEnd}", $scanner['early_minutes'], 'minute', 'เวลาสแกนออก / เวลาเลิกงานตามกะ', 'ข้อมูลลงเวลา');
        }
        if ($scanner['overtime_minutes'] > 0) {
            $events[] = employeeRequestReportBuildEvent("scanner:overtime:{$workDate}", $workDate, 'actual_overtime', 'scanner', "{$shiftEnd} / {$checkOut}", $scanner['overtime_minutes'], 'minute', 'เวลาเลิกงานตามกะ / เวลาสแกนออก', 'ข้อมูลลงเวลา');
        }
    }

    $events = array_merge(
        $events,
        fetchEmployeeRequestReportLeaveEvents($mysqli, $employeeId, $month),
        fetchEmployeeRequestReportHourlyEvents($mysqli, $employeeId, $startDate, $endDate, $scannerByDate),
        fetchEmployeeRequestReportActivityEvents($mysqli, $employeeId, $startDate, $endDate),
        fetchEmployeeRequestReportSwapEvents($mysqli, $employeeId, $startDate, $endDate)
    );
    $events = employeeRequestReportSortEvents($events);
    return ['summary' => employeeRequestReportSummarize($events), 'data' => $events];
}

function fetchEmployeeRequestReportLeaveEvents(mysqli $mysqli, $employeeId, $month) {
    leaveEnsureRequestPartColumns($mysqli);
    leaveEnsureLeaveTypeCalculationColumns($mysqli);
    $startDate = $month . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));
    $stmt = $mysqli->prepare("SELECT lr.id, lr.employee_id, lr.start_date, lr.end_date, lr.start_day_part, lr.end_day_part, lr.reason, lt.type_name
                              FROM leave_requests lr
                              JOIN leave_types lt ON lr.leave_type_id = lt.id
                              WHERE lr.employee_id = ? AND lr.status = 'approved' AND lt.is_actual_leave = 1
                                AND lr.start_date <= ? AND lr.end_date >= ?");
    $stmt->bind_param('iss', $employeeId, $endDate, $startDate);
    $stmt->execute();
    $requestRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $workDays = leaveFetchEmployeeWorkDays($mysqli, $employeeId);
    $holidays = leaveFetchCompanyHolidays($mysqli, $startDate, $endDate);
    $events = [];
    foreach ($requestRows as $request) {
        foreach (leaveExpandApprovedRequestForMonth($request, $month, $workDays, $holidays) as $row) {
            $detail = trim(($row['type_name'] ?? 'ลา') . ' | ' . ($row['day_part_label'] ?? '') . ' | ' . ($row['reason'] ?? ''), ' |');
            $events[] = employeeRequestReportBuildEvent('leave:' . $row['id'] . ':' . $row['leave_date'], $row['leave_date'], 'leave', 'approved_request', substr($row['start_date'], 0, 10) . ' - ' . substr($row['end_date'], 0, 10), (float)$row['leave_days'], 'day', $detail, 'อนุมัติแล้ว');
        }
    }
    return $events;
}

function fetchEmployeeRequestReportHourlyEvents(mysqli $mysqli, $employeeId, $startDate, $endDate, array $scannerByDate) {
    leaveEnsureRequestPartColumns($mysqli);
    $stmt = $mysqli->prepare("SELECT lr.id, lr.start_date, lr.time_request_type, lr.request_minutes, lr.approved_request_minutes, lr.request_start_time, lr.request_end_time, lr.reason
                              FROM leave_requests lr
                              WHERE lr.employee_id = ? AND lr.status = 'approved'
                                AND lr.start_date BETWEEN ? AND ?
                                AND lr.time_request_type IN ('late_arrival','early_departure','overtime_after_work')");
    $stmt->bind_param('iss', $employeeId, $startDate, $endDate);
    $stmt->execute();
    $events = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $typeMap = ['late_arrival' => 'late_request', 'early_departure' => 'early_request', 'overtime_after_work' => 'overtime_request'];
        $type = $typeMap[$row['time_request_type']] ?? '';
        if ($type === '') continue;
        $minutes = (int)$row['approved_request_minutes'] > 0 ? (int)$row['approved_request_minutes'] : (int)$row['request_minutes'];
        $timeLabel = trim(substr((string)$row['request_start_time'], 0, 5) . ' - ' . substr((string)$row['request_end_time'], 0, 5), ' -');
        if ($timeLabel === '') $timeLabel = '-';
        $extra = [];
        if ($type === 'overtime_request') $extra['actual_overtime_minutes'] = (int)($scannerByDate[$row['start_date']]['overtime_minutes'] ?? 0);
        $events[] = employeeRequestReportBuildEvent('request:' . $row['time_request_type'] . ':' . $row['id'], $row['start_date'], $type, 'approved_request', $timeLabel, $minutes, 'minute', (string)($row['reason'] ?? ''), 'อนุมัติแล้ว', $extra);
    }
    return $events;
}

function fetchEmployeeRequestReportActivityEvents(mysqli $mysqli, $employeeId, $startDate, $endDate) {
    trainingRequestEnsureTable($mysqli);
    $stmt = $mysqli->prepare("SELECT tr.id, tr.start_date, tr.end_date, tr.start_day_part, tr.end_day_part, tr.course_name, tr.location, tr.objective, at.type_name AS activity_type_name
                              FROM training_requests tr
                              LEFT JOIN activity_types at ON tr.activity_type_id = at.id
                              WHERE tr.employee_id = ? AND tr.status = 'approved'
                                AND tr.start_date <= ? AND tr.end_date >= ?");
    $stmt->bind_param('iss', $employeeId, $endDate, $startDate);
    $stmt->execute();
    $events = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $from = new DateTimeImmutable(max($startDate, $row['start_date']));
        $to = new DateTimeImmutable(min($endDate, $row['end_date']));
        for ($date = $from; $date <= $to; $date = $date->modify('+1 day')) {
            $workDate = $date->format('Y-m-d');
            $part = 'เต็มวัน';
            if ($workDate === $row['start_date'] && $row['start_day_part'] !== 'full') $part = trainingRequestDayPartLabel($row['start_day_part']);
            if ($workDate === $row['end_date'] && $row['end_day_part'] !== 'full') $part = trainingRequestDayPartLabel($row['end_day_part']);
            $detail = implode(' | ', array_filter([$row['activity_type_name'], $row['course_name'], $row['location'], $row['objective']]));
            $events[] = employeeRequestReportBuildEvent('activity:' . $row['id'] . ':' . $workDate, $workDate, 'activity', 'approved_request', $part, $part === 'เต็มวัน' ? 1 : 0.5, 'day', $detail, 'อนุมัติแล้ว');
        }
    }
    return $events;
}

function fetchEmployeeRequestReportSwapEvents(mysqli $mysqli, $employeeId, $startDate, $endDate) {
    daySwapEnsureTable($mysqli);
    $stmt = $mysqli->prepare("SELECT dsr.id, dsr.requester_employee_id, dsr.target_employee_id, dsr.requester_date, dsr.target_date, dsr.reason,
                                     CONCAT_WS(' ', requester.first_name_th, requester.last_name_th) AS requester_name,
                                     CONCAT_WS(' ', target.first_name_th, target.last_name_th) AS target_name
                              FROM day_swap_requests dsr
                              JOIN employees requester ON dsr.requester_employee_id = requester.id
                              JOIN employees target ON dsr.target_employee_id = target.id
                              WHERE dsr.status = 'approved'
                                AND (dsr.requester_employee_id = ? OR dsr.target_employee_id = ?)
                                AND ((dsr.requester_date BETWEEN ? AND ?) OR (dsr.target_date BETWEEN ? AND ?))");
    $stmt->bind_param('iissss', $employeeId, $employeeId, $startDate, $endDate, $startDate, $endDate);
    $stmt->execute();
    $events = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $isRequester = (int)$row['requester_employee_id'] === (int)$employeeId;
        $eventDate = $isRequester ? $row['requester_date'] : $row['target_date'];
        $counterpart = $isRequester ? $row['target_name'] : $row['requester_name'];
        $detail = ($isRequester ? 'ผู้ขอสลับกับ ' : 'ผู้รับสลับจาก ') . $counterpart . ' | ' . $row['reason'];
        $events[] = employeeRequestReportBuildEvent('shift-swap:' . $row['id'] . ':' . $employeeId, $eventDate, 'shift_swap', 'approved_request', $row['requester_date'] . ' ↔ ' . $row['target_date'], 1, 'request', $detail, 'อนุมัติแล้ว');
    }
    return $events;
}

function buildAttendanceReportRange($mysqli, array $employee, $startMonth, $endMonth) {
    $rows = [];
    foreach (buildAttendanceMonthRange($startMonth, $endMonth) as $month) {
        $rows = array_merge($rows, buildMonthlyAttendanceReport($mysqli, $employee, $month));
    }
    return $rows;
}

function buildAttendanceMissingScanReport(mysqli $mysqli, array $employees, $month, $missingType = 'all') {
    $rows = fetchAttendanceMissingScanReportRows($mysqli, $employees, $month);
    $rows = attendanceFilterMissingScanReportRows($rows, $missingType);
    usort($rows, function ($a, $b) {
        return strcmp(($a['work_date'] ?? '') . ($a['full_name'] ?? ''), ($b['work_date'] ?? '') . ($b['full_name'] ?? ''));
    });
    return $rows;
}

function buildAttendanceLateEarlyReport(mysqli $mysqli, array $employees, $month, $incidentType = 'all') {
    if (!$employees) return [];

    $start = new DateTimeImmutable($month . '-01');
    $end = $start->modify('last day of this month');
    $startDate = $start->format('Y-m-d');
    $endDate = $end->format('Y-m-d');
    $employeeIds = array_map('intval', array_column($employees, 'id'));
    $records = fetchAttendanceRecordsForEmployeesMonth($mysqli, $employeeIds, $month);
    $overrideMap = fetchAttendanceOverridesForEmployeesMonth($mysqli, $employeeIds, $startDate, $endDate);
    $shiftAssignments = fetchShiftAssignmentsForEmployeesMonth($mysqli, $employeeIds, $startDate, $endDate);
    $shiftOverrides = fetchShiftOverridesForEmployeesMonth($mysqli, $employeeIds, $startDate, $endDate);
    $holidays = fetchCompanyHolidaysForMonth($mysqli, $month);
    $leaveMap = fetchApprovedLeaveMapForEmployeesMonth($mysqli, $employeeIds, $month, $startDate, $endDate);
    $trainingMap = fetchApprovedTrainingMapForEmployeesMonth($mysqli, $employeeIds, $month, $startDate, $endDate);
    $daySwapMap = fetchApprovedDaySwapMapForEmployeesMonth($mysqli, $employeeIds, $month, $startDate, $endDate);
    $approvedMinuteMap = fetchApprovedLateEarlyMinutesForEmployeesMonth($mysqli, $employeeIds, $startDate, $endDate);

    $rows = [];
    foreach ($employees as $employee) {
        $employeeId = (int)$employee['id'];
        $baseShift = [
            'start_time' => $employee['start_time'] ?? null,
            'end_time' => $employee['end_time'] ?? null,
            'late_tolerance_mins' => $employee['late_tolerance_mins'] ?? 0,
            'work_days' => $employee['work_days'] ?? '',
        ];

        for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
            $workDate = $date->format('Y-m-d');
            if (isset($holidays[$workDate]) || isset($leaveMap[$employeeId][$workDate]) || isset($trainingMap[$employeeId][$workDate])) {
                continue;
            }

            $rawRecord = $records[$employeeId][$workDate] ?? ['check_in' => null, 'check_out' => null];
            $record = attendanceApplyRecordOverride($rawRecord, $overrideMap[$employeeId][$workDate] ?? null);
            $assignmentShift = employeeShiftAssignmentsResolveForDate($shiftAssignments[$employeeId] ?? [], $baseShift, $workDate);
            $effectiveShift = attendanceResolveShiftForDate($assignmentShift, $shiftOverrides[$employeeId] ?? [], $workDate);
            if (isset($daySwapMap[$employeeId][$workDate])) {
                $effectiveShift = attendanceApplyDayTypeOverride($effectiveShift, $workDate, $daySwapMap[$employeeId][$workDate]);
            }

            $incident = attendanceCalculateLateEarlyIncident(
                $workDate,
                $record['check_in'],
                $record['check_out'],
                $effectiveShift,
                $approvedMinuteMap[$employeeId][$workDate] ?? []
            );
            if ($incident === null) continue;

            $row = [
                'employee_id' => $employeeId,
                'citizen_id' => (string)($employee['citizen_id'] ?? ''),
                'first_name_th' => (string)($employee['first_name_th'] ?? ''),
                'last_name_th' => (string)($employee['last_name_th'] ?? ''),
                'position_name_th' => (string)($employee['position_name_th'] ?? ''),
                'branch_id' => isset($employee['branch_id']) ? (int)$employee['branch_id'] : null,
                'branch_name_th' => (string)($employee['branch_name_th'] ?? ''),
                'company_id' => isset($employee['company_id']) ? (int)$employee['company_id'] : null,
                'company_name_th' => (string)($employee['company_name_th'] ?? ''),
                'work_date' => $workDate,
                'check_in' => $record['check_in'],
                'check_out' => $record['check_out'],
            ];
            $row['full_name'] = trim($row['first_name_th'] . ' ' . $row['last_name_th']);
            $rows[] = array_merge($row, $incident);
        }
    }

    $rows = attendanceFilterLateEarlyReportRows($rows, $incidentType);
    usort($rows, function ($a, $b) {
        return strcmp(($a['work_date'] ?? '') . ($a['full_name'] ?? ''), ($b['work_date'] ?? '') . ($b['full_name'] ?? ''));
    });
    return $rows;
}

function fetchAttendanceMissingScanReportRows(mysqli $mysqli, array $employees, $month) {
    if (!$employees) {
        return [];
    }

    $start = new DateTimeImmutable($month . '-01');
    $end = $start->modify('last day of this month');
    $startDate = $start->format('Y-m-d');
    $endDate = $end->format('Y-m-d');
    $employeeIds = array_map('intval', array_column($employees, 'id'));
    $records = fetchAttendanceRecordsForEmployeesMonth($mysqli, $employeeIds, $month);
    $overrideMap = fetchAttendanceOverridesForEmployeesMonth($mysqli, $employeeIds, $startDate, $endDate);
    $shiftAssignments = fetchShiftAssignmentsForEmployeesMonth($mysqli, $employeeIds, $startDate, $endDate);
    $shiftOverrides = fetchShiftOverridesForEmployeesMonth($mysqli, $employeeIds, $startDate, $endDate);
    $holidays = fetchCompanyHolidaysForMonth($mysqli, $month);
    $leaveMap = fetchApprovedLeaveMapForEmployeesMonth($mysqli, $employeeIds, $month, $startDate, $endDate);
    $trainingMap = fetchApprovedTrainingMapForEmployeesMonth($mysqli, $employeeIds, $month, $startDate, $endDate);
    $daySwapMap = fetchApprovedDaySwapMapForEmployeesMonth($mysqli, $employeeIds, $month, $startDate, $endDate);

    $rows = [];
    foreach ($employees as $employee) {
        $employeeId = (int)$employee['id'];
        $baseShift = [
            'start_time' => $employee['start_time'] ?? null,
            'end_time' => $employee['end_time'] ?? null,
            'late_tolerance_mins' => $employee['late_tolerance_mins'] ?? 0,
            'work_days' => $employee['work_days'] ?? '',
        ];

        for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
            $workDate = $date->format('Y-m-d');
            $rawRecord = $records[$employeeId][$workDate] ?? ['check_in' => null, 'check_out' => null];
            $record = attendanceApplyRecordOverride($rawRecord, $overrideMap[$employeeId][$workDate] ?? null);
            $assignmentShift = employeeShiftAssignmentsResolveForDate($shiftAssignments[$employeeId] ?? [], $baseShift, $workDate);
            $effectiveShift = attendanceResolveShiftForDate($assignmentShift, $shiftOverrides[$employeeId] ?? [], $workDate);
            if (isset($daySwapMap[$employeeId][$workDate])) {
                $effectiveShift = attendanceApplyDayTypeOverride($effectiveShift, $workDate, $daySwapMap[$employeeId][$workDate]);
            }
            $status = attendanceEvaluateStatus(
                $workDate,
                $record['check_in'],
                $record['check_out'],
                $effectiveShift,
                $holidays,
                $leaveMap[$employeeId] ?? [],
                $trainingMap[$employeeId] ?? []
            );

            if (!in_array($status['status'], attendanceMissingScanStatuses(), true)) {
                continue;
            }

            $row['employee_id'] = (int)$employee['id'];
            $row['citizen_id'] = (string)($employee['citizen_id'] ?? '');
            $row['first_name_th'] = (string)($employee['first_name_th'] ?? '');
            $row['last_name_th'] = (string)($employee['last_name_th'] ?? '');
            $row['full_name'] = trim($row['first_name_th'] . ' ' . $row['last_name_th']);
            $row['position_name_th'] = (string)($employee['position_name_th'] ?? '');
            $row['branch_id'] = isset($employee['branch_id']) ? (int)$employee['branch_id'] : null;
            $row['branch_name_th'] = (string)($employee['branch_name_th'] ?? '');
            $row['company_id'] = isset($employee['company_id']) ? (int)$employee['company_id'] : null;
            $row['company_name_th'] = (string)($employee['company_name_th'] ?? '');
            $row['work_date'] = $workDate;
            $row['day_name'] = $date->format('D');
            $row['check_in'] = $record['check_in'];
            $row['check_out'] = $record['check_out'];
            $row['raw_check_in'] = $rawRecord['check_in'];
            $row['raw_check_out'] = $rawRecord['check_out'];
            $row['status'] = $status['status'];
            $row['status_label'] = $status['label'];
            $row['is_late'] = $status['is_late'];
            $row['holiday_name'] = $status['holiday_name'];
            $row['leave_name'] = $status['leave_name'];
            $row['training_name'] = $status['training_name'];
            $row['day_swap_type'] = $daySwapMap[$employeeId][$workDate] ?? null;
            $row['has_override'] = $record['has_override'];
            $rows[] = $row;
        }
    }

    return $rows;
}

function attendanceBuildInClause(array $ids) {
    return implode(',', array_fill(0, count($ids), '?'));
}

function attendanceBindDynamicParams(mysqli_stmt $stmt, $types, array $params) {
    if ($types === '' || !$params) return;
    $refs = [$types];
    foreach ($params as $index => $value) {
        $refs[] = &$params[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function fetchAttendanceRecordsForEmployeesMonth(mysqli $mysqli, array $employeeIds, $month) {
    $map = [];
    $sql = "SELECT employee_id, work_date, check_in, check_out
            FROM attendance_records
            WHERE import_month = ? AND employee_id IN (" . attendanceBuildInClause($employeeIds) . ")";
    $stmt = $mysqli->prepare($sql);
    attendanceBindDynamicParams($stmt, 's' . str_repeat('i', count($employeeIds)), array_merge([$month], $employeeIds));
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $employeeId = (int)$row['employee_id'];
        $map[$employeeId][$row['work_date']] = [
            'check_in' => attendanceNormalizeTime($row['check_in'] ?? null),
            'check_out' => attendanceNormalizeTime($row['check_out'] ?? null),
        ];
    }
    return $map;
}

function fetchApprovedLateEarlyMinutesForEmployeesMonth(mysqli $mysqli, array $employeeIds, $startDate, $endDate) {
    if (!$employeeIds) return [];
    leaveEnsureRequestPartColumns($mysqli);
    $map = [];
    $sql = "SELECT lr.employee_id, lr.start_date AS work_date, lr.time_request_type,
                   CASE
                       WHEN COALESCE(lr.approved_request_minutes, 0) > 0 THEN lr.approved_request_minutes
                       ELSE COALESCE(lr.request_minutes, 0)
                   END AS effective_minutes
            FROM leave_requests lr
            WHERE lr.employee_id IN (" . attendanceBuildInClause($employeeIds) . ")
              AND lr.start_date BETWEEN ? AND ?
              AND lr.status = 'approved'
              AND lr.time_request_type IN ('late_arrival', 'early_departure')";
    $stmt = $mysqli->prepare($sql);
    attendanceBindDynamicParams($stmt, str_repeat('i', count($employeeIds)) . 'ss', array_merge($employeeIds, [$startDate, $endDate]));
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $employeeId = (int)$row['employee_id'];
        $workDate = (string)$row['work_date'];
        $type = (string)$row['time_request_type'];
        $map[$employeeId][$workDate][$type] = ($map[$employeeId][$workDate][$type] ?? 0) + max(0, (int)$row['effective_minutes']);
    }
    return $map;
}

function fetchAttendanceOverridesForEmployeesMonth(mysqli $mysqli, array $employeeIds, $startDate, $endDate) {
    attendanceEnsureOverrideTable($mysqli);
    $map = [];
    $sql = "SELECT aro.employee_id, aro.work_date, aro.override_check_in, aro.override_check_out,
                   aro.reason, aro.created_at, aro.updated_at
            FROM attendance_record_overrides aro
            WHERE aro.employee_id IN (" . attendanceBuildInClause($employeeIds) . ")
              AND aro.work_date BETWEEN ? AND ?";
    $stmt = $mysqli->prepare($sql);
    attendanceBindDynamicParams($stmt, str_repeat('i', count($employeeIds)) . 'ss', array_merge($employeeIds, [$startDate, $endDate]));
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $employeeId = (int)$row['employee_id'];
        $workDate = (string)$row['work_date'];
        $map[$employeeId][$workDate] = [
            'employee_id' => $employeeId,
            'work_date' => $workDate,
            'override_check_in' => attendanceNormalizeTime($row['override_check_in'] ?? null),
            'override_check_out' => attendanceNormalizeTime($row['override_check_out'] ?? null),
            'reason' => trim((string)($row['reason'] ?? '')),
            'created_by_name' => '',
            'updated_by_name' => '',
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
    return $map;
}

function fetchShiftAssignmentsForEmployeesMonth(mysqli $mysqli, array $employeeIds, $startDate, $endDate) {
    $map = [];
    $sql = "SELECT esa.employee_id, esa.shift_id, esa.effective_from, esa.effective_to,
                   ws.start_time, ws.end_time, ws.late_tolerance_mins, ws.work_days
            FROM employee_shift_assignments esa
            JOIN work_shifts ws ON esa.shift_id = ws.id
            WHERE esa.employee_id IN (" . attendanceBuildInClause($employeeIds) . ")
              AND esa.effective_from <= ?
              AND (esa.effective_to IS NULL OR esa.effective_to = '0000-00-00' OR esa.effective_to >= ?)
            ORDER BY esa.employee_id, esa.effective_from DESC, esa.id DESC";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return $map;
    attendanceBindDynamicParams($stmt, str_repeat('i', count($employeeIds)) . 'ss', array_merge($employeeIds, [$endDate, $startDate]));
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $map[(int)$row['employee_id']][] = $row;
    }
    return $map;
}

function fetchShiftOverridesForEmployeesMonth(mysqli $mysqli, array $employeeIds, $startDate, $endDate) {
    $map = [];
    $sql = "SELECT employee_id, day_of_week, start_time, end_time, late_tolerance_mins, effective_from, effective_to
            FROM employee_shift_overrides
            WHERE employee_id IN (" . attendanceBuildInClause($employeeIds) . ")
              AND is_active = 1
              AND effective_from <= ?
              AND (effective_to IS NULL OR effective_to = '0000-00-00' OR effective_to >= ?)
            ORDER BY employee_id, effective_from DESC, id DESC";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return $map;
    attendanceBindDynamicParams($stmt, str_repeat('i', count($employeeIds)) . 'ss', array_merge($employeeIds, [$endDate, $startDate]));
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $map[(int)$row['employee_id']][] = $row;
    }
    return $map;
}

function fetchApprovedLeaveMapForEmployeesMonth(mysqli $mysqli, array $employeeIds, $month, $startDate, $endDate) {
    $map = [];
    $sql = "SELECT lr.employee_id, lr.start_date, lr.end_date, lt.type_name
            FROM leave_requests lr
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.employee_id IN (" . attendanceBuildInClause($employeeIds) . ")
              AND lr.status IN ('approved','pending_cancel_hr')
              AND (lr.request_unit = 'day'
                   OR (lr.request_unit = 'hour'
                       AND lr.time_request_type IS NULL
                       AND COALESCE(lr.total_days, 0) >= 1))
              AND lr.start_date <= ?
              AND lr.end_date >= ?
            ORDER BY lr.employee_id, lr.start_date";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return $map;
    attendanceBindDynamicParams($stmt, str_repeat('i', count($employeeIds)) . 'ss', array_merge($employeeIds, [$endDate, $startDate]));
    $stmt->execute();
    $grouped = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $grouped[(int)$row['employee_id']][] = $row;
    }
    foreach ($grouped as $employeeId => $rows) {
        $map[$employeeId] = attendanceBuildApprovedLeaveMap($rows, $month);
    }
    return $map;
}

function fetchApprovedTrainingMapForEmployeesMonth(mysqli $mysqli, array $employeeIds, $month, $startDate, $endDate) {
    $map = [];
    $sql = "SELECT tr.employee_id, tr.start_date, tr.end_date, tr.course_name, at.type_name AS activity_type_name
            FROM training_requests tr
            LEFT JOIN activity_types at ON tr.activity_type_id = at.id
            WHERE tr.employee_id IN (" . attendanceBuildInClause($employeeIds) . ")
              AND tr.status IN ('approved','pending_cancel_hr')
              AND tr.start_date <= ?
              AND tr.end_date >= ?
            ORDER BY tr.employee_id, tr.start_date, tr.id";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return $map;
    attendanceBindDynamicParams($stmt, str_repeat('i', count($employeeIds)) . 'ss', array_merge($employeeIds, [$endDate, $startDate]));
    $stmt->execute();
    $grouped = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $grouped[(int)$row['employee_id']][] = $row;
    }
    foreach ($grouped as $employeeId => $rows) {
        $map[$employeeId] = attendanceBuildApprovedTrainingMap($rows, $month);
    }
    return $map;
}

function fetchApprovedDaySwapMapForEmployeesMonth(mysqli $mysqli, array $employeeIds, $month, $startDate, $endDate) {
    $map = [];
    $sql = "SELECT requester_employee_id, target_employee_id, requester_date, target_date
            FROM day_swap_requests
            WHERE status IN ('approved','pending_cancel_hr')
              AND (requester_employee_id IN (" . attendanceBuildInClause($employeeIds) . ")
                   OR target_employee_id IN (" . attendanceBuildInClause($employeeIds) . "))
              AND ((requester_date BETWEEN ? AND ?) OR (target_date BETWEEN ? AND ?))";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return $map;
    $types = str_repeat('i', count($employeeIds) * 2) . 'ssss';
    $params = array_merge($employeeIds, $employeeIds, [$startDate, $endDate, $startDate, $endDate]);
    attendanceBindDynamicParams($stmt, $types, $params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($employeeIds as $employeeId) {
        $map[$employeeId] = attendanceBuildApprovedDaySwapMap($rows, $employeeId, $month);
    }
    return $map;
}

function isValidAttendanceMonthRange($startMonth, $endMonth) {
    return preg_match('/^\d{4}-\d{2}$/', $startMonth)
        && preg_match('/^\d{4}-\d{2}$/', $endMonth)
        && $startMonth <= $endMonth
        && count(buildAttendanceMonthRange($startMonth, $endMonth)) <= 12;
}

function buildAttendanceMonthRange($startMonth, $endMonth) {
    $months = [];
    $start = new DateTimeImmutable($startMonth . '-01');
    $end = new DateTimeImmutable($endMonth . '-01');
    for ($date = $start; $date <= $end; $date = $date->modify('+1 month')) {
        $months[] = $date->format('Y-m');
    }
    return $months;
}

function fetchApprovedDaySwapsForMonth($mysqli, $employeeId, $month) {
    return daySwapFetchApprovedRowsForMonth($mysqli, $employeeId, $month);
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
                                AND lr.status IN ('approved','pending_cancel_hr')
                                AND (lr.request_unit = 'day'
                                     OR (lr.request_unit = 'hour'
                                         AND lr.time_request_type IS NULL
                                         AND COALESCE(lr.total_days, 0) >= 1))
                                AND lr.start_date <= ?
                                AND lr.end_date >= ?
                              ORDER BY lr.start_date");
    $stmt->bind_param('iss', $employeeId, $end, $start);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    return attendanceBuildApprovedLeaveMap($rows, $month);
}

function fetchApprovedTrainingRequestsForMonth($mysqli, $employeeId, $month) {
    trainingRequestEnsureTable($mysqli);
    $start = $month . '-01';
    $end = (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');
    $stmt = $mysqli->prepare("SELECT tr.start_date, tr.end_date, tr.course_name, at.type_name AS activity_type_name
                              FROM training_requests tr
                              LEFT JOIN activity_types at ON tr.activity_type_id = at.id
                              WHERE tr.employee_id = ?
                                AND tr.status IN ('approved','pending_cancel_hr')
                                AND tr.start_date <= ?
                                AND tr.end_date >= ?
                              ORDER BY tr.start_date, tr.id");
    $stmt->bind_param('iss', $employeeId, $end, $start);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    return attendanceBuildApprovedTrainingMap($rows, $month);
}

function fetchApprovedHourlyRequestsForMonth($mysqli, $employeeId, $month) {
    leaveEnsureRequestPartColumns($mysqli);
    $start = $month . '-01';
    $end = (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');
    $stmt = $mysqli->prepare("SELECT lr.start_date, lr.request_unit, lr.time_request_type, lr.request_minutes, lr.approved_request_minutes, lr.request_start_time, lr.request_end_time, lt.type_name
                              FROM leave_requests lr
                              JOIN leave_types lt ON lr.leave_type_id = lt.id
                              WHERE lr.employee_id = ?
                                AND lr.status IN ('approved','pending_cancel_hr')
                                AND lr.request_unit = 'hour'
                                AND NOT (lr.time_request_type IS NULL
                                         AND COALESCE(lr.total_days, 0) >= 1)
                                AND lr.start_date BETWEEN ? AND ?
                              ORDER BY lr.start_date, lr.id");
    $stmt->bind_param('iss', $employeeId, $start, $end);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    return attendanceBuildApprovedHourlyRequestMap($rows, $month);
}

function fetchAttendanceImportSummary($mysqli, $role) {
    $currentMonth = date('Y-m');
    $oldestMonth = (new DateTimeImmutable($currentMonth . '-01'))->modify('-5 months')->format('Y-m');
    $sql = "SELECT ar.import_month,
                   COUNT(*) AS record_count,
                   COUNT(DISTINCT ar.employee_id) AS employee_count,
                   MAX(ar.work_date) AS latest_work_date
            FROM attendance_records ar";
    $types = 'ss';
    $params = [$oldestMonth, $currentMonth];

    if ($role === 'hr') {
        $sql .= " JOIN employees e ON ar.employee_id = e.id";
    }

    $sql .= " WHERE ar.import_month BETWEEN ? AND ?";

    if ($role === 'hr') {
        $scopeClause = hrScopeBuildEmployeeWhereClause($role, hrScopeCurrentSessionScopes(), 'e');
        $sql .= $scopeClause['sql'];
        $types .= $scopeClause['types'];
        $params = array_merge($params, $scopeClause['params']);
    }

    $sql .= " GROUP BY ar.import_month
              ORDER BY ar.import_month DESC";
    $stmt = $mysqli->prepare($sql);
    hrScopeBindParams($stmt, $types, $params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    return attendanceBuildImportSummaryMonths($rows, date('Y-m-d'), 6);
}

function fetchAttendanceImportSummaryEmployees($mysqli, $month, $role) {
    $sql = "SELECT ar.employee_id,
                   e.citizen_id,
                   e.first_name_th,
                   e.last_name_th,
                   p.position_name_th,
                   b.branch_name_th,
                   c.company_name_th,
                   COUNT(*) AS record_count,
                   MIN(ar.work_date) AS first_work_date,
                   MAX(ar.work_date) AS latest_work_date
            FROM attendance_records ar
            JOIN employees e ON ar.employee_id = e.id
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN branches b ON e.branch_id = b.id
            LEFT JOIN companies c ON e.company_id = c.id
            WHERE ar.import_month = ?";
    $types = 's';
    $params = [$month];

    if ($role === 'hr') {
        $scopeClause = hrScopeBuildEmployeeWhereClause($role, hrScopeCurrentSessionScopes(), 'e');
        $sql .= $scopeClause['sql'];
        $types .= $scopeClause['types'];
        $params = array_merge($params, $scopeClause['params']);
    }

    $sql .= " GROUP BY ar.employee_id, e.citizen_id, e.first_name_th, e.last_name_th, p.position_name_th, b.branch_name_th, c.company_name_th
              ORDER BY e.first_name_th, e.last_name_th";
    $stmt = $mysqli->prepare($sql);
    hrScopeBindParams($stmt, $types, $params);
    $stmt->execute();
    return attendanceBuildImportEmployeeRows($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

function normalizeAttendanceAdjustmentPayload(array $payload) {
    $workDate = trim((string)($payload['work_date'] ?? ''));
    $checkIn = attendanceNormalizeTime($payload['override_check_in'] ?? '');
    $checkOut = attendanceNormalizeTime($payload['override_check_out'] ?? '');
    $reason = trim((string)($payload['reason'] ?? ''));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) {
        sendJsonError('Invalid date');
    }
    if ($checkIn === null && $checkOut === null) {
        sendJsonError('กรุณาระบุเวลาเข้า หรือเวลาออก');
    }
    if ($reason === '') {
        sendJsonError('กรุณาระบุเหตุผล');
    }

    return [$workDate, $checkIn, $checkOut, $reason];
}

function saveAttendanceOverrideRow(mysqli $mysqli, $employeeId, $workDate, $checkIn, $checkOut, $reason, $userId) {
    attendanceEnsureOverrideTable($mysqli);
    $stmt = $mysqli->prepare("INSERT INTO attendance_record_overrides
        (employee_id, work_date, override_check_in, override_check_out, reason, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            override_check_in = VALUES(override_check_in),
            override_check_out = VALUES(override_check_out),
            reason = VALUES(reason),
            updated_by = VALUES(created_by),
            updated_at = NOW()");
    $stmt->bind_param('issssi', $employeeId, $workDate, $checkIn, $checkOut, $reason, $userId);
    if (!$stmt->execute()) {
        throw new Exception('Save attendance override failed: ' . $stmt->error);
    }
    return $employeeId;
}

function saveAttendanceAdjustment(mysqli $mysqli, $role, array $payload) {
    $employeeId = (int)($payload['employee_id'] ?? 0);
    if ($employeeId <= 0 || !attendanceCanViewEmployee($mysqli, $employeeId)) {
        sendJsonError('Access Denied');
    }
    [$workDate, $checkIn, $checkOut, $reason] = normalizeAttendanceAdjustmentPayload($payload);
    saveAttendanceOverrideRow($mysqli, $employeeId, $workDate, $checkIn, $checkOut, $reason, (int)$_SESSION['user_id']);
    return 1;
}

function saveBulkAttendanceAdjustments(mysqli $mysqli, $role, array $payload) {
    [$workDate, $checkIn, $checkOut, $reason] = normalizeAttendanceAdjustmentPayload($payload);
    $employeeIds = array_values(array_unique(array_map('intval', $payload['employee_ids'] ?? [])));
    $employeeIds = array_filter($employeeIds, fn($id) => $id > 0);
    if (!$employeeIds) {
        sendJsonError('กรุณาเลือกพนักงาน');
    }

    $mysqli->begin_transaction();
    try {
        foreach ($employeeIds as $employeeId) {
            if (!attendanceCanViewEmployee($mysqli, $employeeId)) {
                throw new InvalidArgumentException('Access Denied');
            }
            saveAttendanceOverrideRow($mysqli, $employeeId, $workDate, $checkIn, $checkOut, $reason, (int)$_SESSION['user_id']);
        }
        $mysqli->commit();
        return count($employeeIds);
    } catch (Throwable $e) {
        $mysqli->rollback();
        if ($e instanceof InvalidArgumentException) {
            sendJsonError($e->getMessage());
        }
        throw $e;
    }
}

function fetchAttendanceAdjustmentEmployees(mysqli $mysqli, $role, $workDate, array $filters) {
    attendanceEnsureOverrideTable($mysqli);
    $sql = "SELECT e.id AS employee_id, e.citizen_id, e.first_name_th, e.last_name_th,
                   p.position_name_th, b.branch_name_th, c.company_name_th,
                   ar.check_in AS raw_check_in, ar.check_out AS raw_check_out,
                   aro.override_check_in, aro.override_check_out, aro.reason AS override_reason
            FROM employees e
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN branches b ON e.branch_id = b.id
            LEFT JOIN companies c ON e.company_id = c.id
            LEFT JOIN attendance_records ar ON ar.employee_id = e.id AND ar.work_date = ?
            LEFT JOIN attendance_record_overrides aro ON aro.employee_id = e.id AND aro.work_date = ?
            WHERE e.status IN ('active', 'probation')";
    $types = 'ss';
    $params = [$workDate, $workDate];

    if ($role === 'hr') {
        $scopeClause = hrScopeBuildEmployeeWhereClause($role, hrScopeCurrentSessionScopes(), 'e');
        $sql .= $scopeClause['sql'];
        $types .= $scopeClause['types'];
        $params = array_merge($params, $scopeClause['params']);
    }

    foreach (['position_id' => 'e.position_id', 'branch_id' => 'e.branch_id', 'company_id' => 'e.company_id'] as $key => $column) {
        if ((int)($filters[$key] ?? 0) > 0) {
            $sql .= " AND {$column} = ?";
            $types .= 'i';
            $params[] = (int)$filters[$key];
        }
    }

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $sql .= " AND (e.first_name_th LIKE ? OR e.last_name_th LIKE ? OR e.citizen_id LIKE ?)";
        $types .= 'sss';
        $term = '%' . $search . '%';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $sql .= " ORDER BY e.first_name_th, e.last_name_th";
    $stmt = $mysqli->prepare($sql);
    hrScopeBindParams($stmt, $types, $params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchAttendanceMissingScanEmployees(mysqli $mysqli, $role, array $filters) {
    $sql = "SELECT e.id, e.citizen_id, e.first_name_th, e.last_name_th,
                   e.company_id, e.branch_id,
                   p.position_name_th, b.branch_name_th, c.company_name_th,
                   ws.start_time, ws.end_time, ws.late_tolerance_mins, ws.work_days
            FROM employees e
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN branches b ON e.branch_id = b.id
            LEFT JOIN companies c ON e.company_id = c.id
            LEFT JOIN work_shifts ws ON e.default_shift_id = ws.id
            WHERE e.status IN ('active', 'probation')";
    $types = '';
    $params = [];

    if ($role === 'hr') {
        $scopeClause = hrScopeBuildEmployeeWhereClause($role, hrScopeCurrentSessionScopes(), 'e');
        $sql .= $scopeClause['sql'];
        $types .= $scopeClause['types'];
        $params = array_merge($params, $scopeClause['params']);
    }

    $companyId = (int)($filters['company_id'] ?? 0);
    if ($companyId > 0) {
        $sql .= " AND e.company_id = ?";
        $types .= 'i';
        $params[] = $companyId;
    }

    $branchId = (int)($filters['branch_id'] ?? 0);
    if ($branchId > 0) {
        $sql .= " AND e.branch_id = ?";
        $types .= 'i';
        $params[] = $branchId;
    }

    $sql .= " ORDER BY c.company_name_th, b.branch_name_th, e.first_name_th, e.last_name_th";
    $stmt = $mysqli->prepare($sql);
    if ($types !== '') {
        hrScopeBindParams($stmt, $types, $params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchAttendanceAdjustmentFilterOptions(mysqli $mysqli, $role) {
    $sql = "SELECT e.company_id, c.company_name_th,
                   e.branch_id, b.branch_name_th,
                   e.position_id, p.position_name_th
            FROM employees e
            LEFT JOIN companies c ON e.company_id = c.id
            LEFT JOIN branches b ON e.branch_id = b.id
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE e.status IN ('active', 'probation')";
    $types = '';
    $params = [];

    if ($role === 'hr') {
        $scopeClause = hrScopeBuildEmployeeWhereClause($role, hrScopeCurrentSessionScopes(), 'e');
        $sql .= $scopeClause['sql'];
        $types .= $scopeClause['types'];
        $params = array_merge($params, $scopeClause['params']);
    }

    $stmt = $mysqli->prepare($sql);
    if ($types !== '') {
        hrScopeBindParams($stmt, $types, $params);
    }
    $stmt->execute();

    $companies = [];
    $branches = [];
    $positions = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        if (!empty($row['company_id'])) {
            $companies[(int)$row['company_id']] = $row['company_name_th'] ?: '-';
        }
        if (!empty($row['branch_id'])) {
            $branches[(int)$row['branch_id']] = [
                'label' => $row['branch_name_th'] ?: '-',
                'company_id' => (int)$row['company_id'],
            ];
        }
        if (!empty($row['position_id'])) {
            $positions[(int)$row['position_id']] = $row['position_name_th'] ?: '-';
        }
    }

    return [
        'companies' => attendanceBuildFilterOptionRows($companies),
        'branches' => attendanceBuildFilterOptionRows($branches),
        'positions' => attendanceBuildFilterOptionRows($positions),
    ];
}

function attendanceBuildFilterOptionRows(array $items) {
    $rows = [];
    foreach ($items as $id => $label) {
        if (is_array($label)) {
            $rows[] = ['id' => (int)$id, 'label' => $label['label'], 'company_id' => $label['company_id']];
        } else {
            $rows[] = ['id' => (int)$id, 'label' => $label];
        }
    }
    usort($rows, fn($a, $b) => strnatcmp((string)$a['label'], (string)$b['label']));
    return $rows;
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
