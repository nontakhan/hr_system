/*
 * Logic สำหรับหน้าประวัติการลาของฉัน (my_leaves.php)
 */

document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('myLeavesTable');
    if (table) {
        loadMyLeaves();

        // Event Delegation สำหรับปุ่มยกเลิก
        table.addEventListener('click', (e) => {
            if (e.target.closest('.btn-cancel')) {
                const button = e.target.closest('.btn-cancel');
                const id = button.getAttribute('data-id');
                const status = button.getAttribute('data-status') || '';
                handleCancelLeave(id, status);
            }
        });
    }
});

function myLeavesRenderLeaveStatusBadge(status) {
    const map = {
        pending: ['รอหัวหน้างานอนุมัติ', 'warning text-dark'],
        pending_manager: ['รอหัวหน้างานอนุมัติ', 'warning text-dark'],
        pending_hr: ['รอ HR อนุมัติ', 'info text-dark'],
        approved: ['อนุมัติแล้ว', 'success'],
        pending_cancel_hr: ['รอ HR/Admin อนุมัติยกเลิก', 'warning text-dark'],
        rejected: ['ไม่อนุมัติ', 'danger'],
        cancelled: ['ยกเลิกแล้ว', 'secondary'],
    };
    const item = map[status] || [status || '-', 'secondary'];
    return `<span class="badge bg-${item[1]}">${item[0]}</span>`;
}

function myLeavesIsPendingLeaveStatus(status) {
    return status === 'pending' || status === 'pending_manager' || status === 'pending_hr';
}

async function loadMyLeaves() {
    return loadServerTable({ tableId: 'myLeavesTable', url: 'api/leave_history_api.php',
        renderRow: renderMyLeaveRow, onResult: result => myLeavesRenderLeaveUsageSummary(result.usage_summary),
        order: [[0, 'desc']], unsortable: [6] });
}

function renderMyLeaveRow(item) {
                const createdDate = formatThaiDate(item.created_at);
                const startDate = formatThaiDate(item.start_date);
                const endDate = formatThaiDate(item.end_date);
                const dateRange = formatLeaveDateRange(item.start_date, item.end_date, item.start_day_part, item.end_day_part);
                const durationText = myLeavesFormatLeaveDuration(item);
                const itemId = Number.parseInt(item.id, 10) || 0;
                const typeName = escapeHtml(item.type_name);
                const reason = escapeHtml(item.reason);
                const proxyHtml = renderProxyCreatorLine(item);
                
                // Badge สถานะ
                let actionBtn = '';
                if (item.status === 'pending_hr') actionBtn = '<small class="text-muted d-block">รอฝ่ายบุคคลพิจารณา หากต้องการถอนใบลา กรุณาติดต่อ HR</small>';
                const statusBadge = myLeavesRenderLeaveStatusBadge(item.status);
                const canCancel = item.status === 'pending' || item.status === 'pending_manager' || item.status === 'approved';

                if (canCancel) {
                    const cancelLabel = item.status === 'approved' ? 'ขอยกเลิก' : 'ยกเลิก';
                    actionBtn = `<button class="btn btn-sm btn-outline-danger btn-cancel request-cancel-button" data-id="${itemId}" data-status="${escapeAttr(item.status)}">
                                    <i class="fas fa-times"></i> ${cancelLabel}
                                 </button>`;
                }

                return `
                    <tr>
                        <td>${createdDate}</td>
                        <td><span class="fw-bold text-primary">${typeName}</span></td>
                        <td>${dateRange || `${startDate} - ${endDate}`}</td>
                        <td>${durationText}</td>
                        <td><small class="text-muted">${reason}</small>${proxyHtml}</td>
                        <td>${statusBadge}</td>
                        <td class="request-status-actions">${actionBtn}</td>
                    </tr>
                `;
}

function myLeavesRenderLeaveUsageSummary(summary) {
    const grid = document.getElementById('leaveUsageSummaryGrid');
    const overallGrid = document.getElementById('leaveUsageOverallGrid');
    const fiscalText = document.getElementById('leaveUsageFiscalYearText');
    if (!grid) return;

    if (fiscalText && summary && summary.fiscal_year) {
        fiscalText.textContent = `ช่วงปีงบประมาณ ${formatThaiDate(summary.fiscal_year.start_date)} ถึง ${formatThaiDate(summary.fiscal_year.end_date)}`;
    }

    if (!summary || !summary.overall) {
        if (overallGrid) {
            overallGrid.innerHTML = '<div class="text-muted small">ยังไม่มีข้อมูลสรุปการลา</div>';
        }
        grid.innerHTML = '<div class="text-muted small">ยังไม่มีข้อมูลสรุปการลา</div>';
        return;
    }

    const typeItems = Array.isArray(summary.items) ? summary.items : [];
    if (overallGrid) {
        overallGrid.innerHTML = myLeavesRenderOverallLeaveUsageCard(summary.overall);
    }
    grid.innerHTML = typeItems.length
        ? typeItems.map(item => myLeavesRenderTypeLeaveUsageCard(item)).join('')
        : '<div class="text-muted small">ยังไม่มีประเภทการลาที่นำมาสรุปสิทธิ์</div>';
}


