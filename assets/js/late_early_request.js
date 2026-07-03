let lateEarlyHistoryDataTable = null;

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('lateEarlyRequestForm');
    const historyBody = document.getElementById('lateEarlyHistoryBody');
    const refreshBtn = document.getElementById('refreshTimeRequestsBtn');
    let calculateTimer = null;
    let latestCalculation = null;

    refreshBtn?.addEventListener('click', loadTimeRequestHistory);
    if (historyBody) loadTimeRequestHistory();

    if (form) {
        const typeInputs = Array.from(document.querySelectorAll('input[name="time_request_type"]'));
        const dateInput = document.getElementById('timeRequestDate');
        const timeInput = document.getElementById('timeRequestTime');
        const overtimeStartInput = document.getElementById('overtimeStartTime');
        const overtimeEndInput = document.getElementById('overtimeEndTime');

        [...typeInputs, dateInput, timeInput, overtimeStartInput, overtimeEndInput].forEach(input => {
            if (!input) return;
            input.addEventListener('change', scheduleTimeRequestCalculation);
            input.addEventListener('input', scheduleTimeRequestCalculation);
        });
        dateInput?.addEventListener('change', loadOvertimeDateContext);
        typeInputs.forEach(input => input.addEventListener('change', syncTimeRequestFields));
        syncTimeRequestFields();
        loadOvertimeDateContext();

        form.addEventListener('submit', submitLateEarlyRequest);
    }

    function scheduleTimeRequestCalculation() {
        window.clearTimeout(calculateTimer);
        calculateTimer = window.setTimeout(calculateTimeRequest, 200);
    }

    async function calculateTimeRequest() {
        const box = document.getElementById('timeRequestCalculation');
        const text = document.getElementById('timeRequestCalculationText');
        const dateInput = document.getElementById('timeRequestDate');
        const timeInput = document.getElementById('timeRequestTime');
        const overtimeStartInput = document.getElementById('overtimeStartTime');
        const overtimeEndInput = document.getElementById('overtimeEndTime');
        latestCalculation = null;

        const requestType = getSelectedTimeRequestType();
        const missingOvertimeTime = isOvertimeRequest() && (!overtimeStartInput.value || !overtimeEndInput.value);
        if (!requestType || !dateInput.value || (!isOvertimeRequest() && !timeInput.value) || missingOvertimeTime) {
            if (isOvertimeRequest()) showLocalOvertimeDuration();
            box?.classList.add('d-none');
            return;
        }
        if (isOvertimeRequest()) showLocalOvertimeDuration();

        const params = new URLSearchParams({
            action: 'calculate',
            time_request_type: requestType,
            work_date: dateInput.value,
        });
        if (isOvertimeRequest()) {
            params.set('overtime_start_time', overtimeStartInput.value);
            params.set('overtime_end_time', overtimeEndInput.value);
        } else {
            params.set('request_time', timeInput.value);
        }

        try {
            const response = await fetch(`api/late_early_request_api.php?${params.toString()}`);
            const result = await response.json();
            box?.classList.remove('d-none', 'alert-danger', 'alert-success');
            box?.classList.add(result.status === 'success' ? 'alert-success' : 'alert-danger');

            if (result.status === 'success') {
                latestCalculation = result.data;
                if (isOvertimeRequest()) {
                    text.textContent = `ขอ OT ${formatTime(result.data.request_start_time)}-${formatTime(result.data.request_end_time)} รวม ${formatHourMinuteDuration(result.data.request_minutes)}`;
                } else {
                    text.textContent = `ขอเวลา ${result.data.request_minutes} นาที (กะ ${formatTime(result.data.shift_start_time)} - ${formatTime(result.data.shift_end_time)})`;
                }
            } else {
                text.textContent = result.message || 'ไม่สามารถคำนวณเวลาได้';
            }
        } catch (error) {
            console.error(error);
            box?.classList.remove('d-none', 'alert-success');
            box?.classList.add('alert-danger');
            text.textContent = 'ไม่สามารถคำนวณเวลาได้';
        }
    }

    function getSelectedTimeRequestType() {
        if (window.timeRequestFixedType) {
            return window.timeRequestFixedType;
        }
        return form?.querySelector('input[name="time_request_type"]:checked')?.value
            || form?.querySelector('input[name="time_request_type"]')?.value
            || '';
    }

    function isOvertimeRequest() {
        return getSelectedTimeRequestType() === 'overtime_after_work';
    }

    function syncTimeRequestFields() {
        const timeField = document.getElementById('timeRequestTime')?.closest('.col-md-6');
        const timeInput = document.getElementById('timeRequestTime');
        const overtimeStartField = document.getElementById('overtimeStartField');
        const overtimeEndField = document.getElementById('overtimeEndField');
        const overtimeStartInput = document.getElementById('overtimeStartTime');
        const overtimeEndInput = document.getElementById('overtimeEndTime');
        const ot = isOvertimeRequest();
        timeField?.classList.toggle('d-none', ot);
        overtimeStartField?.classList.toggle('d-none', !ot);
        overtimeEndField?.classList.toggle('d-none', !ot);
        if (timeInput) timeInput.required = !ot;
        if (overtimeStartInput) overtimeStartInput.required = ot;
        if (overtimeEndInput) overtimeEndInput.required = ot;
    }

    async function loadOvertimeDateContext() {
        const target = document.getElementById('timeRequestDateContext');
        const dateInput = document.getElementById('timeRequestDate');
        if (!target || !dateInput || !isOvertimeRequest()) return;
        if (!dateInput.value) {
            target.classList.add('d-none');
            target.innerHTML = '';
            return;
        }

        target.classList.remove('d-none', 'alert-danger');
        target.classList.add('alert-light');
        target.innerHTML = '<span class="text-muted small">กำลังโหลดข้อมูลกะ...</span>';

        try {
            const params = new URLSearchParams({ action: 'work_date_context', work_date: dateInput.value });
            const response = await fetch(`api/late_early_request_api.php?${params.toString()}`);
            const result = await response.json();
            if (result.status !== 'success') {
                target.classList.remove('alert-light');
                target.classList.add('alert-danger');
                target.textContent = result.message || 'ไม่พบข้อมูลกะของวันที่เลือก';
                return;
            }
            target.classList.remove('alert-danger');
            target.classList.add('alert-light');
            target.innerHTML = renderWorkDateContext(result.data || {});
        } catch (error) {
            console.error(error);
            target.classList.remove('alert-light');
            target.classList.add('alert-danger');
            target.textContent = 'ไม่สามารถโหลดข้อมูลกะของวันที่เลือกได้';
        }
    }

    function renderWorkDateContext(context) {
        const dayLabel = escapeHtml(context.day_type_label || '-');
        const holiday = context.holiday_name ? ` <span class="text-muted">(${escapeHtml(context.holiday_name)})</span>` : '';
        const shift = context.shift_start_time && context.shift_end_time
            ? `${formatTime(context.shift_start_time)}-${formatTime(context.shift_end_time)}`
            : '-';
        return `<div class="small text-muted">ข้อมูลวันที่เลือก</div><div class="fw-semibold">${dayLabel}${holiday} | กะ ${shift}</div>`;
    }

    function showLocalOvertimeDuration() {
        const box = document.getElementById('timeRequestCalculation');
        const text = document.getElementById('timeRequestCalculationText');
        const startInput = document.getElementById('overtimeStartTime');
        const endInput = document.getElementById('overtimeEndTime');
        if (!box || !text || !startInput?.value || !endInput?.value) return;

        const duration = formatLocalOvertimeDuration(startInput.value, endInput.value);
        box.classList.remove('d-none', 'alert-danger', 'alert-success');
        box.classList.add(duration.valid ? 'alert-success' : 'alert-danger');
        text.textContent = duration.valid ? `รวม ${duration.label}` : duration.message;
    }

    async function submitLateEarlyRequest(event) {
        event.preventDefault();
        syncTimeRequestFields();
        await calculateTimeRequest();
        if (!latestCalculation || !latestCalculation.valid) {
            const message = isOvertimeRequest()
                ? 'กรุณาระบุเวลาเริ่มและเวลาสิ้นสุด OT ให้ถูกต้อง'
                : 'กรุณาระบุเวลาที่อยู่ภายในช่วง 1-60 นาทีจากกะของวันนั้น';
            Swal.fire('ตรวจสอบเวลา', message, 'warning');
            return;
        }

        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        const formData = new FormData(form);

        try {
            const response = await fetch('api/late_early_request_api.php', {
                method: 'POST',
                body: formData,
            });
            const result = await response.json();
            if (result.status === 'success') {
                await Swal.fire('ส่งคำขอแล้ว', result.message, 'success');
                form.reset();
                syncTimeRequestFields();
                latestCalculation = null;
                document.getElementById('timeRequestCalculation')?.classList.add('d-none');
                loadTimeRequestHistory();
            } else {
                Swal.fire('ไม่สำเร็จ', result.message || 'ไม่สามารถส่งคำขอได้', 'error');
            }
        } catch (error) {
            console.error(error);
            Swal.fire('ผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
        } finally {
            submitBtn.disabled = false;
        }
    }
});

