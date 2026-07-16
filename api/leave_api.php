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
    require_once '../includes/hr_scope_helpers.php';
    require_once '../includes/employee_warning_helpers.php';
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        sendJsonError('กรุณาเข้าสู่ระบบก่อนใช้งาน');
    }

    $method = $_SERVER['REQUEST_METHOD'];

    // --- GET: ดึงข้อมูล ---
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';
        $role = $_SESSION['role'] ?? 'employee';

        if (in_array($action, ['approved_leave_report', 'approved_leave_report_filters'], true)) {
            if (!in_array($role, ['admin', 'hr'], true)) {
                sendJsonError('Access Denied');
            }

            if ($action === 'approved_leave_report_filters') {
                echo json_encode(['status' => 'success', 'data' => fetchApprovedLeaveReportFilters($mysqli, $role)]);
                exit();
            }

            $month = $_GET['month'] ?? date('Y-m');
            if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
                sendJsonError('Invalid month');
            }
            foreach (['company_id', 'branch_id', 'leave_type_id'] as $filterKey) {
                if (isset($_GET[$filterKey]) && $_GET[$filterKey] !== '' && (!ctype_digit((string)$_GET[$filterKey]) || (int)$_GET[$filterKey] <= 0)) {
                    sendJsonError('Invalid filter');
                }
            }

            $rows = fetchApprovedLeaveReportRows($mysqli, $role, [
                'month' => $month,
                'company_id' => (int)($_GET['company_id'] ?? 0),
                'branch_id' => (int)($_GET['branch_id'] ?? 0),
                'leave_type_id' => (int)($_GET['leave_type_id'] ?? 0),
            ]);
            employeeWarningEnsureTables($mysqli);
            $rows = employeeWarningAnnotateReportRows(
                $mysqli,
                $rows,
                EMPLOYEE_WARNING_SOURCE_APPROVED_LEAVE,
                fn(array $row): array => [
                    'id' => $row['id'] ?? 0,
                    'leave_date' => $row['leave_date'] ?? '',
                ]
            );
            echo json_encode([
                'status' => 'success',
                'month' => $month,
                'summary' => leaveCountApprovedReportRows($rows),
                'data' => $rows,
            ]);
            exit();
        }
        
        if ($action === 'get_types') {
            leaveEnsureHourlyRequestTypes($mysqli);
            leaveEnsureLeaveTypeCalculationColumns($mysqli);
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
                    'vacation_min_months_before_leave' => $activePolicy['vacation_min_months_before_leave'],
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
            leaveEnsureLeaveTypeCalculationColumns($mysqli);
            $calculation = leaveNormalizeLeaveTypeCalculation($input);
            $sql = "INSERT INTO leave_types (type_name, days_per_year, description, requires_file, calculation_unit, hours_per_day, hour_full_day_threshold, vacation_min_months_before_leave, is_actual_leave) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $mysqli->prepare($sql);
            $req_file = !empty($input['requires_file']) ? 1 : 0;
            $stmt->bind_param('sisisddii', $input['type_name'], $input['days_per_year'], $input['description'], $req_file, $calculation['calculation_unit'], $calculation['hours_per_day'], $calculation['hour_full_day_threshold'], $calculation['vacation_min_months_before_leave'], $calculation['is_actual_leave']);
            
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'เพิ่มประเภทการลาสำเร็จ']);
            } else {
                throw new Exception($stmt->error);
            }
        }
        elseif ($action === 'update_type') {
            leaveEnsureLeaveTypeCalculationColumns($mysqli);
            $calculation = leaveNormalizeLeaveTypeCalculation($input);
            $sql = "UPDATE leave_types SET type_name=?, days_per_year=?, description=?, requires_file=?, calculation_unit=?, hours_per_day=?, hour_full_day_threshold=?, vacation_min_months_before_leave=?, is_actual_leave=? WHERE id=?";
            $stmt = $mysqli->prepare($sql);
            $req_file = !empty($input['requires_file']) ? 1 : 0;
            $stmt->bind_param('sisisddiii', $input['type_name'], $input['days_per_year'], $input['description'], $req_file, $calculation['calculation_unit'], $calculation['hours_per_day'], $calculation['hour_full_day_threshold'], $calculation['vacation_min_months_before_leave'], $calculation['is_actual_leave'], $input['id']);
            
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
                'vacation_min_months_before_leave' => $input['vacation_min_months_before_leave'] ?? 0,
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
                    'vacation_min_months_before_leave' => $activePolicy['vacation_min_months_before_leave'],
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
                    'vacation_min_months_before_leave' => $activePolicy['vacation_min_months_before_leave'],
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

function fetchApprovedLeaveReportFilters(mysqli $mysqli, $role) {
    $sql = "SELECT e.company_id, c.company_name_th, e.branch_id, b.branch_name_th
            FROM employees e
            LEFT JOIN companies c ON e.company_id = c.id
            LEFT JOIN branches b ON e.branch_id = b.id
            WHERE e.status IN ('active', 'probation')";
    $types = '';
    $params = [];
    if ($role === 'hr') {
        $scope = hrScopeBuildEmployeeWhereClause($role, hrScopeCurrentSessionScopes(), 'e');
        $sql .= $scope['sql'];
        $types .= $scope['types'];
        $params = array_merge($params, $scope['params']);
    }
    $stmt = $mysqli->prepare($sql);
    hrScopeBindParams($stmt, $types, $params);
    $stmt->execute();

    $companies = [];
    $branches = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        if (!empty($row['company_id'])) {
            $companies[(int)$row['company_id']] = $row['company_name_th'] ?: '-';
        }
        if (!empty($row['branch_id'])) {
            $branches[(int)$row['branch_id']] = [
                'label' => $row['branch_name_th'] ?: '-',
                'company_id' => (int)$row['company_id'],
            ];
        }
    }
    $stmt->close();

    $leaveTypes = [];
    $result = $mysqli->query("SELECT id, type_name FROM leave_types WHERE is_actual_leave = 1 ORDER BY type_name");
    while ($row = $result->fetch_assoc()) {
        $leaveTypes[] = ['id' => (int)$row['id'], 'label' => $row['type_name']];
    }

    return [
        'companies' => leaveReportBuildOptionRows($companies),
        'branches' => leaveReportBuildOptionRows($branches),
        'leave_types' => $leaveTypes,
    ];
}

