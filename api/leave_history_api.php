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
    require_once '../includes/leave_helpers.php';
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        sendJsonError('กรุณาเข้าสู่ระบบ');
    }

    $emp_id = $_SESSION['employee_id'];
    $method = $_SERVER['REQUEST_METHOD'];

    // --- GET: ดึงประวัติการลา ---
    if ($method === 'GET') {
        leaveEnsureTwoStepApprovalColumns($mysqli);
        $sql = "SELECT lr.*, lr.created_via, lr.created_by_role, lr.proxy_note, lt.type_name,
                       CONCAT_WS(' ', pce.first_name_th, pce.last_name_th) AS proxy_creator_name
                FROM leave_requests lr
                JOIN leave_types lt ON lr.leave_type_id = lt.id
                LEFT JOIN employees pce ON lr.created_by_employee_id = pce.id
                WHERE lr.employee_id = ?
                  AND (lr.request_unit IS NULL OR lr.request_unit <> 'hour' OR lr.time_request_type IS NULL)
                ORDER BY lr.created_at DESC";
        
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('i', $emp_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        echo json_encode([
            'status' => 'success',
            'data' => $result->fetch_all(MYSQLI_ASSOC),
            'usage_summary' => leaveFetchUsageSummary($mysqli, (int)$emp_id),
        ]);
    }

    // --- POST: ยกเลิกใบลา ---
    elseif ($method === 'POST') {
        leaveEnsureTwoStepApprovalColumns($mysqli);
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        if ($action === 'cancel_leave') {
            $id = (int)$input['id'];
            $cancel_reason = trim((string)($input['cancel_reason'] ?? ''));

            if ($cancel_reason === '') {
                sendJsonError('กรุณาระบุเหตุผลการยกเลิกใบลา');
            }

            // ตรวจสอบว่าเป็นใบลาของตัวเอง และสถานะยังเป็น pending
            $check = $mysqli->prepare("SELECT id, status FROM leave_requests WHERE id = ? AND employee_id = ? AND (request_unit IS NULL OR request_unit <> 'hour' OR time_request_type IS NULL) AND status IN ('pending','pending_manager','approved')");
            $check->bind_param('ii', $id, $emp_id);
            $check->execute();
            $request = $check->get_result()->fetch_assoc();
            if (!$request) {
                sendJsonError('ไม่สามารถยกเลิกได้ (อาจอนุมัติไปแล้ว หรือไม่ใช่ใบลาของคุณ)');
            }

            if ($request['status'] === 'approved') {
                $update = $mysqli->prepare("UPDATE leave_requests SET status = 'pending_cancel_hr', cancellation_reason = ? WHERE id = ? AND status = 'approved'");
                $update->bind_param('si', $cancel_reason, $id);
                $successMessage = 'ส่งคำขอยกเลิกใบลาเรียบร้อยแล้ว รอ HR/Admin อนุมัติ';
            } else {
                // อัปเดตสถานะเป็น cancelled
                $update = $mysqli->prepare("UPDATE leave_requests SET status = 'cancelled', cancellation_reason = ? WHERE id = ?");
                $update->bind_param('si', $cancel_reason, $id);
                $successMessage = 'ยกเลิกใบลาเรียบร้อยแล้ว';
            }
            
            if ($update->execute()) {
                echo json_encode(['status' => 'success', 'message' => $successMessage]);
            } else {
                throw new Exception($update->error);
            }
        }
    }

} catch (Throwable $e) {
    error_log($e->getMessage());
    sendJsonError('System Error');
}

$mysqli->close();