function formatLocalOvertimeDuration(startTime, endTime) {
    const start = parseTimeToMinutes(startTime);
    const end = parseTimeToMinutes(endTime);
    if (start === null || end === null) {
        return { valid: false, message: 'รูปแบบเวลาไม่ถูกต้อง', minutes: 0, label: '' };
    }
    const minutes = end - start;
    if (minutes <= 0) {
        return { valid: false, message: 'เวลาสิ้นสุดต้องมากกว่าเวลาเริ่ม', minutes: 0, label: '' };
    }
    return { valid: true, message: '', minutes, label: formatHourMinuteDuration(minutes) };
}

function parseTimeToMinutes(value) {
    const match = String(value || '').match(/^(\d{2}):(\d{2})/);
    if (!match) return null;
    const hours = Number.parseInt(match[1], 10);
    const minutes = Number.parseInt(match[2], 10);
    if (hours < 0 || hours > 23 || minutes < 0 || minutes > 59) return null;
    return hours * 60 + minutes;
}

async function loadTimeRequestHistory() {
    const tbody = document.getElementById('lateEarlyHistoryBody');
    if (!tbody) return;
    resetLateEarlyHistoryDataTable();
    tbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center">กำลังโหลดข้อมูล...</td></tr>';

    try {
        const params = new URLSearchParams({ action: 'history' });
        params.set('time_request_type', window.timeRequestHistoryType || 'late_early');
        const response = await fetch(`api/late_early_request_api.php?${params.toString()}`);
        const result = await response.json();
        if (result.status !== 'success') {
            tbody.innerHTML = `<tr><td colspan="5" class="text-danger text-center">${escapeHtml(result.message || 'โหลดข้อมูลไม่สำเร็จ')}</td></tr>`;
            return;
        }
        if (!result.data.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center">ยังไม่มีคำขอเวลา</td></tr>';
            return;
        }

        tbody.innerHTML = result.data.map(item => {
            const proxyHtml = renderProxyCreatorLine(item);
            return `
            <tr>
                <td>${formatThaiDate(item.created_at)}</td>
                <td>${escapeHtml(formatTimeRequestType(item.time_request_type))}${proxyHtml}</td>
                <td>${formatThaiDate(item.start_date)}</td>
                <td>${escapeHtml(formatTimeRequestDuration(item))}</td>
                <td>${formatRequestStatusBadge(item.status)}</td>
            </tr>
        `;
        }).join('');
        initLateEarlyHistoryDataTable();
    } catch (error) {
        console.error(error);
        tbody.innerHTML = '<tr><td colspan="5" class="text-danger text-center">โหลดข้อมูลไม่สำเร็จ</td></tr>';
    }
}

