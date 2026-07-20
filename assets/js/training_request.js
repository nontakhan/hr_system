document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('trainingRequestForm')) {
        initTrainingRequestPage();
    }
    if (document.getElementById('trainingRequestHistoryBody')) {
        initTrainingRequestHistoryPage();
    }
    if (document.getElementById('trainingRequestPendingBody')) {
        initTrainingRequestApprovalPage();
    }
});

const trainingRequestDataTables = {};

function initTrainingRequestPage() {
    const form = document.getElementById('trainingRequestForm');
    form.addEventListener('submit', submitTrainingRequest);
    form.querySelector('[name="start_date"]')?.addEventListener('change', updateTrainingDayPartVisibility);
    form.querySelector('[name="end_date"]')?.addEventListener('change', updateTrainingDayPartVisibility);
    form.querySelector('[name="start_day_part"]')?.addEventListener('change', syncTrainingEndDayPart);
    loadTrainingActivityTypes();
    updateTrainingDayPartVisibility();
}

function initTrainingRequestHistoryPage() {
    document.getElementById('refreshTrainingRequestHistoryBtn')?.addEventListener('click', loadTrainingRequestHistory);
    loadTrainingRequestHistory();
}

function initTrainingRequestApprovalPage() {
    document.getElementById('training-request-pending-tab')?.addEventListener('shown.bs.tab', loadTrainingRequestPendingApprovals);
    document.getElementById('training-request-history-tab')?.addEventListener('shown.bs.tab', loadTrainingRequestApprovalHistory);
    document.getElementById('trainingRequestApprovalForm')?.addEventListener('submit', submitTrainingRequestApproval);
    document.getElementById('trainingRequestApprovalHistoryBody')?.addEventListener('click', event => {
        const button = event.target.closest('.reviewer-cancel-request-button');
        if (!button) return;
        reviewerCancelApprovedTrainingRequest(Number(button.dataset.requestId), button.dataset.employeeName || '-', button.dataset.courseName || '-');
    });
    loadTrainingRequestPendingApprovals();
}

async function submitTrainingRequest(event) {
    event.preventDefault();
    const form = event.target;
    if (!document.getElementById('activityTypeSelect')?.value) {
        Swal.fire('เลือกประเภทกิจกรรม', 'กรุณาเลือกประเภทกิจกรรมก่อนส่งคำขอ', 'warning');
        return;
    }
    const formData = new FormData(form);
    formData.set('action', 'create');

    try {
        const response = await fetch('api/training_request_api.php', {
            method: 'POST',
            body: formData,
        });
        const res = await response.json();
        if (res.status !== 'success') throw new Error(res.message || 'Save failed');
        Swal.fire('สำเร็จ', res.message, 'success');
        form.reset();
        selectTrainingActivityType('');
        updateTrainingDayPartVisibility();
    } catch (err) {
        Swal.fire('ผิดพลาด', err.message, 'error');
    }
}

async function loadTrainingRequestHistory() {
    const tbody = document.getElementById('trainingRequestHistoryBody');
    if (!tbody) return;

    resetTrainingRequestDataTable('trainingRequestHistoryTable');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">กำลังโหลด...</td></tr>';
    try {
        const response = await fetch('api/training_request_api.php?action=my_requests');
        const res = await response.json();
        if (res.status !== 'success') throw new Error(res.message || 'Load failed');
        if (!res.data.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">ยังไม่มีคำขอกิจกรรม</td></tr>';
            return;
        }
        tbody.innerHTML = res.data.map(item => {
            const proxyHtml = renderProxyCreatorLine(item);
            return `
            <tr>
                <td>${formatThaiDate(item.created_at)}</td>
                  <td>
                      <div class="fw-semibold">${escapeHtml(item.course_name || '-')}</div>
                      <div class="small text-muted">${escapeHtml(item.activity_type_name || 'กิจกรรม')}</div>
                      ${proxyHtml}
                  </td>
                  <td>${formatTrainingRequestDateRangeWithParts(item)}</td>
                  <td>${escapeHtml(item.location || '-')}</td>
                <td><div class="request-status-actions">${renderTrainingRequestStatus(item.status)}${renderTrainingRequestCancellation(item)}</div></td>
                <td><small class="text-muted">${escapeHtml(item.rejection_reason || item.objective || '-')}</small>${renderTrainingRequestAttachment(item)}</td>
            </tr>
        `;
        }).join('');
        initTrainingRequestDataTable('trainingRequestHistoryTable', [[0, 'desc']], [5]);
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">โหลดข้อมูลไม่สำเร็จ</td></tr>';
    }
}

