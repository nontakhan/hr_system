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
    <link rel="icon" type="image/svg+xml" href="assets/img/nr-backoffice-favicon.svg">

    <style>
        body { background: #f3f1f1; min-height: 100vh; }
        .login-container { background: #f3f1f1; min-height: 100vh; display: grid; place-items: center; padding: 2rem 1rem; }
        .login-shell { display: block; box-shadow: 0 2px 8px rgba(0,0,0,.06); width: 100%; max-width: 440px; background: #fff; border: 1px solid #e5dfdf; border-radius: 12px; }
        .login-info-panel { background: #fff; color: #111827; padding: 1.5rem 1.75rem 0; border-top: 5px solid #b91c1c; border-radius: 12px 12px 0 0; }
        .login-system-title { font-size: 1.35rem; font-weight: 700; margin: 0; color: #991b1b; }
        .login-system-desc { color: #4b5563; margin: .5rem 0 0; font-size: .9rem; }
        .login-card.card { padding: 0; border: 0; background: transparent; }
        .login-card .card-body { padding: 1.5rem 1.75rem; }
        .login-card h2 { font-size: 1.5rem; font-weight: 700; }
        .form-label { font-weight: 600; }
        .input-elegant { position: relative; }
        .input-elegant .form-control { min-height: 48px; padding-left: 2.5rem; }
        .input-icon { position: absolute; left: .9rem; top: 50%; transform: translateY(-50%); color: #6b7280; pointer-events: none; }
        #password { padding-right: 3rem; }
        .toggle-password { position: absolute; right: 2px; top: 2px; width: 44px; height: 44px; border: 0; background: transparent; color: #4b5563; border-radius: 4px; }
        #passwordHelpToggle, .btn-login { min-height: 44px; }
        .btn-login { background: #b91c1c; border-color: #b91c1c; font-weight: 600; }
        .btn-login .spinner-border { display: none; width: 1rem; height: 1rem; }
        .login-footer-note { color: #595959; font-size: .8rem; margin: 1.5rem 0 0; }
        @media (max-width: 400px) { .login-container { padding: 1rem .75rem; align-items: start; } .login-info-panel { padding: 1.25rem 1rem 0; } .login-card .card-body { padding: 1.25rem 1rem; } }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-shell">
            <section class="login-info-panel" aria-labelledby="loginSystemTitle">
                <h1 class="login-system-title" id="loginSystemTitle">NR Backoffice · เครือนำรุ่ง</h1>
                <p class="login-system-desc">ระบบบริหารทรัพยากรบุคคล</p>
            </section>

            <div class="login-card card">
                <div class="card-body">
                    <h2 class="mb-1">เข้าสู่ระบบ</h2>
                    <p class="text-muted mb-4">ใช้บัญชีที่ได้รับจากฝ่ายบุคคลหรือผู้ดูแลระบบ</p>

                    <form id="loginForm" method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">ชื่อผู้ใช้</label>
                            <div class="input-elegant">
                                <i class="bi bi-person-fill input-icon"></i>
                                <input type="text" class="form-control" id="username" name="username" placeholder="กรอกชื่อผู้ใช้งาน" required autocomplete="username">
                            </div>
                        </div>

                        <div class="mb-2">
                            <label for="password" class="form-label">รหัสผ่าน</label>
                            <div class="input-elegant">
                                <i class="bi bi-lock-fill input-icon"></i>
                                <input type="password" class="form-control" id="password" name="password" placeholder="กรอกรหัสผ่าน" required autocomplete="current-password">
                                <button type="button" class="toggle-password" id="togglePassword" aria-label="แสดงรหัสผ่าน" aria-pressed="false">
                                    <i class="bi bi-eye-fill"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end align-items-center mt-3 mb-1">
                            <button type="button" id="passwordHelpToggle" class="btn btn-link btn-sm text-danger px-1" aria-expanded="false" aria-controls="passwordHelp">ลืมรหัสผ่าน?</button>
                        </div>

                        <p id="passwordHelp" class="small text-muted mt-3 mb-0" hidden>ติดต่อฝ่ายบุคคลหรือผู้ดูแลระบบเพื่อขอรีเซ็ตรหัสผ่าน โดยแจ้งชื่อผู้ใช้ของคุณ</p>

                        <button type="submit" class="btn btn-login btn-danger w-100 mt-3 text-white" id="loginBtn">
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                            เข้าสู่ระบบ
                        </button>
                    </form>

                    <p class="login-footer-note">© <?php echo date('Y'); ?> เครือนำรุ่ง Backoffice System. All rights reserved.</p>
                </div>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/login.js"></script>
    <script>
        // Toggle password visibility (เสริม UX โดยไม่กระทบฟังก์ชันเดิม)
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function () {
                const isPassword = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                this.setAttribute('aria-label', isPassword ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน');
                this.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
                this.querySelector('i').classList.toggle('bi-eye-fill');
                this.querySelector('i').classList.toggle('bi-eye-slash-fill');
            });
        }

        document.getElementById('passwordHelpToggle')?.addEventListener('click', function () {
            const help = document.getElementById('passwordHelp');
            help.hidden = !help.hidden;
            this.setAttribute('aria-expanded', help.hidden ? 'false' : 'true');
        });
    </script>
</body>
</html>
