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

    if (!isset($_SESSION['user_id'])) {
        sendJsonError('กรุณาเข้าสู่ระบบ');
    }

    $my_emp_id = $_SESSION['employee_id'];
    $my_role = $_SESSION['role'];
    // ใช้ Null Coalescing Operator เผื่อ session ยังไม่อัปเดต
    $my_company_id = $_SESSION['company_id'] ?? 0; 
    
    $method = $_SERVER['REQUEST_METHOD'];

    // --- GET: ดึงรายการรออนุมัติ / ประวัติ ---
    if ($method === 'GET') {
        $type = $_GET['type'] ?? 'pending';
        
        // (แก้ไข) ใช้ citizen_id เป็น employee_code
        $sql = "SELECT lr.*, 
                       e.first_name_th, e.last_name_th, e.citizen_id as employee_code, e.profile_img_url,
                       lt.type_name,
                       la.file_path, la.file_name
                FROM leave_requests lr
                JOIN employees e ON lr.employee_id = e.id
                JOIN leave_types lt ON lr.leave_type_id = lt.id
                LEFT JOIN leave_attachments la ON lr.id = la.leave_request_id
                WHERE 1=1 ";

        // 1. กรองตาม Role
        if ($my_role === 'admin') {
            // Admin เห็นทั้งหมด
        } elseif ($my_role === 'hr') {
            // HR เห็นเฉพาะคนในบริษัทตัวเอง
            $sql .= " AND e.company_id = ? ";
        } else {
            // Manager เห็นเฉพาะลูกน้อง
            $sql .= " AND e.supervisor_id = ? ";
        }

        // 2. กรองตาม Status (Pending / History)
        if ($type === 'pending') {
            $sql .= " AND lr.status = 'pending' ";
        } else {
            // History: Approved หรือ Rejected
            if ($my_role === 'admin') {
                $sql .= " AND lr.status IN ('approved', 'rejected') ";
            } elseif ($my_role === 'hr') {
                $sql .= " AND lr.status IN ('approved', 'rejected') "; // (บริษัทถูกกรองจากข้อ 1 แล้ว)
            } else {
                // Manager: เห็นประวัติของลูกน้อง หรือ ที่ตัวเองเป็นคนกดอนุมัติ
                $sql .= " AND (lr.status IN ('approved', 'rejected') AND (e.supervisor_id = ? OR lr.approver_id = ?)) ";
            }
        }

        $sql .= " ORDER BY lr.created_at DESC";
        $stmt = $mysqli->prepare($sql);

        // 3. Bind Parameters
        if ($my_role === 'admin') {
            $stmt->execute();
        } elseif ($my_role === 'hr') {
            // HR: Bind Company ID
            $stmt->bind_param('i', $my_company_id);
            $stmt->execute();
        } else {
            // Manager
            if ($type === 'pending') {
                // Pending: Bind Supervisor ID
                $stmt->bind_param('i', $my_emp_id);
            } else {
                // History: Bind Supervisor ID + Approver ID
                $stmt->bind_param('ii', $my_emp_id, $my_emp_id);
            }
            $stmt->execute();
        }

        $result = $stmt->get_result();
        echo json_encode(['status' => 'success', 'data' => $result->fetch_all(MYSQLI_ASSOC)]);
    }

    // --- POST: อนุมัติ / ไม่อนุมัติ ---
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        $req_id = (int)($input['request_id'] ?? 0);
        $reason = $input['reason'] ?? null;

        if ($action === 'approve' || $action === 'reject') {
            if ($req_id <= 0) {
                sendJsonError('Invalid request ID');
            }

            $auth_sql = "SELECT lr.id
                         FROM leave_requests lr
                         JOIN employees e ON lr.employee_id = e.id
                         WHERE lr.id = ? AND lr.status = 'pending'";
            if ($my_role === 'hr') {
                $auth_sql .= " AND e.company_id = ?";
            } elseif ($my_role !== 'admin') {
                $auth_sql .= " AND e.supervisor_id = ?";
            }

            $auth_stmt = $mysqli->prepare($auth_sql);
            if ($my_role === 'admin') {
                $auth_stmt->bind_param('i', $req_id);
            } elseif ($my_role === 'hr') {
                $auth_stmt->bind_param('ii', $req_id, $my_company_id);
            } else {
                $auth_stmt->bind_param('ii', $req_id, $my_emp_id);
            }
            $auth_stmt->execute();
            if ($auth_stmt->get_result()->num_rows !== 1) {
                sendJsonError('Access Denied');
            }
            $new_status = ($action === 'approve') ? 'approved' : 'rejected';
            $now = date('Y-m-d H:i:s');

            $sql = "UPDATE leave_requests SET 
                    status = ?, 
                    approver_id = ?, 
                    approval_date = ?, 
                    rejection_reason = ? 
                    WHERE id = ? AND status = 'pending'";
            
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('sissi', $new_status, $my_emp_id, $now, $reason, $req_id);
            
            if ($stmt->execute() && $stmt->affected_rows === 1) {
                echo json_encode(['status' => 'success', 'message' => 'บันทึกผลการพิจารณาเรียบร้อย']);
            } else {
                throw new Exception($stmt->error ?: 'Leave request was already processed');
            }
        } else {
            sendJsonError('Invalid Action');
        }
    }

} catch (Throwable $e) {
    sendJsonError($e->getMessage());
}

$mysqli->close();
?>
