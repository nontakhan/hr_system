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
    require_once '../includes/hr_scope_helpers.php';
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
        elseif ($action === 'update_profile_image') {
            echo json_encode(updateEmployeeProfileImage($mysqli, $_POST, $_FILES));
        }
        elseif ($action === 'transfer_employee') {
            echo json_encode(transferEmployee($mysqli, $_POST));
        }
        elseif ($action === 'update_transfer_history') {
            echo json_encode(updateTransferHistory($mysqli, $_POST));
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

function normalizeShiftOverrideDays($days) {
    $allowed = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $input = is_array($days) ? $days : explode(',', (string)$days);
    $normalized = [];

    foreach ($input as $day) {
        $day = trim((string)$day);
        if (in_array($day, $allowed, true) && !in_array($day, $normalized, true)) {
            $normalized[] = $day;
        }
    }

    return $normalized;
}

function normalizeNullableDate($value) {
    $value = trim((string)$value);
    if ($value === '') return null;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        throw new InvalidArgumentException("Invalid shift override date");
    }
    return $value;
}

function normalizeShiftOverrideTime($value, $fieldName) {
    $value = trim((string)$value);
    if (!preg_match('/^\d{2}:\d{2}$/', $value) && !preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
        throw new InvalidArgumentException("Invalid shift override {$fieldName}");
    }
    return strlen($value) === 5 ? $value . ':00' : $value;
}

