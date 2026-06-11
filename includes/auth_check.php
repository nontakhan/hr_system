<?php
/*
 * ไฟล์สำหรับตรวจสอบการ Login
 * (สำคัญ!) เรียกใช้ไฟล์นี้ใน "ทุกหน้า" ที่ต้องการให้ Login ก่อน
 * ยกเว้นหน้า Login (index.php)
 */

// เริ่ม session ถ้ายังไม่เริ่ม
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบว่ามี session 'user_id' หรือไม่
if (!isset($_SESSION['user_id'])) {
    // ถ้าไม่มี (ยังไม่ Login)
    // ให้ Redirect กลับไปหน้า Login (index.php)
    header("Location: index.php");
    exit(); // หยุดการทำงานของสคริปต์ทันที
}

// (ถ้ามี session 'user_id' อยู่แล้ว โค้ดจะทำงานต่อไปยังเนื้อหาของเพจ)
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/hr_scope_helpers.php';
hrScopeRefreshSession($mysqli);

if (!empty($_SESSION['employee_id'])) {
    $stmt = $mysqli->prepare("SELECT e.first_name_th, e.last_name_th, e.company_id, p.position_name_th
                              FROM employees e
                              LEFT JOIN positions p ON e.position_id = p.id
                              WHERE e.id = ?
                              LIMIT 1");
    if ($stmt) {
        $employeeId = (int)$_SESSION['employee_id'];
        $stmt->bind_param('i', $employeeId);
        $stmt->execute();
        $profile = $stmt->get_result()->fetch_assoc();
        if ($profile) {
            $_SESSION['full_name'] = trim($profile['first_name_th'] . ' ' . $profile['last_name_th']);
            $_SESSION['position_name'] = $profile['position_name_th'] ?: '-';
            $_SESSION['company_id'] = $profile['company_id'];
        }
        $stmt->close();
    }
}
?>
