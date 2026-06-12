/*
 * Logic สำหรับหน้ายื่นใบลา (leave_request.php)
 */

document.addEventListener('DOMContentLoaded', () => {
    const leaveRequestForm = document.getElementById('leaveRequestForm');

if (leaveRequestForm) {
        loadLeaveOptions();
        loadLeaveUsageSummary();
        setupDateCalculation();
        leaveRequestForm.addEventListener('submit', handleSubmitLeave);
    }
});

let leaveTypesData = [];
let leaveUsageSummary = null;
let latestLeaveSummary = null;
let leaveCalculationTimer = null;

async function loadLeaveUsageSummary() {
    try {
        const response = await fetch('api/leave_request_api.php?action=get_leave_usage');
        const res = await response.json();
        if (res.status !== 'success') {
            throw new Error(res.message || 'โหลดสรุปสิทธิ์ลาไม่สำเร็จ');
        }

        leaveUsageSummary = res.data;
        renderLeaveUsageSummary(leaveUsageSummary);
        updateLeaveTypeCondition();
    } catch (error) {
        const grid = document.getElementById('leaveUsageSummaryGrid');
        if (grid) {
            grid.innerHTML = `<div class="text-danger small">${escapeHtml(error.message)}</div>`;
        }
    }
}

async function loadLeaveOptions() {
    const select = document.getElementById('leaveTypeSelect');
    try {
        const response = await fetch('api/leave_request_api.php?action=get_leave_types');
        const res = await response.json();

        if (res.status === 'success') {
            leaveTypesData = res.data;
            renderLeaveTypeCards(res.data);
        }
    } catch (error) {
        console.error(error);
    }

    select.addEventListener('change', () => updateLeaveTypeCondition());
}

function renderLeaveTypeCards(types) {
    const grid = document.getElementById('leaveTypeIconGrid');
    if (!grid) return;

    grid.innerHTML = types.map(type => {
        const presentation = getLeaveTypePresentation(type.type_name || '');
        return `
            <button type="button" class="leave-type-card" data-leave-type-id="${escapeAttr(type.id)}" role="radio" aria-checked="false">
                <span class="leave-type-icon text-${presentation.color}">
                    <i class="fas ${presentation.icon}"></i>
                </span>
                <span class="leave-type-name">${escapeHtml(type.type_name)}</span>
            </button>
        `;
    }).join('');

    grid.querySelectorAll('.leave-type-card').forEach(card => {
        card.addEventListener('click', () => selectLeaveType(card.dataset.leaveTypeId));
    });
}

function getLeaveUsageItem(typeId) {
    if (!leaveUsageSummary) return null;
    return leaveUsageSummary.overall || null;
}

function getSelectedLeaveType() {
    const selectedId = document.getElementById('leaveTypeSelect')?.value;
    return leaveTypesData.find(t => t.id == selectedId) || null;
}

function renderLeaveUsageSummary(summary) {
    const grid = document.getElementById('leaveUsageSummaryGrid');
    const fiscalText = document.getElementById('leaveUsageFiscalYearText');
    if (!grid) return;

    if (fiscalText && summary && summary.fiscal_year) {
        fiscalText.textContent = `ช่วงปีงบประมาณ ${formatThaiDate(summary.fiscal_year.start_date)} ถึง ${formatThaiDate(summary.fiscal_year.end_date)}`;
    }

    if (!summary || !summary.overall) {
        grid.innerHTML = '<div class="text-muted small">ยังไม่มีข้อมูลสรุปการลา</div>';
        return;
    }

    grid.innerHTML = renderOverallLeaveUsageCard(summary.overall);
}

function renderOverallLeaveUsageCard(item) {
    const statusClass = `leave-usage-card-${item.status || 'normal'}`;
    const percent = Number.parseFloat(item.request_usage_percent || 0);
    const progressWidth = Math.min(Math.max(percent, 0), 100);
    const requestLimitText = Number.parseInt(item.request_limit || 0, 10) > 0
        ? `${item.request_limit} ครั้ง`
        : 'ไม่จำกัด';
    const remainingRequests = item.remaining_requests === null
        ? 'ไม่จำกัด'
        : `${item.remaining_requests} ครั้ง`;
    const pendingText = Number.parseFloat(item.pending_days || 0) > 0
        ? `<div class="leave-usage-pending">รออนุมัติ ${item.pending_requests || 0} ครั้ง รวม ${formatLeaveDayNumber(item.pending_days)} วัน</div>`
        : '';

    return `
        <div class="leave-usage-card ${statusClass}">
            <div class="d-flex justify-content-between gap-2">
                <strong>รวมการลาทั้งปีงบประมาณ</strong>
                <span>${item.approved_requests || 0} / ${requestLimitText}</span>
            </div>
            <div class="leave-usage-progress" aria-hidden="true">
                <span style="width: ${progressWidth}%"></span>
            </div>
            <div class="small mt-2">
                ใช้แล้ว ${item.approved_requests || 0} ครั้ง, คงเหลือ ${remainingRequests}
            </div>
            <div class="small mt-1">จำนวนวันรวมที่อนุมัติแล้ว: ${formatLeaveDayNumber(item.approved_days)} วัน</div>
            ${pendingText}
            ${renderLeaveUsageEntries(item.entries || [])}
        </div>
    `;
}

