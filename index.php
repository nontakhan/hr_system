<?php
/*
 * หน้า Login (index.php)
 * หน้านี้ "ไม่ต้อง" เรียกใช้ auth_check.php
 */

// เริ่ม session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ถ้า Login อยู่แล้ว ให้ Redirect ไปหน้า dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// ตั้งชื่อ Title สำหรับหน้านี้ (จะถูกใช้ใน header.php)
$page_title = "Login - HR System";

// (เราจะไม่ include header.php แบบปกติ เพราะหน้านี้มี layout พิเศษ)
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>

    <!-- โหลด CSS ที่จำเป็น -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

    <div class="login-container">
        <div class="login-card card">
            <div class="card-body">
                <h3 class="text-center mb-4">HR SYSTEM LOGIN</h3>
                <p class="text-center text-muted mb-4">กรุณาลงชื่อเข้าสู่ระบบ</p>
                
                <!-- ฟอร์ม Login -->
                <!-- (สำคัญ!) เราจะใช้ id="loginForm" เพื่อให้ main.js มาจัดการ -->
                <form id="loginForm" method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <!-- (เราจะใช้ปุ่ม type="submit" เพื่อให้ JavaScript ดักจับ event) -->
                    <button type="submit" class="btn btn-primary w-100 mt-3">
                        Login
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- โหลด JS ที่จำเป็น -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/main.js"></script>

    <!-- (เราจะเขียน JS สำหรับจัดการ Login ใน main.js หรือที่นี่ทีหลัง) -->

</body>
</html>