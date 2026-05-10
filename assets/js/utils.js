/*
 * ไฟล์รวมฟังก์ชันส่วนกลาง (Utilities)
 */

// ฟังก์ชันสำหรับแสดง SweetAlert แบบมาตรฐาน
function showAlert(icon, title, text) {
    Swal.fire({
        icon: icon,
        title: title,
        text: text,
        confirmButtonColor: '#0d6efd'
    });
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function escapeAttr(value) {
    return escapeHtml(value).replace(/`/g, '&#096;');
}

function safeUploadPath(value, fallback = '') {
    const path = String(value ?? '');
    if (/^assets\/uploads\/[A-Za-z0-9_./-]+$/.test(path)) {
        return path;
    }
    return fallback;
}
