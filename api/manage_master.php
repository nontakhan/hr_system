<?php
/*
 * API (Backend) สำหรับจัดการข้อมูล Master Data
 * (Updated: รองรับ company_color)
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์เข้าถึงส่วนนี้ (Admin only)']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$response = ['status' => 'error', 'message' => 'Invalid Request Method'];

try {
    if ($method === 'GET') {
        if (isset($_GET['type']) && $_GET['type'] === 'all') {
            $response = getAllMasterData($mysqli);
        }
        
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if ($input['action'] === 'create') {
            $response = createMasterData($mysqli, $input['type'], $input);
        } elseif ($input['action'] === 'update') {
            $response = updateMasterData($mysqli, $input['type'], $input);
        }
        
    } elseif ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['type']) && isset($input['id'])) {
            $response = deleteMasterData($mysqli, $input['type'], $input['id']);
        }
    }
} catch (Exception $e) {
    $response = ['status' => 'error', 'message' => 'API Error: ' . $e->getMessage()];
}

echo json_encode($response);
$mysqli->close();
exit();

// ===============================================
// === FUNCTIONS ===
// ===============================================

function getAllMasterData($mysqli) {
    $data = [
        'companies' => [],
        'branches' => [],
        'departments' => [],
        'positions' => []
    ];
    
    $data['companies'] = $mysqli->query("SELECT * FROM companies ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
    
    $sql_branch = "SELECT b.*, c.company_name_th 
                   FROM branches b
                   JOIN companies c ON b.company_id = c.id
                   ORDER BY b.company_id ASC, b.id ASC";
    $data['branches'] = $mysqli->query($sql_branch)->fetch_all(MYSQLI_ASSOC);

    $data['departments'] = $mysqli->query("SELECT * FROM departments ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
    
    $data['positions'] = $mysqli->query("SELECT * FROM positions ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);

    return ['status' => 'success', 'data' => $data];
}

function createMasterData($mysqli, $type, $data) {
    
    if ($type === 'company') {
        // (Updated: เพิ่ม company_color)
        $sql = "INSERT INTO companies (company_name_th, company_address, company_color) VALUES (?, ?, ?)";
        $stmt = $mysqli->prepare($sql);
        // กำหนดค่า Default สีถ้าไม่ได้ส่งมา
        $color = !empty($data['company_color']) ? $data['company_color'] : '#005A9C';
        $stmt->bind_param('sss', $data['company_name_th'], $data['company_address'], $color);
        
    } elseif ($type === 'branch') {
        $sql = "INSERT INTO branches (company_id, branch_name_th) VALUES (?, ?)";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('is', $data['company_id'], $data['branch_name_th']);

    } elseif ($type === 'department') {
        $sql = "INSERT INTO departments (dept_name_th, dept_name_en) VALUES (?, ?)";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('ss', $data['dept_name_th'], $data['dept_name_en']);
        
    } elseif ($type === 'position') {
        $sql = "INSERT INTO positions (position_name_th, position_name_en) VALUES (?, ?)";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('ss', $data['position_name_th'], $data['position_name_en']);
    } else {
        return ['status' => 'error', 'message' => 'Invalid data type (create)'];
    }

    try {
        if ($stmt->execute()) {
            return ['status' => 'success', 'message' => 'เพิ่มข้อมูลสำเร็จ'];
        } else {
            throw new Exception($stmt->error);
        }
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => 'DB Error (Create): ' . $e->getMessage()];
    }
}

function updateMasterData($mysqli, $type, $data) {
    $id = $data['id'];

    if ($type === 'company') {
        // (Updated: เพิ่ม company_color)
        $sql = "UPDATE companies SET company_name_th = ?, company_address = ?, company_color = ? WHERE id = ?";
        $stmt = $mysqli->prepare($sql);
        $color = !empty($data['company_color']) ? $data['company_color'] : '#005A9C';
        $stmt->bind_param('sssi', $data['company_name_th'], $data['company_address'], $color, $id);
        
    } elseif ($type === 'branch') {
        $sql = "UPDATE branches SET company_id = ?, branch_name_th = ? WHERE id = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('isi', $data['company_id'], $data['branch_name_th'], $id);

    } elseif ($type === 'department') {
        $sql = "UPDATE departments SET dept_name_th = ?, dept_name_en = ? WHERE id = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('ssi', $data['dept_name_th'], $data['dept_name_en'], $id);
        
    } elseif ($type === 'position') {
        $sql = "UPDATE positions SET position_name_th = ?, position_name_en = ? WHERE id = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('ssi', $data['position_name_th'], $data['position_name_en'], $id);
    } else {
        return ['status' => 'error', 'message' => 'Invalid data type (update)'];
    }

    try {
        if ($stmt->execute()) {
            return ['status' => 'success', 'message' => 'อัปเดตข้อมูลสำเร็จ'];
        } else {
            throw new Exception($stmt->error);
        }
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => 'DB Error (Update): ' . $e->getMessage()];
    }
}

function deleteMasterData($mysqli, $type, $id) {
    // (Logic Delete เหมือนเดิม)
    if ($type === 'company') {
        $check = $mysqli->query("SELECT id FROM branches WHERE company_id = $id LIMIT 1");
        if ($check->num_rows > 0) return ['status' => 'error', 'message' => 'ลบไม่ได้! มี "สาขา" ใช้งานบริษัทนี้อยู่'];
        
        $check = $mysqli->query("SELECT id FROM employees WHERE company_id = $id LIMIT 1");
        if ($check->num_rows > 0) return ['status' => 'error', 'message' => 'ลบไม่ได้! มี "พนักงาน" ใช้งานบริษัทนี้อยู่'];
        $table = 'companies';
        
    } elseif ($type === 'branch') {
        $check = $mysqli->query("SELECT id FROM employees WHERE branch_id = $id LIMIT 1");
        if ($check->num_rows > 0) return ['status' => 'error', 'message' => 'ลบไม่ได้! มี "พนักงาน" ใช้งานสาขานี้อยู่'];
        $table = 'branches';

    } elseif ($type === 'department') {
        $check = $mysqli->query("SELECT id FROM employees WHERE department_id = $id LIMIT 1");
        if ($check->num_rows > 0) return ['status' => 'error', 'message' => 'ลบไม่ได้! มี "พนักงาน" ใช้งานแผนกนี้อยู่'];
        $table = 'departments';
        
    } elseif ($type === 'position') {
        $check = $mysqli->query("SELECT id FROM employees WHERE position_id = $id LIMIT 1");
        if ($check->num_rows > 0) return ['status' => 'error', 'message' => 'ลบไม่ได้! มี "พนักงาน" ใช้งานตำแหน่งนี้อยู่'];
        $table = 'positions';
    } else {
        return ['status' => 'error', 'message' => 'Invalid data type (delete)'];
    }

    $sql = "DELETE FROM $table WHERE id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        return ['status' => 'success', 'message' => 'ลบข้อมูลสำเร็จ'];
    } else {
        return ['status' => 'error', 'message' => 'DB Error (Delete): ' . $stmt->error];
    }
}
?>