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
?>