function renderProxyCreatorLine(item) {
    if (!item || item.created_via !== 'admin_proxy') return '';
    const name = item.proxy_creator_name || item.created_by_role || '';
    return `<div class="small text-muted mt-1">สร้างโดย HR/Admin${name ? `: ${escapeHtml(name)}` : ''}</div>`;
}

function resetLateEarlyHistoryDataTable() {
    const selector = getTimeRequestHistoryTableSelector();
    if (!selector) {
        return;
    }
    if (lateEarlyHistoryDataTable) {
        lateEarlyHistoryDataTable.destroy();
        lateEarlyHistoryDataTable = null;
    } else if (window.jQuery && $.fn.DataTable && $.fn.DataTable.isDataTable(selector)) {
        $(selector).DataTable().destroy();
    }
}

function initLateEarlyHistoryDataTable() {
    const selector = getTimeRequestHistoryTableSelector();
    if (!window.jQuery || !$.fn.DataTable || !selector) {
        return;
    }
    lateEarlyHistoryDataTable = $(selector).DataTable({
        language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json' },
        pageLength: 10,
        order: [[0, 'desc']],
        columnDefs: [
            { orderable: false, targets: [4] },
        ],
    });
}

function getTimeRequestHistoryTableSelector() {
    if (document.getElementById('lateEarlyHistoryTable')) {
        return '#lateEarlyHistoryTable';
    }
    if (document.getElementById('overtimeHistoryTable')) {
        return '#overtimeHistoryTable';
    }
    return null;
}

