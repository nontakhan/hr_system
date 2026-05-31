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

async function loadMyLeaves() {
    const tbody = document.getElementById('myLeavesTableBody');
    
    try {
        const response = await fetch('api/leave_history_api.php');
        const res = await response.json();

        if (res.status === 'success') {
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
                const itemId = Number.parseInt(item.id, 10) || 0;
                const typeName = escapeHtml(item.type_name);
                const reason = escapeHtml(item.reason);
                
                // Badge สถานะ
                let statusBadge = '';
                let actionBtn = '';
                
                if (item.status === 'pending') {
                    statusBadge = '<span class="badge bg-warning text-dark">รออนุมัติ</span>';
                    // ปุ่มยกเลิก แสดงเฉพาะตอนรออนุมัติ
                    actionBtn = `<button class="btn btn-sm btn-outline-danger btn-cancel" data-id="${itemId}">
                                    <i class="fas fa-times"></i> ยกเลิก
                                 </button>`;
                } else if (item.status === 'approved') {
                    statusBadge = '<span class="badge bg-success">อนุมัติแล้ว</span>';
                } else if (item.status === 'rejected') {
                    statusBadge = '<span class="badge bg-danger">ไม่อนุมัติ</span>';
                } else if (item.status === 'cancelled') {
                    statusBadge = '<span class="badge bg-secondary">ยกเลิกแล้ว</span>';
                }

                tbody.innerHTML += `
                    <tr>
                        <td>${createdDate}</td>
                        <td><span class="fw-bold text-primary">${typeName}</span></td>
                        <td>${dateRange || `${startDate} - ${endDate}`}</td>
                        <td>${parseFloat(item.total_days)} วัน</td>
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
