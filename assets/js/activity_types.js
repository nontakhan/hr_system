document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('activityTypesTable');
    if (!table) return;

    const modal = document.getElementById('activityTypeModal');
    const form = document.getElementById('activityTypeForm');

    loadActivityTypes();

    modal?.addEventListener('show.bs.modal', (event) => {
        const action = event.relatedTarget?.getAttribute('data-action') || 'create';
        const title = document.getElementById('activityTypeModalTitle');
        form.reset();
        document.getElementById('activityTypeId').value = '';
        document.getElementById('activityTypeIsActive').checked = true;
        title.textContent = 'เพิ่มประเภทกิจกรรม';

        if (action === 'edit') {
            const data = JSON.parse(event.relatedTarget.getAttribute('data-info') || '{}');
            title.textContent = 'แก้ไขประเภทกิจกรรม';
            form.id.value = data.id || '';
            form.type_name.value = data.type_name || '';
            form.description.value = data.description || '';
            form.is_active.checked = Number.parseInt(data.is_active ?? 1, 10) === 1;
        }
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(form).entries());
        payload.action = 'save';
        payload.is_active = form.is_active.checked ? 1 : 0;

        try {
            const response = await fetch('api/activity_type_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const res = await response.json();
            if (res.status !== 'success') throw new Error(res.message || 'Save failed');
            Swal.fire('สำเร็จ', res.message, 'success');
            bootstrap.Modal.getInstance(modal).hide();
            loadActivityTypes();
        } catch (err) {
            Swal.fire('ผิดพลาด', err.message, 'error');
        }
    });

    table.addEventListener('click', (event) => {
        const deleteBtn = event.target.closest('.btn-activity-delete');
        if (deleteBtn) {
            deleteActivityType(Number.parseInt(deleteBtn.dataset.id, 10) || 0);
        }
    });
});

async function loadActivityTypes() {
    const tbody = document.getElementById('activityTypesTableBody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">กำลังโหลดข้อมูล...</td></tr>';
    try {
        const response = await fetch('api/activity_type_api.php?action=list');
        const res = await response.json();
        if (res.status !== 'success') throw new Error(res.message || 'Load failed');
        if (!res.data.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">ยังไม่มีประเภทกิจกรรม</td></tr>';
            return;
        }
        tbody.innerHTML = res.data.map((item) => {
            const data = escapeAttr(JSON.stringify(item));
            const active = Number.parseInt(item.is_active, 10) === 1;
            return `
                <tr>
                    <td>${escapeHtml(item.id)}</td>
                    <td><strong>${escapeHtml(item.type_name || '-')}</strong></td>
                    <td>${escapeHtml(item.description || '-')}</td>
                    <td>${active ? '<span class="badge bg-success">เปิดใช้งาน</span>' : '<span class="badge bg-secondary">ปิดใช้งาน</span>'}</td>
                    <td>
                        <button class="btn btn-sm btn-warning me-1" data-bs-toggle="modal" data-bs-target="#activityTypeModal" data-action="edit" data-info="${data}">
                            <i class="fas fa-pencil-alt"></i>
                        </button>
                        <button class="btn btn-sm btn-danger btn-activity-delete" data-id="${escapeAttr(item.id)}">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">${escapeHtml(err.message)}</td></tr>`;
    }
}

async function deleteActivityType(id) {
    if (id <= 0) return;
    const confirm = await Swal.fire({
        title: 'ยืนยันการลบ?',
        text: 'ลบได้เฉพาะประเภทกิจกรรมที่ยังไม่มีคำขอใช้งาน',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'ลบ',
    });
    if (!confirm.isConfirmed) return;

    try {
        const response = await fetch('api/activity_type_api.php?action=delete', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id }),
        });
        const res = await response.json();
        if (res.status !== 'success') throw new Error(res.message || 'Delete failed');
        Swal.fire('สำเร็จ', res.message, 'success');
        loadActivityTypes();
    } catch (err) {
        Swal.fire('ลบไม่ได้', err.message, 'error');
    }
}
