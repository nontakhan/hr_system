<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

function sendAccountJsonError($message) {
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit();
}

function accountGetPostValue($key, $default = '') {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}

try {
    if (empty($_SESSION['user_id'])) {
        sendAccountJsonError('กรุณาเข้าสู่ระบบก่อนใช้งาน');
    }

    require_once '../includes/db_connect.php';

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_POST['action'] ?? ($_GET['action'] ?? '');

    if ($method !== 'POST') {
        sendAccountJsonError('Invalid Method');
    }

    if ($action === 'change_password') {
        echo json_encode(changeCurrentUserPassword($mysqli));
    } else {
        sendAccountJsonError('Invalid Action: ' . $action);
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    sendAccountJsonError('System Error');
}

if (isset($mysqli)) {
    $mysqli->close();
}
exit();

function changeCurrentUserPassword(mysqli $mysqli): array
{
    try {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            throw new InvalidArgumentException('ไม่พบข้อมูลผู้ใช้งาน');
        }

        $currentPassword = accountGetPostValue('current_password');
        $newPassword = accountGetPostValue('new_password');
        $confirmPassword = accountGetPostValue('confirm_password');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            throw new InvalidArgumentException('กรุณากรอกรหัสผ่านให้ครบถ้วน');
        }

        if (strlen($newPassword) < 8) {
            throw new InvalidArgumentException('รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร');
        }

        if ($newPassword !== $confirmPassword) {
            throw new InvalidArgumentException('รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน');
        }

        if ($currentPassword === $newPassword) {
            throw new InvalidArgumentException('รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสผ่านเดิม');
        }

        $stmt = $mysqli->prepare('SELECT password FROM users WHERE id=? LIMIT 1');
        if (!$stmt) throw new Exception('Prepare password lookup failed: ' . $mysqli->error);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify($currentPassword, (string)$user['password'])) {
            throw new InvalidArgumentException('รหัสผ่านปัจจุบันไม่ถูกต้อง');
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = $mysqli->prepare('UPDATE users SET password=? WHERE id=?');
        if (!$update) throw new Exception('Prepare password update failed: ' . $mysqli->error);
        $update->bind_param('si', $newHash, $userId);
        if (!$update->execute()) throw new Exception('Password update failed: ' . $update->error);
        $update->close();

        return ['status' => 'success', 'message' => 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว'];
    } catch (Throwable $e) {
        if ($e instanceof InvalidArgumentException) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
        error_log($e->getMessage());
        return ['status' => 'error', 'message' => 'System Error'];
    }
}
