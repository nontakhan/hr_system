/*
 * Logic สำหรับหน้าอนุมัติการลา (leave_approvals.php)
 */

document.addEventListener('DOMContentLoaded', () => {
    // โหลดข้อมูล Tab แรก (Pending) ทันที
    if (document.getElementById('pendingTable')) {
        loadPendingLeaves();
    }

    // Event Listener สำหรับ Tab
    const pendingTab = document.getElementById('pending-tab');
    const historyTab = document.getElementById('history-tab');

    if (pendingTab) pendingTab.addEventListener('shown.bs.tab', loadPendingLeaves);
    if (historyTab) historyTab.addEventListener('shown.bs.tab', loadHistoryLeaves);

    document.getElementById('historyTableBody')?.addEventListener('click', event => {
        const button = event.target.closest('.reviewer-cancel-request-button');
        if (!button) return;
        reviewerCancelApprovedLeaveRequest(Number(button.dataset.requestId), button.dataset.employeeName || '-', button.dataset.requestType || '-');
    });

    // Modal Action Logic
    const approvalForm = document.getElementById('approvalForm');
    if (approvalForm) {
        approvalForm.addEventListener('submit', handleSubmitApproval);
    }
});

const leaveApprovalDataTables = {
    pending: null,
    history: null,
};

function resetLeaveApprovalDataTable(tableId, key) {
    const selector = `#${tableId}`;
    if (leaveApprovalDataTables[key]) {
        leaveApprovalDataTables[key].destroy();
        leaveApprovalDataTables[key] = null;
        return;
    }
    if (window.jQuery && $.fn.DataTable && $.fn.DataTable.isDataTable(selector)) {
        $(selector).DataTable().destroy();
    }
}

function initLeaveApprovalDataTable(tableId, key, orderColumn = 0) {
    const selector = `#${tableId}`;
    if (!window.jQuery || !$.fn.DataTable || !document.getElementById(tableId)) {
        return;
    }
    leaveApprovalDataTables[key] = $(selector).DataTable({
        language: {
            lengthMenu: 'แสดง _MENU_ รายการ ต่อหน้า',
            zeroRecords: 'ไม่พบข้อมูลที่ตรงกัน',
            info: 'แสดง _START_ ถึง _END_ จากทั้งหมด _TOTAL_ รายการ',
            infoEmpty: 'แสดง 0 ถึง 0 จากทั้งหมด 0 รายการ',
            infoFiltered: '(กรองจากทั้งหมด _MAX_ รายการ)',
            search: 'ค้นหา:',
            paginate: {
                first: 'หน้าแรก',
                last: 'สุดท้าย',
                next: 'ถัดไป',
                previous: 'ก่อนหน้า',
            },
        },
        order: [[orderColumn, 'asc']],
        pageLength: 10,
        deferRender: true,
        autoWidth: false,
        columnDefs: [{ targets: -1, orderable: false, searchable: false }],
    });
}

function getLeaveApprovalRequestUnit() {
    return window.leaveApprovalRequestUnit === 'hour' ? 'hour' : 'day';
}

function getLeaveApprovalTimeRequestType() {
    if (window.leaveApprovalTimeRequestType === 'overtime_after_work') {
        return 'overtime_after_work';
    }
    return window.leaveApprovalTimeRequestType === 'late_early' ? 'late_early' : '';
}

function getLeaveApprovalRequestLabel() {
    if (getLeaveApprovalTimeRequestType() === 'overtime_after_work') {
        return 'คำขอ OT';
    }
    return getLeaveApprovalRequestUnit() === 'hour' ? 'คำขอเวลา' : 'การลา';
}

function renderLeaveStatusBadge(status) {
    const map = {
        pending: ['รอหัวหน้างานอนุมัติ', 'warning text-dark'],
        pending_manager: ['รอหัวหน้างานอนุมัติ', 'warning text-dark'],
        pending_hr: ['รอ HR อนุมัติ', 'info text-dark'],
        approved: ['อนุมัติแล้ว', 'success'],
        pending_cancel_hr: ['รอ HR/Admin อนุมัติยกเลิก', 'warning text-dark'],
        rejected: ['ไม่อนุมัติ', 'danger'],
        cancelled: ['ยกเลิก', 'secondary'],
    };
    const item = map[status] || [status || '-', 'secondary'];
    return `<span class="badge bg-${item[1]}">${item[0]}</span>`;
}

