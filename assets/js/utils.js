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

function renderEmployeeAvatar(value, options = {}) {
    const fallback = options.fallback || 'assets/img/user.png';
    const src = escapeAttr(safeUploadPath(value, fallback));
    const className = escapeAttr(options.className || 'rounded-circle me-2 border');
    const size = Number.parseInt(options.size || 35, 10) || 35;
    const alt = escapeAttr(options.alt || 'Employee profile');

    return `<img src="${src}" alt="${alt}" onerror="this.onerror=null;this.src='${fallback}'" class="${className}" style="width:${size}px;height:${size}px;object-fit:cover;">`;
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

    input.value = input.dataset?.nativeDatePicker === 'true'
        ? toGregorianDateInputValue(value)
        : toThaiDateInputValue(value);
}

let thaiDatePickerPopover = null;
let thaiDatePickerInput = null;
let thaiDatePickerViewDate = null;

function thaiDatePickerMonthLabel(date) {
    return new Intl.DateTimeFormat('th-TH-u-ca-buddhist', {
        month: 'long',
        year: 'numeric',
    }).format(date);
}

function thaiDatePickerWeekdayLabels() {
    const base = new Date(2026, 0, 4);
    return Array.from({ length: 7 }, (_, index) => new Intl.DateTimeFormat('th-TH', {
        weekday: 'short',
    }).format(new Date(base.getFullYear(), base.getMonth(), base.getDate() + index)));
}

function thaiDatePickerDateFromInput(input) {
    const gregorian = toGregorianDateInputValue(input?.value || '');
    const parsed = parseLocalDate(gregorian);
    return parsed || new Date();
}

function thaiDatePickerDateKey(date) {
    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}

function ensureThaiDatePickerPopover() {
    if (thaiDatePickerPopover || typeof document === 'undefined') {
        return thaiDatePickerPopover;
    }

    thaiDatePickerPopover = document.createElement('div');
    thaiDatePickerPopover.className = 'thai-datepicker-popover';
    thaiDatePickerPopover.hidden = true;
    document.body.appendChild(thaiDatePickerPopover);

    thaiDatePickerPopover.addEventListener('mousedown', event => {
        event.preventDefault();
    });

    thaiDatePickerPopover.addEventListener('click', event => {
        event.stopPropagation();

        const actionButton = event.target.closest('[data-thai-datepicker-action]');
        if (actionButton) {
            const action = actionButton.getAttribute('data-thai-datepicker-action') || actionButton.dataset.thaiDatepickerAction;
            if (action === 'prev') {
                thaiDatePickerViewDate = new Date(thaiDatePickerViewDate.getFullYear(), thaiDatePickerViewDate.getMonth() - 1, 1);
                renderThaiDatePicker();
            } else if (action === 'next') {
                thaiDatePickerViewDate = new Date(thaiDatePickerViewDate.getFullYear(), thaiDatePickerViewDate.getMonth() + 1, 1);
                renderThaiDatePicker();
            } else if (action === 'today') {
                setThaiDatePickerValue(new Date());
            }
            return;
        }

        const dayButton = event.target.closest('[data-thai-datepicker-date]');
        if (dayButton) {
            setThaiDatePickerValue(parseLocalDate(dayButton.getAttribute('data-thai-datepicker-date') || dayButton.dataset.thaiDatepickerDate));
        }
    });

    return thaiDatePickerPopover;
}

function positionThaiDatePicker() {
    if (!thaiDatePickerPopover || !thaiDatePickerInput || thaiDatePickerPopover.hidden) return;

    const rect = thaiDatePickerInput.getBoundingClientRect();
    thaiDatePickerPopover.style.left = `${window.scrollX + rect.left}px`;
    thaiDatePickerPopover.style.top = `${window.scrollY + rect.bottom + 6}px`;
    thaiDatePickerPopover.style.minWidth = `${Math.max(rect.width, 280)}px`;
}