function syncEmployeeShiftOverrides($mysqli, $employeeId, $data) {
    $delete = $mysqli->prepare("DELETE FROM employee_shift_overrides WHERE employee_id = ?");
    if (!$delete) {
        return;
    }
    $delete->bind_param('i', $employeeId);
    if (!$delete->execute()) throw new Exception("Delete shift overrides failed: " . $delete->error);

    $days = normalizeShiftOverrideDays($data['shift_override_days'] ?? []);
    if (empty($days)) {
        return;
    }

    $startTime = normalizeShiftOverrideTime($data['shift_override_start_time'] ?? '', 'start time');
    $endTime = normalizeShiftOverrideTime($data['shift_override_end_time'] ?? '', 'end time');
    $lateTolerance = (int)getVal($data, 'shift_override_late_tolerance_mins', 0);
    if ($lateTolerance < 0) $lateTolerance = 0;
    $effectiveFrom = normalizeNullableDate(getVal($data, 'shift_override_effective_from', date('Y-m-d')));
    $effectiveTo = normalizeNullableDate($data['shift_override_effective_to'] ?? '');
    if ($effectiveFrom === null) $effectiveFrom = date('Y-m-d');
    if ($effectiveTo !== null && $effectiveTo < $effectiveFrom) {
        throw new InvalidArgumentException("Shift override end date must be after start date");
    }

    $dayOfWeek = implode(',', $days);
    $stmt = $mysqli->prepare("INSERT INTO employee_shift_overrides
        (employee_id, day_of_week, start_time, end_time, late_tolerance_mins, effective_from, effective_to, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
    if (!$stmt) throw new Exception("Prepare shift override insert failed: " . $mysqli->error);
    $stmt->bind_param('isssiss', $employeeId, $dayOfWeek, $startTime, $endTime, $lateTolerance, $effectiveFrom, $effectiveTo);
    if (!$stmt->execute()) throw new Exception("Insert shift override failed: " . $stmt->error);
}

function normalizePostedIds($value) {
    $values = is_array($value) ? $value : [$value];
    return array_values(array_unique(array_filter(array_map('intval', $values))));
}

function validateIdsExist(mysqli $mysqli, $table, array $ids) {
    if (!$ids) return true;
    if (!in_array($table, ['companies', 'branches'], true)) {
        return false;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM {$table} WHERE id IN ({$placeholders})");
    if (!$stmt) throw new Exception("Prepare {$table} validation failed: " . $mysqli->error);
    hrScopeBindParams($stmt, str_repeat('i', count($ids)), $ids);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)$row['total'] === count($ids);
}

function syncUserHrScopes(mysqli $mysqli, $userId, $role, array $data) {
    hrScopeEnsureTable($mysqli);
    $userId = (int)$userId;
    $delete = $mysqli->prepare("DELETE FROM user_hr_scopes WHERE user_id = ?");
    if (!$delete) throw new Exception("Prepare HR scope delete failed: " . $mysqli->error);
    $delete->bind_param('i', $userId);
    if (!$delete->execute()) throw new Exception("Delete HR scopes failed: " . $delete->error);

    if (!in_array($role, ['hr', 'admin'], true)) {
        return;
    }

    $companyIds = normalizePostedIds($data['hr_company_ids'] ?? []);
    $branchIds = normalizePostedIds($data['hr_branch_ids'] ?? []);
    if (!validateIdsExist($mysqli, 'companies', $companyIds)) {
        throw new InvalidArgumentException('บริษัท HR ที่เลือกไม่ถูกต้อง');
    }
    if (!validateIdsExist($mysqli, 'branches', $branchIds)) {
        throw new InvalidArgumentException('สาขา HR ที่เลือกไม่ถูกต้อง');
    }

    $insert = $mysqli->prepare("INSERT INTO user_hr_scopes (user_id, scope_type, scope_id) VALUES (?, ?, ?)");
    if (!$insert) throw new Exception("Prepare HR scope insert failed: " . $mysqli->error);
    foreach ($companyIds as $id) {
        $type = 'company';
        $insert->bind_param('isi', $userId, $type, $id);
        if (!$insert->execute()) throw new Exception("Insert HR company scope failed: " . $insert->error);
    }
    foreach ($branchIds as $id) {
        $type = 'branch';
        $insert->bind_param('isi', $userId, $type, $id);
        if (!$insert->execute()) throw new Exception("Insert HR branch scope failed: " . $insert->error);
    }
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
            (int)getVal($data, 'supervisor_id', 0),
            (int)getVal($data, 'default_shift_id', 0),
            // Strings (2)
            getVal($data, 'start_date', date('Y-m-d')), getVal($data, 'status')
        ];

        $sql = "INSERT INTO employees 
        (prefix_th, first_name_th, last_name_th, prefix_en, first_name_en, last_name_en, 
        citizen_id, birth_date, gender, religion, blood_group, marital_status,
        phone_number, current_address, district, province, education_level, emergency_contact_name, emergency_contact_phone,
        profile_img_url, company_id, branch_id, department_id, position_id, employment_type_id, supervisor_id, default_shift_id, start_date, status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULLIF(?,0),?,?,?)";

        $stmt = $mysqli->prepare($sql);
        if(!$stmt) throw new Exception("Prepare failed: " . $mysqli->error);

        // สร้าง Type String อัตโนมัติ: s(20) + i(7) + s(2) = 29 chars
        $types = str_repeat('s', 20) . str_repeat('i', 7) . str_repeat('s', 2);

        bindParamsStrict($stmt, $types, $params);

        if (!$stmt->execute()) throw new Exception("Insert Failed: " . $stmt->error);
        $emp_pk = $mysqli->insert_id;

        syncEmployeeShiftOverrides($mysqli, $emp_pk, $data);

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
            if (!$u_stmt->execute()) throw new Exception("User insert failed: " . $u_stmt->error);
            syncUserHrScopes($mysqli, $mysqli->insert_id, $role, $data);
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
            (int)getVal($data, 'supervisor_id', 0),
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
            employment_type_id=?, supervisor_id=NULLIF(?,0), default_shift_id=?, start_date=?, status=?
            WHERE id=?";

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) throw new Exception("Prepare Update Failed: " . $mysqli->error);

        // สร้าง Type String อัตโนมัติ: s(20) + i(7) + s(2) + i(1) = 30 chars
        $types = str_repeat('s', 20) . str_repeat('i', 7) . str_repeat('s', 2) . 'i';

        bindParamsStrict($stmt, $types, $params);

        if (!$stmt->execute()) throw new Exception("Update Failed: " . $stmt->error);

        syncEmployeeShiftOverrides($mysqli, $id, $data);

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
                if (!$u_stmt->execute()) throw new Exception("User update failed: " . $u_stmt->error);
            } else {
                $u_stmt = $mysqli->prepare("UPDATE users SET role=? WHERE employee_id=?");
                $u_stmt->bind_param('si', $role, $id);
                if (!$u_stmt->execute()) throw new Exception("User role update failed: " . $u_stmt->error);
            }
            syncUserHrScopes($mysqli, (int)$user_exists['id'], $role, $data);
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
                if (!$u_stmt->execute()) throw new Exception("User insert failed: " . $u_stmt->error);
                syncUserHrScopes($mysqli, $mysqli->insert_id, $role, $data);
            }
        }

        $mysqli->commit();
        return ['status'=>'success', 'message'=>'แก้ไขข้อมูลสำเร็จ'];

    } catch (Throwable $e) {
        $mysqli->rollback();
        if ($mysqli->errno == 1062) return ['status'=>'error', 'message'=>'ข้อมูลซ้ำ (เช่น เลขบัตรประชาชน หรือ Username)'];
        if ($mysqli->errno == 1452) return ['status'=>'error', 'message'=>'ข้อมูลอ้างอิงไม่ถูกต้อง กรุณาตรวจสอบ บริษัท/สาขา/แผนก/ตำแหน่ง/หัวหน้างาน'];
        if ($e instanceof InvalidArgumentException) return ['status'=>'error', 'message'=> $e->getMessage()];
        error_log($e->getMessage());
        return ['status'=>'error', 'message'=> 'System Error'];
    }
}

