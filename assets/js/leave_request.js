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
        renderProjectedLeaveUsageSummary();
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
    updateLeaveRequestMode();
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
    const selectedItem = Array.isArray(leaveUsageSummary.items)
        ? leaveUsageSummary.items.find(item => String(item.leave_type_id) === String(typeId))
        : null;
    return selectedItem || leaveUsageSummary.overall || null;
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

    const typeItems = Array.isArray(summary.items) ? summary.items : [];
    grid.innerHTML = typeItems.length
        ? typeItems.map(item => renderTypeLeaveUsageCard(item)).join('')
        : renderOverallLeaveUsageCard(summary.overall);
}

function renderProjectedLeaveUsageSummary() {
    renderLeaveUsageSummary(buildProjectedLeaveUsageSummary());
}

function buildProjectedLeaveUsageSummary() {
    if (!leaveUsageSummary) return leaveUsageSummary;

    const selectedId = document.getElementById('leaveTypeSelect')?.value;
    const projectedSelectedDays = Number.parseFloat(latestLeaveSummary?.total_days || 0) || 0;
    if (!selectedId || projectedSelectedDays <= 0) {
        return leaveUsageSummary;
    }

    const clone = {
        ...leaveUsageSummary,
        overall: leaveUsageSummary.overall ? { ...leaveUsageSummary.overall } : null,
        items: Array.isArray(leaveUsageSummary.items)
            ? leaveUsageSummary.items.map(item => ({ ...item }))
            : [],
    };

    clone.items = clone.items.map(item => {
        if (String(item.leave_type_id) !== String(selectedId)) {
            return item;
        }
        return applyProjectedLeaveDays(item, projectedSelectedDays);
    });
    if (clone.overall) {
        clone.overall = applyProjectedLeaveDays(clone.overall, projectedSelectedDays);
    }

    return clone;
}

function applyProjectedLeaveDays(item, projectedSelectedDays) {
    const projected = { ...item };
    const approvedDays = Number.parseFloat(projected.approved_days || 0) || 0;
    const pendingDays = Number.parseFloat(projected.pending_days || 0) || 0;
    const limit = Number.parseFloat(projected.request_limit || projected.limit_days || 0) || 0;
    const projectedTotalDays = approvedDays + projectedSelectedDays;

    projected.pending_days = Number.parseFloat(pendingDays.toFixed(2));
    projected.projectedSelectedDays = Number.parseFloat(projectedSelectedDays.toFixed(2));
    projected.projected_total_days = Number.parseFloat(projectedTotalDays.toFixed(2));
    projected.remaining_days = limit > 0 ? Number.parseFloat((limit - approvedDays).toFixed(2)) : projected.remaining_days;
    projected.remaining_requests = limit > 0 ? Number.parseFloat((limit - approvedDays).toFixed(2)) : projected.remaining_requests;
    projected.projected_remaining_days = limit > 0 ? Number.parseFloat((limit - projectedTotalDays).toFixed(2)) : null;
    projected.projected_usage_percent = limit > 0 ? Number.parseFloat(((projectedTotalDays / limit) * 100).toFixed(1)) : 0;
    return projected;
}

function renderOverallLeaveUsageCard(item) {
    const statusClass = `leave-usage-card-${item.status || 'normal'}`;
    const percent = Number.parseFloat(item.request_usage_percent || 0);
    const progressWidth = Math.min(Math.max(percent, 0), 100);
    const requestLimitText = Number.parseInt(item.request_limit || 0, 10) > 0
        ? `${formatLeaveDayNumber(item.request_limit)} วัน`
        : 'ไม่จำกัด';
    const balanceText = formatUsageBalanceText(item, 'remaining_requests');
    const pendingText = Number.parseFloat(item.pending_days || 0) > 0
        ? `<div class="leave-usage-pending">รออนุมัติ ${item.pending_requests || 0} ครั้ง รวม ${formatLeaveDayNumber(item.pending_days)} วัน</div>`
        : '';

    return `
        <div class="leave-usage-card ${statusClass}">
            <div class="d-flex justify-content-between gap-2">
                <strong>รวมการลาทั้งปีงบประมาณ</strong>
                <span>${formatLeaveDayNumber(item.approved_days)} / ${requestLimitText}</span>
            </div>
            <div class="leave-usage-progress" aria-hidden="true">
                <span style="width: ${progressWidth}%"></span>
            </div>
            <div class="small mt-2">
                ใช้แล้ว ${formatLeaveDayNumber(item.approved_days)} วัน, ${balanceText}
            </div>
            <div class="small mt-1">จำนวนใบลาที่อนุมัติแล้ว: ${item.approved_requests || 0} ครั้ง</div>
            ${pendingText}
            ${renderLeaveUsageEntries(item.entries || [])}
        </div>
    `;
}

