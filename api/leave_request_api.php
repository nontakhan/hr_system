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
    require_once '../includes/leave_helpers.php';
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        sendJsonError('กรุณาเข้าสู่ระบบก่อนใช้งาน');
    }

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';

        if ($action === 'get_leave_types') {
            $sql = "SELECT id, type_name, days_per_year, requires_file FROM leave_types ORDER BY id ASC";
            $result = $mysqli->query($sql);
            echo json_encode(['status' => 'success', 'data' => $result->fetch_all(MYSQLI_ASSOC)]);
        } elseif ($action === 'get_leave_usage') {
            echo json_encode([
                'status' => 'success',
                'data' => leaveFetchUsageSummary($mysqli, (int)$_SESSION['employee_id']),
            ]);
        } elseif ($action === 'calculate_leave') {
            $emp_id = (int)$_SESSION['employee_id'];
            $start = trim((string)($_GET['start_date'] ?? ''));
            $end = trim((string)($_GET['end_date'] ?? ''));
            $summary = leaveBuildDateSummary(
                $start,
                $end,
                $_GET['start_day_part'] ?? 'full',
                $_GET['end_day_part'] ?? 'full',
                leaveFetchEmployeeWorkDays($mysqli, $emp_id),
                leaveFetchCompanyHolidays($mysqli, $start, $end)
            );
            echo json_encode([
                'status' => $summary['valid'] ? 'success' : 'error',
                'message' => $summary['message'],
                'data' => $summary,
            ]);
        } else {
            sendJsonError('Invalid Action');
        }
    } elseif ($method === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'submit_leave') {
            submitLeaveRequest($mysqli, $_POST, $_FILES);
        } else {
            sendJsonError('Invalid Action');
        }
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    sendJsonError('System Error');
}

$mysqli->close();
exit();

function submitLeaveRequest($mysqli, $data, $files) {
    $mysqli->begin_transaction();
    try {
        $emp_id = (int)$_SESSION['employee_id'];
        $type_id = (int)$data['leave_type_id'];
        $start = trim((string)($data['start_date'] ?? ''));
        $end = trim((string)($data['end_date'] ?? ''));
        $start_part = leaveNormalizeDayPart($data['start_day_part'] ?? 'full');
        $end_part = leaveNormalizeDayPart($data['end_day_part'] ?? 'full');
        $reason = trim((string)($data['reason'] ?? ''));

        if ($type_id <= 0 || $start === '' || $end === '' || $reason === '') {
            throw new Exception('กรุณากรอกข้อมูลให้ครบถ้วน');
        }
        if ($end < $start) {
            throw new Exception('วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่ม');
        }

        $summary = leaveBuildDateSummary(
            $start,
            $end,
            $start_part,
            $end_part,
            leaveFetchEmployeeWorkDays($mysqli, $emp_id),
            leaveFetchCompanyHolidays($mysqli, $start, $end)
        );
        if (!$summary['valid']) {
            throw new Exception($summary['message']);
        }

        $total_days = (float)$summary['total_days'];
        if ($total_days <= 0) {
            throw new Exception('ช่วงวันที่เลือกไม่มีวันทำงานที่สามารถลาได้');
        }

        $stmt_type = $mysqli->prepare("SELECT requires_file FROM leave_types WHERE id = ?");
        $stmt_type->bind_param('i', $type_id);
        $stmt_type->execute();
        $type_info = $stmt_type->get_result()->fetch_assoc();
        if (!$type_info) {
            throw new Exception('ไม่พบประเภทการลา');
        }

        if ((int)$type_info['requires_file'] === 1) {
            if (!isset($files['attachment']) || $files['attachment']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('ประเภทการลานี้ต้องแนบไฟล์หลักฐาน');
            }
        }

        leaveEnsureRequestPartColumns($mysqli);
        $sql = "INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, start_day_part, end_day_part, total_days, reason, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('iissssds', $emp_id, $type_id, $start, $end, $start_part, $end_part, $total_days, $reason);

        if (!$stmt->execute()) {
            throw new Exception('บันทึกข้อมูลไม่สำเร็จ: ' . $stmt->error);
        }
        $request_id = $mysqli->insert_id;

        if (isset($files['attachment']) && $files['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file_path = saveLeaveAttachment($files['attachment'], $request_id);
            $original_name = basename($files['attachment']['name']);
            $sql_file = "INSERT INTO leave_attachments (leave_request_id, file_name, file_path) VALUES (?, ?, ?)";
            $stmt_file = $mysqli->prepare($sql_file);
            $stmt_file->bind_param('iss', $request_id, $original_name, $file_path);
            $stmt_file->execute();
        }

        $mysqli->commit();
        echo json_encode([
            'status' => 'success',
            'message' => 'ส่งใบลาเรียบร้อยแล้ว รอการอนุมัติ',
            'data' => $summary,
        ]);
    } catch (Exception $e) {
        $mysqli->rollback();
        error_log($e->getMessage());
        sendJsonError($e->getMessage() ?: 'System Error');
    }
}
