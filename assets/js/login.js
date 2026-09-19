document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('loginForm')?.addEventListener('submit', handleLoginForm);
});

async function handleLoginForm(e) {
    e.preventDefault(); // หยุดการ Submit ฟอร์มแบบปกติ (ไม่ให้หน้า Reload)

    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;

    // (แสดง Popup "กำลังโหลด")
    Swal.fire({
        title: 'กำลังตรวจสอบ...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        const response = await fetch('api/login_process.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ username, password })
        });

        const data = await response.json();

        if (data.status === 'success') {
            // (Login สำเร็จ)
            Swal.fire({
                icon: 'success',
                title: 'เข้าสู่ระบบสำเร็จ!',
                text: 'กำลังพาคุณไปยัง Dashboard...',
                timer: 1500, // (หน่วงเวลา 1.5 วินาที)
                showConfirmButton: false
            }).then(() => {
                // (Redirect ไปหน้า Dashboard)
                window.location.href = 'dashboard.php';
            });

        } else {
            // (Login ไม่สำเร็จ)
            Swal.fire({
                icon: 'error',
                title: 'ผิดพลาด',
                text: data.message || 'Username หรือ Password ไม่ถูกต้อง'
            });
        }

    } catch (error) {
        // (Error การเชื่อมต่อ)
        Swal.fire({
            icon: 'error',
            title: 'เชื่อมต่อล้มเหลว',
            text: 'ไม่สามารถเชื่อมต่อ API ได้ (login_process)'
        });
        console.error('Error:', error);
    }
}
