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
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) sendJsonError('Login Required');

    $method = $_SERVER['REQUEST_METHOD'];

    // GET: List Shifts
    if ($method === 'GET') {
        $sql = "SELECT * FROM work_shifts ORDER BY id ASC";
        $res = $mysqli->query($sql);
        echo json_encode(['status' => 'success', 'data' => $res->fetch_all(MYSQLI_ASSOC)]);
    }

    // POST: Create / Update
    elseif ($method === 'POST') {
        if (!in_array($_SESSION['role'], ['admin', 'hr'])) sendJsonError('Access Denied');

        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        if ($action === 'create_shift') {
            $sql = "INSERT INTO work_shifts (shift_name, start_time, end_time, late_tolerance_mins, work_days) VALUES (?, ?, ?, ?, ?)";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('sssis', $input['shift_name'], $input['start_time'], $input['end_time'], $input['late_tolerance_mins'], $input['work_days']);
            if ($stmt->execute()) echo json_encode(['status' => 'success', 'message' => 'เพิ่มกะสำเร็จ']);
            else throw new Exception($stmt->error);
        } 
        elseif ($action === 'update_shift') {
            $sql = "UPDATE work_shifts SET shift_name=?, start_time=?, end_time=?, late_tolerance_mins=?, work_days=? WHERE id=?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('sssisi', $input['shift_name'], $input['start_time'], $input['end_time'], $input['late_tolerance_mins'], $input['work_days'], $input['id']);
            if ($stmt->execute()) echo json_encode(['status' => 'success', 'message' => 'แก้ไขสำเร็จ']);
            else throw new Exception($stmt->error);
        }
    }

    // DELETE
    elseif ($method === 'DELETE') {
        if (!in_array($_SESSION['role'], ['admin', 'hr'])) sendJsonError('Access Denied');
        
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)$input['id'];

        // Check Usage (มีพนักงานใช้กะนี้อยู่ไหม)
        $check = $mysqli->query("SELECT id FROM employees WHERE default_shift_id = $id LIMIT 1");
        if ($check->num_rows > 0) sendJsonError('ลบไม่ได้: มีพนักงานใช้กะการทำงานนี้อยู่');

        $sql = "DELETE FROM work_shifts WHERE id = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) echo json_encode(['status' => 'success', 'message' => 'ลบสำเร็จ']);
        else throw new Exception($stmt->error);
    }

} catch (Throwable $e) {
    error_log($e->getMessage());
    sendJsonError('System Error');
}
$mysqli->close();
