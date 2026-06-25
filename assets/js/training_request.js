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

function initTrainingRequestPage() {
    document.getElementById('trainingRequestForm').addEventListener('submit', submitTrainingRequest);
}

function initTrainingRequestHistoryPage() {
    document.getElementById('refreshTrainingRequestHistoryBtn')?.addEventListener('click', loadTrainingRequestHistory);
    loadTrainingRequestHistory();
}

function initTrainingRequestApprovalPage() {
    document.getElementById('training-request-pending-tab')?.addEventListener('shown.bs.tab', loadTrainingRequestPendingApprovals);
    document.getElementById('training-request-history-tab')?.addEventListener('shown.bs.tab', loadTrainingRequestApprovalHistory);
    document.getElementById('trainingRequestApprovalForm')?.addEventListener('submit', submitTrainingRequestApproval);
    loadTrainingRequestPendingApprovals();
}

async function submitTrainingRequest(event) {
    event.preventDefault();
    const form = event.target;
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
    } catch (err) {
        Swal.fire('ผิดพลาด', err.message, 'error');
    }
}

async function loadTrainingRequestHistory() {
    const tbody = document.getElementById('trainingRequestHistoryBody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">กำลังโหลด...</td></tr>';
    try {
        const response = await fetch('api/training_request_api.php?action=my_requests');
        const res = await response.json();
        if (res.status !== 'success') throw new Error(res.message || 'Load failed');
        if (!res.data.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">ยังไม่มีคำขออบรม</td></tr>';
            return;
        }
        tbody.innerHTML = res.data.map(item => `
            <tr>
                <td>${formatThaiDate(item.created_at)}</td>
                <td>
                    <div class="fw-semibold">${escapeHtml(item.course_name || '-')}</div>
                    <small class="text-muted">${escapeHtml(item.training_type || '-')}</small>
                </td>
                <td>${formatTrainingRequestDateRange(item)}</td>
                <td>${escapeHtml(item.provider || '-')}<br><small class="text-muted">${escapeHtml(item.location || '-')}</small></td>
                <td>${renderTrainingRequestStatus(item.status)}</td>
                <td><small class="text-muted">${escapeHtml(item.rejection_reason || item.objective || '-')}</small>${renderTrainingRequestAttachment(item)}</td>
            </tr>
        `).join('');
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">โหลดข้อมูลไม่สำเร็จ</td></tr>';
    }
}

async function loadTrainingRequestPendingApprovals() {
    const tbody = document.getElementById('trainingRequestPendingBody');
    if (!tbody) return;

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
                <td>${escapeHtml(item.employee_name || '-')}<br><small class="text-muted">${escapeHtml(item.employee_code || '')}</small><div class="mt-1">${renderTrainingRequestStatus(item.status)}</div></td>
                <td><div class="fw-semibold">${escapeHtml(item.course_name || '-')}</div><small class="text-muted">${escapeHtml(item.training_type || '-')}</small></td>
                <td>${formatTrainingRequestDateRange(item)}</td>
                <td>
                    <div>${escapeHtml(item.provider || '-')}</div>
                    <small class="text-muted">${escapeHtml(item.location || '-')}</small>
                    <div class="small text-muted mt-1">${escapeHtml(item.objective || '-')}</div>
                    ${renderTrainingRequestAttachment(item)}
                </td>
                <td>
                    <button class="btn btn-sm btn-success me-1" onclick="openTrainingRequestActionModal(${item.id}, 'approve', '${escapeAttr(item.employee_name || '')}')">
                        <i class="fas fa-check"></i> อนุมัติ
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="openTrainingRequestActionModal(${item.id}, 'reject', '${escapeAttr(item.employee_name || '')}')">
                        <i class="fas fa-times"></i> ไม่
                    </button>
                </td>
            </tr>
        `).join('');
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">โหลดข้อมูลไม่สำเร็จ</td></tr>';
    }
}

async function loadTrainingRequestApprovalHistory() {
    const tbody = document.getElementById('trainingRequestApprovalHistoryBody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">กำลังโหลด...</td></tr>';
    try {
        const response = await fetch('api/training_request_api.php?action=history');
        const res = await response.json();
        if (res.status !== 'success') throw new Error(res.message || 'Load failed');
        if (!res.data.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">ยังไม่มีประวัติ</td></tr>';
            return;
        }
        tbody.innerHTML = res.data.map(item => `
            <tr>
                <td>${item.approval_date ? formatThaiDate(item.approval_date) : '-'}</td>
                <td>${escapeHtml(item.employee_name || '-')}</td>
                <td>${escapeHtml(item.course_name || '-')}</td>
                <td>${formatTrainingRequestDateRange(item)}</td>
                <td>${renderTrainingRequestStatus(item.status)}</td>
                <td><small class="text-muted">${escapeHtml(item.rejection_reason || '-')}</small></td>
            </tr>
        `).join('');
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">โหลดข้อมูลไม่สำเร็จ</td></tr>';
    }
}

window.openTrainingRequestActionModal = function(id, action, name) {
    const modal = new bootstrap.Modal(document.getElementById('trainingRequestActionModal'));
    const isApprove = action === 'approve';
    document.getElementById('trainingRequestId').value = id;
    document.getElementById('trainingRequestActionType').value = action;
    document.getElementById('trainingRequestActionTitle').textContent = isApprove ? 'ยืนยันการอนุมัติ' : 'ยืนยันการปฏิเสธ';
    document.getElementById('trainingRequestActionTitle').className = `modal-title ${isApprove ? 'text-success' : 'text-danger'}`;
    document.getElementById('trainingRequestActionMessage').innerHTML = `ต้องการ${isApprove ? 'อนุมัติ' : 'ไม่อนุมัติ'}คำขออบรมของ <strong>${escapeHtml(name)}</strong> ใช่หรือไม่?`;
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
        approved: ['อนุมัติแล้ว', 'success'],
        rejected: ['ไม่อนุมัติ', 'danger'],
        cancelled: ['ยกเลิก', 'secondary'],
    };
    const item = map[status] || [status || '-', 'secondary'];
    return `<span class="badge bg-${item[1]}">${item[0]}</span>`;
}

function formatTrainingRequestDateRange(item) {
    const start = item.start_date ? formatThaiDate(item.start_date) : '-';
    const end = item.end_date ? formatThaiDate(item.end_date) : '-';
    return start === end ? start : `${start} - ${end}`;
}

function renderTrainingRequestAttachment(item) {
    if (!item.attachment_path) return '';
    return `<div class="mt-1"><a href="${escapeAttr(item.attachment_path)}" target="_blank" class="small"><i class="fas fa-paperclip"></i> เปิดไฟล์แนบ</a></div>`;
}
