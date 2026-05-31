document.addEventListener('DOMContentLoaded', () => {
    const holidayTable = document.getElementById('holidayTableBody');
    if (!holidayTable) return;

    loadCompanyHolidays();
    document.getElementById('holidayLoadBtn').addEventListener('click', loadCompanyHolidays);

    const modal = document.getElementById('holidayModal');
    const form = document.getElementById('holidayForm');
    modal.addEventListener('show.bs.modal', (e) => {
        const btn = e.relatedTarget;
        const action = btn.getAttribute('data-action');
        form.reset();
        document.getElementById('holidayId').value = '';

        if (action === 'edit') {
            const data = JSON.parse(btn.getAttribute('data-info'));
            document.getElementById('holidayModalTitle').textContent = 'แก้ไขวันหยุดพิเศษ';
            document.getElementById('holidayId').value = data.id;
            setThaiDateInputValue(document.getElementById('holidayDate'), data.holiday_date);
            document.getElementById('holidayName').value = data.holiday_name;
            document.getElementById('holidayNotes').value = data.notes || '';
        } else {
            document.getElementById('holidayModalTitle').textContent = 'เพิ่มวันหยุดพิเศษ';
        }
    });

    form.addEventListener('submit', saveCompanyHoliday);
    holidayTable.addEventListener('click', (e) => {
        const deleteBtn = e.target.closest('.btn-delete-holiday');
        if (deleteBtn) deleteCompanyHoliday(deleteBtn.dataset.id);
    });
});

async function loadCompanyHolidays() {
    const tbody = document.getElementById('holidayTableBody');
    const yearInput = document.getElementById('holidayYear').value || new Date().getFullYear();
    const year = normalizeGregorianYearInput(yearInput);
    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">กำลังโหลด...</td></tr>';

    try {
        const response = await fetch(`api/company_holiday_api.php?year=${encodeURIComponent(year)}`);
        const res = await response.json();
        if (res.status !== 'success') {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-danger">${res.message || 'โหลดข้อมูลไม่สำเร็จ'}</td></tr>`;
            return;
        }

        if (!res.data.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">ยังไม่มีวันหยุดพิเศษในปีนี้</td></tr>';
            return;
        }

        tbody.innerHTML = '';
        res.data.forEach(item => {
            const info = JSON.stringify(item).replace(/"/g, '&quot;');
            tbody.innerHTML += `
                <tr>
                    <td>${formatThaiDate(item.holiday_date, { month: 'long' })}</td>
                    <td><span class="badge bg-light text-dark border me-2">วันหยุด</span>${escapeHtml(item.holiday_name)}</td>
                    <td>${escapeHtml(item.notes || '-')}</td>
                    <td>
                        <button class="btn btn-warning btn-sm me-1" data-bs-toggle="modal" data-bs-target="#holidayModal" data-action="edit" data-info="${info}">
                            <i class="fas fa-pencil-alt"></i>
                        </button>
                        <button class="btn btn-danger btn-sm btn-delete-holiday" data-id="${item.id}">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                </tr>`;
        });
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-danger">${err.message}</td></tr>`;
    }
}

async function saveCompanyHoliday(e) {
    e.preventDefault();
    const id = document.getElementById('holidayId').value;
    const payload = {
        action: id ? 'update_holiday' : 'create_holiday',
        id,
        holiday_date: toGregorianDateInputValue(document.getElementById('holidayDate').value),
        holiday_name: document.getElementById('holidayName').value,
        notes: document.getElementById('holidayNotes').value,
    };

    try {
        const response = await fetch('api/company_holiday_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const res = await response.json();
        if (res.status !== 'success') {
            Swal.fire('Error', res.message || 'บันทึกไม่สำเร็จ', 'error');
            return;
        }

        Swal.fire('สำเร็จ', res.message, 'success');
        bootstrap.Modal.getInstance(document.getElementById('holidayModal')).hide();
        loadCompanyHolidays();
    } catch (err) {
        Swal.fire('Error', err.message, 'error');
    }
}

function deleteCompanyHoliday(id) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: 'วันหยุดนี้จะไม่ถูกใช้คำนวณเวลาทำงานอีก',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ลบ',
        cancelButtonText: 'ยกเลิก',
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const response = await fetch('api/company_holiday_api.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        });
        const res = await response.json();
        if (res.status === 'success') {
            Swal.fire('สำเร็จ', res.message, 'success');
            loadCompanyHolidays();
        } else {
            Swal.fire('Error', res.message || 'ลบไม่สำเร็จ', 'error');
        }
    });
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
