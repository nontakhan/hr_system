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
        $timeRequestTypeFilter = leaveApprovalNormalizeTimeRequestTypeFilter($_GET['time_request_type'] ?? '');
        $scopeClause = hrScopeBuildEmployeeWhereClause($my_role, hrScopeCurrentSessionScopes(), 'e');

                $sql = "SELECT lr.*,
                       lr.cancellation_reason AS cancel_reason,
                       e.first_name_th, e.last_name_th, e.citizen_id as employee_code, e.profile_img_url,
                       lt.type_name,
                       la.file_path, la.file_name,
                       CONCAT_WS(' ', ma.first_name_th, ma.last_name_th) AS manager_approver_name,
                       CONCAT_WS(' ', ha.first_name_th, ha.last_name_th) AS hr_approver_name
                FROM leave_requests lr
                JOIN employees e ON lr.employee_id = e.id
                JOIN leave_types lt ON lr.leave_type_id = lt.id
                LEFT JOIN leave_attachments la ON lr.id = la.leave_request_id
                LEFT JOIN employees ma ON lr.manager_approver_id = ma.id
                LEFT JOIN employees ha ON lr.hr_approver_id = ha.id
                WHERE 1=1 ";

        $types = '';
        $params = [];

        if ($requestUnitFilter === 'hour') {
            $sql .= " AND lr.request_unit = 'hour' AND lr.time_request_type IS NOT NULL ";
            if ($timeRequestTypeFilter === 'overtime_after_work') {
                $sql .= " AND lr.time_request_type = 'overtime_after_work' ";
            } elseif ($timeRequestTypeFilter === 'late_early') {
                $sql .= " AND lr.time_request_type IN ('late_arrival','early_departure') ";
            }
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
        echo json_encode(['status' => 'success', 'data' => $rows]);
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        $requestUnitFilter = $input['request_unit'] ?? 'day';
        $timeRequestTypeFilter = leaveApprovalNormalizeTimeRequestTypeFilter($input['time_request_type'] ?? '');
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
            if ($timeRequestTypeFilter === 'overtime_after_work') {
                $auth_sql .= " AND lr.time_request_type = 'overtime_after_work'";
            } elseif ($timeRequestTypeFilter === 'late_early') {
                $auth_sql .= " AND lr.time_request_type IN ('late_arrival','early_departure')";
            }
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
                $approvedMinutes = max(1, (int)$request['request_minutes']);
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

function leaveApprovalNormalizeTimeRequestTypeFilter($value) {
    if ($value === 'overtime_after_work') {
        return 'overtime_after_work';
    }
    if ($value === 'late_early') {
        return 'late_early';
    }
    return '';
}

$mysqli->close();
?>
