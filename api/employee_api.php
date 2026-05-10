<?php
// 1. ตั้งค่า Error Handling
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Helper Functions
function getVal($arr, $key, $default = null) {
    return isset($arr[$key]) && $arr[$key] !== '' ? $arr[$key] : $default;
}

function sendJsonError($message) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit();
}

try {
    if (session_status() == PHP_SESSION_NONE) session_start();
    
    if (!file_exists('../includes/db_connect.php')) {
        sendJsonError('Database connection file not found');
    }
    require_once '../includes/db_connect.php';
    require_once '../includes/upload_security.php';
    header('Content-Type: application/json');

    // 2. Check Login
    if (!isset($_SESSION['user_id'])) {
        sendJsonError('กรุณาเข้าสู่ระบบก่อนใช้งาน (Login Required)');
    }

    $method = $_SERVER['REQUEST_METHOD'];

    // 3. Handle POST
    if ($method === 'POST') {
        if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'hr') {
            sendJsonError('Access Denied');
        }

        $action = $_POST['action'] ?? '';
        
        if ($action === 'create_employee') {
            echo json_encode(createEmployee($mysqli, $_POST, $_FILES));
        } 
        elseif ($action === 'update_employee') {
            echo json_encode(updateEmployee($mysqli, $_POST, $_FILES));
        }
        elseif ($action === 'transfer_employee') {
            echo json_encode(transferEmployee($mysqli, $_POST));
        }
        else {
            sendJsonError('Invalid Action: ' . $action);
        }

    } elseif ($method === 'GET') {
        if (isset($_GET['action']) && $_GET['action'] === 'get_history') {
            $emp_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
            echo json_encode(getTransferHistory($mysqli, $emp_id));
        } else {
            echo json_encode(getAllEmployees($mysqli));
        }
    } elseif ($method === 'DELETE') {
        if ($_SESSION['role'] !== 'admin') sendJsonError('Admin Only');
        $input = json_decode(file_get_contents('php://input'), true);
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        if ($id > 0) echo json_encode(deleteEmployee($mysqli, $id));
        else sendJsonError('Invalid ID');
    }

} catch (Throwable $e) {
    error_log($e->getMessage());
    sendJsonError('System Error');
}

$mysqli->close();
exit();

// ===================================================================================
// HELPER FOR DYNAMIC BINDING (ปลอดภัย 100%)
// ===================================================================================
function bindParamsStrict($stmt, $types, $params) {
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array(array($stmt, 'bind_param'), $bind_names);
}

// ===================================================================================
// FUNCTIONS
// ===================================================================================

