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
    const raw = String(value ?? '').trim();
    if (!raw) return fallback;

    // Normalize Windows path separators to URL-style path separators.
    const path = raw.replace(/\\/g, '/');

    // Allow local app assets and uploaded images.
    if (/^assets\/(uploads|img)\/[A-Za-z0-9_./-]+$/.test(path)) return path;

    // Backward-compatibility for older DB values like "profile_xxx.jpg" or "1764.jpg"
    if (/^[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp|gif)$/i.test(path)) {
        return `assets/uploads/profile_images/${path}`;
    }

    return fallback;
}
