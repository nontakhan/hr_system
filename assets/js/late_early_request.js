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

        [...typeInputs, dateInput, timeInput].forEach(input => {
            if (!input) return;
            input.addEventListener('change', scheduleTimeRequestCalculation);
            input.addEventListener('input', scheduleTimeRequestCalculation);
        });

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
        latestCalculation = null;

        const requestType = getSelectedTimeRequestType();
        if (!requestType || !dateInput.value || !timeInput.value) {
            box?.classList.add('d-none');
            return;
        }

        const params = new URLSearchParams({
            action: 'calculate',
            time_request_type: requestType,
            work_date: dateInput.value,
            request_time: timeInput.value,
        });

        try {
            const response = await fetch(`api/late_early_request_api.php?${params.toString()}`);
            const result = await response.json();
            box?.classList.remove('d-none', 'alert-danger', 'alert-success');
            box?.classList.add(result.status === 'success' ? 'alert-success' : 'alert-danger');

            if (result.status === 'success') {
                latestCalculation = result.data;
                text.textContent = `ขอเวลา ${result.data.request_minutes} นาที (กะ ${formatTime(result.data.shift_start_time)} - ${formatTime(result.data.shift_end_time)})`;
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
        return form?.querySelector('input[name="time_request_type"]:checked')?.value || '';
    }

    async function submitLateEarlyRequest(event) {
        event.preventDefault();
        await calculateTimeRequest();
        if (!latestCalculation || !latestCalculation.valid) {
            Swal.fire('ตรวจสอบเวลา', 'กรุณาระบุเวลาที่อยู่ภายในช่วง 1-60 นาทีจากกะของวันนั้น', 'warning');
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

async function loadTimeRequestHistory() {
    const tbody = document.getElementById('lateEarlyHistoryBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center">กำลังโหลดข้อมูล...</td></tr>';

    try {
        const response = await fetch('api/late_early_request_api.php?action=history');
        const result = await response.json();
        if (result.status !== 'success') {
            tbody.innerHTML = `<tr><td colspan="5" class="text-danger text-center">${escapeHtml(result.message || 'โหลดข้อมูลไม่สำเร็จ')}</td></tr>`;
            return;
        }
        if (!result.data.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center">ยังไม่มีคำขอเวลา</td></tr>';
            return;
        }

        tbody.innerHTML = result.data.map(item => `
            <tr>
                <td>${formatThaiDate(item.created_at)}</td>
                <td>${escapeHtml(formatTimeRequestType(item.time_request_type))}</td>
                <td>${formatThaiDate(item.start_date)}</td>
                <td>${escapeHtml(formatTimeRequestDuration(item))}</td>
                <td>${formatRequestStatusBadge(item.status)}</td>
            </tr>
        `).join('');
    } catch (error) {
        console.error(error);
        tbody.innerHTML = '<tr><td colspan="5" class="text-danger text-center">โหลดข้อมูลไม่สำเร็จ</td></tr>';
    }
}

function formatTimeRequestType(type) {
    return type === 'early_departure' ? 'ขอออกก่อนเวลา' : 'ขอมาสาย';
}

function formatTimeRequestDuration(item) {
    const minutes = Math.max(1, Math.min(60, Number.parseInt(item.request_minutes || 0, 10) || 60));
    return `${formatTimeRequestType(item.time_request_type)} ${minutes} นาที`;
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
