<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

function sendActivityTypeJson($payload) {
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

function sendActivityTypeError($message) {
    sendActivityTypeJson(['status' => 'error', 'message' => $message]);
}

try {
    if (session_status() === PHP_SESSION_NONE) session_start();
    require_once '../includes/db_connect.php';
    require_once '../includes/training_request_helpers.php';

    if (!isset($_SESSION['user_id'])) {
        sendActivityTypeError('กรุณาเข้าสู่ระบบก่อนใช้งาน');
    }
    if (!in_array($_SESSION['role'] ?? '', ['admin', 'hr'], true)) {
        sendActivityTypeError('Access Denied');
    }

    trainingRequestEnsureActivityTypesTable($mysqli);

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    if ($method === 'GET') {
        if ($action === 'list') {
            sendActivityTypeJson(['status' => 'success', 'data' => trainingRequestFetchActivityTypes($mysqli)]);
        }
        sendActivityTypeError('Invalid Action');
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $input['action'] ?? $action;

    if ($method === 'POST') {
        if ($action === 'save') {
            $id = (int)($input['id'] ?? 0);
            $typeName = trainingRequestTrim((string)($input['type_name'] ?? ''), 255);
            $description = trim((string)($input['description'] ?? ''));
            $isActive = !empty($input['is_active']) ? 1 : 0;
            if ($typeName === '') {
                sendActivityTypeError('กรุณาระบุชื่อประเภทกิจกรรม');
            }

            if ($id > 0) {
                $stmt = $mysqli->prepare("UPDATE activity_types SET type_name = ?, description = ?, is_active = ? WHERE id = ?");
                $stmt->bind_param('ssii', $typeName, $description, $isActive, $id);
            } else {
                $stmt = $mysqli->prepare("INSERT INTO activity_types (type_name, description, is_active) VALUES (?, ?, ?)");
                $stmt->bind_param('ssi', $typeName, $description, $isActive);
            }
            if (!$stmt->execute()) {
                throw new RuntimeException($stmt->error ?: 'Cannot save activity type');
            }
            sendActivityTypeJson(['status' => 'success', 'message' => 'บันทึกประเภทกิจกรรมเรียบร้อย']);
        }
        sendActivityTypeError('Invalid Action');
    }

    if ($method === 'DELETE') {
        if ($action === 'delete') {
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) {
                sendActivityTypeError('Invalid ID');
            }
            $check = $mysqli->prepare("SELECT id FROM training_requests WHERE activity_type_id = ? LIMIT 1");
            $check->bind_param('i', $id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                sendActivityTypeError('ไม่สามารถลบได้ เนื่องจากมีคำขอใช้ประเภทกิจกรรมนี้แล้ว');
            }
            $stmt = $mysqli->prepare("DELETE FROM activity_types WHERE id = ?");
            $stmt->bind_param('i', $id);
            if (!$stmt->execute()) {
                throw new RuntimeException($stmt->error ?: 'Cannot delete activity type');
            }
            sendActivityTypeJson(['status' => 'success', 'message' => 'ลบประเภทกิจกรรมเรียบร้อย']);
        }
        sendActivityTypeError('Invalid Action');
    }

    sendActivityTypeError('Method Not Allowed');
} catch (Throwable $e) {
    error_log($e->getMessage());
    sendActivityTypeError($e instanceof InvalidArgumentException ? $e->getMessage() : 'System Error');
}
?>
