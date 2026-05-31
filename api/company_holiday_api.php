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
    if (!in_array($_SESSION['role'], ['admin', 'hr'], true)) sendJsonError('Access Denied');

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $year = isset($_GET['year']) && preg_match('/^\d{4}$/', $_GET['year']) ? $_GET['year'] : date('Y');
        $start = $year . '-01-01';
        $end = $year . '-12-31';
        $stmt = $mysqli->prepare("SELECT id, holiday_date, holiday_name, notes FROM company_holidays WHERE holiday_date BETWEEN ? AND ? ORDER BY holiday_date");
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        echo json_encode(['status' => 'success', 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        $holidayDate = trim((string)($input['holiday_date'] ?? ''));
        $holidayName = trim((string)($input['holiday_name'] ?? ''));
        $notes = trim((string)($input['notes'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $holidayDate) || $holidayName === '') {
            sendJsonError('กรุณากรอกวันที่และชื่อวันหยุด');
        }

        if ($action === 'create_holiday') {
            $stmt = $mysqli->prepare("INSERT INTO company_holidays (holiday_date, holiday_name, notes) VALUES (?, ?, ?)");
            $stmt->bind_param('sss', $holidayDate, $holidayName, $notes);
            if ($stmt->execute()) echo json_encode(['status' => 'success', 'message' => 'เพิ่มวันหยุดสำเร็จ']);
            else throw new Exception($stmt->error);
        } elseif ($action === 'update_holiday') {
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) sendJsonError('Invalid ID');
            $stmt = $mysqli->prepare("UPDATE company_holidays SET holiday_date = ?, holiday_name = ?, notes = ? WHERE id = ?");
            $stmt->bind_param('sssi', $holidayDate, $holidayName, $notes, $id);
            if ($stmt->execute()) echo json_encode(['status' => 'success', 'message' => 'แก้ไขวันหยุดสำเร็จ']);
            else throw new Exception($stmt->error);
        } else {
            sendJsonError('Invalid Action');
        }
    } elseif ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) sendJsonError('Invalid ID');

        $stmt = $mysqli->prepare("DELETE FROM company_holidays WHERE id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) echo json_encode(['status' => 'success', 'message' => 'ลบวันหยุดสำเร็จ']);
        else throw new Exception($stmt->error);
    } else {
        sendJsonError('Method Not Allowed');
    }
} catch (Throwable $e) {
    if ($mysqli->errno == 1062) sendJsonError('วันที่นี้มีอยู่แล้ว');
    error_log($e->getMessage());
    sendJsonError('System Error');
}
