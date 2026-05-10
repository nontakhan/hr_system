<?php
/*
 * (เครื่องมือพิเศษ) สำหรับสร้าง Hashed Password
 * * วิธีใช้:
 * 1. (ถ้าต้องการ) เปลี่ยนรหัสผ่านใน $plain_password เป็นรหัสที่คุณต้องการ
 * 2. รันไฟล์นี้บนเบราว์เซอร์ (เช่น http://localhost/hr_system/generate_password.php)
 * 3. คัดลอก Hash ที่แสดงบนหน้าจอ
 * 4. นำไปวางในฐานข้อมูล (phpMyAdmin -> ตาราง users -> คอลัมน์ password)
 * 5. (สำคัญ!) เมื่อใช้งานเสร็จแล้ว ให้ลบไฟล์นี้ทิ้งทันที!
 */

// --- 1. ตั้งค่ารหัสผ่านที่คุณต้องการใช้ ---
$plain_password = 'admin1234';
// -------------------------------------


// 2. สร้าง Hash (ใช้ BCRYPT ซึ่งเป็น Default ที่ปลอดภัย)
$hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Hash Generator</title>
    <!-- ใช้ Bootstrap เพื่อความสวยงาม -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Kanit', sans-serif; 
            background-color: #f4f4f4;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .hash-card {
            width: 100%;
            max-width: 800px;
            padding: 2rem;
            border-radius: 0.75rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .hash-output {
            font-family: 'Courier New', Courier, monospace;
            background-color: #e9ecef;
            padding: 1rem;
            border-radius: 0.5rem;
            word-wrap: break-word; /* ให้ตัดคำถ้ามันยาวไป */
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card hash-card">
            <div class="card-body">
                <h3 class="text-center mb-4">Password Hash Generator</h3>
                
                <p class="text-secondary">รหัสผ่าน (Plain Text) ที่คุณตั้งค่า:</p>
                <div class="alert alert-info">
                    <strong><?php echo htmlspecialchars($plain_password); ?></strong>
                </div>

                <p class="text-secondary mt-4">รหัสผ่านที่เข้ารหัส (Hashed Password) สำหรับฐานข้อมูล:</p>
                <div classs="hash-output" id="hashOutput">
                    <strong><?php echo htmlspecialchars($hashed_password); ?></strong>
                </div>
                
                <button class="btn btn-primary mt-3" onclick="copyToClipboard()">
                    คัดลอก Hash
                </button>

                <hr>
                <p class="text-danger">
                    <strong>คำเตือน:</strong> เมื่อคัดลอก Hash นี้ไปใส่ในฐานข้อมูล (ตาราง users) เรียบร้อยแล้ว <strong>กรุณาลบไฟล์ `generate_password.php` นี้ทิ้งทันที</strong> เพื่อความปลอดภัย
                </p>
            </div>
        </div>
    </div>

    <script>
        function copyToClipboard() {
            // คัดลอก Hashed Password
            const hashText = document.getElementById('hashOutput').innerText;
            navigator.clipboard.writeText(hashText).then(function() {
                alert('คัดลอก Hashed Password เรียบร้อยแล้ว!');
            }, function(err) {
                alert('ไม่สามารถคัดลอกได้: ', err);
            });
        }
    </script>
</body>
</html>