function renderTypeLeaveUsageCard(item) {
    const presentation = getLeaveTypePresentation(item.type_name || '');
    const tone = getLeaveUsageCardTone(presentation.color);
    const statusClass = `leave-usage-card-${item.status || 'normal'}`;
    const percent = Number.parseFloat(item.projected_usage_percent || item.usage_percent || 0);
    const progressWidth = Math.min(Math.max(percent, 0), 100);
    const limitDays = Number.parseFloat(item.limit_days || item.request_limit || 0);
    const limitText = limitDays > 0
        ? `${formatLeaveDayNumber(limitDays)} วัน`
        : 'ไม่จำกัด';
    const projectedSelectedDays = Number.parseFloat(item.projectedSelectedDays || 0);
    const displayedUsedDays = projectedSelectedDays > 0
        ? Number.parseFloat(item.projected_total_days || 0)
        : Number.parseFloat(item.approved_days || 0);
    const projectedText = projectedSelectedDays > 0
        ? `<div class="leave-usage-pending text-primary">ใบนี้ ${formatLeaveDayNumber(projectedSelectedDays)} วัน หลังส่งจะใช้รวม ${formatLeaveDayNumber(item.projected_total_days || 0)} วัน</div>`
        : '';
    const pendingDays = Number.parseFloat(item.pending_days || 0);
    const pendingText = pendingDays > 0
        ? `<div class="leave-usage-pending">รออนุมัติ ${item.pending_requests || 0} ครั้ง รวม ${formatLeaveDayNumber(pendingDays)} วัน</div>`
        : '';
    const balanceText = item.projected_remaining_days !== null && item.projected_remaining_days !== undefined
        ? `หลังส่งคงเหลือ ${formatLeaveDayNumber(item.projected_remaining_days)} วัน`
        : formatUsageBalanceText(item, 'remaining_days');

    return `
        <div class="leave-usage-card leave-usage-card-${tone} ${statusClass}">
            <div class="d-flex justify-content-between gap-2">
                <div class="leave-usage-card-title">
                    <span class="leave-usage-icon text-${presentation.color || 'secondary'}"><i class="fas ${presentation.icon}"></i></span>
                    <strong>${escapeHtml(item.type_name || 'ประเภทการลา')}</strong>
                </div>
                <span>${formatLeaveDayNumber(displayedUsedDays)} / ${limitText}</span>
            </div>
            <div class="leave-usage-progress" aria-hidden="true">
                <span style="width: ${progressWidth}%"></span>
            </div>
            <div class="small mt-2">
                ใช้แล้ว ${formatLeaveDayNumber(displayedUsedDays)} วัน, ${balanceText}
            </div>
            <div class="small mt-1">สิทธิ์ตามหน้าตั้งค่าประเภทการลา: ${limitText}</div>
            ${projectedText}
            ${pendingText}
        </div>
    `;
}

function getLeaveUsageCardTone(color) {
    return {
        primary: 'blue',
        info: 'cyan',
        danger: 'rose',
        warning: 'amber',
        success: 'green',
        purple: 'purple',
        secondary: 'slate',
    }[color] || 'slate';
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
    updateLeaveRequestMode();
    updateLeaveTypeCondition();
    updateSelectedLeaveUsageProjection();
}

function isSelectedLeaveTypeHourly() {
    const type = getSelectedLeaveType();
    return type && type.calculation_unit === 'hour';
}

