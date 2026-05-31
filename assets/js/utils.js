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

function parseLocalDate(value) {
    if (!value) return null;

    const text = String(value).trim();
    const match = text.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (match) {
        return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    }

    const parsed = new Date(text);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

function formatThaiDate(value, options = {}) {
    const date = parseLocalDate(value);
    if (!date) return options.fallback || '-';

    return new Intl.DateTimeFormat('th-TH-u-ca-buddhist', {
        day: options.day || '2-digit',
        month: options.month || '2-digit',
        year: 'numeric',
    }).format(date);
}

function formatThaiMonth(value) {
    if (!value) return '-';

    const [year, month] = String(value).split('-').map(Number);
    if (!year || !month) return '-';

    return new Intl.DateTimeFormat('th-TH-u-ca-buddhist', {
        year: 'numeric',
        month: 'long',
    }).format(new Date(year, month - 1, 1));
}

function normalizeGregorianYearInput(value) {
    const year = Number.parseInt(value, 10);
    if (!year) return '';

    return String(year > 2400 ? year - 543 : year);
}

function toThaiDateInputValue(value) {
    const date = parseLocalDate(value);
    if (!date) return '';

    return [
        String(date.getDate()).padStart(2, '0'),
        String(date.getMonth() + 1).padStart(2, '0'),
        date.getFullYear() + 543,
    ].join('/');
}

function toGregorianDateInputValue(value) {
    const text = String(value || '').trim();
    if (!text) return '';

    if (/^\d{4}-\d{2}-\d{2}$/.test(text)) return text;

    const match = text.match(/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/);
    if (!match) return text;

    const day = Number(match[1]);
    const month = Number(match[2]);
    let year = Number(match[3]);
    if (year > 2400) year -= 543;

    return `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function setThaiDateInputValue(input, value) {
    if (!input) return;

    input.value = toThaiDateInputValue(value);
}

function setupThaiDateInputs(root = document) {
    root.querySelectorAll('input[type="date"]').forEach(input => {
        if (input.dataset.nativeDatePicker === 'true') {
            return;
        }

        input.dataset.originalType = 'date';
        input.type = 'text';
        input.inputMode = 'numeric';
        input.placeholder = 'dd/mm/พ.ศ.';
        setThaiDateInputValue(input, input.value);
    });
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        setupThaiDateInputs();
    });

    document.addEventListener('submit', (event) => {
        const inputs = event.target.querySelectorAll('input[data-original-type="date"]');
        inputs.forEach(input => {
            input.value = toGregorianDateInputValue(input.value);
        });

        setTimeout(() => {
            inputs.forEach(input => setThaiDateInputValue(input, input.value));
        }, 0);
    }, true);
}
