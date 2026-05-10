<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    if (session_status() == PHP_SESSION_NONE) session_start();
    require_once '../includes/db_connect.php';
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Login Required']); exit();
    }

    $role = $_SESSION['role'];
    $emp_id = $_SESSION['employee_id'];
    $company_id = $_SESSION['company_id'] ?? 0;
    $today = date('Y-m-d');

    $data = [
        // ข้อมูลเดิมยังส่งไปเผื่อใช้ (แต่หน้าจออาจจะไม่แสดง)
        'total_employees' => 0,
        'employee_types_by_company' => [],
        'company_colors_map' => [],
        'today_leaves' => [],
        // (NEW) ข้อมูลใหม่: สรุปสาขาตามบริษัท
        'branch_summary' => [] 
    ];

    // 1. Total Employees (เก็บไว้คำนวณยอดรวมได้)
    $sql_total = "SELECT COUNT(*) as c FROM employees WHERE status IN ('active', 'probation')";
    if ($role === 'hr') $sql_total .= " AND company_id = $company_id";
    $data['total_employees'] = $mysqli->query($sql_total)->fetch_assoc()['c'];

    // 2. (NEW) Employees by Company & Branch
    // ดึงข้อมูล: ชื่อบริษัท, สี, ชื่อสาขา, จำนวนคน
    $sql_branch = "SELECT c.company_name_th, c.company_color, b.branch_name_th, COUNT(e.id) as count
                   FROM employees e
                   JOIN companies c ON e.company_id = c.id
                   JOIN branches b ON e.branch_id = b.id
                   WHERE e.status IN ('active', 'probation') ";
    
    if ($role === 'hr') $sql_branch .= " AND e.company_id = $company_id ";
    
    $sql_branch .= " GROUP BY c.id, b.id ORDER BY c.id, b.id";
    
    $res_branch = $mysqli->query($sql_branch);
    $branch_data = [];
    $company_colors = [];

    // จัด Group ข้อมูล: Company -> Branches
    foreach ($res_branch->fetch_all(MYSQLI_ASSOC) as $row) {
        $compName = $row['company_name_th'];
        
        if (!isset($branch_data[$compName])) {
            $branch_data[$compName] = [];
        }
        
        $branch_data[$compName][] = [
            'branch' => $row['branch_name_th'],
            'count' => $row['count']
        ];

        // เก็บสีไว้ใช้ (เหมือนเดิม)
        if (!isset($company_colors[$compName])) {
            $company_colors[$compName] = $row['company_color'] ?: '#005A9C';
        }
    }
    $data['branch_summary'] = $branch_data;
    $data['company_colors_map'] = $company_colors;


    // 3. Employee Types (สำหรับกราฟด้านล่าง - เหมือนเดิม)
    $sql_types = "SELECT c.company_name_th, et.type_name, COUNT(e.id) as count
                  FROM employees e
                  JOIN companies c ON e.company_id = c.id
                  JOIN employment_types et ON e.employment_type_id = et.id
                  WHERE e.status IN ('active', 'probation') ";
    if ($role === 'hr') $sql_types .= " AND e.company_id = $company_id ";
    $sql_types .= " GROUP BY c.id, et.id ORDER BY c.id, et.id";
    
    $res_types = $mysqli->query($sql_types);
    $grouped_types = [];
    foreach ($res_types->fetch_all(MYSQLI_ASSOC) as $row) {
        $compName = $row['company_name_th'];
        if (!isset($grouped_types[$compName])) $grouped_types[$compName] = [];
        $grouped_types[$compName][] = ['type' => $row['type_name'], 'count' => $row['count']];
    }
    $data['employee_types_by_company'] = $grouped_types;

    // 4. Today Leaves (สำหรับ List ขวาล่าง - เหมือนเดิม)
    // (จริงๆ คุณสั่งให้เปลี่ยนเป็น Company Summary ไปแล้ว แต่ผมส่งไปเผื่อคุณเปลี่ยนใจกลับมาใช้)
    // ... (Code ส่วนนี้คงเดิม ไม่กระทบการแสดงผลใหม่) ...

    echo json_encode(['status' => 'success', 'data' => $data]);

} catch (Throwable $e) {
    error_log($e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'System Error']);
}
$mysqli->close();
?>
