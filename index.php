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

    <style>
        :root {
            --maroon-950: #1a0000;
            --maroon-900: #2b0000;
            --red-800: #8b0000;
            --red-700: #a8071a;
            --red-600: #c8102e;
            --red-500: #e11d3c;
            --gold-400: #d4af37;
            --gold-300: #e8c76b;
            --cream: #fdf6ec;
        }

        * { font-family: 'Sarabun', sans-serif; }

        body {
            margin: 0;
            min-height: 100vh;
            background: radial-gradient(circle at 20% 20%, var(--red-800) 0%, var(--maroon-950) 55%, #000 100%);
            overflow-x: hidden;
            position: relative;
        }

        /* Decorative glowing orbs */
        body::before, body::after {
            content: "";
            position: fixed;
            border-radius: 50%;
            filter: blur(90px);
            z-index: 0;
            opacity: .55;
        }
        body::before {
            width: 480px; height: 480px;
            background: var(--red-600);
            top: -140px; left: -140px;
        }
        body::after {
            width: 420px; height: 420px;
            background: var(--gold-400);
            bottom: -160px; right: -120px;
            opacity: .25;
        }

        .login-container {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .login-shell {
            width: 100%;
            max-width: 980px;
            display: grid;
            grid-template-columns: 1.05fr 1fr;
            border-radius: 28px;
            overflow: hidden;
            box-shadow:
                0 25px 60px -15px rgba(0,0,0,.65),
                0 0 0 1px rgba(212,175,55,.25),
                0 0 80px rgba(200,16,46,.25);
            background: rgba(20,4,4,.35);
            backdrop-filter: blur(6px);
        }

        @media (max-width: 860px) {
            .login-shell { grid-template-columns: 1fr; }
        }

        /* LEFT INFO PANEL */
        .login-info-panel {
            position: relative;
            padding: 3.2rem 2.6rem;
            background:
                linear-gradient(160deg, var(--red-700) 0%, var(--maroon-900) 65%, #0d0000 100%);
            color: var(--cream);
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 1rem;
            overflow: hidden;
        }

        .login-info-panel::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image:
                repeating-linear-gradient(135deg, rgba(212,175,55,.06) 0 2px, transparent 2px 26px);
            pointer-events: none;
        }

        .login-info-panel::after {
            content: "";
            position: absolute;
            width: 260px; height: 260px;
            border: 1px solid rgba(212,175,55,.35);
            border-radius: 50%;
            right: -90px;
            bottom: -90px;
        }

        .brand-mark {
            display: flex;
            align-items: center;
            gap: .6rem;
            margin-bottom: .6rem;
        }

        .brand-mark .dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            background: var(--gold-300);
            box-shadow: 0 0 12px var(--gold-300);
        }

        .login-kicker {
            letter-spacing: 3px;
            text-transform: uppercase;
            font-size: .74rem;
            font-weight: 600;
            color: var(--gold-300);
            margin: 0;
        }

        .login-system-title {
            font-family: 'Sarabun', sans-serif;
            font-size: 2.15rem;
            font-weight: 700;
            line-height: 1.3;
            margin: .3rem 0 .8rem;
            position: relative;
            z-index: 1;
        }

        .login-system-desc {
            font-size: .96rem;
            line-height: 1.75;
            color: rgba(253,246,236,.82);
            max-width: 420px;
            position: relative;
            z-index: 1;
        }

        .info-feature-list {
            list-style: none;
            padding: 0;
            margin: 1.6rem 0 0;
            display: flex;
            flex-direction: column;
            gap: .65rem;
            position: relative;
            z-index: 1;
        }
        .info-feature-list li {
            display: flex;
            align-items: center;
            gap: .6rem;
            font-size: .88rem;
            color: rgba(253,246,236,.9);
        }
        .info-feature-list li i {
            width: 26px; height: 26px;
            border-radius: 8px;
            background: rgba(212,175,55,.15);
            border: 1px solid rgba(212,175,55,.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .8rem;
            color: var(--gold-300);
            flex-shrink: 0;
        }

        /* RIGHT LOGIN CARD */
        .login-card.card {
            border: none;
            border-radius: 0;
            background: linear-gradient(180deg, #fffdf9 0%, var(--cream) 100%);
            display: flex;
            align-items: center;
        }

        .login-card .card-body {
            padding: 3.4rem 2.8rem;
            width: 100%;
        }

        .login-card h3 {
            font-family: 'Sarabun', sans-serif;
            font-weight: 700;
            color: var(--maroon-950);
            letter-spacing: .3px;
        }

        .login-card .text-muted {
            font-size: .9rem;
        }

        .login-badge-circle {
            width: 58px; height: 58px;
            border-radius: 16px;
            background: linear-gradient(145deg, var(--red-600), var(--maroon-900));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold-300);
            font-size: 1.5rem;
            margin-bottom: 1.1rem;
            box-shadow: 0 10px 25px -8px rgba(168,7,26,.55);
        }

        .form-label {
            font-weight: 600;
            font-size: .85rem;
            color: var(--maroon-900);
            letter-spacing: .2px;
        }

        .input-elegant {
            position: relative;
        }

        .input-elegant .form-control {
            border: 1.5px solid #eadfd4;
            border-radius: 12px;
            padding: .72rem 1rem .72rem 2.6rem;
            font-size: .95rem;
            background: #fff;
            transition: all .25s ease;
        }

        .input-elegant .form-control:focus {
            border-color: var(--red-600);
            box-shadow: 0 0 0 3px rgba(200,16,46,.14);
        }

        .input-elegant .input-icon {
            position: absolute;
            left: .95rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--red-700);
            font-size: .95rem;
            opacity: .75;
            pointer-events: none;
        }

        .toggle-password {
            position: absolute;
            right: .9rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #8b7d6b;
            cursor: pointer;
            font-size: .9rem;
            padding: 0;
        }
        .toggle-password:hover { color: var(--red-700); }

        .form-check-label { font-size: .85rem; color: #6b5f52; }

        .btn-login {
            background: linear-gradient(135deg, var(--red-600), var(--red-800));
            border: none;
            border-radius: 12px;
            padding: .78rem 1rem;
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: .3px;
            box-shadow: 0 12px 28px -10px rgba(168,7,26,.6);
            transition: transform .2s ease, box-shadow .2s ease, filter .2s ease;
        }

        .btn-login:hover, .btn-login:focus {
            transform: translateY(-2px);
            filter: brightness(1.06);
            box-shadow: 0 16px 32px -10px rgba(168,7,26,.7);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login .spinner-border {
            display: none;
            width: 1.05rem; height: 1.05rem;
            margin-right: .5rem;
        }

        .login-footer-note {
            text-align: center;
            font-size: .78rem;
            color: #a89a89;
            margin-top: 1.8rem;
        }

        .gold-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--gold-400), transparent);
            margin: 1.6rem 0;
            opacity: .55;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-shell { animation: fadeUp .6s ease; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-shell">
            <section class="login-info-panel">
                <div class="brand-mark">
                    <span class="dot"></span>
                    <p class="login-kicker">Backoffice Platform</p>
                </div>
                <h1 class="login-system-title">ระบบ Backoffice<br>เครือนำรุ่ง</h1>
                <p class="login-system-desc">
                    ระบบบริหารทรัพยากรบุคคลสำหรับการจัดการข้อมูลพนักงาน การลา
                    และงานปฏิบัติการภายในองค์กรอย่างมืออาชีพ ปลอดภัย และเชื่อถือได้
                </p>
                <ul class="info-feature-list">
                    <li><i class="bi bi-shield-lock"></i> ระบบความปลอดภัยระดับองค์กร</li>
                    <li><i class="bi bi-people"></i> จัดการข้อมูลพนักงานครบวงจร</li>
                    <li><i class="bi bi-graph-up-arrow"></i> รายงานและวิเคราะห์ข้อมูลแบบเรียลไทม์</li>
                </ul>
            </section>

            <div class="login-card card">
                <div class="card-body">
                    <div class="login-badge-circle">
                        <i class="bi bi-briefcase-fill"></i>
                    </div>
                    <h3 class="mb-1">เข้าสู่ระบบ</h3>
                    <p class="text-muted mb-4">กรุณากรอก Username และ Password เพื่อดำเนินการต่อ</p>

                    <form id="loginForm" method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <div class="input-elegant">
                                <i class="bi bi-person-fill input-icon"></i>
                                <input type="text" class="form-control" id="username" name="username" placeholder="กรอกชื่อผู้ใช้งาน" required autocomplete="username">
                            </div>
                        </div>

                        <div class="mb-2">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-elegant">
                                <i class="bi bi-lock-fill input-icon"></i>
                                <input type="password" class="form-control" id="password" name="password" placeholder="กรอกรหัสผ่าน" required autocomplete="current-password">
                                <button type="button" class="toggle-password" id="togglePassword" tabindex="-1">
                                    <i class="bi bi-eye-fill"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3 mb-1">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="rememberMe" name="remember">
                                <label class="form-check-label" for="rememberMe">จดจำฉันไว้</label>
                            </div>
                            <a href="#" class="small text-danger text-decoration-none" style="color:#8b0000 !important;">ลืมรหัสผ่าน?</a>
                        </div>

                        <button type="submit" class="btn btn-login btn-danger w-100 mt-3 text-white" id="loginBtn">
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                            เข้าสู่ระบบ
                        </button>
                    </form>

                    <div class="gold-divider"></div>
                    <p class="login-footer-note">© <?php echo date('Y'); ?> เครือนำรุ่ง Backoffice System. All rights reserved.</p>
                </div>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/main.js"></script>
    <script>
        // Toggle password visibility (เสริม UX โดยไม่กระทบฟังก์ชันเดิม)
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function () {
                const isPassword = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                this.querySelector('i').classList.toggle('bi-eye-fill');
                this.querySelector('i').classList.toggle('bi-eye-slash-fill');
            });
        }

        // แสดง loading state บนปุ่ม (ไม่แทรกแซง submit handler เดิมใน main.js)
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        if (loginForm && loginBtn) {
            loginForm.addEventListener('submit', function () {
                const spinner = loginBtn.querySelector('.spinner-border');
                if (spinner) spinner.style.display = 'inline-block';
                loginBtn.disabled = true;
                // หาก main.js มีการ preventDefault และจัดการ AJAX เอง
                // ให้ปลด disabled กลับในไฟล์ main.js ตาม flow เดิมของระบบ
            });
        }
    </script>
</body>
</html>
