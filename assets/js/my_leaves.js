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
                const id = e.target.closest('.btn-cancel').getAttribute('data-id');
                handleCancelLeave(id);
            }
        });
    }
});

function renderLeaveStatusBadge(status) {
    const map = {
        pending: ['รอหัวหน้างานอนุมัติ', 'warning text-dark'],
        pending_manager: ['รอหัวหน้างานอนุมัติ', 'warning text-dark'],
        pending_hr: ['รอ HR อนุมัติ', 'info text-dark'],
        approved: ['อนุมัติแล้ว', 'success'],
        rejected: ['ไม่อนุมัติ', 'danger'],
        cancelled: ['ยกเลิกแล้ว', 'secondary'],
    };
    const item = map[status] || [status || '-', 'secondary'];
    return `<span class="badge bg-${item[1]}">${item[0]}</span>`;
}

function isPendingLeaveStatus(status) {
    return status === 'pending' || status === 'pending_manager' || status === 'pending_hr';
}

async function loadMyLeaves() {
    const tbody = document.getElementById('myLeavesTableBody');
    
    try {
        const response = await fetch('api/leave_history_api.php');
        const res = await response.json();

        if (res.status === 'success') {
            renderLeaveUsageSummary(res.usage_summary);
            tbody.innerHTML = '';
            if (res.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">ไม่พบประวัติการลา</td></tr>`;
                return;
            }

            res.data.forEach(item => {
                const createdDate = formatThaiDate(item.created_at);
                const startDate = formatThaiDate(item.start_date);
                const endDate = formatThaiDate(item.end_date);
                const dateRange = formatLeaveDateRange(item.start_date, item.end_date, item.start_day_part, item.end_day_part);
                const durationText = formatLeaveDuration(item);
                const itemId = Number.parseInt(item.id, 10) || 0;
                const typeName = escapeHtml(item.type_name);
                const reason = escapeHtml(item.reason);
                
                // Badge สถานะ
                let actionBtn = '';
                const statusBadge = renderLeaveStatusBadge(item.status);
                const canCancel = item.status === 'pending' || item.status === 'pending_manager';

                if (canCancel) {
                    actionBtn = `<button class="btn btn-sm btn-outline-danger btn-cancel" data-id="${itemId}">
                                    <i class="fas fa-times"></i> ยกเลิก
                                 </button>`;
                }

                tbody.innerHTML += `
                    <tr>
                        <td>${createdDate}</td>
                        <td><span class="fw-bold text-primary">${typeName}</span></td>
                        <td>${dateRange || `${startDate} - ${endDate}`}</td>
                        <td>${durationText}</td>
                        <td><small class="text-muted">${reason}</small></td>
                        <td>${statusBadge}</td>
                        <td>${actionBtn}</td>
                    </tr>
                `;
            });
        }
    } catch (err) {
        console.error(err);
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">เกิดข้อผิดพลาดในการโหลดข้อมูล</td></tr>`;
    }
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
        ? `${formatLeaveDayNumber(item.request_limit)} วัน`
        : 'ไม่จำกัด';
    const remainingDays = item.remaining_requests === null
        ? 'ไม่จำกัด'
        : `${formatLeaveDayNumber(item.remaining_requests)} วัน`;
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
                ใช้แล้ว ${formatLeaveDayNumber(item.approved_days)} วัน, คงเหลือ ${remainingDays}
            </div>
            <div class="small mt-1">จำนวนใบลาที่อนุมัติแล้ว: ${item.approved_requests || 0} ครั้ง</div>
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

function formatLeaveDayNumber(value) {
    const number = Number.parseFloat(value) || 0;
    return Number.isInteger(number) ? String(number) : number.toFixed(1);
}

function formatLeaveDuration(item) {
    if (item.request_unit === 'hour') {
        const minutes = Math.max(1, Math.min(60, Number.parseInt(item.request_minutes || 0, 10) || 60));
        return item.time_request_type === 'early_departure'
            ? `ขอออกก่อน ${minutes} นาที`
            : `ขอมาสาย ${minutes} นาที`;
    }
    return `${parseFloat(item.total_days)} วัน`;
}

function handleCancelLeave(id) {
    Swal.fire({
        title: 'ยืนยันการยกเลิก?',
        text: "คุณต้องการยกเลิกคำขอลาใบนี้ใช่หรือไม่?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'ใช่, ยกเลิกเลย',
        cancelButtonText: 'ไม่'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await fetch('api/leave_history_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'cancel_leave', id: id })
                });
                const res = await response.json();

                if (res.status === 'success') {
                    Swal.fire('สำเร็จ', 'ยกเลิกใบลาเรียบร้อยแล้ว', 'success');
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

function formatLeaveDateRange(startDate, endDate, startPart, endPart) {
    const start = formatThaiDate(startDate);
    const end = formatThaiDate(endDate);
    const startLabel = getLeavePartLabel(startPart);
    const endLabel = getLeavePartLabel(endPart);

    if (!startDate || !endDate) return '';
    if (startDate === endDate) {
        const label = startLabel !== 'เต็มวัน' ? startLabel : endLabel;
        return `${start}${label !== 'เต็มวัน' ? ` (${label})` : ''}`;
    }

    return `${start}${startLabel !== 'เต็มวัน' ? ` (${startLabel})` : ''} - ${end}${endLabel !== 'เต็มวัน' ? ` (${endLabel})` : ''}`;
}

function getLeavePartLabel(part) {
    return {
        morning: 'ครึ่งวันเช้า',
        afternoon: 'ครึ่งวันบ่าย',
        full: 'เต็มวัน',
    }[part] || 'เต็มวัน';
}
