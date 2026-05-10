<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$page_title = "Login - HR System";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-shell">
            <section class="login-info-panel">
                <p class="login-kicker">Backoffice Platform</p>
                <h1 class="login-system-title">ระบบ Backoffice เครือนำรุ่ง</h1>
                <p class="login-system-desc">
                    ระบบบริหารทรัพยากรบุคคลสำหรับการจัดการข้อมูลพนักงาน การลา และงานปฏิบัติการภายในองค์กรอย่างมืออาชีพ
                </p>
            </section>

            <div class="login-card card">
                <div class="card-body">
                    <h3 class="mb-2">เข้าสู่ระบบ</h3>
                    <p class="text-muted mb-4">กรุณากรอก Username และ Password</p>
                    <form id="loginForm" method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mt-3">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/main.js"></script>
</body>
</html>
