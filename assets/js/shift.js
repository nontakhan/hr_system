/*
 * Logic สำหรับจัดการกะการทำงาน (shifts.php)
 */

document.addEventListener('DOMContentLoaded', () => {
    const shiftTable = document.getElementById('shiftTable');
    if (shiftTable) {
        loadShifts();

        // Modal Action
        const modal = document.getElementById('shiftModal');
        const form = document.getElementById('shiftForm');
        
        modal.addEventListener('show.bs.modal', (e) => {
            const btn = e.relatedTarget;
            const action = btn.getAttribute('data-action');
            const title = document.getElementById('modalTitle');
            
            form.reset();
            // Reset checkboxes
            document.querySelectorAll('.day-check').forEach(cb => cb.checked = false);
            // Default Mon-Fri
            ['Mon','Tue','Wed','Thu','Fri'].forEach(d => {
                if(document.getElementById('day_'+d)) document.getElementById('day_'+d).checked = true;
            });

            if (action === 'create') {
                title.innerText = 'เพิ่มกะการทำงาน';
                document.getElementById('shift_id').value = '';
            } else {
                title.innerText = 'แก้ไขกะการทำงาน';
                const data = JSON.parse(btn.getAttribute('data-info'));
                
                document.getElementById('shift_id').value = data.id;
                form.shift_name.value = data.shift_name;
                form.start_time.value = data.start_time; // Format HH:mm:ss
                form.end_time.value = data.end_time;
                form.late_tolerance_mins.value = data.late_tolerance_mins;

                // Set days
                const days = data.work_days.split(',');
                document.querySelectorAll('.day-check').forEach(cb => cb.checked = false);
                days.forEach(d => {
                    const el = document.getElementById('day_' + d.trim());
                    if (el) el.checked = true;
                });
            }
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // Collect Days
            const days = [];
            document.querySelectorAll('.day-check:checked').forEach(cb => days.push(cb.value));
            
            const data = {
                id: document.getElementById('shift_id').value,
                shift_name: form.shift_name.value,
                start_time: form.start_time.value,
                end_time: form.end_time.value,
                late_tolerance_mins: form.late_tolerance_mins.value,
                work_days: days.join(','),
                action: document.getElementById('shift_id').value ? 'update_shift' : 'create_shift'
            };

            try {
                const response = await fetch('api/shift_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                const res = await response.json();
                if (res.status === 'success') {
                    Swal.fire('สำเร็จ', res.message, 'success');
                    bootstrap.Modal.getInstance(modal).hide();
                    loadShifts();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            } catch (err) { Swal.fire('Error', err.message, 'error'); }
        });

        // Delete
        shiftTable.addEventListener('click', (e) => {
            if (e.target.closest('.btn-delete')) {
                const id = e.target.closest('.btn-delete').getAttribute('data-id');
                handleDeleteShift(id);
            }
        });
    }
});

async function loadShifts() {
    const tbody = document.getElementById('shiftTableBody');
    try {
        const response = await fetch('api/shift_api.php');
        const res = await response.json();
        
        if (res.status === 'success') {
            tbody.innerHTML = '';
            if (res.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">ยังไม่มีข้อมูล</td></tr>`;
                return;
            }
            res.data.forEach(item => {
                const info = JSON.stringify(item).replace(/"/g, '&quot;');
                tbody.innerHTML += `
                    <tr>
                        <td><strong>${item.shift_name}</strong></td>
                        <td>${item.start_time.substring(0,5)}</td>
                        <td>${item.end_time.substring(0,5)}</td>
                        <td>${item.late_tolerance_mins} นาที</td>
                        <td><span class="badge bg-secondary">${item.work_days}</span></td>
                        <td>
                            <button class="btn btn-warning btn-sm me-1" 
                                data-bs-toggle="modal" data-bs-target="#shiftModal" data-action="edit" data-info="${info}">
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                            <button class="btn btn-danger btn-sm btn-delete" data-id="${item.id}">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>`;
            });
        }
    } catch (err) { console.error(err); }
}

function handleDeleteShift(id) {
    Swal.fire({
        title: 'ยืนยันการลบ?', text: 'ข้อมูลจะถูกลบถาวร', icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'ลบ'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await fetch('api/shift_api.php', {
                    method: 'DELETE',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: id})
                });
                const res = await response.json();
                if (res.status === 'success') {
                    Swal.fire('สำเร็จ', 'ลบข้อมูลเรียบร้อย', 'success');
                    loadShifts();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            } catch (err) { Swal.fire('Error', err.message, 'error'); }
        }
    });
}