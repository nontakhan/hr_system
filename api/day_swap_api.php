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
                    FROM employees
                    WHERE status IN ('active', 'probation')
                      AND id <> ?";
            if ($myRole === 'employee' || $myRole === 'manager' || $myRole === 'hr') {
                $sql .= " AND company_id = ?";
            }
            $sql .= " ORDER BY first_name_th, last_name_th";
            $stmt = $mysqli->prepare($sql);
            if ($myRole === 'admin') {
                $stmt->bind_param('i', $myEmployeeId);
            } else {
                $stmt->bind_param('ii', $myEmployeeId, $myCompanyId);
            }
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
            $stmt = $mysqli->prepare("SELECT dsr.*,
                                             CONCAT_WS(' ', te.first_name_th, te.last_name_th) AS target_name,
                                             CONCAT_WS(' ', re.first_name_th, re.last_name_th) AS requester_name,
                                             CONCAT_WS(' ', ae.first_name_th, ae.last_name_th) AS approver_name
                                      FROM day_swap_requests dsr
                                      JOIN employees te ON dsr.target_employee_id = te.id
                                      JOIN employees re ON dsr.requester_employee_id = re.id
                                      LEFT JOIN employees ae ON dsr.approver_id = ae.id
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
            $sql = daySwapApprovalQuery($action, $myRole);
            $stmt = $mysqli->prepare($sql);
            if ($myRole === 'hr') {
                $stmt->bind_param('i', $myCompanyId);
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
                VALUES (?, ?, ?, ?, ?, 'pending')");
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

            if (!daySwapCanApproveRequest($mysqli, $requestId, $myRole, $myEmployeeId, $myCompanyId)) {
                sendDaySwapError('Access Denied');
            }

            $newStatus = $postAction === 'approve' ? 'approved' : 'rejected';
            $now = date('Y-m-d H:i:s');
            $stmt = $mysqli->prepare("UPDATE day_swap_requests
                                      SET status = ?, approver_id = ?, approval_date = ?, rejection_reason = ?
                                      WHERE id = ? AND status = 'pending'");
            $stmt->bind_param('sissi', $newStatus, $myEmployeeId, $now, $reason, $requestId);
            if ($stmt->execute() && $stmt->affected_rows === 1) {
                sendDaySwapJson(['status' => 'success', 'message' => 'บันทึกผลการพิจารณาเรียบร้อย']);
            }
            throw new Exception($stmt->error ?: 'Request was already processed');
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
    $stmt = $mysqli->prepare("SELECT id FROM employees WHERE id = ? AND company_id = ? AND status IN ('active', 'probation')");
    $stmt->bind_param('ii', $employeeId, $companyId);
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
                              WHERE status IN ('pending','approved')
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

function daySwapApprovalQuery($type, $role) {
    $sql = "SELECT dsr.*,
                   CONCAT_WS(' ', re.first_name_th, re.last_name_th) AS requester_name,
                   re.citizen_id AS requester_code,
                   CONCAT_WS(' ', te.first_name_th, te.last_name_th) AS target_name,
                   te.citizen_id AS target_code,
                   CONCAT_WS(' ', ae.first_name_th, ae.last_name_th) AS approver_name
            FROM day_swap_requests dsr
            JOIN employees re ON dsr.requester_employee_id = re.id
            JOIN employees te ON dsr.target_employee_id = te.id
            LEFT JOIN employees ae ON dsr.approver_id = ae.id
            WHERE 1=1";
    if ($role === 'hr') {
        $sql .= " AND re.company_id = ?";
    } elseif ($role !== 'admin') {
        $sql .= " AND re.supervisor_id = ?";
    }

    if ($type === 'pending') {
        $sql .= " AND dsr.status = 'pending'";
    } else {
        $sql .= " AND dsr.status IN ('approved','rejected')";
    }
    $sql .= " ORDER BY dsr.created_at DESC";

    return $sql;
}

function daySwapCanApproveRequest($mysqli, $requestId, $role, $myEmployeeId, $companyId) {
    $sql = "SELECT dsr.id
            FROM day_swap_requests dsr
            JOIN employees re ON dsr.requester_employee_id = re.id
            WHERE dsr.id = ? AND dsr.status = 'pending'";
    if ($role === 'hr') {
        $sql .= " AND re.company_id = ?";
    } elseif ($role !== 'admin') {
        $sql .= " AND re.supervisor_id = ?";
    }
    $stmt = $mysqli->prepare($sql);
    if ($role === 'admin') {
        $stmt->bind_param('i', $requestId);
    } else {
        $scopeValue = $role === 'hr' ? $companyId : $myEmployeeId;
        $stmt->bind_param('ii', $requestId, $scopeValue);
    }
    $stmt->execute();
    return $stmt->get_result()->num_rows === 1;
}