function updateEmployeeProfileImage($mysqli, $data, $files) {
    try {
        $id = (int)getVal($data, 'id', 0);
        if ($id <= 0) throw new InvalidArgumentException("Invalid employee ID");

        if (!isset($files['profile_image']) || $files['profile_image']['error'] === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException("กรุณาเลือกรูปภาพ");
        }

        $profile_img_url = saveProfileImage($files['profile_image']);
        $stmt = $mysqli->prepare("UPDATE employees SET profile_img_url = ? WHERE id = ?");
        if (!$stmt) throw new Exception("Prepare profile image update failed: " . $mysqli->error);

        $stmt->bind_param('si', $profile_img_url, $id);
        if (!$stmt->execute()) throw new Exception("Profile image update failed: " . $stmt->error);

        return [
            'status' => 'success',
            'message' => 'อัปโหลดรูปโปรไฟล์สำเร็จ',
            'profile_img_url' => $profile_img_url
        ];
    } catch (Throwable $e) {
        if ($e instanceof InvalidArgumentException) return ['status'=>'error', 'message'=> $e->getMessage()];
        error_log($e->getMessage());
        return ['status'=>'error', 'message'=> 'System Error'];
    }
}

function getAllEmployees($mysqli) {
    try {
        $role = $_SESSION['role'];
        $company_id = $_SESSION['company_id'] ?? 0;
        $scopes = hrScopeCurrentSessionScopes();
        
        // รับค่า branch_id จาก URL
        $filter_branch_id = isset($_GET['branch_id']) && is_numeric($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;

        $sql = "SELECT e.id, e.citizen_id, e.first_name_th, e.last_name_th, e.profile_img_url, e.status,
                       p.position_name_th, d.dept_name_th, c.company_name_th, b.branch_name_th 
                FROM employees e
                LEFT JOIN positions p ON e.position_id = p.id
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN companies c ON e.company_id = c.id
                LEFT JOIN branches b ON e.branch_id = b.id
                WHERE 1=1 ";

        if ($role === 'hr') {
            $scopeClause = hrScopeBuildEmployeeWhereClause($role, $scopes, 'e');
            $sql .= $scopeClause['sql'];
            if ($filter_branch_id > 0) {
                $sql .= " AND e.branch_id = ? ";
                $scopeClause['types'] .= 'i';
                $scopeClause['params'][] = $filter_branch_id;
            }
            $sql .= " ORDER BY e.id DESC";
            $stmt = $mysqli->prepare($sql);
            hrScopeBindParams($stmt, $scopeClause['types'], $scopeClause['params']);
            $stmt->execute();
            $res = $stmt->get_result();
            return ['status'=>'success', 'data'=>$res->fetch_all(MYSQLI_ASSOC)];
        }

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

function updateTransferHistory($mysqli, $data) {
    $mysqli->begin_transaction();
    try {
        $log_id = (int)getVal($data, 'transfer_log_id', 0);
        $emp_id = (int)getVal($data, 'employee_id', 0);
        if ($log_id <= 0 || $emp_id <= 0) throw new InvalidArgumentException("Invalid history ID");

        $check = $mysqli->prepare("SELECT id FROM employee_transfer_log WHERE id = ? AND employee_id = ?");
        $check->bind_param('ii', $log_id, $emp_id);
        $check->execute();
        if ($check->get_result()->num_rows !== 1) throw new InvalidArgumentException("History record not found");

        $new_company = (int)getVal($data, 'new_company_id', 0);
        $new_branch = (int)getVal($data, 'new_branch_id', 0);
        $new_dept = (int)getVal($data, 'new_department_id', 0);
        $new_pos = (int)getVal($data, 'new_position_id', 0);
        if ($new_company <= 0 || $new_branch <= 0 || $new_dept <= 0 || $new_pos <= 0) {
            throw new InvalidArgumentException("กรุณาเลือกข้อมูลใหม่ให้ครบถ้วน");
        }

        $eff_date = getVal($data, 'effective_date', date('Y-m-d'));
        $full_notes = "[" . getVal($data, 'transfer_type', 'transfer') . "] " . getVal($data, 'notes');

        $sql = "UPDATE employee_transfer_log
                SET effective_date = ?, to_company_id = ?, to_branch_id = ?,
                    to_department_id = ?, to_position_id = ?, notes = ?
                WHERE id = ? AND employee_id = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('siiiisii', $eff_date, $new_company, $new_branch, $new_dept, $new_pos, $full_notes, $log_id, $emp_id);
        if (!$stmt->execute()) throw new Exception("Update transfer history failed: " . $stmt->error);

        $latest = $mysqli->prepare("SELECT id, to_company_id, to_branch_id, to_department_id, to_position_id
                                    FROM employee_transfer_log
                                    WHERE employee_id = ?
                                    ORDER BY effective_date DESC, id DESC
                                    LIMIT 1");
        $latest->bind_param('i', $emp_id);
        $latest->execute();
        $latest_row = $latest->get_result()->fetch_assoc();

        if ($latest_row) {
            $current_company = (int)$latest_row['to_company_id'];
            $current_branch = (int)$latest_row['to_branch_id'];
            $current_dept = (int)$latest_row['to_department_id'];
            $current_pos = (int)$latest_row['to_position_id'];
            $up_stmt = $mysqli->prepare("UPDATE employees SET company_id=?, branch_id=?, department_id=?, position_id=? WHERE id=?");
            $up_stmt->bind_param('iiiii', $current_company, $current_branch, $current_dept, $current_pos, $emp_id);
            $up_stmt->execute();
        }

        $mysqli->commit();
        return ['status'=>'success', 'message'=>'แก้ไขประวัติสำเร็จ'];
    } catch (Throwable $e) {
        $mysqli->rollback();
        if ($e instanceof InvalidArgumentException) return ['status'=>'error', 'message'=> $e->getMessage()];
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
