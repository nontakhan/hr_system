<?php
// (สำคัญ!) เริ่ม session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db_connect.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$response = ['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง'];

if (isset($input['username']) && isset($input['password'])) {
    
    $username = $input['username'];
    $password = $input['password'];

    // (แก้ไข: เพิ่ม e.company_id ในการ SELECT)
    $sql = "SELECT 
                u.id AS user_id, 
                u.employee_id, 
                u.username, 
                u.password, 
                u.role,
                e.first_name_th,
                e.last_name_th,
                e.company_id,
                p.position_name_th
            FROM users u
            JOIN employees e ON u.employee_id = e.id
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE u.username = ?";
            
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Login สำเร็จ -> เก็บ Session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['employee_id'] = $user['employee_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['first_name_th'] . ' ' . $user['last_name_th'];
                $_SESSION['position_name'] = $user['position_name_th'] ?: '-';
                
                // (สำคัญ!) เก็บ Company ID ไว้ใช้กรองข้อมูล
                $_SESSION['company_id'] = $user['company_id'];
                
                $response['status'] = 'success';
                $response['message'] = 'เข้าสู่ระบบสำเร็จ!';
                
            } else {
                $response['message'] = 'Username หรือ Password ไม่ถูกต้อง';
            }
        } else {
            $response['message'] = 'Username หรือ Password ไม่ถูกต้อง';
        }
        $stmt->close();
    } else {
        $response['message'] = 'SQL Error';
    }
    $mysqli->close();
} else {
    $response['message'] = 'กรุณากรอกข้อมูล';
}

echo json_encode($response);
exit();
?>