function updateLeaveRequestMode() {
    const isHourly = isSelectedLeaveTypeHourly();
    const startLabel = document.getElementById('startDateLabel');
    const startField = document.getElementById('startDateField');
    const endInput = document.getElementById('endDate');
    const startInput = document.getElementById('startDate');
    const startPart = document.getElementById('startDayPart');
    const endPart = document.getElementById('endDayPart');
    const hourlyFields = document.getElementById('hourlyLeaveFields');
    const requestStartTime = document.getElementById('requestStartTime');
    const requestEndTime = document.getElementById('requestEndTime');
    const ruleText = document.getElementById('hourlyLeaveRuleText');
    const type = getSelectedLeaveType();

    document.querySelectorAll('.day-leave-field').forEach(field => {
        field.classList.toggle('d-none', isHourly);
    });
    if (startField) {
        startField.classList.toggle('col-md-12', isHourly);
        startField.classList.toggle('col-md-6', !isHourly);
    }
    if (hourlyFields) hourlyFields.classList.toggle('d-none', !isHourly);
    if (startLabel) startLabel.innerHTML = isHourly ? 'วันที่ลา <span class="text-danger">*</span>' : 'วันที่เริ่มลา <span class="text-danger">*</span>';
    if (endInput) {
        endInput.required = !isHourly;
        if (isHourly && startInput?.value) endInput.value = startInput.value;
    }
    if (startPart) startPart.required = !isHourly;
    if (endPart) endPart.required = !isHourly;
    if (requestStartTime) {
        requestStartTime.required = !!isHourly;
        if (!isHourly) requestStartTime.value = '';
    }
    if (requestEndTime) {
        requestEndTime.required = !!isHourly;
        if (!isHourly) requestEndTime.value = '';
    }
    if (ruleText && type) {
        const hoursPerDay = formatLeaveDayNumber(type.hours_per_day || 8);
        const threshold = Number.parseFloat(type.hour_full_day_threshold || 0);
        ruleText.textContent = threshold > 0
            ? `${hoursPerDay} ชม. = 1 วัน, ถ้าเกิน ${formatLeaveDayNumber(threshold)} ชม. จะนับเป็น 1 วัน`
            : `${hoursPerDay} ชม. = 1 วัน`;
    }

    scheduleLeaveCalculation();
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
        if (type.calculation_unit === 'hour') {
            const hoursPerDay = formatLeaveDayNumber(type.hours_per_day || 8);
            const threshold = Number.parseFloat(type.hour_full_day_threshold || 0);
            conditionText.textContent += ` | คิดเป็นรายชั่วโมง (${hoursPerDay} ชม. = 1 วัน`;
            conditionText.textContent += threshold > 0 ? `, เกิน ${formatLeaveDayNumber(threshold)} ชม.นับ 1 วัน)` : ')';
        }

        const usage = getLeaveUsageItem(selectedId);
        if (usage) {
            conditionText.textContent += ` | ปีงบนี้ลาแล้ว ${formatLeaveDayNumber(usage.approved_days)} วัน`;
            if (Number.parseFloat(usage.request_limit || 0) > 0) {
                conditionText.textContent += ` จากสิทธิ์ ${formatLeaveDayNumber(usage.request_limit)} วัน/ปีงบ`;
            }
            if (usage.is_over_limit || Number.parseFloat(usage.over_limit_days || 0) > 0) {
                conditionDiv.classList.add('text-danger');
                conditionText.textContent += ` | เกินสิทธิ์แล้ว ${formatLeaveDayNumber(usage.over_limit_days)} วัน`;
            }
        }

        if (type.requires_file == 1) {
            attachmentSection.classList.remove('d-none');
            attachmentInput.required = false;
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
    if (!selectedId || !latestLeaveSummary) {
        renderProjectedLeaveUsageSummary();
        return;
    }

    const usage = getLeaveUsageItem(selectedId);
    const conditionDiv = document.getElementById('leaveTypeCondition');
    const conditionText = document.getElementById('conditionText');
    if (!usage || !conditionDiv || !conditionText) return;

    conditionDiv.classList.remove('text-danger', 'text-warning');
    const projectedDays = (Number.parseFloat(usage.approved_days || 0) || 0) + (Number.parseFloat(latestLeaveSummary.total_days || 0) || 0);
    const requestLimit = Number.parseFloat(usage.request_limit || 0);
    const projectedRequestPercent = requestLimit > 0 ? (projectedDays / requestLimit) * 100 : 0;
    const projectedPercent = projectedRequestPercent;

    if (projectedPercent > 100) {
        conditionDiv.classList.add('text-danger');
        conditionText.textContent += ` | หลังส่งใบนี้จะเกินสิทธิ์ ${formatLeaveDayNumber(projectedDays - requestLimit)} วัน`;
    } else if (projectedPercent >= 80) {
        conditionDiv.classList.add('text-warning');
        conditionText.textContent += ` | หลังส่งใบนี้จะใกล้ครบ ${formatLeaveDayNumber(projectedDays)} วัน`;
    }
    renderProjectedLeaveUsageSummary();
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
        document.getElementById('requestStartTime'),
        document.getElementById('requestEndTime'),
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
    if (isSelectedLeaveTypeHourly()) {
        const startInput = document.getElementById('startDate');
        const endInput = document.getElementById('endDate');
        if (startInput && endInput) endInput.value = startInput.value;
    }
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

function parseLeaveTimeToMinutes(value) {
    const match = String(value || '').match(/^(\d{2}):(\d{2})/);
    if (!match) return null;
    const hours = Number.parseInt(match[1], 10);
    const minutes = Number.parseInt(match[2], 10);
    if (hours < 0 || hours > 23 || minutes < 0 || minutes > 59) return null;
    return hours * 60 + minutes;
}

function getHourlyLeaveDuration() {
    const startValue = document.querySelector('[name="request_start_time"]')?.value || '';
    const endValue = document.querySelector('[name="request_end_time"]')?.value || '';
    if (!startValue || !endValue) {
        return { hasInput: false, valid: false, minutes: 0, message: '' };
    }

    const start = parseLeaveTimeToMinutes(startValue);
    const end = parseLeaveTimeToMinutes(endValue);
    if (start === null || end === null) {
        return { hasInput: true, valid: false, minutes: 0, message: 'รูปแบบเวลาไม่ถูกต้อง' };
    }

    const minutes = end - start;
    if (minutes <= 0) {
        return { hasInput: true, valid: false, minutes: 0, message: 'เวลาสิ้นสุดต้องมากกว่าเวลาเริ่ม' };
    }

    return { hasInput: true, valid: true, minutes, message: '' };
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

    if (isSelectedLeaveTypeHourly()) {
        const duration = getHourlyLeaveDuration();
        const type = getSelectedLeaveType();

        if (!startInput.value || !duration.hasInput) {
            totalDisplay.textContent = '0';
            totalInput.value = 0;
            breakdown.innerHTML = '';
            renderProjectedLeaveUsageSummary();
            return;
        }
        if (!duration.valid) {
            totalDisplay.textContent = duration.message;
            totalDisplay.classList.add('text-danger');
            totalInput.value = 0;
            breakdown.innerHTML = '';
            renderProjectedLeaveUsageSummary();
            return;
        }

        const hoursPerDay = Number.parseFloat(type?.hours_per_day || 8) || 8;
        const threshold = Number.parseFloat(type?.hour_full_day_threshold || 0) || 0;
        const requestHours = duration.minutes / 60;
        const totalDays = threshold > 0 && requestHours > threshold
            ? 1
            : requestHours / hoursPerDay;
        latestLeaveSummary = {
            valid: true,
            total_days: Number.parseFloat(totalDays.toFixed(2)),
            included_dates: [{ date: startInput.value, days: Number.parseFloat(totalDays.toFixed(2)), label: `${formatLeaveDayNumber(requestHours)} ชม.` }],
            excluded_dates: [],
        };
        totalDisplay.textContent = `${formatLeaveDayNumber(latestLeaveSummary.total_days)} วัน`;
        totalInput.value = latestLeaveSummary.total_days;
        breakdown.innerHTML = `<div class="mt-2"><span class="fw-semibold text-success">นับสิทธิ์ลา:</span> ${formatLeaveDayNumber(requestHours)} ชม. = ${formatLeaveDayNumber(latestLeaveSummary.total_days)} วัน</div>`;
        updateLeaveTypeCondition();
        updateSelectedLeaveUsageProjection();
        return;
    }

    if (!startInput.value || !endInput.value) {
        totalDisplay.textContent = '0';
        totalInput.value = 0;
        breakdown.innerHTML = '';
        renderProjectedLeaveUsageSummary();
        return;
    }

    if (endInput.value < startInput.value) {
        totalDisplay.textContent = 'วันที่ไม่ถูกต้อง';
        totalDisplay.classList.add('text-danger');
        totalInput.value = 0;
        breakdown.innerHTML = '';
        renderProjectedLeaveUsageSummary();
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
        renderProjectedLeaveUsageSummary();
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
    return Number.isInteger(number) ? String(number) : number.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
}

function formatUsageBalanceText(item, remainingKey) {
    const remaining = item[remainingKey];
    if (remaining === null || remaining === undefined) {
        return 'ไม่จำกัด';
    }

    const overLimitDays = Number.parseFloat(item.over_limit_days || 0);
    if (item.is_over_limit || overLimitDays > 0 || Number.parseFloat(remaining) < 0) {
        const exceededDays = overLimitDays > 0 ? overLimitDays : Math.abs(Number.parseFloat(remaining) || 0);
        return `เกินสิทธิ์ ${formatLeaveDayNumber(exceededDays)} วัน (ยังส่งคำขอได้)`;
    }

    return `คงเหลือ ${formatLeaveDayNumber(remaining)} วัน`;
}

async function handleSubmitLeave(e) {
    e.preventDefault();
    const form = e.target;
    if (!document.getElementById('leaveTypeSelect').value) {
        Swal.fire('เลือกประเภทการลา', 'กรุณาเลือกประเภทการลาก่อนส่งใบลา', 'warning');
        return;
    }
    if (isSelectedLeaveTypeHourly()) {
        const duration = getHourlyLeaveDuration();
        if (!duration.valid) {
            Swal.fire('ระบุช่วงเวลา', duration.message || 'กรุณาระบุเวลาเริ่มและเวลาสิ้นสุดการลา', 'warning');
            return;
        }
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
