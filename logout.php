<?php
/*
 * ไฟล์สำหรับ Logout
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. ลบค่าทั้งหมดใน session
session_unset();

// 2. ทำลาย session
session_destroy();

// 3. Redirect กลับไปหน้า Login
header("Location: index.php");
exit();
?>