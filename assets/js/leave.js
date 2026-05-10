/*
 * Logic สำหรับระบบการลา (Leave System)
 */

document.addEventListener('DOMContentLoaded', () => {
    
    // 1. หน้าจัดการประเภทการลา (leave_types.php)
    const leaveTypesTable = document.getElementById('leaveTypesTable');
    if (leaveTypesTable) {
        loadLeaveTypes();

        // จัดการ Modal (Create/Edit)
        const modal = document.getElementById('leaveTypeModal');
        const form = document.getElementById('leaveTypeForm');
        const modalTitle = document.getElementById('modalTitle');

        // เมื่อเปิด Modal
        modal.addEventListener('show.bs.modal', (e) => {
            const btn = e.relatedTarget;
            const action = btn.getAttribute('data-action');
            
            if (action === 'create') {
                form.reset();
                document.getElementById('type_id').value = '';
                modalTitle.innerText = 'เพิ่มประเภทการลา';
            } else if (action === 'edit') {
                modalTitle.innerText = 'แก้ไขประเภทการลา';
                // ดึงข้อมูลจากปุ่มมาใส่ฟอร์ม (ใช้ Dataset ที่ฝังไว้ในปุ่ม)
                const data = JSON.parse(btn.getAttribute('data-info'));
                form.type_name.value = data.type_name;
                form.days_per_year.value = data.days_per_year;
                form.description.value = data.description || '';
                form.requires_file.checked = data.requires_file == 1;
                document.getElementById('type_id').value = data.id;
            }
        });

        // Submit Form
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('type_id').value;
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            // เพิ่ม checkbox ถ้าไม่ได้ติ๊ก (เพราะ FormData จะไม่ส่งค่าถ้าไม่ติ๊ก)
            data.requires_file = form.requires_file.checked ? 1 : 0;
            data.action = id ? 'update_type' : 'create_type';

            try {
                const response = await fetch('api/leave_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const res = await response.json();
                
                if (res.status === 'success') {
                    Swal.fire('สำเร็จ', res.message, 'success');
                    bootstrap.Modal.getInstance(modal).hide();
                    loadLeaveTypes();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            } catch (err) { Swal.fire('Error', err.message, 'error'); }
        });

        // Delete
        leaveTypesTable.addEventListener('click', (e) => {
            if (e.target.closest('.btn-delete')) {
                const id = e.target.closest('.btn-delete').getAttribute('data-id');
                handleDeleteLeaveType(id);
            }
        });
    }
});

async function loadLeaveTypes() {
    const tbody = document.getElementById('leaveTypesTableBody');
    try {
        const response = await fetch('api/leave_api.php?action=get_types');
        const res = await response.json();
        
        if (res.status === 'success') {
            tbody.innerHTML = '';
            if (res.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-3">ยังไม่มีข้อมูล</td></tr>`;
                return;
            }
            res.data.forEach(item => {
                // เก็บข้อมูลใส่ data-info เพื่อใช้ตอน Edit
                const jsonInfo = JSON.stringify(item);
                const itemId = Number.parseInt(item.id, 10) || 0;
                const typeName = escapeHtml(item.type_name);
                const description = escapeHtml(item.description || '-');
                const daysPerYear = Number.parseFloat(item.days_per_year) || 0;
                
                tbody.innerHTML += `
                    <tr>
                        <td>${itemId}</td>
                        <td>
                            <strong>${typeName}</strong>
                            <div class="small text-muted">${description}</div>
                        </td>
                        <td>${item.days_per_year} วัน</td>
                        <td>
                            ${item.requires_file == 1 ? '<span class="badge bg-warning text-dark"><i class="fas fa-file-medical"></i> ต้องมีใบรับรอง</span>' : '-'}
                        </td>
                        <td>
                            <button class="btn btn-sm btn-warning me-1" 
                                data-bs-toggle="modal" 
                                data-bs-target="#leaveTypeModal" 
                                data-action="edit" 
                                data-info="${escapeAttr(jsonInfo)}">
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                            <button class="btn btn-sm btn-danger btn-delete" data-id="${itemId}">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
        }
    } catch (err) { console.error(err); }
}

async function handleDeleteLeaveType(id) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: "ข้อมูลนี้จะถูกลบถาวร",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'ลบข้อมูล'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await fetch('api/leave_api.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                const res = await response.json();
                
                if (res.status === 'success') {
                    Swal.fire('สำเร็จ', 'ลบข้อมูลเรียบร้อย', 'success');
                    loadLeaveTypes();
                } else {
                    Swal.fire('ลบไม่ได้', res.message, 'error');
                }
            } catch (err) { Swal.fire('Error', err.message, 'error'); }
        }
    });
}
