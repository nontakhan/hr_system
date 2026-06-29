<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

function sendDaySwapJson($payload) {
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

function sendDaySwapError($message) {
    sendDaySwapJson(['status' => 'error', 'message' => $message]);
}

try {
    if (session_status() == PHP_SESSION_NONE) session_start();
    require_once '../includes/db_connect.php';
    require_once '../includes/attendance_helpers.php';
    require_once '../includes/day_swap_helpers.php';
    require_once '../includes/hr_scope_helpers.php';

    if (!isset($_SESSION['user_id'])) {
        sendDaySwapError('Login Required');
    }

    daySwapEnsureTable($mysqli);

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');
    $myEmployeeId = (int)($_SESSION['employee_id'] ?? 0);
    $myRole = $_SESSION['role'] ?? 'employee';
    $myCompanyId = (int)($_SESSION['company_id'] ?? 0);

    if ($method === 'GET') {
        if ($action === 'employees') {
            $sql = "SELECT id, citizen_id, first_name_th, last_name_th
                    FROM employees e
                    WHERE status IN ('active', 'probation')
                      AND id <> ?";
            $types = 'i';
            $params = [$myEmployeeId];

            if ($myRole === 'hr') {
                $scopeClause = hrScopeBuildEmployeeWhereClause($myRole, hrScopeCurrentSessionScopes(), 'e');
                $sql .= $scopeClause['sql'];
                $types .= $scopeClause['types'];
                $params = array_merge($params, $scopeClause['params']);
            } elseif ($myRole !== 'admin') {
                $sql .= " AND company_id = ?";
                $types .= 'i';
                $params[] = $myCompanyId;
            }

            $sql .= " ORDER BY first_name_th, last_name_th";
            $stmt = $mysqli->prepare($sql);
            hrScopeBindParams($stmt, $types, $params);
            $stmt->execute();
            sendDaySwapJson(['status' => 'success', 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        }

        if ($action === 'holidays') {
            $employeeId = (int)($_GET['employee_id'] ?? $myEmployeeId);
            $month = $_GET['month'] ?? date('Y-m');
            if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
                sendDaySwapError('Invalid month');
            }
            if ($employeeId !== $myEmployeeId && !daySwapCanViewEmployee($mysqli, $employeeId, $myRole, $myCompanyId)) {
                sendDaySwapError('Access Denied');
            }
            sendDaySwapJson(['status' => 'success', 'data' => daySwapBuildHolidayOptions($mysqli, $employeeId, $month)]);
        }

        if ($action === 'my_requests') {
            $stmt = $mysqli->prepare("SELECT dsr.*, dsr.created_via, dsr.created_by_role, dsr.proxy_note,
                                             CONCAT_WS(' ', te.first_name_th, te.last_name_th) AS target_name,
                                             CONCAT_WS(' ', re.first_name_th, re.last_name_th) AS requester_name,
                                             CONCAT_WS(' ', ae.first_name_th, ae.last_name_th) AS approver_name,
                                             CONCAT_WS(' ', pce.first_name_th, pce.last_name_th) AS proxy_creator_name
                                      FROM day_swap_requests dsr
                                      JOIN employees te ON dsr.target_employee_id = te.id
                                      JOIN employees re ON dsr.requester_employee_id = re.id
                                      LEFT JOIN employees ae ON dsr.approver_id = ae.id
                                      LEFT JOIN employees pce ON dsr.created_by_employee_id = pce.id
                                      WHERE dsr.requester_employee_id = ? OR dsr.target_employee_id = ?
                                      ORDER BY dsr.created_at DESC");
            $stmt->bind_param('ii', $myEmployeeId, $myEmployeeId);
            $stmt->execute();
            sendDaySwapJson(['status' => 'success', 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        }

        if ($action === 'pending' || $action === 'history') {
            if (!in_array($myRole, ['manager', 'hr', 'admin'], true)) {
                sendDaySwapError('Access Denied');
            }
            $scopes = hrScopeCurrentSessionScopes();
            $sql = daySwapApprovalQuery($action, $myRole, $scopes);
            $stmt = $mysqli->prepare($sql);
            if ($myRole === 'hr') {
                $scopeClause = hrScopeBuildEmployeeWhereClause($myRole, $scopes, 're');
                hrScopeBindParams($stmt, $scopeClause['types'], $scopeClause['params']);
            } elseif ($myRole !== 'admin') {
                $stmt->bind_param('i', $myEmployeeId);
            }
            $stmt->execute();
            sendDaySwapJson(['status' => 'success', 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        }

        sendDaySwapError('Invalid Action');
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $postAction = $input['action'] ?? $action;

        if ($postAction === 'create') {
            $targetId = (int)($input['target_employee_id'] ?? 0);
            $requesterDate = trim((string)($input['requester_date'] ?? ''));
            $targetDate = trim((string)($input['target_date'] ?? ''));
            $reason = trim((string)($input['reason'] ?? ''));

            if ($targetId <= 0 || $targetId === $myEmployeeId) sendDaySwapError('กรุณาเลือกพนักงานที่ต้องการสลับ');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $requesterDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) sendDaySwapError('กรุณาเลือกวันที่ให้ครบ');
            if ($reason === '') sendDaySwapError('กรุณาระบุเหตุผล');
            if (!daySwapCanViewEmployee($mysqli, $targetId, $myRole, $myCompanyId)) sendDaySwapError('Access Denied');

            $requesterMonth = substr($requesterDate, 0, 7);
            $targetMonth = substr($targetDate, 0, 7);
            if (!daySwapDateIsSelectableHoliday($mysqli, $myEmployeeId, $requesterDate, $requesterMonth)) sendDaySwapError('วันที่ของคุณไม่ใช่วันหยุดปกติที่เลือกได้');
            if (!daySwapDateIsSelectableHoliday($mysqli, $targetId, $targetDate, $targetMonth)) sendDaySwapError('วันที่ของพนักงานที่เลือกไม่ใช่วันหยุดปกติที่เลือกได้');

            $conflict = daySwapHasPendingOrApprovedConflict($mysqli, $myEmployeeId, $targetId, $requesterDate, $targetDate);
            if ($conflict) sendDaySwapError('มีคำขอสลับวันหยุดของวันที่เลือกอยู่แล้ว');

            $stmt = $mysqli->prepare("INSERT INTO day_swap_requests
                (requester_employee_id, target_employee_id, requester_date, target_date, reason, status)
                VALUES (?, ?, ?, ?, ?, 'pending_manager')");
            $stmt->bind_param('iisss', $myEmployeeId, $targetId, $requesterDate, $targetDate, $reason);
            if ($stmt->execute()) {
                sendDaySwapJson(['status' => 'success', 'message' => 'ส่งคำขอสลับวันหยุดเรียบร้อยแล้ว']);
            }
            throw new Exception($stmt->error);
        }

        if ($postAction === 'approve' || $postAction === 'reject') {
            if (!in_array($myRole, ['manager', 'hr', 'admin'], true)) sendDaySwapError('Access Denied');
            $requestId = (int)($input['request_id'] ?? 0);
            $reason = trim((string)($input['reason'] ?? ''));
            if ($requestId <= 0) sendDaySwapError('Invalid request ID');

            $request = daySwapFetchApprovableRequest($mysqli, $requestId, $myRole, $myEmployeeId, hrScopeCurrentSessionScopes());
            if (!$request) {
                sendDaySwapError('Access Denied');
            }

            $currentStatus = $request['status'] === 'pending' ? 'pending_manager' : $request['status'];
            $now = date('Y-m-d H:i:s');
            $stmt = null;

            if ($currentStatus === 'pending_manager') {
                if (!($myRole === 'admin' || (int)$request['supervisor_id'] === $myEmployeeId)) {
                    sendDaySwapError('Access Denied');
                }

                if ($postAction === 'approve') {
                    $stmt = $mysqli->prepare("UPDATE day_swap_requests
                                              SET status = 'pending_hr',
                                                  manager_approver_id = ?,
                                                  manager_approval_date = ?
                                              WHERE id = ? AND status IN ('pending','pending_manager')");
                    $stmt->bind_param('isi', $myEmployeeId, $now, $requestId);
                } else {
                    $stmt = $mysqli->prepare("UPDATE day_swap_requests
                                              SET status = 'rejected',
                                                  manager_approver_id = ?,
                                                  manager_approval_date = ?,
                                                  approver_id = ?,
                                                  approval_date = ?,
                                                  rejection_reason = ?
                                              WHERE id = ? AND status IN ('pending','pending_manager')");
                    $stmt->bind_param('isissi', $myEmployeeId, $now, $myEmployeeId, $now, $reason, $requestId);
                }
            } elseif ($currentStatus === 'pending_hr') {
                if (!in_array($myRole, ['admin', 'hr'], true)) sendDaySwapError('Access Denied');
                $newStatus = $postAction === 'approve' ? 'approved' : 'rejected';
                $rejectReason = $postAction === 'approve' ? null : $reason;
                $stmt = $mysqli->prepare("UPDATE day_swap_requests
                                          SET status = ?,
                                              hr_approver_id = ?,
                                              hr_approval_date = ?,
                                              approver_id = ?,
                                              approval_date = ?,
                                              rejection_reason = ?
                                          WHERE id = ? AND status = 'pending_hr'");
                $stmt->bind_param('sisissi', $newStatus, $myEmployeeId, $now, $myEmployeeId, $now, $rejectReason, $requestId);
            } else {
                sendDaySwapError('Request was already processed');
            }

            if ($stmt && $stmt->execute() && $stmt->affected_rows === 1) {
                sendDaySwapJson(['status' => 'success', 'message' => 'บันทึกผลการพิจารณาเรียบร้อย']);
            }
            throw new Exception($stmt ? ($stmt->error ?: 'Request was already processed') : 'Invalid approval stage');
        }

        sendDaySwapError('Invalid Action');
    }

    sendDaySwapError('Method Not Allowed');
} catch (Throwable $e) {
    error_log($e->getMessage());
    sendDaySwapError('System Error');
}

function daySwapCanViewEmployee($mysqli, $employeeId, $role, $companyId) {
    if ($role === 'admin') return true;
    $sql = "SELECT id FROM employees e WHERE id = ? AND status IN ('active', 'probation')";
    $types = 'i';
    $params = [(int)$employeeId];

    if ($role === 'hr') {
        $scopeClause = hrScopeBuildEmployeeWhereClause($role, hrScopeCurrentSessionScopes(), 'e');
        $sql .= $scopeClause['sql'];
        $types .= $scopeClause['types'];
        $params = array_merge($params, $scopeClause['params']);
    } else {
        $sql .= " AND company_id = ?";
        $types .= 'i';
        $params[] = (int)$companyId;
    }

    $stmt = $mysqli->prepare($sql);
    hrScopeBindParams($stmt, $types, $params);
    $stmt->execute();
    return $stmt->get_result()->num_rows === 1;
}

function daySwapDateIsSelectableHoliday($mysqli, $employeeId, $date, $month) {
    foreach (daySwapBuildHolidayOptions($mysqli, $employeeId, $month) as $day) {
        if ($day['date'] === $date) {
            return true;
        }
    }
    return false;
}

function daySwapHasPendingOrApprovedConflict($mysqli, $employeeId, $targetId, $requesterDate, $targetDate) {
    $checks = [
        [$employeeId, $requesterDate],
        [$employeeId, $targetDate],
        [$targetId, $requesterDate],
        [$targetId, $targetDate],
    ];
    $stmt = $mysqli->prepare("SELECT id
                              FROM day_swap_requests
                              WHERE status IN ('pending','pending_manager','pending_hr','approved')
                                AND (
                                    (requester_employee_id = ? AND (requester_date = ? OR target_date = ?))
                                    OR (target_employee_id = ? AND (requester_date = ? OR target_date = ?))
                                )
                              LIMIT 1");

    foreach ($checks as $check) {
        [$checkEmployeeId, $checkDate] = $check;
        $stmt->bind_param('ississ', $checkEmployeeId, $checkDate, $checkDate, $checkEmployeeId, $checkDate, $checkDate);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            return true;
        }
    }

    return false;
}

function daySwapApprovalQuery($type, $role, array $scopes) {
    $sql = "SELECT dsr.*, dsr.created_via, dsr.created_by_role, dsr.proxy_note,
                   CONCAT_WS(' ', re.first_name_th, re.last_name_th) AS requester_name,
                   re.citizen_id AS requester_code,
                   CONCAT_WS(' ', te.first_name_th, te.last_name_th) AS target_name,
                   te.citizen_id AS target_code,
                   CONCAT_WS(' ', ae.first_name_th, ae.last_name_th) AS approver_name,
                   CONCAT_WS(' ', pce.first_name_th, pce.last_name_th) AS proxy_creator_name
            FROM day_swap_requests dsr
            JOIN employees re ON dsr.requester_employee_id = re.id
            JOIN employees te ON dsr.target_employee_id = te.id
            LEFT JOIN employees ae ON dsr.approver_id = ae.id
            LEFT JOIN employees pce ON dsr.created_by_employee_id = pce.id
            WHERE 1=1";

    if ($role === 'hr') {
        $scopeClause = hrScopeBuildEmployeeWhereClause($role, $scopes, 're');
        $sql .= $scopeClause['sql'];
    } elseif ($role !== 'admin') {
        $sql .= " AND re.supervisor_id = ?";
    }

    if ($type === 'pending') {
        if ($role === 'hr') {
            $sql .= " AND dsr.status = 'pending_hr'";
        } elseif ($role === 'admin') {
            $sql .= " AND dsr.status IN ('pending','pending_manager','pending_hr')";
        } else {
            $sql .= " AND dsr.status IN ('pending','pending_manager')";
        }
    } else {
        $sql .= " AND dsr.status IN ('approved','rejected')";
    }
    $sql .= " ORDER BY dsr.created_at DESC";

    return $sql;
}

function daySwapFetchApprovableRequest($mysqli, $requestId, $role, $myEmployeeId, array $scopes) {
    $sql = "SELECT dsr.id, dsr.status, re.supervisor_id
            FROM day_swap_requests dsr
            JOIN employees re ON dsr.requester_employee_id = re.id
            WHERE dsr.id = ?";
    $types = 'i';
    $params = [(int)$requestId];

    if ($role === 'hr') {
        $scopeClause = hrScopeBuildEmployeeWhereClause($role, $scopes, 're');
        $sql .= $scopeClause['sql'];
        $types .= $scopeClause['types'];
        $params = array_merge($params, $scopeClause['params']);
    } elseif ($role !== 'admin') {
        $sql .= " AND re.supervisor_id = ?";
        $types .= 'i';
        $params[] = (int)$myEmployeeId;
    }

    $stmt = $mysqli->prepare($sql);
    hrScopeBindParams($stmt, $types, $params);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
