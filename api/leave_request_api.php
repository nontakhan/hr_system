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
    require_once '../includes/upload_security.php';
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        sendJsonError('กรุณาเข้าสู่ระบบก่อนใช้งาน');
    }

    $method = $_SERVER['REQUEST_METHOD'];

    // --- GET: ดึงประเภทการลา (สำหรับ Dropdown) ---
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';
        
        if ($action === 'get_leave_types') {
            $sql = "SELECT id, type_name, days_per_year, requires_file FROM leave_types ORDER BY id ASC";
            $result = $mysqli->query($sql);
            echo json_encode(['status' => 'success', 'data' => $result->fetch_all(MYSQLI_ASSOC)]);
        }
    }

    // --- POST: ส่งใบลา ---
    elseif ($method === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'submit_leave') {
            submitLeaveRequest($mysqli, $_POST, $_FILES);
        } else {
            sendJsonError('Invalid Action');
        }
    }

} catch (Throwable $e) {
    sendJsonError($e->getMessage());
}

$mysqli->close();
exit();

// ฟังก์ชันบันทึกการลา
function submitLeaveRequest($mysqli, $data, $files) {
    $mysqli->begin_transaction();
    try {
        $emp_id = $_SESSION['employee_id']; // ID ของพนักงานที่ Login
        $type_id = (int)$data['leave_type_id'];
        $start = $data['start_date'];
        $end = $data['end_date'];
        $reason = trim($data['reason']);

        // 1. ตรวจสอบข้อมูลเบื้องต้น
        if (empty($start) || empty($end) || empty($reason)) {
            throw new Exception("กรุณากรอกข้อมูลให้ครบถ้วน");
        }
        if ($end < $start) {
            throw new Exception("วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่ม");
        }

        // 2. คำนวณจำนวนวัน (Logic ง่ายๆ: จบ - เริ่ม + 1)
        // (ในอนาคตอาจต้องตัดวันหยุดเสาร์-อาทิตย์ ออก)
        $d_start = new DateTime($start);
        $d_end = new DateTime($end);
        $interval = $d_start->diff($d_end);
        $total_days = $interval->days + 1;

        // 3. ตรวจสอบเงื่อนไขไฟล์แนบ
        $stmt_type = $mysqli->prepare("SELECT requires_file FROM leave_types WHERE id = ?");
        $stmt_type->bind_param('i', $type_id);
        $stmt_type->execute();
        $type_info = $stmt_type->get_result()->fetch_assoc();

        if ($type_info['requires_file'] == 1) {
            if (!isset($files['attachment']) || $files['attachment']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("ประเภทการลานี้ต้องแนบไฟล์หลักฐาน");
            }
        }

        // 4. บันทึกข้อมูลลงตาราง leave_requests
        $sql = "INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, total_days, reason, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('iissds', $emp_id, $type_id, $start, $end, $total_days, $reason);
        
        if (!$stmt->execute()) throw new Exception("บันทึกข้อมูลไม่สำเร็จ: " . $stmt->error);
        $request_id = $mysqli->insert_id;

        // 5. จัดการไฟล์แนบ (ถ้ามี)
        if (isset($files['attachment']) && $files['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file_path = saveLeaveAttachment($files['attachment'], $request_id);
            $original_name = basename($files['attachment']['name']);
            $sql_file = "INSERT INTO leave_attachments (leave_request_id, file_name, file_path) VALUES (?, ?, ?)";
            $stmt_file = $mysqli->prepare($sql_file);
            $stmt_file->bind_param('iss', $request_id, $original_name, $file_path);
            $stmt_file->execute();
        }

        $mysqli->commit();
        echo json_encode(['status' => 'success', 'message' => 'ส่งใบลาเรียบร้อยแล้ว รอการอนุมัติ']);

    } catch (Exception $e) {
        $mysqli->rollback();
        sendJsonError($e->getMessage());
    }
}
?>
