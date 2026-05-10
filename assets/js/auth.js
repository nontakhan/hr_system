/*
 * Logic สำหรับระบบ Authentication (Login)
 */

document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLoginForm);
    }
});

async function handleLoginForm(e) {
    e.preventDefault();

    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;

    Swal.fire({
        title: 'กำลังตรวจสอบ...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const response = await fetch('api/login_process.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });

        const data = await response.json();

        if (data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'เข้าสู่ระบบสำเร็จ!',
                text: 'กำลังพาคุณไปยัง Dashboard...',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                window.location.href = 'dashboard.php';
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'ผิดพลาด',
                text: data.message || 'Username หรือ Password ไม่ถูกต้อง'
            });
        }
    } catch (error) {
        console.error('Login Error:', error);
        Swal.fire({ icon: 'error', title: 'Error', text: 'เชื่อมต่อ Server ไม่ได้' });
    }
}