async function loadTrainingRequestPendingApprovals() {
    const tbody = document.getElementById('trainingRequestPendingBody');
    if (!tbody) return;

    resetTrainingRequestDataTable('trainingRequestPendingTable');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">กำลังโหลด...</td></tr>';
    try {
        const response = await fetch('api/training_request_api.php?action=pending');
        const res = await response.json();
        if (res.status !== 'success') throw new Error(res.message || 'Load failed');
        if (!res.data.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">ไม่มีรายการรออนุมัติ</td></tr>';
            return;
        }
        tbody.innerHTML = res.data.map(item => `
            <tr>
                <td>${renderTrainingRequestEmployeeCell(item, renderTrainingRequestStatus(item.status))}</td>
                <td><div class="fw-semibold">${escapeHtml(item.course_name || '-')}</div><div class="small text-muted">${escapeHtml(item.activity_type_name || 'กิจกรรม')}</div>${item.cancellation_reason ? `<div class="small text-danger">เหตุผลขอยกเลิก: ${escapeHtml(item.cancellation_reason)}</div>` : ''}</td>
                <td>${formatTrainingRequestDateRangeWithParts(item)}</td>
                <td>
                    <div>${escapeHtml(item.location || '-')}</div>
                    <div class="small text-muted mt-1">${escapeHtml(item.objective || '-')}</div>
                    ${renderTrainingRequestAttachment(item)}
                </td>
                <td>
                    <button class="btn btn-sm btn-success me-1" onclick="openTrainingRequestActionModal(${item.id}, 'approve', '${escapeAttr(item.employee_name || '')}', '${escapeAttr(item.status)}')">
                        <i class="fas fa-check"></i> ${item.status === 'pending_cancel_hr' ? 'อนุมัติยกเลิก' : 'อนุมัติ'}
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="openTrainingRequestActionModal(${item.id}, 'reject', '${escapeAttr(item.employee_name || '')}', '${escapeAttr(item.status)}')">
                        <i class="fas fa-times"></i> ${item.status === 'pending_cancel_hr' ? 'ไม่อนุมัติยกเลิก' : 'ไม่'}
                    </button>
                </td>
            </tr>
        `).join('');
        initTrainingRequestDataTable('trainingRequestPendingTable', [[2, 'asc']], [4]);
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">โหลดข้อมูลไม่สำเร็จ</td></tr>';
    }
}

function renderTrainingRequestEmployeeCell(item, footerHtml = '') {
    return `
        <div class="d-flex align-items-center">
            ${renderEmployeeAvatar(item.employee_profile_img_url)}
            <div>
                <div class="fw-bold">${escapeHtml(item.employee_name || '-')}</div>
                <small class="text-muted">${escapeHtml(item.employee_code || '')}</small>
                ${footerHtml ? `<div class="mt-1">${footerHtml}</div>` : ''}
            </div>
        </div>
    `;
}

async function loadTrainingRequestApprovalHistory() {
    const tbody = document.getElementById('trainingRequestApprovalHistoryBody');
    if (!tbody) return;

    resetTrainingRequestDataTable('trainingRequestApprovalHistoryTable');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">กำลังโหลด...</td></tr>';
    try {
        const response = await fetch('api/training_request_api.php?action=history');
        const res = await response.json();
        if (res.status !== 'success') throw new Error(res.message || 'Load failed');
        if (!res.data.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">ยังไม่มีประวัติ</td></tr>';
            return;
        }
        tbody.innerHTML = res.data.map(item => {
            const proxyHtml = renderProxyCreatorLine(item);
            const action = item.can_reviewer_cancel
                ? `<button type="button" class="btn btn-outline-danger reviewer-cancel-request-button" data-request-id="${Number(item.id)}" data-employee-name="${escapeAttr(item.employee_name || '-')}" data-course-name="${escapeAttr(item.course_name || '-')}">ยกเลิกรายการ</button>`
                : '-';
            return `
            <tr>
                <td>${item.approval_date ? formatThaiDate(item.approval_date) : '-'}</td>
                <td>${escapeHtml(item.employee_name || '-')}${proxyHtml}</td>
                <td>${escapeHtml(item.course_name || '-')}<div class="small text-muted">${escapeHtml(item.activity_type_name || 'กิจกรรม')}</div></td>
                <td>${formatTrainingRequestDateRangeWithParts(item)}</td>
                <td>${renderTrainingRequestStatus(item.status)}</td>
                <td>${renderTrainingReviewerCancellationAudit(item)}</td>
                <td>${action}</td>
            </tr>
        `;
        }).join('');
        initTrainingRequestDataTable('trainingRequestApprovalHistoryTable', [[0, 'desc']], [6]);
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">โหลดข้อมูลไม่สำเร็จ</td></tr>';
    }
}