function myLeavesRenderOverallLeaveUsageCard(item) {
    const statusClass = `leave-usage-card-${item.status || 'normal'}`;
    const percent = Number.parseFloat(item.request_usage_percent || 0);
    const progressWidth = Math.min(Math.max(percent, 0), 100);
    const requestLimitText = Number.parseInt(item.request_limit || 0, 10) > 0
        ? `${myLeavesFormatLeaveDayNumber(item.request_limit)} วัน`
        : 'ไม่จำกัด';
    const balanceText = myLeavesFormatUsageBalanceText(item, 'remaining_requests');
    const pendingText = Number.parseFloat(item.pending_days || 0) > 0
        ? `<div class="leave-usage-pending">รออนุมัติ ${item.pending_requests || 0} ครั้ง รวม ${myLeavesFormatLeaveDayNumber(item.pending_days)} วัน</div>`
        : '';

    return `
        <div class="leave-usage-card leave-usage-card-overall ${statusClass}">
            <div class="d-flex justify-content-between gap-2">
                <div class="leave-usage-card-title">
                    <span class="leave-usage-icon"><i class="fas fa-chart-pie"></i></span>
                    <strong>รวมการลาทั้งปีงบประมาณ</strong>
                </div>
                <span>${myLeavesFormatLeaveDayNumber(item.approved_days)} / ${requestLimitText}</span>
            </div>
            <div class="leave-usage-progress" aria-hidden="true">
                <span style="width: ${progressWidth}%"></span>
            </div>
            <div class="small mt-2">
                ใช้แล้ว ${myLeavesFormatLeaveDayNumber(item.approved_days)} วัน, ${balanceText}
            </div>
            <div class="small mt-1">จำนวนใบลาที่อนุมัติแล้ว: ${item.approved_requests || 0} ครั้ง</div>
            ${pendingText}
        </div>
    `;
}

function myLeavesRenderTypeLeaveUsageCard(item) {
    const presentation = myLeavesGetLeaveTypePresentation(item.type_name || '');
    const statusClass = `leave-usage-card-${item.status || 'normal'}`;
    const percent = Number.parseFloat(item.usage_percent || 0);
    const progressWidth = Math.min(Math.max(percent, 0), 100);
    const limitDays = Number.parseFloat(item.limit_days || 0);
    const limitText = limitDays > 0
        ? `${myLeavesFormatLeaveDayNumber(limitDays)} วัน`
        : 'ไม่จำกัด';
    const balanceText = myLeavesFormatUsageBalanceText(item, 'remaining_days');
    const pendingText = Number.parseFloat(item.pending_days || 0) > 0
        ? `<div class="leave-usage-pending">รออนุมัติ ${item.pending_requests || 0} ครั้ง รวม ${myLeavesFormatLeaveDayNumber(item.pending_days)} วัน</div>`
        : '';

    return `
        <div class="leave-usage-card leave-usage-card-${presentation.tone} ${statusClass}">
            <div class="d-flex justify-content-between gap-2">
                <div class="leave-usage-card-title">
                    <span class="leave-usage-icon"><i class="fas ${presentation.icon}"></i></span>
                    <strong>${escapeHtml(item.type_name || 'ประเภทการลา')}</strong>
                </div>
                <span>${myLeavesFormatLeaveDayNumber(item.approved_days)} / ${limitText}</span>
            </div>
            <div class="leave-usage-progress" aria-hidden="true">
                <span style="width: ${progressWidth}%"></span>
            </div>
            <div class="small mt-2">
                ใช้แล้ว ${myLeavesFormatLeaveDayNumber(item.approved_days)} วัน, ${balanceText}
            </div>
            <div class="small mt-1">สิทธิ์ตามหน้าตั้งค่าประเภทการลา: ${limitText}</div>
            ${pendingText}
        </div>
    `;
}

