<?php
require_once 'includes/auth_check.php';

$page_title = 'เปลี่ยนรหัสผ่าน';
require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h1 class="h5 mb-1"><i class="fas fa-key me-2 text-primary"></i>เปลี่ยนรหัสผ่าน</h1>
                <p class="text-muted small mb-0">เพื่อความปลอดภัย กรุณากรอกรหัสผ่านปัจจุบันก่อนตั้งรหัสผ่านใหม่</p>
            </div>
            <form id="changePasswordForm" autocomplete="off">
                <input type="hidden" name="action" value="change_password">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">รหัสผ่านปัจจุบัน <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="current_password" autocomplete="current-password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">รหัสผ่านใหม่ <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="new_password" autocomplete="new-password" minlength="8" required>
                        <small class="text-muted">อย่างน้อย 8 ตัวอักษร</small>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">ยืนยันรหัสผ่านใหม่ <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="confirm_password" autocomplete="new-password" minlength="8" required>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="my_profile.php" class="btn btn-outline-secondary">
                        <i class="fas fa-chevron-left me-1"></i> กลับโปรไฟล์
                    </a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save me-1"></i> บันทึกรหัสผ่านใหม่
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('changePasswordForm');
    if (!form) return;

    form.addEventListener('submit', async function(event) {
        event.preventDefault();

        const newPassword = form.elements.new_password.value;
        const confirmPassword = form.elements.confirm_password.value;
        if (newPassword !== confirmPassword) {
            Swal.fire({ icon: 'warning', title: 'ตรวจสอบรหัสผ่าน', text: 'รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน' });
            return;
        }

        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) submitButton.disabled = true;

        try {
            const response = await fetch('api/account_api.php?action=change_password', {
                method: 'POST',
                body: new FormData(form)
            });
            const result = await response.json();

            if (result.status === 'success') {
                await Swal.fire({ icon: 'success', title: 'เปลี่ยนรหัสผ่านแล้ว', text: result.message, timer: 1600, showConfirmButton: false });
                form.reset();
                return;
            }

            Swal.fire({ icon: 'error', title: 'เปลี่ยนรหัสผ่านไม่สำเร็จ', text: result.message || 'ไม่สามารถเปลี่ยนรหัสผ่านได้' });
        } catch (error) {
            console.error('Change Password Error:', error);
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถเชื่อมต่อ API ได้' });
        } finally {
            if (submitButton) submitButton.disabled = false;
        }
    });
});
</script>