function renderTrainingReviewerCancellationAudit(item) {
    const reason = escapeHtml(item.cancellation_reason || item.rejection_reason || '-');
    if (item.status !== 'cancelled' || (!item.cancelled_by_name && !item.cancelled_by_role && !item.cancelled_at)) {
        return `<small class="text-muted">${reason}</small>`;
    }
    const actor = escapeHtml(item.cancelled_by_name || item.cancelled_by_role || 'HR/Admin');
    const role = item.cancelled_by_name && item.cancelled_by_role ? ` (${escapeHtml(item.cancelled_by_role)})` : '';
    const time = item.cancelled_at ? `${formatThaiDate(item.cancelled_at)} ${escapeHtml(String(item.cancelled_at).slice(11, 16))} น.` : '';
    return `<div class="small text-danger">เหตุผลยกเลิก: ${reason}</div><div class="small text-muted">ยกเลิกโดย ${actor}${role}${time ? `, ${time}` : ''}</div>`;
}

window.reviewerCancelApprovedTrainingRequest = async function(requestId, employeeName, courseName) {
    const result = await Swal.fire({
        title: 'ยกเลิกรายการอบรม/กิจกรรม?',
        text: `${courseName} ของ ${employeeName}`,
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
    const response = await fetch('api/training_request_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'reviewer_cancel', request_id: Number(requestId), cancellation_reason: String(result.value || '').trim() }),
    });
    const payload = await response.json();
    await Swal.fire(payload.status === 'success' ? 'สำเร็จ' : 'ไม่สำเร็จ', payload.message || '', payload.status === 'success' ? 'success' : 'error');
    if (payload.status === 'success') await loadTrainingRequestApprovalHistory();
};

function renderProxyCreatorLine(item) {
    if (!item || item.created_via !== 'admin_proxy') return '';
    const name = item.proxy_creator_name || item.created_by_role || '';
    return `<div class="small text-muted mt-1">สร้างโดย HR/Admin${name ? `: ${escapeHtml(name)}` : ''}</div>`;
}

function resetTrainingRequestDataTable(tableId) {
    const selector = `#${tableId}`;
    if (trainingRequestDataTables[tableId]) {
        trainingRequestDataTables[tableId].destroy();
        delete trainingRequestDataTables[tableId];
    } else if (window.jQuery && jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable(selector)) {
        jQuery(selector).DataTable().destroy();
    }
}

function initTrainingRequestDataTable(tableId, order = [[0, 'desc']], unsortableTargets = []) {
    if (!window.jQuery || !jQuery.fn.DataTable || !document.getElementById(tableId)) {
        return;
    }

    const options = {
        language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json' },
        pageLength: 10,
        order,
    };
    if (unsortableTargets.length) {
        options.columnDefs = [{ orderable: false, targets: unsortableTargets }];
    }

    trainingRequestDataTables[tableId] = jQuery(`#${tableId}`).DataTable(options);
}

window.openTrainingRequestActionModal = function(id, action, name, status = '') {
    const modal = new bootstrap.Modal(document.getElementById('trainingRequestActionModal'));
    const isApprove = action === 'approve';
    const isCancellation = status === 'pending_cancel_hr';
    document.getElementById('trainingRequestId').value = id;
    document.getElementById('trainingRequestActionType').value = action;
    document.getElementById('trainingRequestActionTitle').textContent = isCancellation ? (isApprove ? 'ยืนยันอนุมัติยกเลิก' : 'ยืนยันไม่อนุมัติยกเลิก') : (isApprove ? 'ยืนยันการอนุมัติ' : 'ยืนยันการปฏิเสธ');
    document.getElementById('trainingRequestActionTitle').className = `modal-title ${isApprove ? 'text-success' : 'text-danger'}`;
    document.getElementById('trainingRequestActionMessage').innerHTML = `ต้องการ${isApprove ? 'อนุมัติ' : 'ไม่อนุมัติ'}คำขอกิจกรรมของ <strong>${escapeHtml(name)}</strong> ใช่หรือไม่?`;
    document.getElementById('trainingRequestConfirmBtn').className = `btn ${isApprove ? 'btn-success' : 'btn-danger'}`;
    document.getElementById('trainingRequestConfirmBtn').textContent = isApprove ? 'ยืนยันอนุมัติ' : 'ยืนยันไม่อนุมัติ';
    document.getElementById('trainingRequestRejectReasonWrap').style.display = isApprove ? 'none' : 'block';
    document.getElementById('trainingRequestRejectReason').required = !isApprove;
    document.getElementById('trainingRequestRejectReason').value = '';
    modal.show();
};

async function submitTrainingRequestApproval(event) {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(event.target).entries());
    data.action = data.action_type;

    try {
        const response = await fetch('api/training_request_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        });
        const res = await response.json();
        if (res.status !== 'success') throw new Error(res.message || 'Save failed');
        Swal.fire('สำเร็จ', res.message, 'success');
        bootstrap.Modal.getInstance(document.getElementById('trainingRequestActionModal')).hide();
        loadTrainingRequestPendingApprovals();
    } catch (err) {
        Swal.fire('ผิดพลาด', err.message, 'error');
    }
}

function renderTrainingRequestStatus(status) {
    const map = {
        pending: ['รอหัวหน้างานอนุมัติ', 'warning text-dark'],
        pending_manager: ['รอหัวหน้างานอนุมัติ', 'warning text-dark'],
        pending_hr: ['รอ HR อนุมัติ', 'info text-dark'],
        pending_cancel_hr: ['รอ HR/Admin อนุมัติยกเลิก', 'warning text-dark'],
        approved: ['อนุมัติแล้ว', 'success'],
        rejected: ['ไม่อนุมัติ', 'danger'],
        cancelled: ['ยกเลิก', 'secondary'],
    };
    const item = map[status] || [status || '-', 'secondary'];
    return `<span class="badge bg-${item[1]}">${item[0]}</span>`;
}

function renderTrainingRequestCancellation(item) {
    const reason = item.cancellation_reason ? `<div class="request-cancellation-reason small text-danger">เหตุผลขอยกเลิก: ${escapeHtml(item.cancellation_reason)}</div>` : '';
    if (!['pending', 'pending_manager', 'pending_hr', 'approved'].includes(item.status)) return reason;
    const label = item.status === 'approved' ? 'ขอยกเลิก' : 'ยกเลิก';
    const action = `<button type="button" class="btn btn-sm btn-outline-danger request-cancel-button" onclick="cancelTrainingRequest(${Number(item.id)}, '${escapeAttr(item.status)}')">${label}</button>`;
    return `${action}${reason}`;
}

window.cancelTrainingRequest = async function (requestId, status) {
    const result = await Swal.fire({ title: status === 'approved' ? 'ขอยกเลิกรายการที่อนุมัติแล้ว?' : 'ยืนยันการยกเลิก?', text: status === 'approved' ? 'คำขอนี้จะถูกส่งให้ HR/Admin อนุมัติ' : '', input: 'textarea', inputLabel: 'เหตุผลการยกเลิก', inputValidator: value => String(value || '').trim() ? undefined : 'กรุณาระบุเหตุผลการยกเลิก', showCancelButton: true, confirmButtonText: 'ยืนยัน', cancelButtonText: 'ไม่' });
    if (!result.isConfirmed) return;
    const response = await fetch('api/training_request_api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'cancel', request_id: requestId, cancellation_reason: String(result.value || '').trim() }) });
    const payload = await response.json();
    await Swal.fire(payload.status === 'success' ? 'สำเร็จ' : 'ไม่สำเร็จ', payload.message || '', payload.status === 'success' ? 'success' : 'error');
    if (payload.status === 'success') loadMyTrainingRequests();
};

function formatTrainingRequestDateRange(item) {
    const start = item.start_date ? formatThaiDate(item.start_date) : '-';
    const end = item.end_date ? formatThaiDate(item.end_date) : '-';
    return start === end ? start : `${start} - ${end}`;
}

function formatTrainingRequestDateRangeWithParts(item) {
    const range = formatTrainingRequestDateRange(item);
    const startPart = trainingRequestDayPartLabel(item.start_day_part || 'full');
    const endPart = trainingRequestDayPartLabel(item.end_day_part || 'full');
    if (!item.start_date || !item.end_date) return range;
    if (item.start_date === item.end_date) {
        return startPart === endPart ? `${range} (${startPart})` : `${range} (${startPart}-${endPart})`;
    }
    return `${range}<div class="small text-muted">${startPart} ถึง ${endPart}</div>`;
}

function trainingRequestDayPartLabel(value) {
    if (value === 'morning') return 'ครึ่งวันเช้า';
    if (value === 'afternoon') return 'ครึ่งวันบ่าย';
    return 'เต็มวัน';
}

async function loadTrainingActivityTypes() {
    const input = document.getElementById('activityTypeSelect');
    if (!input) return;
    try {
        const response = await fetch('api/training_request_api.php?action=activity_types');
        const res = await response.json();
        if (res.status !== 'success') throw new Error(res.message || 'Load failed');
        renderTrainingActivityTypeButtons(res.data || []);
    } catch (err) {
        const grid = document.getElementById('activityTypeButtonGrid');
        if (grid) {
            grid.innerHTML = '<div class="activity-type-loading text-danger">โหลดประเภทกิจกรรมไม่สำเร็จ</div>';
        }
    }
}

function renderTrainingActivityTypeButtons(types) {
    const grid = document.getElementById('activityTypeButtonGrid');
    if (!grid) return;
    if (!types.length) {
        grid.innerHTML = '<div class="activity-type-loading text-muted">ยังไม่มีประเภทกิจกรรมที่เปิดใช้งาน</div>';
        return;
    }

    grid.innerHTML = types.map((item) => {
        const presentation = getTrainingActivityTypePresentation(item.type_name || '');
        return `
            <button type="button" class="activity-type-card" data-activity-type-id="${escapeAttr(item.id)}" role="radio" aria-checked="false">
                <span class="activity-type-icon text-${presentation.color}">
                    <i class="fas ${presentation.icon}"></i>
                </span>
                <span class="activity-type-name">${escapeHtml(item.type_name || '-')}</span>
            </button>
        `;
    }).join('');

    grid.querySelectorAll('.activity-type-card').forEach((button) => {
        button.addEventListener('click', () => selectTrainingActivityType(button.dataset.activityTypeId));
    });
}

function selectTrainingActivityType(selectedId) {
    const input = document.getElementById('activityTypeSelect');
    const grid = document.getElementById('activityTypeButtonGrid');
    if (!input || !grid) return;

    input.value = selectedId || '';
    grid.querySelectorAll('.activity-type-card').forEach((button) => {
        const isSelected = button.dataset.activityTypeId === String(selectedId);
        button.classList.toggle('is-selected', isSelected);
        button.setAttribute('aria-checked', isSelected ? 'true' : 'false');
    });
}

function getTrainingActivityTypePresentation(typeName) {
    const name = String(typeName || '').toLowerCase();
    const rules = [
        { match: ['อบรม', 'training', 'ฝึก'], icon: 'fa-graduation-cap', color: 'primary' },
        { match: ['สัมมนา', 'seminar', 'ประชุม'], icon: 'fa-users-viewfinder', color: 'info' },
        { match: ['บุญ', 'ศาสนา'], icon: 'fa-hands-praying', color: 'warning' },
        { match: ['อาสา', 'ช่วย', 'สังคม'], icon: 'fa-hand-holding-heart', color: 'success' },
    ];
    const found = rules.find((rule) => rule.match.some((keyword) => name.includes(keyword)));
    return found || { icon: 'fa-people-arrows', color: 'danger' };
}

function updateTrainingDayPartVisibility() {
    const form = document.getElementById('trainingRequestForm');
    if (!form) return;
    const startDate = form.querySelector('[name="start_date"]')?.value || '';
    const endDate = form.querySelector('[name="end_date"]')?.value || '';
    const isMultiDay = Boolean(startDate && endDate && startDate !== endDate);
    const startPart = form.querySelector('[name="start_day_part"]');

    form.querySelectorAll('.training-day-part-field').forEach((field) => {
        field.classList.toggle('d-none', isMultiDay);
    });
    if (isMultiDay && startPart) {
        startPart.value = 'full';
    }
    syncTrainingEndDayPart();
}

function syncTrainingEndDayPart() {
    const form = document.getElementById('trainingRequestForm');
    if (!form) return;
    const startPart = form.querySelector('[name="start_day_part"]');
    const endPart = form.querySelector('[name="end_day_part"]');
    if (!startPart || !endPart) return;
    endPart.value = startPart.value || 'full';
}

function renderTrainingRequestAttachment(item) {
    if (!item.attachment_path) return '';
    return `<div class="mt-1"><a href="${escapeAttr(item.attachment_path)}" target="_blank" class="small"><i class="fas fa-paperclip"></i> เปิดไฟล์แนบ</a></div>`;
}
