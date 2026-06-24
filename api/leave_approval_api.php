<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

function sendJsonError($message) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit();
}

try {
    if (session_status() == PHP_SESSION_NONE) session_start();
    require_once '../includes/db_connect.php';
    require_once '../includes/attendance_helpers.php';
    require_once '../includes/day_swap_helpers.php';
    require_once '../includes/employee_shift_assignment_helpers.php';
    require_once '../includes/leave_helpers.php';
    require_once '../includes/hr_scope_helpers.php';
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        sendJsonError('กรุณาเข้าสู่ระบบ');
    }

    leaveEnsureTwoStepApprovalColumns($mysqli);

    $my_emp_id = (int)($_SESSION['employee_id'] ?? 0);
    $my_role = $_SESSION['role'] ?? 'employee';
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $type = $_GET['type'] ?? 'pending';
        $requestUnitFilter = $_GET['request_unit'] ?? 'day';
        $scopeClause = hrScopeBuildEmployeeWhereClause($my_role, hrScopeCurrentSessionScopes(), 'e');

                $sql = "SELECT lr.*,
                       lr.cancellation_reason AS cancel_reason,
                       e.first_name_th, e.last_name_th, e.citizen_id as employee_code, e.profile_img_url,
                       lt.type_name,
                       ar.check_out AS raw_check_out,
                       la.file_path, la.file_name,
                       CONCAT_WS(' ', ma.first_name_th, ma.last_name_th) AS manager_approver_name,
                       CONCAT_WS(' ', ha.first_name_th, ha.last_name_th) AS hr_approver_name
                FROM leave_requests lr
                JOIN employees e ON lr.employee_id = e.id
                JOIN leave_types lt ON lr.leave_type_id = lt.id
                LEFT JOIN attendance_records ar ON ar.employee_id = lr.employee_id AND ar.work_date = lr.start_date
                LEFT JOIN leave_attachments la ON lr.id = la.leave_request_id
                LEFT JOIN employees ma ON lr.manager_approver_id = ma.id
                LEFT JOIN employees ha ON lr.hr_approver_id = ha.id
                WHERE 1=1 ";

        $types = '';
        $params = [];

        if ($requestUnitFilter === 'hour') {
            $sql .= " AND lr.request_unit = 'hour' AND lr.time_request_type IS NOT NULL ";
        } else {
            $sql .= " AND (lr.request_unit IS NULL OR lr.request_unit <> 'hour' OR lr.time_request_type IS NULL) ";
        }

        if ($my_role === 'admin') {
            // Admin sees all rows in the requested stage.
        } elseif ($my_role === 'hr') {
            $sql .= $scopeClause['sql'];
            $types .= $scopeClause['types'];
            $params = array_merge($params, $scopeClause['params']);
        } else {
            $sql .= " AND e.supervisor_id = ? ";
            $types .= 'i';
            $params[] = $my_emp_id;
        }

        if ($type === 'pending') {
            if ($my_role === 'hr') {
                $sql .= " AND lr.status IN ('pending_hr','pending_cancel_hr') ";
            } elseif ($my_role === 'admin') {
                $sql .= " AND lr.status IN ('pending','pending_manager','pending_hr','pending_cancel_hr') ";
            } else {
                $sql .= " AND lr.status IN ('pending','pending_manager') ";
            }
        } else {
            if ($my_role === 'admin' || $my_role === 'hr') {
                $sql .= " AND lr.status IN ('approved','rejected','cancelled') ";
            } else {
                $sql .= " AND lr.status IN ('pending_hr','approved','rejected') ";
            }
        }

        $sql .= " ORDER BY lr.created_at DESC";
        $stmt = $mysqli->prepare($sql);
        hrScopeBindParams($stmt, $types, $params);
        $stmt->execute();

        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        if ($requestUnitFilter === 'hour') {
            $rows = leaveApprovalAttachOvertimeScanDetails($mysqli, $rows);
        }
        echo json_encode(['status' => 'success', 'data' => $rows]);
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        $requestUnitFilter = $input['request_unit'] ?? 'day';
        $req_id = (int)($input['request_id'] ?? 0);
        $reason = trim((string)($input['reason'] ?? ''));

        if (!in_array($action, ['approve', 'reject'], true)) {
            sendJsonError('Invalid Action');
        }
        if ($req_id <= 0) {
            sendJsonError('Invalid request ID');
        }

        $scopeClause = hrScopeBuildEmployeeWhereClause($my_role, hrScopeCurrentSessionScopes(), 'e');
        $auth_sql = "SELECT lr.id, lr.employee_id, lr.start_date, lr.request_minutes, lr.time_request_type, lr.status, e.supervisor_id
                     FROM leave_requests lr
                     JOIN employees e ON lr.employee_id = e.id
                     WHERE lr.id = ?";
        $types = 'i';
        $params = [$req_id];

        if ($requestUnitFilter === 'hour') {
            $auth_sql .= " AND lr.request_unit = 'hour' AND lr.time_request_type IS NOT NULL";
        } else {
            $auth_sql .= " AND (lr.request_unit IS NULL OR lr.request_unit <> 'hour' OR lr.time_request_type IS NULL)";
        }

        if ($my_role === 'admin') {
            // Admin may act on the current stage.
        } elseif ($my_role === 'hr') {
            $auth_sql .= $scopeClause['sql'];
            $types .= $scopeClause['types'];
            $params = array_merge($params, $scopeClause['params']);
        } else {
            $auth_sql .= " AND e.supervisor_id = ?";
            $types .= 'i';
            $params[] = $my_emp_id;
        }

        $auth_stmt = $mysqli->prepare($auth_sql);
        hrScopeBindParams($auth_stmt, $types, $params);
        $auth_stmt->execute();
        $request = $auth_stmt->get_result()->fetch_assoc();
        if (!$request) {
            sendJsonError('Access Denied');
        }

        $currentStatus = $request['status'] === 'pending' ? 'pending_manager' : $request['status'];
        $now = date('Y-m-d H:i:s');
        $stmt = null;

        if ($currentStatus === 'pending_manager') {
            if (!($my_role === 'admin' || (int)$request['supervisor_id'] === $my_emp_id)) {
                sendJsonError('Access Denied');
            }

            if ($action === 'approve') {
                $stmt = $mysqli->prepare("UPDATE leave_requests
                                          SET status = 'pending_hr',
                                              manager_approver_id = ?,
                                              manager_approval_date = ?
                                          WHERE id = ? AND status IN ('pending','pending_manager')");
                $stmt->bind_param('isi', $my_emp_id, $now, $req_id);
            } else {
                $stmt = $mysqli->prepare("UPDATE leave_requests
                                          SET status = 'rejected',
                                              manager_approver_id = ?,
                                              manager_approval_date = ?,
                                              approver_id = ?,
                                              approval_date = ?,
                                              rejection_reason = ?
                                          WHERE id = ? AND status IN ('pending','pending_manager')");
                $stmt->bind_param('isissi', $my_emp_id, $now, $my_emp_id, $now, $reason, $req_id);
            }
        } elseif ($currentStatus === 'pending_hr') {
            if (!in_array($my_role, ['admin', 'hr'], true)) {
                sendJsonError('Access Denied');
            }

            if ($action === 'approve' && ($request['time_request_type'] ?? '') === 'overtime_after_work') {
                $shift = leaveApprovalFetchEffectiveShift($mysqli, (int)$request['employee_id'], $request['start_date']);
                $checkOut = leaveApprovalFetchAttendanceCheckOut($mysqli, (int)$request['employee_id'], $request['start_date']);
                $ot = attendanceCalculateOvertimeAfterWorkMinutes($request['start_date'], $shift['end_time'] ?? null, $checkOut, (int)$request['request_minutes']);
                if (!$ot['valid']) {
                    sendJsonError($ot['message']);
                }
                $approvedMinutes = (int)$ot['approved_minutes'];
                $newStatus = 'approved';
                $rejectReason = null;
                $stmt = $mysqli->prepare("UPDATE leave_requests
                                          SET status = ?,
                                              approved_request_minutes = ?,
                                              hr_approver_id = ?,
                                              hr_approval_date = ?,
                                              approver_id = ?,
                                              approval_date = ?,
                                              rejection_reason = ?
                                          WHERE id = ? AND status = 'pending_hr'");
                $stmt->bind_param('siisissi', $newStatus, $approvedMinutes, $my_emp_id, $now, $my_emp_id, $now, $rejectReason, $req_id);
            } else {
                $newStatus = $action === 'approve' ? 'approved' : 'rejected';
                $rejectReason = $action === 'approve' ? null : $reason;
                $stmt = $mysqli->prepare("UPDATE leave_requests
                                          SET status = ?,
                                              hr_approver_id = ?,
                                              hr_approval_date = ?,
                                              approver_id = ?,
                                              approval_date = ?,
                                              rejection_reason = ?
                                          WHERE id = ? AND status = 'pending_hr'");
                $stmt->bind_param('sisissi', $newStatus, $my_emp_id, $now, $my_emp_id, $now, $rejectReason, $req_id);
            }
        } elseif ($currentStatus === 'pending_cancel_hr') {
            if (!in_array($my_role, ['admin', 'hr'], true)) {
                sendJsonError('Access Denied');
            }

            if ($action === 'approve') {
                $stmt = $mysqli->prepare("UPDATE leave_requests
                                          SET status = 'cancelled',
                                              hr_approver_id = ?,
                                              hr_approval_date = ?,
                                              approver_id = ?,
                                              approval_date = ?,
                                              rejection_reason = NULL
                                          WHERE id = ? AND status = 'pending_cancel_hr'");
                $stmt->bind_param('isisi', $my_emp_id, $now, $my_emp_id, $now, $req_id);
            } else {
                $stmt = $mysqli->prepare("UPDATE leave_requests
                                          SET status = 'approved',
                                              hr_approver_id = ?,
                                              hr_approval_date = ?,
                                              approver_id = ?,
                                              approval_date = ?,
                                              rejection_reason = ?
                                          WHERE id = ? AND status = 'pending_cancel_hr'");
                $stmt->bind_param('isissi', $my_emp_id, $now, $my_emp_id, $now, $reason, $req_id);
            }
        } else {
            sendJsonError('Request was already processed');
        }

        if ($stmt && $stmt->execute() && $stmt->affected_rows === 1) {
            echo json_encode(['status' => 'success', 'message' => 'บันทึกผลการพิจารณาเรียบร้อย']);
        } else {
            throw new Exception($stmt ? ($stmt->error ?: 'Leave request was already processed') : 'Invalid approval stage');
        }
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    sendJsonError('System Error');
}

function leaveApprovalAttachOvertimeScanDetails(mysqli $mysqli, array $rows) {
    foreach ($rows as &$row) {
        if (($row['time_request_type'] ?? '') !== 'overtime_after_work') {
            continue;
        }

        $employeeId = (int)($row['employee_id'] ?? 0);
        $workDate = (string)($row['start_date'] ?? '');
        $shift = leaveApprovalFetchEffectiveShift($mysqli, $employeeId, $workDate);
        $checkOut = leaveApprovalFetchAttendanceCheckOut($mysqli, $employeeId, $workDate);
        $calc = attendanceCalculateOvertimeAfterWorkMinutes($workDate, $shift['end_time'] ?? null, $checkOut, (int)($row['request_minutes'] ?? 0));

        $row['shift_end_time'] = $shift['end_time'] ?? null;
        $row['actual_check_out'] = $checkOut;
        $row['eligible_overtime_minutes'] = $calc['eligible_minutes'] ?? 0;
        $row['approval_overtime_minutes'] = $calc['approved_minutes'] ?? 0;
        $row['overtime_scan_valid'] = (bool)($calc['valid'] ?? false);
        $row['overtime_scan_message'] = $calc['message'] ?? '';
    }
    unset($row);
    return $rows;
}

function leaveApprovalFetchAttendanceCheckOut(mysqli $mysqli, $employeeId, $workDate) {
    leaveApprovalEnsureAttendanceOverrideTable($mysqli);
    $stmt = $mysqli->prepare("SELECT ar.check_out, aro.override_check_out
                              FROM attendance_records ar
                              LEFT JOIN attendance_record_overrides aro
                                ON aro.employee_id = ar.employee_id
                               AND aro.work_date = ar.work_date
                              WHERE ar.employee_id = ? AND ar.work_date = ?
                              LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('is', $employeeId, $workDate);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return null;
    }
    return attendanceNormalizeTime($row['override_check_out'] ?? null) ?? attendanceNormalizeTime($row['check_out'] ?? null);
}

function leaveApprovalEnsureAttendanceOverrideTable(mysqli $mysqli) {
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
    $mysqli->query($sql);
}

function leaveApprovalFetchEffectiveShift(mysqli $mysqli, $employeeId, $workDate) {
    if ($employeeId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$workDate)) {
        return [];
    }

    $stmt = $mysqli->prepare("SELECT e.id, ws.start_time, ws.end_time, ws.late_tolerance_mins, ws.work_days
                              FROM employees e
                              LEFT JOIN work_shifts ws ON e.default_shift_id = ws.id
                              WHERE e.id = ?
                              LIMIT 1");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $employee = $stmt->get_result()->fetch_assoc();
    if (!$employee) {
        return [];
    }

    $month = substr($workDate, 0, 7);
    $baseShift = [
        'start_time' => $employee['start_time'] ?? null,
        'end_time' => $employee['end_time'] ?? null,
        'late_tolerance_mins' => $employee['late_tolerance_mins'] ?? 0,
        'work_days' => $employee['work_days'] ?? '',
    ];
    $assignments = employeeShiftAssignmentsFetchForMonth($mysqli, $employeeId, $month);
    $assignmentShift = employeeShiftAssignmentsResolveForDate($assignments, $baseShift, $workDate);
    $effectiveShift = attendanceResolveShiftForDate($assignmentShift, leaveApprovalFetchShiftOverridesForMonth($mysqli, $employeeId, $month), $workDate);
    $swapMap = attendanceBuildApprovedDaySwapMap(daySwapFetchApprovedRowsForMonth($mysqli, $employeeId, $month), $employeeId, $month);
    if (isset($swapMap[$workDate])) {
        $effectiveShift = attendanceApplyDayTypeOverride($effectiveShift, $workDate, $swapMap[$workDate]);
    }
    return $effectiveShift;
}

function leaveApprovalFetchShiftOverridesForMonth(mysqli $mysqli, $employeeId, $month) {
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

$mysqli->close();
?>
