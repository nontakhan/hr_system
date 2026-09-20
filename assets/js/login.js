let loginInProgress = false;

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('loginForm')?.addEventListener('submit', handleLoginForm);
});

async function handleLoginForm(e) {
    e.preventDefault();
    if (loginInProgress) return;
    loginInProgress = true;
    const button = document.getElementById('loginBtn');
    const spinner = button?.querySelector('.spinner-border');
    if (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); }
    if (spinner) spinner.style.display = 'inline-block';
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 15000);
    let navigating = false;
    try {
        Swal.fire({ title: 'กำลังตรวจสอบ...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        const response = await fetch('api/login_process.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                username: document.getElementById('username').value,
                password: document.getElementById('password').value
            }),
            signal: controller.signal
        });
        if (!response.ok) throw new Error('Login unavailable');
        const data = await response.json();
        if (data.status === 'success') {
            navigating = true;
            await Swal.fire({ icon: 'success', title: 'เข้าสู่ระบบสำเร็จ!', text: 'กำลังพาคุณไปยังหน้าหลัก...', timer: 1500, showConfirmButton: false });
            window.location.href = 'dashboard.php';
        } else {
            Swal.fire({ icon: 'error', title: 'เข้าสู่ระบบไม่สำเร็จ', text: data.message || 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง กรุณาตรวจสอบแล้วลองใหม่' });
        }
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'เชื่อมต่อไม่สำเร็จ', text: 'กรุณาตรวจสอบการเชื่อมต่อ แล้วกดเข้าสู่ระบบอีกครั้ง' });
    } finally {
        clearTimeout(timeout);
        if (!navigating) {
            loginInProgress = false;
            if (button) { button.disabled = false; button.removeAttribute('aria-busy'); }
            if (spinner) spinner.style.display = 'none';
        }
    }
}