function renderThaiDatePicker() {
    const popover = ensureThaiDatePickerPopover();
    if (!popover || !thaiDatePickerInput || !thaiDatePickerViewDate) return;

    const year = thaiDatePickerViewDate.getFullYear();
    const month = thaiDatePickerViewDate.getMonth();
    const firstDate = new Date(year, month, 1);
    const startOffset = firstDate.getDay();
    const gridStart = new Date(year, month, 1 - startOffset);
    const selectedKey = toGregorianDateInputValue(thaiDatePickerInput.value);
    const todayKey = thaiDatePickerDateKey(new Date());
    const weekdays = thaiDatePickerWeekdayLabels();

    let html = `
        <div class="thai-datepicker-header">
            <button type="button" class="thai-datepicker-nav" data-thai-datepicker-action="prev" aria-label="Previous month">&lsaquo;</button>
            <div class="thai-datepicker-title">${escapeHtml(thaiDatePickerMonthLabel(firstDate))}</div>
            <button type="button" class="thai-datepicker-nav" data-thai-datepicker-action="next" aria-label="Next month">&rsaquo;</button>
        </div>
        <div class="thai-datepicker-weekdays">
            ${weekdays.map(label => `<span>${escapeHtml(label)}</span>`).join('')}
        </div>
        <div class="thai-datepicker-grid">
    `;

    for (let index = 0; index < 42; index++) {
        const date = new Date(gridStart.getFullYear(), gridStart.getMonth(), gridStart.getDate() + index);
        const key = thaiDatePickerDateKey(date);
        const classes = [
            'thai-datepicker-day',
            date.getMonth() !== month ? 'is-muted' : '',
            key === selectedKey ? 'is-selected' : '',
            key === todayKey ? 'is-today' : '',
        ].filter(Boolean).join(' ');

        html += `<button type="button" class="${classes}" data-thai-datepicker-date="${key}">${date.getDate()}</button>`;
    }

    html += `
        </div>
        <div class="thai-datepicker-footer">
            <button type="button" class="thai-datepicker-today" data-thai-datepicker-action="today">วันนี้</button>
        </div>
    `;

    popover.innerHTML = html;
    popover.hidden = false;
    positionThaiDatePicker();
}

function setThaiDatePickerValue(date) {
    if (!thaiDatePickerInput || !(date instanceof Date) || Number.isNaN(date.getTime())) return;

    setThaiDateInputValue(thaiDatePickerInput, thaiDatePickerDateKey(date));
    thaiDatePickerInput.dispatchEvent(new Event('input', { bubbles: true }));
    thaiDatePickerInput.dispatchEvent(new Event('change', { bubbles: true }));
    hideThaiDatePicker();
}

function showThaiDatePicker(input) {
    if (!input || input.dataset.nativeDatePicker === 'true') return;

    thaiDatePickerInput = input;
    const currentDate = thaiDatePickerDateFromInput(input);
    thaiDatePickerViewDate = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
    renderThaiDatePicker();
}

function hideThaiDatePicker() {
    if (thaiDatePickerPopover) {
        thaiDatePickerPopover.hidden = true;
    }
    thaiDatePickerInput = null;
}

function attachThaiDatePicker(input) {
    if (!input || input.dataset.thaiDatePicker === 'true') return;

    input.dataset.thaiDatePicker = 'true';
    if (typeof input.addEventListener !== 'function') return;

    input.addEventListener('focus', () => showThaiDatePicker(input));
    input.addEventListener('click', () => showThaiDatePicker(input));
    input.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            hideThaiDatePicker();
        }
    });
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
        attachThaiDatePicker(input);
    });
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        setupThaiDateInputs();
    });

    document.addEventListener('click', event => {
        if (!thaiDatePickerPopover || thaiDatePickerPopover.hidden) return;
        if (event.target === thaiDatePickerInput || thaiDatePickerPopover.contains(event.target)) return;
        hideThaiDatePicker();
    });

    window.addEventListener('resize', positionThaiDatePicker);
    window.addEventListener('scroll', positionThaiDatePicker, true);

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