function renderLeaveUsageEntries(entries) {
    if (!entries.length) {
        return '<div class="leave-usage-entry-list text-muted">ยังไม่มีรายการลาในปีงบประมาณนี้</div>';
    }

    return `
        <div class="leave-usage-entry-list">
            ${entries.map(entry => `
                <div class="leave-usage-entry">
                    <span>${formatLeaveDateRange(entry.start_date, entry.end_date, 'full', 'full')} ${entry.type_name ? `- ${escapeHtml(entry.type_name)}` : ''}</span>
                    <span>${escapeHtml(entry.duration_label || `${formatLeaveDayNumber(entry.days)} วัน`)} (${isPendingLeaveStatus(entry.status) ? 'รออนุมัติ' : 'อนุมัติแล้ว'})</span>
                </div>
            `).join('')}
        </div>
    `;
}

function isPendingLeaveStatus(status) {
    return status === 'pending' || status === 'pending_manager' || status === 'pending_hr';
}

function selectLeaveType(selectedId) {
    const input = document.getElementById('leaveTypeSelect');
    const grid = document.getElementById('leaveTypeIconGrid');
    if (!input || !grid) return;

    input.value = selectedId || '';
    grid.querySelectorAll('.leave-type-card').forEach(card => {
        const isSelected = card.dataset.leaveTypeId === String(selectedId);
        card.classList.toggle('is-selected', isSelected);
        card.setAttribute('aria-checked', isSelected ? 'true' : 'false');
    });
    updateLeaveTypeCondition();
    updateSelectedLeaveUsageProjection();
}

function updateLeaveTypeCondition() {
    const selectedId = document.getElementById('leaveTypeSelect').value;
    const conditionDiv = document.getElementById('leaveTypeCondition');
    const conditionText = document.getElementById('conditionText');
    const attachmentSection = document.getElementById('attachmentSection');
    const attachmentInput = document.getElementById('attachmentInput');
    const type = getSelectedLeaveType();

    conditionDiv.classList.remove('text-danger', 'text-warning');

    if (type) {
        conditionDiv.classList.remove('d-none');
        conditionText.textContent = `สิทธิ์ลาสูงสุด: ${type.days_per_year} วัน/ปี`;

        const usage = getLeaveUsageItem(selectedId);
        if (usage) {
            conditionText.textContent += ` | ปีงบนี้ลาแล้ว ${usage.approved_requests || 0} ครั้ง`;
            if (Number.parseInt(usage.request_limit || 0, 10) > 0) {
                conditionText.textContent += ` จากสิทธิ์ ${usage.request_limit} ครั้ง/ปีงบ`;
            }
        }

        if (type.requires_file == 1) {
            attachmentSection.classList.remove('d-none');
            attachmentInput.required = true;
        } else {
            attachmentSection.classList.add('d-none');
            attachmentInput.required = false;
            attachmentInput.value = '';
        }
    } else {
        conditionDiv.classList.add('d-none');
        attachmentSection.classList.add('d-none');
        attachmentInput.required = false;
        attachmentInput.value = '';
    }
}

function updateSelectedLeaveUsageProjection() {
    const selectedId = document.getElementById('leaveTypeSelect')?.value;
    if (!selectedId || !latestLeaveSummary) return;

    const usage = getLeaveUsageItem(selectedId);
    const conditionDiv = document.getElementById('leaveTypeCondition');
    const conditionText = document.getElementById('conditionText');
    if (!usage || !conditionDiv || !conditionText) return;

    conditionDiv.classList.remove('text-danger', 'text-warning');
    const projectedRequests = Number.parseInt(usage.approved_requests || 0, 10) + 1;
    const requestLimit = Number.parseInt(usage.request_limit || 0, 10);
    const projectedRequestPercent = requestLimit > 0 ? (projectedRequests / requestLimit) * 100 : 0;
    const projectedPercent = projectedRequestPercent;

    if (projectedPercent > 100) {
        conditionDiv.classList.add('text-danger');
        conditionText.textContent += ` | หลังส่งใบนี้จะเกินสิทธิ์เป็น ${projectedRequests} ครั้ง`;
    } else if (projectedPercent >= 80) {
        conditionDiv.classList.add('text-warning');
        conditionText.textContent += ` | หลังส่งใบนี้จะใกล้ครบ ${projectedRequests} ครั้ง`;
    }
}