function myLeavesGetLeaveTypePresentation(typeName) {
    const name = String(typeName || '').toLowerCase();
    const rules = [
        { match: ['ป่วย', 'sick'], icon: 'fa-user-injured', tone: 'blue' },
        { match: ['คลอด', 'maternity', 'บุตร'], icon: 'fa-baby', tone: 'purple' },
        { match: ['กิจ', 'business'], icon: 'fa-envelope-open-text', tone: 'cyan' },
        { match: ['พักผ่อน', 'annual', 'vacation'], icon: 'fa-mug-hot', tone: 'rose' },
        { match: ['ศาสนา', 'relig'], icon: 'fa-bookmark', tone: 'amber' },
        { match: ['ช่วยภริยา', 'ภรรยา', 'paternity'], icon: 'fa-baby-carriage', tone: 'green' },
        { match: ['ศึกษา', 'ฝึกอบรม', 'อบรม', 'training', 'study'], icon: 'fa-graduation-cap', tone: 'indigo' },
        { match: ['สมรส', 'แต่งงาน', 'marriage'], icon: 'fa-venus-mars', tone: 'pink' },
        { match: ['เลี้ยงดู', 'child'], icon: 'fa-hand-holding-heart', tone: 'orange' },
    ];
    return rules.find(rule => rule.match.some(keyword => name.includes(keyword)))
        || { icon: 'fa-calendar-check', tone: 'slate' };
}

function myLeavesFormatLeaveDayNumber(value) {
    const number = Number.parseFloat(value) || 0;
    return Number.isInteger(number) ? String(number) : number.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
}

function myLeavesFormatUsageBalanceText(item, remainingKey) {
    const remaining = item[remainingKey];
    if (remaining === null || remaining === undefined) {
        return 'ไม่จำกัด';
    }

    const overLimitDays = Number.parseFloat(item.over_limit_days || 0);
    if (item.is_over_limit || overLimitDays > 0 || Number.parseFloat(remaining) < 0) {
        const exceededDays = overLimitDays > 0 ? overLimitDays : Math.abs(Number.parseFloat(remaining) || 0);
        return `เกินสิทธิ์ ${myLeavesFormatLeaveDayNumber(exceededDays)} วัน`;
    }

    return `คงเหลือ ${myLeavesFormatLeaveDayNumber(remaining)} วัน`;
}

function myLeavesFormatLeaveDuration(item) {
    if (item.request_unit === 'hour') {
        const rawMinutes = Number.parseInt(item.request_minutes || 0, 10) || 0;
        if (!item.time_request_type) {
            const hours = rawMinutes / 60;
            return `${myLeavesFormatLeaveDayNumber(hours)} ชม. (${myLeavesFormatLeaveDayNumber(item.total_days || 0)} วัน)`;
        }
        const minutes = Math.max(1, Math.min(60, rawMinutes || 60));
        return item.time_request_type === 'early_departure'
            ? `ขอออกก่อน ${minutes} นาที`
            : `ขอมาสาย ${minutes} นาที`;
    }
    return `${parseFloat(item.total_days)} วัน`;
}

function handleCancelLeave(id, status) {
    const isApprovedLeave = status === 'approved';
    Swal.fire({
        title: isApprovedLeave ? 'ขอยกเลิกใบลาที่อนุมัติแล้ว?' : 'ยืนยันการยกเลิก?',
        text: isApprovedLeave ? 'คำขอนี้จะถูกส่งให้ HR/Admin อนุมัติ' : "คุณต้องการยกเลิกคำขอลาใบนี้ใช่หรือไม่?",
        icon: 'warning',
        input: 'textarea',
        inputLabel: 'เหตุผลการยกเลิกใบลา',
        inputPlaceholder: 'ระบุเหตุผลการยกเลิก...',
        inputAttributes: {
            'aria-label': 'เหตุผลการยกเลิกใบลา'
        },
        inputValidator: (value) => {
            if (!value || !value.trim()) {
                return 'กรุณาระบุเหตุผลการยกเลิกใบลา';
            }
            return null;
        },
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: isApprovedLeave ? 'ส่งคำขอยกเลิก' : 'ใช่, ยกเลิกเลย',
        cancelButtonText: 'ไม่'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await fetch('api/leave_history_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'cancel_leave',
                        id: id,
                        cancel_reason: result.value || ''
                    })
                });
                const res = await response.json();

                if (res.status === 'success') {
                    Swal.fire('สำเร็จ', res.message || 'ดำเนินการเรียบร้อยแล้ว', 'success');
                    loadMyLeaves(); // โหลดตารางใหม่
                } else {
                    Swal.fire('ทำรายการไม่ได้', res.message, 'error');
                }
            } catch (err) {
                Swal.fire('Error', err.message, 'error');
            }
        }
    });
}