function createEmployee($mysqli, $data, $files) {
    $mysqli->begin_transaction();
    try {
        // 1. Upload Image
        $profile_img_url = 'assets/img/user.png';
        if (isset($files['profile_image']) && $files['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $profile_img_url = saveProfileImage($files['profile_image']);
        }

        // 2. Prepare Variables (29 items)
        $params = [
            // Strings (20)
            getVal($data, 'title_th'), getVal($data, 'first_name_th'), getVal($data, 'last_name_th'),
            getVal($data, 'title_en'), getVal($data, 'first_name_en'), getVal($data, 'last_name_en'),
            getVal($data, 'citizen_id'), getVal($data, 'birth_date', date('Y-m-d')), getVal($data, 'gender'),
            getVal($data, 'religion'), getVal($data, 'blood_group'), getVal($data, 'marital_status'),
            getVal($data, 'phone_number'), getVal($data, 'current_address'), getVal($data, 'district'), getVal($data, 'province'),
            getVal($data, 'education_level'), getVal($data, 'emergency_contact_name'), getVal($data, 'emergency_contact_phone'),
            $profile_img_url,
            // Ints (7)
            (int)getVal($data, 'company_id', 0), (int)getVal($data, 'branch_id', 0), (int)getVal($data, 'department_id', 0),
            (int)getVal($data, 'position_id', 0), (int)getVal($data, 'employment_type_id', 0),
            getVal($data, 'supervisor_id') ? (int)getVal($data, 'supervisor_id') : null,
            (int)getVal($data, 'default_shift_id', 0),
            // Strings (2)
            getVal($data, 'start_date', date('Y-m-d')), getVal($data, 'status')
        ];

        $sql = "INSERT INTO employees 
        (prefix_th, first_name_th, last_name_th, prefix_en, first_name_en, last_name_en, 
        citizen_id, birth_date, gender, religion, blood_group, marital_status,
        phone_number, current_address, district, province, education_level, emergency_contact_name, emergency_contact_phone,
        profile_img_url, company_id, branch_id, department_id, position_id, employment_type_id, supervisor_id, default_shift_id, start_date, status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $stmt = $mysqli->prepare($sql);
        if(!$stmt) throw new Exception("Prepare failed: " . $mysqli->error);

        // สร้าง Type String อัตโนมัติ: s(20) + i(7) + s(2) = 29 chars
        $types = str_repeat('s', 20) . str_repeat('i', 7) . str_repeat('s', 2);

        bindParamsStrict($stmt, $types, $params);

        if (!$stmt->execute()) throw new Exception("Insert Failed: " . $stmt->error);
        $emp_pk = $mysqli->insert_id;

        // Create User
        $username = getVal($data, 'username');
        $password = getVal($data, 'password');
        $role     = getVal($data, 'role', 'employee');

        if ($username && $password) {
            $check = $mysqli->prepare("SELECT id FROM users WHERE username=?");
            $check->bind_param('s', $username); $check->execute();
            if($check->get_result()->num_rows > 0) throw new Exception("Username '$username' ซ้ำ");

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $u_stmt = $mysqli->prepare("INSERT INTO users (employee_id, username, password, role) VALUES (?,?,?,?)");
            $u_stmt->bind_param('isss', $emp_pk, $username, $hash, $role);
            $u_stmt->execute();
        }

        $mysqli->commit();
        return ['status'=>'success', 'message'=>'เพิ่มพนักงานสำเร็จ', 'new_pk'=>$emp_pk];

    } catch (Throwable $e) {
        $mysqli->rollback();
        if ($mysqli->errno == 1062) return ['status'=>'error', 'message'=>'ข้อมูลซ้ำ (บัตรประชาชน หรือ Username)'];
        if ($e instanceof InvalidArgumentException) return ['status'=>'error', 'message'=> $e->getMessage()];
        error_log($e->getMessage());
        return ['status'=>'error', 'message'=> 'System Error'];
    }
}

function updateEmployee($mysqli, $data, $files) {
    $mysqli->begin_transaction();
    try {
        $id = (int)getVal($data, 'id', 0);
        if ($id <= 0) throw new Exception("Invalid ID");

        // 1. Image Logic
        $profile_img_url = getVal($data, 'old_profile_image', 'assets/img/user.png');
        if (isset($files['profile_image']) && $files['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $profile_img_url = saveProfileImage($files['profile_image']);
        }

        // 2. Prepare Variables (30 items: 29 updates + 1 ID)
        $params = [
            // Strings (20)
            getVal($data, 'title_th'), getVal($data, 'first_name_th'), getVal($data, 'last_name_th'),
            getVal($data, 'title_en'), getVal($data, 'first_name_en'), getVal($data, 'last_name_en'),
            getVal($data, 'citizen_id'), getVal($data, 'birth_date', date('Y-m-d')), getVal($data, 'gender'),
            getVal($data, 'religion'), getVal($data, 'blood_group'), getVal($data, 'marital_status'),
            getVal($data, 'phone_number'), getVal($data, 'current_address'), getVal($data, 'district'), getVal($data, 'province'),
            getVal($data, 'education_level'), getVal($data, 'emergency_contact_name'), getVal($data, 'emergency_contact_phone'),
            $profile_img_url,
            // Ints (7)
            (int)getVal($data, 'company_id', 0), (int)getVal($data, 'branch_id', 0), (int)getVal($data, 'department_id', 0),
            (int)getVal($data, 'position_id', 0), (int)getVal($data, 'employment_type_id', 0),
            getVal($data, 'supervisor_id') ? (int)getVal($data, 'supervisor_id') : null,
            (int)getVal($data, 'default_shift_id', 0),
            // Strings (2)
            getVal($data, 'start_date', date('Y-m-d')), getVal($data, 'status'),
            // ID (1)
            $id
        ];

        $sql = "UPDATE employees SET 
            prefix_th=?, first_name_th=?, last_name_th=?, prefix_en=?, first_name_en=?, last_name_en=?, 
            citizen_id=?, birth_date=?, gender=?, religion=?, blood_group=?, marital_status=?,
            phone_number=?, current_address=?, district=?, province=?, education_level=?, emergency_contact_name=?, emergency_contact_phone=?,
            profile_img_url=?, company_id=?, branch_id=?, department_id=?, position_id=?, 
            employment_type_id=?, supervisor_id=?, default_shift_id=?, start_date=?, status=?
            WHERE id=?";

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) throw new Exception("Prepare Update Failed: " . $mysqli->error);

        // สร้าง Type String อัตโนมัติ: s(20) + i(7) + s(2) + i(1) = 30 chars
        $types = str_repeat('s', 20) . str_repeat('i', 7) . str_repeat('s', 2) . 'i';

        bindParamsStrict($stmt, $types, $params);

        if (!$stmt->execute()) throw new Exception("Update Failed: " . $stmt->error);

        // 3. Update User
        $username = getVal($data, 'username');
        $password = getVal($data, 'password');
        $role     = getVal($data, 'role', 'employee');

        // Check existing user
        $chk = $mysqli->prepare("SELECT id FROM users WHERE employee_id = ?");
        $chk->bind_param('i', $id);
        $chk->execute();
        $user_exists = $chk->get_result()->fetch_assoc();

        if ($user_exists) {
            // UPDATE
            if ($password) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $u_stmt = $mysqli->prepare("UPDATE users SET password=?, role=? WHERE employee_id=?");
                $u_stmt->bind_param('ssi', $hash, $role, $id);
                $u_stmt->execute();
            } else {
                $u_stmt = $mysqli->prepare("UPDATE users SET role=? WHERE employee_id=?");
                $u_stmt->bind_param('si', $role, $id);
                $u_stmt->execute();
            }
        } else {
            // INSERT NEW
            if ($username && $password) {
                $dup = $mysqli->prepare("SELECT id FROM users WHERE username = ?");
                $dup->bind_param('s', $username);
                $dup->execute();
                if ($dup->get_result()->num_rows > 0) throw new Exception("Username '$username' ถูกใช้แล้ว");

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $u_stmt = $mysqli->prepare("INSERT INTO users (employee_id, username, password, role) VALUES (?,?,?,?)");
                $u_stmt->bind_param('isss', $id, $username, $hash, $role);
                $u_stmt->execute();
            }
        }

        $mysqli->commit();
        return ['status'=>'success', 'message'=>'แก้ไขข้อมูลสำเร็จ'];

    } catch (Throwable $e) {
        $mysqli->rollback();
        if ($e instanceof InvalidArgumentException) return ['status'=>'error', 'message'=> $e->getMessage()];
        error_log($e->getMessage());
        return ['status'=>'error', 'message'=> 'System Error'];
    }
}

function getAllEmployees($mysqli) {
    try {
        $role = $_SESSION['role'];
        $company_id = $_SESSION['company_id'] ?? 0;
        
        // รับค่า branch_id จาก URL
        $filter_branch_id = isset($_GET['branch_id']) && is_numeric($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;

        $sql = "SELECT e.*, p.position_name_th, d.dept_name_th, c.company_name_th, b.branch_name_th 
                FROM employees e
                LEFT JOIN positions p ON e.position_id = p.id
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN companies c ON e.company_id = c.id
                LEFT JOIN branches b ON e.branch_id = b.id
                WHERE 1=1 ";

        // --- Filter Logic ---
        
        // 1. HR + เลือกสาขา
        if ($role === 'hr' && $filter_branch_id > 0) {
            $sql .= " AND e.company_id = ? AND e.branch_id = ? ";
            $sql .= " ORDER BY e.id DESC";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('ii', $company_id, $filter_branch_id);
        }
        // 2. HR ไม่เลือกสาขา
        elseif ($role === 'hr') {
            $sql .= " AND e.company_id = ? ";
            $sql .= " ORDER BY e.id DESC";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('i', $company_id);
        }
        // 3. Admin + เลือกสาขา
        elseif ($filter_branch_id > 0) {
            $sql .= " AND e.branch_id = ? ";
            $sql .= " ORDER BY e.id DESC";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('i', $filter_branch_id);
        }
        // 4. Admin ไม่เลือกสาขา
        else {
            $sql .= " ORDER BY e.id DESC";
            $stmt = $mysqli->prepare($sql);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        return ['status'=>'success', 'data'=>$res->fetch_all(MYSQLI_ASSOC)];

    } catch (Throwable $e) {
        error_log($e->getMessage());
        return ['status'=>'error', 'message'=>'System Error'];
    }
}

// ... (Functions below: transfer, delete, getTransferHistory remain the same) ...
function transferEmployee($mysqli, $data) {
    $mysqli->begin_transaction();
    try {
        $emp_id = (int)getVal($data, 'employee_id', 0);
        if ($emp_id <= 0) throw new Exception("Invalid ID");

        $stmt = $mysqli->prepare("SELECT company_id, branch_id, department_id, position_id FROM employees WHERE id = ?");
        $stmt->bind_param('i', $emp_id); $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();

        $new_company = getVal($data, 'new_company_id') ? (int)getVal($data, 'new_company_id') : $current['company_id'];
        $new_branch = getVal($data, 'new_branch_id') ? (int)getVal($data, 'new_branch_id') : $current['branch_id'];
        $new_dept = getVal($data, 'new_department_id') ? (int)getVal($data, 'new_department_id') : $current['department_id'];
        $new_pos = getVal($data, 'new_position_id') ? (int)getVal($data, 'new_position_id') : $current['position_id'];
        $full_notes = "[" . getVal($data, 'transfer_type', 'transfer') . "] " . getVal($data, 'notes');

        $log_sql = "INSERT INTO employee_transfer_log 
                    (employee_id, effective_date, from_company_id, to_company_id, from_branch_id, to_branch_id, 
                     from_department_id, to_department_id, from_position_id, to_position_id, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $log_stmt = $mysqli->prepare($log_sql);
        $eff_date = getVal($data, 'effective_date', date('Y-m-d'));
        $log_stmt->bind_param('isiiiiiiiis', 
            $emp_id, $eff_date, 
            $current['company_id'], $new_company, $current['branch_id'], $new_branch,
            $current['department_id'], $new_dept, $current['position_id'], $new_pos, $full_notes
        );
        $log_stmt->execute();

        $up_stmt = $mysqli->prepare("UPDATE employees SET company_id=?, branch_id=?, department_id=?, position_id=? WHERE id=?");
        $up_stmt->bind_param('iiiii', $new_company, $new_branch, $new_dept, $new_pos, $emp_id);
        $up_stmt->execute();

        $mysqli->commit();
        return ['status'=>'success', 'message'=>'บันทึกการโยกย้ายสำเร็จ'];
    } catch (Throwable $e) {
        $mysqli->rollback();
        error_log($e->getMessage());
        return ['status'=>'error', 'message'=>'System Error'];
    }
}

function deleteEmployee($mysqli, $id) {
    $mysqli->begin_transaction();
    try {
        $stmt = $mysqli->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $mysqli->commit();
            return ['status' => 'success', 'message' => 'ลบข้อมูลเรียบร้อย'];
        }
        throw new Exception("ลบไม่สำเร็จ");
    } catch (Throwable $e) {
        $mysqli->rollback();
        error_log($e->getMessage());
        return ['status' => 'error', 'message' => 'System Error'];
    }
}

function getTransferHistory($mysqli, $emp_id) {
    try {
        $sql = "SELECT log.*,
                c1.company_name_th as from_comp, c2.company_name_th as to_comp,
                b1.branch_name_th as from_branch, b2.branch_name_th as to_branch,
                d1.dept_name_th as from_dept, d2.dept_name_th as to_dept,
                p1.position_name_th as from_pos, p2.position_name_th as to_pos
                FROM employee_transfer_log log
                LEFT JOIN companies c1 ON log.from_company_id = c1.id
                LEFT JOIN companies c2 ON log.to_company_id = c2.id
                LEFT JOIN branches b1 ON log.from_branch_id = b1.id
                LEFT JOIN branches b2 ON log.to_branch_id = b2.id
                LEFT JOIN departments d1 ON log.from_department_id = d1.id
                LEFT JOIN departments d2 ON log.to_department_id = d2.id
                LEFT JOIN positions p1 ON log.from_position_id = p1.id
                LEFT JOIN positions p2 ON log.to_position_id = p2.id
                WHERE log.employee_id = ?
                ORDER BY log.effective_date DESC, log.id DESC";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('i', $emp_id);
        $stmt->execute();
        return ['status'=>'success', 'data'=>$stmt->get_result()->fetch_all(MYSQLI_ASSOC)];
    } catch (Throwable $e) {
        error_log($e->getMessage());
        return ['status'=>'error', 'message'=> 'System Error'];
    }
}
?>