function formatTimeRequestType(type) {
    if (type === 'overtime_after_work') {
        return 'ขอ OT หลังเลิกงาน';
    }
    return type === 'early_departure' ? 'ขอออกก่อนเวลา' : 'ขอมาสาย';
}

function formatTimeRequestDuration(item) {
    if (item.time_request_type === 'overtime_after_work') {
        const range = item.request_start_time && item.request_end_time
            ? `${formatTime(item.request_start_time)}-${formatTime(item.request_end_time)} `
            : '';
        return `OT หลังเลิกงาน ${range}${formatHourMinuteDuration(item.request_minutes)}`;
    }
    const minutes = Math.max(1, Math.min(60, Number.parseInt(item.request_minutes || 0, 10) || 60));
    return `${formatTimeRequestType(item.time_request_type)} ${minutes} นาที`;
}

function formatHourMinuteDuration(minutes) {
    const safeMinutes = Math.max(0, Number.parseInt(minutes || 0, 10) || 0);
    const hours = Math.floor(safeMinutes / 60);
    const remaining = safeMinutes % 60;
    const parts = [];
    if (hours > 0) parts.push(`${hours} ชม.`);
    if (remaining > 0 || !parts.length) parts.push(`${remaining} นาที`);
    return parts.join(' ');
}

function formatRequestStatusBadge(status) {
    const labels = {
        pending: ['รอหัวหน้าอนุมัติ', 'bg-warning text-dark'],
        pending_manager: ['รอหัวหน้าอนุมัติ', 'bg-warning text-dark'],
        pending_hr: ['รอ HR อนุมัติ', 'bg-info text-dark'],
        approved: ['อนุมัติแล้ว', 'bg-success'],
        rejected: ['ไม่อนุมัติ', 'bg-danger'],
        cancelled: ['ยกเลิก', 'bg-secondary'],
    };
    const [label, cls] = labels[status] || [status || '-', 'bg-secondary'];
    return `<span class="badge ${cls}">${escapeHtml(label)}</span>`;
}

function formatTime(value) {
    return String(value || '').slice(0, 5) || '-';
}