function getLeaveTypePresentation(typeName) {
    const name = String(typeName || '').toLowerCase();
    const rules = [
        { match: ['ป่วย', 'sick'], icon: 'fa-user-injured', color: 'primary' },
        { match: ['คลอด', 'maternity', 'บุตร'], icon: 'fa-baby', color: 'purple' },
        { match: ['กิจ', 'business'], icon: 'fa-envelope-open-text', color: 'info' },
        { match: ['พักผ่อน', 'annual', 'vacation'], icon: 'fa-mug-hot', color: 'danger' },
        { match: ['ศาสนา', 'relig'], icon: 'fa-bookmark', color: 'warning' },
        { match: ['ช่วยภริยา', 'ภรรยา', 'paternity'], icon: 'fa-baby-carriage', color: 'success' },
        { match: ['ศึกษา', 'ฝึกอบรม', 'อบรม', 'training', 'study'], icon: 'fa-graduation-cap', color: 'purple' },
        { match: ['สมรส', 'แต่งงาน', 'marriage'], icon: 'fa-venus-mars', color: 'danger' },
        { match: ['เลี้ยงดู', 'child'], icon: 'fa-hand-holding-heart', color: 'danger' },
    ];
    const found = rules.find(rule => rule.match.some(keyword => name.includes(keyword)));
    return found || { icon: 'fa-calendar-check', color: 'secondary' };
}

function setupDateCalculation() {
    const inputs = [
        document.getElementById('startDate'),
        document.getElementById('endDate'),
        document.getElementById('startDayPart'),
        document.getElementById('endDayPart'),
    ].filter(Boolean);

    document.querySelectorAll('.leave-date-picker').forEach(input => {
        input.addEventListener('focus', openNativeDatePicker);
        input.addEventListener('click', openNativeDatePicker);
    });

    inputs.forEach(input => input.addEventListener('change', handleLeaveDateControlsChange));
    handleLeaveDateControlsChange();
}

function openNativeDatePicker(e) {
    if (typeof e.currentTarget.showPicker === 'function') {
        try {
            e.currentTarget.showPicker();
        } catch (error) {
            // Some browsers block showPicker outside direct user activation.
        }
    }
}

function scheduleLeaveCalculation() {
    window.clearTimeout(leaveCalculationTimer);
    leaveCalculationTimer = window.setTimeout(calculateLeaveDays, 150);
}

function handleLeaveDateControlsChange() {
    updateLeavePartOptions();
    scheduleLeaveCalculation();
}

function updateLeavePartOptions() {
    const startInput = document.getElementById('startDate');
    const endInput = document.getElementById('endDate');
    const startPart = document.getElementById('startDayPart');
    const endPart = document.getElementById('endDayPart');
    if (!startInput || !endInput || !startPart || !endPart) return;

    const isMultiDay = startInput.value && endInput.value && endInput.value > startInput.value;
    if (isMultiDay) {
        setLeavePartOptions(startPart, [
            ['full', 'เต็มวัน'],
            ['afternoon', 'ครึ่งวันบ่าย'],
        ], startPart.value === 'morning' ? 'afternoon' : startPart.value);
        setLeavePartOptions(endPart, [
            ['full', 'เต็มวัน'],
            ['morning', 'ครึ่งวันเช้า'],
        ], endPart.value === 'afternoon' ? 'morning' : endPart.value);
        return;
    }

    setLeavePartOptions(startPart, [
        ['full', 'เต็มวัน'],
        ['morning', 'ครึ่งวันเช้า'],
        ['afternoon', 'ครึ่งวันบ่าย'],
    ], startPart.value);
    setLeavePartOptions(endPart, [
        ['full', 'เต็มวัน'],
        ['morning', 'ครึ่งวันเช้า'],
        ['afternoon', 'ครึ่งวันบ่าย'],
    ], endPart.value);
}

function setLeavePartOptions(select, options, preferredValue) {
    const allowedValues = options.map(([value]) => value);
    const nextValue = allowedValues.includes(preferredValue) ? preferredValue : options[0][0];
    select.innerHTML = options
        .map(([value, label]) => `<option value="${value}">${label}</option>`)
        .join('');
    select.value = nextValue;
}