// โหลดรายการรออนุมัติ
async function loadPendingLeaves() {
    const tbody = document.getElementById('pendingTableBody');
    resetLeaveApprovalDataTable('pendingTable', 'pending');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4">กำลังโหลด...</td></tr>';

    try {
        const params = new URLSearchParams({
            type: 'pending',
            request_unit: getLeaveApprovalRequestUnit(),
        });
        const timeRequestType = getLeaveApprovalTimeRequestType();
        if (timeRequestType) params.set('time_request_type', timeRequestType);
        const response = await fetch(`api/leave_approval_api.php?${params.toString()}`);
        const res = await response.json();

        if (res.status === 'success') {
            if (res.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">ไม่มีรายการรออนุมัติ</td></tr>';
                return;
            }

            tbody.innerHTML = res.data.map(item => {
                const sDate = formatThaiDate(item.start_date);
                const eDate = formatThaiDate(item.end_date);
                const dateRange = formatLeaveDateRange(item.start_date, item.end_date, item.start_day_part, item.end_day_part);
                const itemId = Number.parseInt(item.id, 10) || 0;
                const firstName = escapeHtml(item.first_name_th);
                const lastName = escapeHtml(item.last_name_th);
                const employeeCode = escapeHtml(item.employee_code);
                const typeName = escapeHtml(item.type_name);
                const reason = escapeHtml(item.reason);
                const cancelReason = escapeHtml(item.cancel_reason || item.cancellation_reason || '');
                const totalDays = Number.parseFloat(item.total_days) || 0;
                const durationText = formatLeaveDuration(item);
                item.file_path = escapeAttr(safeUploadPath(item.file_path));
                item.id = itemId;
                item.first_name_th = firstName;
                item.last_name_th = lastName;
                item.employee_code = employeeCode;
                item.type_name = typeName;
                item.reason = reason;
                item.cancel_reason = cancelReason;
                item.total_days = totalDays;
                
                // รูปไฟล์แนบ
                let fileLink = '';
                if (item.file_path) {
                    fileLink = `<a href="${item.file_path}" target="_blank" class="btn btn-sm btn-outline-info ms-1" title="ดูเอกสารแนบ"><i class="fas fa-paperclip"></i></a>`;
                }

                const avatarHtml = renderEmployeeAvatar(item.profile_img_url);

                const isCancellationRequest = item.status === 'pending_cancel_hr';
                const reasonHtml = isCancellationRequest
                    ? `<small class="d-block text-danger">เหตุผลขอยกเลิก: ${item.cancel_reason || '-'}</small>`
                    : `<small class="d-block text-muted text-truncate" style="max-width: 200px;">${item.reason}</small>`;
                const otDetailHtml = item.time_request_type === 'overtime_after_work'
                    ? `<small class="d-block text-primary">ช่วงเวลา OT: ${formatApprovalTime(item.request_start_time)}-${formatApprovalTime(item.request_end_time)}</small>`
                    : '';
                const approveLabel = isCancellationRequest ? 'อนุมัติยกเลิก' : 'อนุมัติ';
                const rejectLabel = isCancellationRequest ? 'ไม่อนุมัติยกเลิก' : 'ไม่';

                return `
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                ${avatarHtml}
                                <div>
                                    <div class="fw-bold">${item.first_name_th} ${item.last_name_th}</div>
                                    <small class="text-muted">${item.employee_code}</small>
                                    <div class="mt-1">${renderLeaveStatusBadge(item.status)}</div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge bg-primary bg-opacity-10 text-primary">${item.type_name}</span></td>
                        <td>${dateRange || `${sDate} - ${eDate}`}</td>
                        <td><strong>${durationText}</strong></td>
                        <td>
                            ${reasonHtml}
                            ${otDetailHtml}
                            ${fileLink}
                        </td>
                        <td>
                            <button class="btn btn-sm btn-success me-1" data-id="${item.id}" data-action="approve" data-status="${escapeAttr(item.status)}" data-name="${escapeAttr(item.first_name_th)}" onclick="openActionModalFromButton(this)">
                                <i class="fas fa-check"></i> ${approveLabel}
                            </button>
                            <button class="btn btn-sm btn-danger" data-id="${item.id}" data-action="reject" data-status="${escapeAttr(item.status)}" data-name="${escapeAttr(item.first_name_th)}" onclick="openActionModalFromButton(this)">
                                <i class="fas fa-times"></i> ${rejectLabel}
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
            initLeaveApprovalDataTable('pendingTable', 'pending', 0);
        }
    } catch (err) { console.error(err); }
}

// โหลดประวัติการอนุมัติ
async function loadHistoryLeaves() {
    const tbody = document.getElementById('historyTableBody');
    resetLeaveApprovalDataTable('historyTable', 'history');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4">กำลังโหลด...</td></tr>';

    try {
        const params = new URLSearchParams({
            type: 'history',
            request_unit: getLeaveApprovalRequestUnit(),
        });
        const timeRequestType = getLeaveApprovalTimeRequestType();
        if (timeRequestType) params.set('time_request_type', timeRequestType);
        const response = await fetch(`api/leave_approval_api.php?${params.toString()}`);
        const res = await response.json();

        if (res.status === 'success') {
            if (res.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">ไม่มีประวัติ</td></tr>';
                return;
            }

            tbody.innerHTML = res.data.map(item => {
                const appDate = item.approval_date ? formatThaiDate(item.approval_date) : '-';
                const sDate = formatThaiDate(item.start_date);
                const dateRange = formatLeaveDateRange(item.start_date, item.end_date, item.start_day_part, item.end_day_part);
                const durationText = formatLeaveDuration(item);
                item.first_name_th = escapeHtml(item.first_name_th);
                item.last_name_th = escapeHtml(item.last_name_th);
                item.type_name = escapeHtml(item.type_name);
                item.rejection_reason = escapeHtml(item.rejection_reason || '-');
                item.total_days = Number.parseFloat(item.total_days) || 0;
                
                const statusBadge = renderLeaveStatusBadge(item.status);
                const employeeName = `${item.first_name_th} ${item.last_name_th}`.trim();
                const reviewerCancelAction = item.can_reviewer_cancel
                    ? `<button type="button" class="btn btn-outline-danger reviewer-cancel-request-button" data-request-id="${Number(item.id)}" data-employee-name="${escapeAttr(employeeName)}" data-request-type="${escapeAttr(item.type_name)}">ยกเลิกรายการ</button>`
                    : '-';
                const historyNote = renderLeaveReviewerCancellationAudit(item);

                return `
                    <tr>
                        <td>${appDate}</td>
                        <td>${item.first_name_th} ${item.last_name_th}</td>
                        <td>${item.type_name}</td>
                        <td>${dateRange || sDate} (${durationText})</td>
                        <td>${statusBadge}</td>
                        <td>${historyNote}</td>
                        <td>${reviewerCancelAction}</td>
                    </tr>
                `;
            }).join('');
            initLeaveApprovalDataTable('historyTable', 'history', 0);
        }
    } catch (err) { console.error(err); }
}

function renderLeaveReviewerCancellationAudit(item) {
    const reason = escapeHtml(item.cancellation_reason || item.rejection_reason || '-');
    if (item.status !== 'cancelled' || (!item.cancelled_by_name && !item.cancelled_by_role && !item.cancelled_at)) {
        return `<small class="text-muted">${reason}</small>`;
    }
    const actor = escapeHtml(item.cancelled_by_name || item.cancelled_by_role || 'HR/Admin');
    const role = item.cancelled_by_name && item.cancelled_by_role ? ` (${escapeHtml(item.cancelled_by_role)})` : '';
    const time = item.cancelled_at ? `${formatThaiDate(item.cancelled_at)} ${escapeHtml(String(item.cancelled_at).slice(11, 16))} น.` : '';
    return `<div class="small text-danger">เหตุผลยกเลิก: ${reason}</div><div class="small text-muted">ยกเลิกโดย ${actor}${role}${time ? `, ${time}` : ''}</div>`;
}

window.reviewerCancelApprovedLeaveRequest = async function(requestId, employeeName, requestType) {
    const result = await Swal.fire({
        title: 'ยกเลิกรายการที่อนุมัติแล้ว?',
        text: `${requestType} ของ ${employeeName}`,
        input: 'textarea',
        inputLabel: 'เหตุผลการยกเลิก',
        inputPlaceholder: 'ระบุเหตุผลที่ HR/Admin ยกเลิกรายการ...',
        inputValidator: value => String(value || '').trim() ? undefined : 'กรุณาระบุเหตุผลการยกเลิก',
        showCancelButton: true,
        confirmButtonText: 'ยืนยันยกเลิกรายการ',
        cancelButtonText: 'ไม่ยกเลิก',
        confirmButtonColor: '#dc3545',
    });
    if (!result.isConfirmed) return;
    const response = await fetch('api/leave_approval_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'reviewer_cancel',
            request_id: Number(requestId),
            cancellation_reason: String(result.value || '').trim(),
            request_unit: getLeaveApprovalRequestUnit(),
            time_request_type: getLeaveApprovalTimeRequestType(),
        }),
    });
    const payload = await response.json();
    await Swal.fire(payload.status === 'success' ? 'สำเร็จ' : 'ไม่สำเร็จ', payload.message || '', payload.status === 'success' ? 'success' : 'error');
    if (payload.status === 'success') await loadHistoryLeaves();
};

// เปิด Modal ยืนยัน
window.openActionModal = function(id, type, name, status) {
    const modal = new bootstrap.Modal(document.getElementById('actionModal'));
    const title = document.getElementById('actionModalTitle');
    const msg = document.getElementById('actionMessage');
    const confirmBtn = document.getElementById('confirmBtn');
    const reasonDiv = document.getElementById('rejectReasonDiv');
    const reasonInput = document.getElementById('rejectReason');

    document.getElementById('requestId').value = id;
    document.getElementById('actionType').value = type;
    reasonInput.value = ''; // Clear

    name = escapeHtml(name);
    const requestLabel = getLeaveApprovalRequestLabel();
    const isCancellationRequest = status === 'pending_cancel_hr';

    if (type === 'approve') {
        title.innerText = isCancellationRequest ? 'ยืนยันการอนุมัติยกเลิก' : 'ยืนยันการอนุมัติ';
        title.className = 'modal-title text-success';
        msg.innerHTML = isCancellationRequest
            ? `คุณต้องการ <strong>อนุมัติยกเลิก</strong> ${requestLabel}ของ <strong>${name}</strong> ใช่หรือไม่?`
            : `คุณต้องการอนุมัติ${requestLabel}ของ <strong>${name}</strong> ใช่หรือไม่?`;
        confirmBtn.className = 'btn btn-success';
        confirmBtn.innerText = isCancellationRequest ? 'ยืนยันอนุมัติยกเลิก' : 'ยืนยันอนุมัติ';
        reasonDiv.style.display = 'none';
        reasonInput.required = false;
    } else {
        title.innerText = isCancellationRequest ? 'ยืนยันไม่อนุมัติยกเลิก' : 'ยืนยันการปฏิเสธ';
        title.className = 'modal-title text-danger';
        msg.innerHTML = isCancellationRequest
            ? `คุณต้องการ <strong>ไม่อนุมัติยกเลิก</strong> ${requestLabel}ของ <strong>${name}</strong> ใช่หรือไม่?`
            : `คุณต้องการ <strong>ไม่อนุมัติ</strong> ${requestLabel}ของ <strong>${name}</strong> ใช่หรือไม่?`;
        confirmBtn.className = 'btn btn-danger';
        confirmBtn.innerText = isCancellationRequest ? 'ยืนยันไม่อนุมัติยกเลิก' : 'ยืนยันไม่อนุมัติ';
        reasonDiv.style.display = 'block';
        reasonInput.required = true;
    }

    modal.show();
}

window.openActionModalFromButton = function(button) {
    openActionModal(
        Number.parseInt(button.dataset.id, 10) || 0,
        button.dataset.action,
        button.dataset.name || '',
        button.dataset.status || ''
    );
}

function formatLeaveDuration(item) {
    if (item.request_unit === 'hour') {
        const rawMinutes = Number.parseInt(item.request_minutes || 0, 10) || 0;
        if (!item.time_request_type) {
            const hours = rawMinutes / 60;
            return `${formatLeaveDayNumber(hours)} ชม. (${formatLeaveDayNumber(item.total_days || 0)} วัน)`;
        }
        if (item.time_request_type === 'overtime_after_work') {
            const approved = Number.parseInt(item.approved_request_minutes || item.approval_overtime_minutes || 0, 10) || 0;
            const suffix = approved > 0 && approved !== rawMinutes ? `, อนุมัติได้ ${formatHourMinuteDuration(approved)}` : '';
            const range = item.request_start_time && item.request_end_time
                ? `${formatApprovalTime(item.request_start_time)}-${formatApprovalTime(item.request_end_time)} `
                : '';
            return `OT หลังเลิกงาน ${range}${formatHourMinuteDuration(rawMinutes)}${suffix}`;
        }
        const minutes = Math.max(1, Math.min(60, rawMinutes || 60));
        return item.time_request_type === 'early_departure'
            ? `ขอออกก่อน ${minutes} นาที`
            : `ขอมาสาย ${minutes} นาที`;
    }
    return `${parseFloat(item.total_days)} วัน`;
}

function formatLeaveDayNumber(value) {
    const number = Number.parseFloat(value) || 0;
    return Number.isInteger(number) ? String(number) : number.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
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

function formatApprovalTime(value) {
    return value ? String(value).substring(0, 5) : '-';
}

// Submit การอนุมัติ/ไม่อนุมัติ
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

async function handleSubmitApproval(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const action = formData.get('action_type'); // approve / reject
    const data = Object.fromEntries(formData.entries());
    data.request_unit = getLeaveApprovalRequestUnit();
    data.time_request_type = getLeaveApprovalTimeRequestType();
    
    // เปลี่ยน key ให้ตรงกับ API ที่คาดหวัง
    data.action = action; 

    try {
        const response = await fetch('api/leave_approval_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const res = await response.json();

        if (res.status === 'success') {
            Swal.fire('สำเร็จ', res.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('actionModal')).hide();
            loadPendingLeaves(); // Reload ตาราง
        } else {
            Swal.fire('ผิดพลาด', res.message, 'error');
        }
    } catch (err) {
        Swal.fire('Error', err.message, 'error');
    }
}