function leaveReportBuildOptionRows(array $items) {
    $rows = [];
    foreach ($items as $id => $label) {
        $row = ['id' => (int)$id];
        if (is_array($label)) {
            $row['label'] = $label['label'];
            $row['company_id'] = (int)$label['company_id'];
        } else {
            $row['label'] = $label;
        }
        $rows[] = $row;
    }
    usort($rows, function ($a, $b) {
        return strnatcasecmp((string)$a['label'], (string)$b['label']);
    });
    return $rows;
}

function fetchApprovedLeaveReportRows(mysqli $mysqli, $role, array $filters) {
    $month = $filters['month'];
    $monthStart = $month . '-01';
    $monthEnd = date('Y-m-t', strtotime($monthStart));
    $sql = "SELECT lr.id, lr.employee_id, lr.start_date, lr.end_date,
                   lr.start_day_part, lr.end_day_part, lr.reason,
                   e.citizen_id, CONCAT(e.first_name_th, ' ', e.last_name_th) AS full_name,
                   e.company_id, e.branch_id,
                   p.position_name_th, c.company_name_th, b.branch_name_th,
                   lt.id AS leave_type_id, lt.type_name AS leave_type_name
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.id
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN companies c ON e.company_id = c.id
            LEFT JOIN branches b ON e.branch_id = b.id
            WHERE lr.status = 'approved'
              AND lt.is_actual_leave = 1
              AND (lr.request_unit = 'day' OR (lr.request_unit = 'hour' AND lr.time_request_type IS NULL))
              AND lr.start_date <= ?
              AND lr.end_date >= ?
              AND e.status IN ('active', 'probation')";
    $types = 'ss';
    $params = [$monthEnd, $monthStart];

    foreach (['company_id' => 'e.company_id', 'branch_id' => 'e.branch_id', 'leave_type_id' => 'lt.id'] as $key => $column) {
        $id = (int)($filters[$key] ?? 0);
        if ($id > 0) {
            $sql .= " AND {$column} = ?";
            $types .= 'i';
            $params[] = $id;
        }
    }
    if ($role === 'hr') {
        $scope = hrScopeBuildEmployeeWhereClause($role, hrScopeCurrentSessionScopes(), 'e');
        $sql .= $scope['sql'];
        $types .= $scope['types'];
        $params = array_merge($params, $scope['params']);
    }
    $sql .= ' ORDER BY lr.start_date, e.first_name_th, e.last_name_th, lr.id';

    $stmt = $mysqli->prepare($sql);
    hrScopeBindParams($stmt, $types, $params);
    $stmt->execute();
    $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $holidays = leaveFetchCompanyHolidays($mysqli, $monthStart, $monthEnd);
    $workDaysByEmployee = [];
    $rows = [];
    foreach ($requests as $request) {
        $employeeId = (int)$request['employee_id'];
        if (!array_key_exists($employeeId, $workDaysByEmployee)) {
            $workDaysByEmployee[$employeeId] = leaveFetchEmployeeWorkDays($mysqli, $employeeId);
        }
        $request['id'] = (int)$request['id'];
        $request['employee_id'] = $employeeId;
        $request['company_id'] = isset($request['company_id']) ? (int)$request['company_id'] : null;
        $request['branch_id'] = isset($request['branch_id']) ? (int)$request['branch_id'] : null;
        $request['leave_type_id'] = (int)$request['leave_type_id'];
        $rows = array_merge($rows, leaveExpandApprovedRequestForMonth(
            $request,
            $month,
            $workDaysByEmployee[$employeeId],
            $holidays
        ));
    }
    usort($rows, function ($a, $b) {
        return [$a['leave_date'], $a['full_name'], $a['id']] <=> [$b['leave_date'], $b['full_name'], $b['id']];
    });
    return $rows;
}
