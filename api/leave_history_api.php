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
        $sql = "SELECT lr.*, lt.type_name 
                FROM leave_requests lr
                JOIN leave_types lt ON lr.leave_type_id = lt.id
                WHERE lr.employee_id = ?
                  AND (lr.request_unit IS NULL OR lr.request_unit <> 'hour')
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
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        if ($action === 'cancel_leave') {
            $id = (int)$input['id'];

            // ตรวจสอบว่าเป็นใบลาของตัวเอง และสถานะยังเป็น pending
            $check = $mysqli->prepare("SELECT id FROM leave_requests WHERE id = ? AND employee_id = ? AND (request_unit IS NULL OR request_unit <> 'hour') AND status IN ('pending','pending_manager')");
            $check->bind_param('ii', $id, $emp_id);
            $check->execute();
            if ($check->get_result()->num_rows === 0) {
                sendJsonError('ไม่สามารถยกเลิกได้ (อาจอนุมัติไปแล้ว หรือไม่ใช่ใบลาของคุณ)');
            }

            // อัปเดตสถานะเป็น cancelled
            $update = $mysqli->prepare("UPDATE leave_requests SET status = 'cancelled' WHERE id = ?");
            $update->bind_param('i', $id);
            
            if ($update->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'ยกเลิกใบลาเรียบร้อยแล้ว']);
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
