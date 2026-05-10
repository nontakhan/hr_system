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

    // Modal Action Logic
    const approvalForm = document.getElementById('approvalForm');
    if (approvalForm) {
        approvalForm.addEventListener('submit', handleSubmitApproval);
    }
});

// โหลดรายการรออนุมัติ
async function loadPendingLeaves() {
    const tbody = document.getElementById('pendingTableBody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4">กำลังโหลด...</td></tr>';

    try {
        const response = await fetch('api/leave_approval_api.php?type=pending');
        const res = await response.json();

        if (res.status === 'success') {
            if (res.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">ไม่มีรายการรออนุมัติ</td></tr>';
                return;
            }

            tbody.innerHTML = res.data.map(item => {
                const sDate = new Date(item.start_date).toLocaleDateString('th-TH');
                const eDate = new Date(item.end_date).toLocaleDateString('th-TH');
                const itemId = Number.parseInt(item.id, 10) || 0;
                const firstName = escapeHtml(item.first_name_th);
                const lastName = escapeHtml(item.last_name_th);
                const employeeCode = escapeHtml(item.employee_code);
                const typeName = escapeHtml(item.type_name);
                const reason = escapeHtml(item.reason);
                const totalDays = Number.parseFloat(item.total_days) || 0;
                item.file_path = escapeAttr(safeUploadPath(item.file_path));
                item.profile_img_url = escapeAttr(safeUploadPath(item.profile_img_url, 'assets/img/user.png'));
                item.id = itemId;
                item.first_name_th = firstName;
                item.last_name_th = lastName;
                item.employee_code = employeeCode;
                item.type_name = typeName;
                item.reason = reason;
                item.total_days = totalDays;
                
                // รูปไฟล์แนบ
                let fileLink = '';
                if (item.file_path) {
                    fileLink = `<a href="${item.file_path}" target="_blank" class="btn btn-sm btn-outline-info ms-1" title="ดูเอกสารแนบ"><i class="fas fa-paperclip"></i></a>`;
                }

                // รูปโปรไฟล์
                const img = item.profile_img_url;

                return `
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="${img}" class="rounded-circle me-2" style="width:35px;height:35px;object-fit:cover;">
                                <div>
                                    <div class="fw-bold">${item.first_name_th} ${item.last_name_th}</div>
                                    <small class="text-muted">${item.employee_code}</small>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge bg-primary bg-opacity-10 text-primary">${item.type_name}</span></td>
                        <td>${sDate} - ${eDate}</td>
                        <td><strong>${parseFloat(item.total_days)}</strong> วัน</td>
                        <td>
                            <small class="d-block text-muted text-truncate" style="max-width: 200px;">${item.reason}</small>
                            ${fileLink}
                        </td>
                        <td>
                            <button class="btn btn-sm btn-success me-1" data-id="${item.id}" data-action="approve" data-name="${escapeAttr(item.first_name_th)}" onclick="openActionModalFromButton(this)">
                                <i class="fas fa-check"></i> อนุมัติ
                            </button>
                            <button class="btn btn-sm btn-danger" data-id="${item.id}" data-action="reject" data-name="${escapeAttr(item.first_name_th)}" onclick="openActionModalFromButton(this)">
                                <i class="fas fa-times"></i> ไม่
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        }
    } catch (err) { console.error(err); }
}

// โหลดประวัติการอนุมัติ
async function loadHistoryLeaves() {
    const tbody = document.getElementById('historyTableBody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4">กำลังโหลด...</td></tr>';

    try {
        const response = await fetch('api/leave_approval_api.php?type=history');
        const res = await response.json();

        if (res.status === 'success') {
            if (res.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">ไม่มีประวัติ</td></tr>';
                return;
            }

            tbody.innerHTML = res.data.map(item => {
                const appDate = item.approval_date ? new Date(item.approval_date).toLocaleDateString('th-TH') : '-';
                const sDate = new Date(item.start_date).toLocaleDateString('th-TH');
                item.first_name_th = escapeHtml(item.first_name_th);
                item.last_name_th = escapeHtml(item.last_name_th);
                item.type_name = escapeHtml(item.type_name);
                item.rejection_reason = escapeHtml(item.rejection_reason || '-');
                item.total_days = Number.parseFloat(item.total_days) || 0;
                
                let statusBadge = item.status === 'approved' 
                    ? '<span class="badge bg-success">อนุมัติแล้ว</span>' 
                    : '<span class="badge bg-danger">ไม่อนุมัติ</span>';

                return `
                    <tr>
                        <td>${appDate}</td>
                        <td>${item.first_name_th} ${item.last_name_th}</td>
                        <td>${item.type_name}</td>
                        <td>${sDate} (${parseFloat(item.total_days)} วัน)</td>
                        <td>${statusBadge}</td>
                        <td><small class="text-muted">${item.rejection_reason || '-'}</small></td>
                    </tr>
                `;
            }).join('');
        }
    } catch (err) { console.error(err); }
}

// เปิด Modal ยืนยัน
window.openActionModal = function(id, type, name) {
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

    if (type === 'approve') {
        title.innerText = 'ยืนยันการอนุมัติ';
        title.className = 'modal-title text-success';
        msg.innerHTML = `คุณต้องการอนุมัติการลาของ <strong>${name}</strong> ใช่หรือไม่?`;
        confirmBtn.className = 'btn btn-success';
        confirmBtn.innerText = 'ยืนยันอนุมัติ';
        reasonDiv.style.display = 'none';
        reasonInput.required = false;
    } else {
        title.innerText = 'ยืนยันการปฏิเสธ';
        title.className = 'modal-title text-danger';
        msg.innerHTML = `คุณต้องการ <strong>ไม่อนุมัติ</strong> การลาของ <strong>${name}</strong> ใช่หรือไม่?`;
        confirmBtn.className = 'btn btn-danger';
        confirmBtn.innerText = 'ยืนยันไม่อนุมัติ';
        reasonDiv.style.display = 'block';
        reasonInput.required = true;
    }

    modal.show();
}

window.openActionModalFromButton = function(button) {
    openActionModal(
        Number.parseInt(button.dataset.id, 10) || 0,
        button.dataset.action,
        button.dataset.name || ''
    );
}

// Submit การอนุมัติ/ไม่อนุมัติ
async function handleSubmitApproval(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const action = formData.get('action_type'); // approve / reject
    const data = Object.fromEntries(formData.entries());
    
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
