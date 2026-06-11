<?php
// ตั้งค่า Error Handling
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
        sendJsonError('กรุณาเข้าสู่ระบบก่อนใช้งาน');
    }

    $method = $_SERVER['REQUEST_METHOD'];

    // --- GET: ดึงข้อมูล ---
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';
        
        if ($action === 'get_types') {
            leaveEnsureHourlyRequestTypes($mysqli);
            $sql = "SELECT * FROM leave_types ORDER BY id ASC";
            $result = $mysqli->query($sql);
            echo json_encode(['status' => 'success', 'data' => $result->fetch_all(MYSQLI_ASSOC)]);
        } elseif ($action === 'get_settings') {
            $activePolicy = leaveGetActivePolicy($mysqli);
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'active_policy' => $activePolicy,
                    'policies' => leaveFetchPolicies($mysqli),
                    'fiscal_year_start_month' => $activePolicy['fiscal_year_start_month'],
                    'leave_max_requests_per_year' => $activePolicy['leave_max_requests_per_year'],
                    'current_fiscal_year' => $activePolicy['current_fiscal_year'],
                ],
            ]);
        }
        else {
            sendJsonError('Invalid Action');
        }
    }

    // --- POST: เพิ่ม/แก้ไข ---
    elseif ($method === 'POST') {
        // เช็คสิทธิ์ Admin/HR
        if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'hr') {
            sendJsonError('ไม่มีสิทธิ์ดำเนินการ');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        if ($action === 'create_type') {
            $sql = "INSERT INTO leave_types (type_name, days_per_year, description, requires_file) VALUES (?, ?, ?, ?)";
            $stmt = $mysqli->prepare($sql);
            $req_file = !empty($input['requires_file']) ? 1 : 0;
            $stmt->bind_param('sisi', $input['type_name'], $input['days_per_year'], $input['description'], $req_file);
            
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'เพิ่มประเภทการลาสำเร็จ']);
            } else {
                throw new Exception($stmt->error);
            }
        }
        elseif ($action === 'update_type') {
            $sql = "UPDATE leave_types SET type_name=?, days_per_year=?, description=?, requires_file=? WHERE id=?";
            $stmt = $mysqli->prepare($sql);
            $req_file = !empty($input['requires_file']) ? 1 : 0;
            $stmt->bind_param('sisii', $input['type_name'], $input['days_per_year'], $input['description'], $req_file, $input['id']);
            
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'แก้ไขข้อมูลสำเร็จ']);
            } else {
                throw new Exception($stmt->error);
            }
        }
        elseif ($action === 'update_settings') {
            $activePolicy = leaveSavePolicy($mysqli, [
                'id' => $input['id'] ?? 0,
                'policy_name' => $input['policy_name'] ?? '',
                'fiscal_year_start_month' => $input['fiscal_year_start_month'] ?? 10,
                'leave_max_requests_per_year' => $input['leave_max_requests_per_year'] ?? 0,
                'is_active' => $input['is_active'] ?? 0,
            ]);
            echo json_encode([
                'status' => 'success',
                'message' => 'บันทึกการตั้งค่าปีงบประมาณสำเร็จ',
                'data' => [
                    'active_policy' => $activePolicy,
                    'policies' => leaveFetchPolicies($mysqli),
                    'fiscal_year_start_month' => $activePolicy['fiscal_year_start_month'],
                    'leave_max_requests_per_year' => $activePolicy['leave_max_requests_per_year'],
                    'current_fiscal_year' => $activePolicy['current_fiscal_year'],
                ],
            ]);
        }
        elseif ($action === 'activate_settings') {
            leaveActivatePolicy($mysqli, $input['id'] ?? 0);
            $activePolicy = leaveGetActivePolicy($mysqli);
            echo json_encode([
                'status' => 'success',
                'message' => 'เลือกนโยบายการลาที่ใช้งานแล้ว',
                'data' => [
                    'active_policy' => $activePolicy,
                    'policies' => leaveFetchPolicies($mysqli),
                    'fiscal_year_start_month' => $activePolicy['fiscal_year_start_month'],
                    'leave_max_requests_per_year' => $activePolicy['leave_max_requests_per_year'],
                    'current_fiscal_year' => $activePolicy['current_fiscal_year'],
                ],
            ]);
        }
    }

    // --- DELETE: ลบ ---
    elseif ($method === 'DELETE') {
        if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'hr') {
            sendJsonError('ไม่มีสิทธิ์ดำเนินการ');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)$input['id'];

        if (($input['action'] ?? '') === 'delete_settings') {
            leaveDeletePolicy($mysqli, $id);
            echo json_encode([
                'status' => 'success',
                'message' => 'ลบนโยบายการลาสำเร็จ',
                'data' => [
                    'active_policy' => leaveGetActivePolicy($mysqli),
                    'policies' => leaveFetchPolicies($mysqli),
                ],
            ]);
            exit();
        }

        // เช็คว่ามีคนใช้ประเภทนี้ไปหรือยัง
        $check = $mysqli->query("SELECT id FROM leave_requests WHERE leave_type_id = $id LIMIT 1");
        if ($check->num_rows > 0) {
            sendJsonError('ไม่สามารถลบได้ เนื่องจากมีการใช้งานประเภทการลานี้แล้ว');
        }

        $sql = "DELETE FROM leave_types WHERE id = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'ลบข้อมูลสำเร็จ']);
        } else {
            throw new Exception($stmt->error);
        }
    }

} catch (Throwable $e) {
    error_log($e->getMessage());
    sendJsonError('System Error');
}

$mysqli->close();