async function calculateLeaveDays() {
    const startInput = document.getElementById('startDate');
    const endInput = document.getElementById('endDate');
    const totalDisplay = document.getElementById('totalDaysDisplay');
    const totalInput = document.getElementById('totalDaysInput');
    const breakdown = document.getElementById('leaveDateBreakdown');

    latestLeaveSummary = null;
    totalDisplay.classList.remove('text-danger');

    if (!startInput.value || !endInput.value) {
        totalDisplay.textContent = '0';
        totalInput.value = 0;
        breakdown.innerHTML = '';
        return;
    }

    if (endInput.value < startInput.value) {
        totalDisplay.textContent = 'วันที่ไม่ถูกต้อง';
        totalDisplay.classList.add('text-danger');
        totalInput.value = 0;
        breakdown.innerHTML = '';
        return;
    }

    totalDisplay.textContent = 'กำลังคำนวณ...';
    const params = new URLSearchParams({
        action: 'calculate_leave',
        start_date: startInput.value,
        end_date: endInput.value,
        start_day_part: document.getElementById('startDayPart').value,
        end_day_part: document.getElementById('endDayPart').value,
    });

    try {
        const response = await fetch(`api/leave_request_api.php?${params.toString()}`);
        const res = await response.json();
        if (res.status !== 'success') {
            throw new Error(res.message || 'คำนวณวันลาไม่สำเร็จ');
        }

        latestLeaveSummary = res.data;
        const totalDays = Number.parseFloat(res.data.total_days || 0);
        totalDisplay.textContent = `${formatLeaveDayNumber(totalDays)} วัน`;
        totalInput.value = totalDays;
        renderLeaveBreakdown(res.data);
        updateLeaveTypeCondition();
        updateSelectedLeaveUsageProjection();
    } catch (error) {
        latestLeaveSummary = null;
        totalInput.value = 0;
        totalDisplay.textContent = error.message;
        totalDisplay.classList.add('text-danger');
        breakdown.innerHTML = '';
    }
}

function renderLeaveBreakdown(summary) {
    const breakdown = document.getElementById('leaveDateBreakdown');
    const included = summary.included_dates || [];
    const excluded = summary.excluded_dates || [];
    const includedHtml = included.length
        ? `<div class="mt-2"><span class="fw-semibold text-success">นับเป็นวันลา:</span> ${included.map(item => `${formatThaiDate(item.date)} (${escapeHtml(item.label)}, ${formatLeaveDayNumber(item.days)} วัน)`).join(', ')}</div>`
        : '<div class="mt-2 text-danger">ไม่มีวันทำงานที่นับเป็นวันลา</div>';
    const excludedHtml = excluded.length
        ? `<div class="mt-2"><span class="fw-semibold text-danger">ตัดออกอัตโนมัติ:</span><ul class="mb-0 mt-1">${excluded.map(item => `<li>${formatThaiDate(item.date)} - ${escapeHtml(item.reason)}</li>`).join('')}</ul></div>`
        : '';

    breakdown.innerHTML = includedHtml + excludedHtml;
}

function formatLeaveDayNumber(value) {
    const number = Number.parseFloat(value) || 0;
    return Number.isInteger(number) ? String(number) : number.toFixed(1);
}

async function handleSubmitLeave(e) {
    e.preventDefault();
    const form = e.target;
    if (!document.getElementById('leaveTypeSelect').value) {
        Swal.fire('เลือกประเภทการลา', 'กรุณาเลือกประเภทการลาก่อนส่งใบลา', 'warning');
        return;
    }

    await calculateLeaveDays();
    if (!latestLeaveSummary || Number.parseFloat(latestLeaveSummary.total_days || 0) <= 0) {
        Swal.fire('ตรวจสอบช่วงวันที่', 'ช่วงวันที่เลือกไม่มีวันทำงานที่สามารถลาได้', 'warning');
        return;
    }

    const formData = new FormData(form);
    formData.append('action', 'submit_leave');

    Swal.fire({
        title: 'ยืนยันการส่งใบลา?',
        text: 'ตรวจสอบข้อมูลให้ถูกต้องก่อนส่ง',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'ยืนยันส่ง',
        cancelButtonText: 'ยกเลิก'
    }).then(async (result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'กำลังบันทึก...', didOpen: () => Swal.showLoading() });

            try {
                const response = await fetch('api/leave_request_api.php', {
                    method: 'POST',
                    body: formData
                });
                const res = await response.json();

                if (res.status === 'success') {
                    Swal.fire('สำเร็จ', res.message, 'success').then(() => {
                        window.location.href = 'my_leaves.php';
                    });
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', res.message, 'error');
                }
            } catch (err) {
                Swal.fire('Error', err.message, 'error');
            }
        }
    });
}
