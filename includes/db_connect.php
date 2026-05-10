<?php
/*
 * ไฟล์สำหรับเชื่อมต่อฐานข้อมูล MySQL
 *
 * (สำคัญ!) กรุณาแก้ไขค่าเหล่านี้ให้ตรงกับสภาพแวดล้อมของคุณ
 */

define('DB_HOST', '10.10.202.156');    // เช่น 'localhost' หรือ IP ของเซิร์ฟเวอร์
define('DB_USER', 'nr');         // Username ของ MySQL
define('DB_PASS', 'P@ssw0rd');             // Password ของ MySQL
define('DB_NAME', 'hr_system');    // ชื่อฐานข้อมูลที่เราสร้าง (hr_database.sql)

// พยายามเชื่อมต่อฐานข้อมูล
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// ตรวจสอบการเชื่อมต่อ
if ($mysqli->connect_errno) {
    // หากเชื่อมต่อไม่ได้ (เช่น ใส่รหัสผิด, ไม่มีฐานข้อมูล)
    // ให้แสดงข้อผิดพลาดและหยุดการทำงานทันที
    echo "Failed to connect to MySQL: " . $mysqli->connect_error;
    exit();
}

// ตั้งค่า Character Set เป็น utf8mb4 (สำคัญมากสำหรับภาษาไทย)
if (!$mysqli->set_charset("utf8mb4")) {
    echo "Error loading character set utf8mb4: " . $mysqli->error;
    exit();
}

// (เราจะใช้ $mysqli ตัวแปรนี้ในไฟล์ API ต่างๆ)
?>