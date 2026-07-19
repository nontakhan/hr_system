document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('employeeWarningMonth')) {
        initEmployeeWarningsAdminPage();
    }
    if (document.getElementById('myWarningMonth')) {
        initMyWarningsPage();
    }
});

let employeeWarningDetailRows = new Map();
let employeeWarningDetailContext = null;
let employeeWarningSearchTerm = '';

function initEmployeeWarningsAdminPage() {
    document.getElementById('employeeWarningMonth')?.addEventListener('change', clearEmployeeWarningSearch);
    document.getElementById('refreshEmployeeWarningsBtn')?.addEventListener('click', refreshEmployeeWarningView);
    document.getElementById('employeeWarningForm')?.addEventListener('submit', submitEmployeeWarning);
    document.getElementById('warningTypeForm')?.addEventListener('submit', submitWarningType);
    document.getElementById('resetWarningTypeFormBtn')?.addEventListener('click', resetWarningTypeForm);
    document.getElementById('warningTypeTableBody')?.addEventListener('click', handleWarningTypeTableClick);
    document.getElementById('employeeWarningDetailBody')?.addEventListener('click', handleEmployeeWarningDetailClick);
    document.getElementById('addEmployeeWarningBtn')?.addEventListener('click', resetEmployeeWarningForm);
    document.getElementById('employeeWarningModal')?.addEventListener('hidden.bs.modal', resetEmployeeWarningForm);
    document.getElementById('employeeWarningSearchForm')?.addEventListener('submit', searchEmployeeWarnings);
    document.getElementById('clearEmployeeWarningSearchBtn')?.addEventListener('click', clearEmployeeWarningSearch);

    loadEmployeeWarningEmployees();
    loadWarningTypes();
    loadEmployeeWarningSummary();
}

function initMyWarningsPage() {
    document.getElementById('myWarningMonth')?.addEventListener('change', loadMyWarnings);
    loadMyWarnings();
}

async function employeeWarningFetchJson(url, options = {}) {
    const response = await fetch(url, options);
    const res = await response.json();
    if (res.status !== 'success') {
        throw new Error(res.message || 'Load failed');
    }
    return res.data;
}

async function loadEmployeeWarningEmployees() {
    const select = document.getElementById('employeeWarningEmployee');
    if (!select) return;

    try {
        const employees = await employeeWarningFetchJson('api/employee_warning_api.php?action=employees');
        if (!employees.length) {
            select.innerHTML = '<option value="">ไม่พบรายชื่อพนักงาน</option>';
            return;
        }
        select.innerHTML = '<option value="">เลือกพนักงาน</option>' + employees.map(employee => {
            const code = employee.citizen_id ? ` (${employee.citizen_id})` : '';
            const context = [employee.company_name_th, employee.branch_name_th, employee.dept_name_th].filter(Boolean).join(' / ');
            return `<option value="${Number.parseInt(employee.id, 10)}">${ewEscapeHtml(employee.employee_name || '-')}${ewEscapeHtml(code)}${context ? ` - ${ewEscapeHtml(context)}` : ''}</option>`;
        }).join('');
    } catch (err) {
        select.innerHTML = '<option value="">โหลดรายชื่อไม่สำเร็จ</option>';
    }
}

async function loadEmployeeWarningSummary() {
    const tbody = document.getElementById('employeeWarningSummaryBody');
    const month = document.getElementById('employeeWarningMonth')?.value || currentYearMonth();
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">กำลังโหลด...</td></tr>';
    try {
        const data = await employeeWarningFetchJson(`api/employee_warning_api.php?action=monthly_summary&month=${encodeURIComponent(month)}`);
        renderEmployeeWarningSummaryCards(data.summary || {});
        renderEmployeeWarningSummaryRows(data.rows || []);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">${ewEscapeHtml(err.message)}</td></tr>`;
    }
}

function renderEmployeeWarningSummaryCards(summary) {
    document.querySelectorAll('[data-warning-summary]').forEach(el => {
        const key = el.getAttribute('data-warning-summary');
        el.textContent = summary[key] ?? (key === 'top_warning_type' ? '-' : '0');
    });
}

function renderEmployeeWarningSummaryRows(rows, mode = 'month') {
    const tbody = document.getElementById('employeeWarningSummaryBody');
    if (!tbody) return;

    if (!rows.length) {
        const message = mode === 'history' ? 'ไม่พบประวัติใบเตือนที่ตรงกับชื่อ' : 'ไม่พบใบเตือนในเดือนที่เลือก';
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">${message}</td></tr>`;
        return;
    }

    tbody.innerHTML = rows.map(row => {
        const employeeId = Number.parseInt(row.employee_id, 10) || 0;
        const employeeName = row.employee_name || '-';
        const unit = [row.company_name_th, row.branch_name_th, row.dept_name_th, row.position_name_th].filter(Boolean).join('<br>');
        return `
            <tr>
                <td>
                    <div class="fw-semibold">${ewEscapeHtml(employeeName)}</div>
                    <small class="text-muted">${ewEscapeHtml(row.citizen_id || '')}</small>
                </td>
                <td><small>${unit || '-'}</small></td>
                <td><span class="badge bg-danger">${Number.parseInt(row.warning_count, 10) || 0}</span></td>
                <td>${ewEscapeHtml(row.warning_types || '-')}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="openEmployeeWarningDetails(${employeeId}, '${ewEscapeAttr(employeeName)}', '${mode}')">
                        ดู
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

async function submitEmployeeWarning(event) {
    event.preventDefault();
    const form = event.target;
    const data = Object.fromEntries(new FormData(form).entries());
    data.action = data.id ? 'update_warning' : 'create_warning';

    try {
        await employeeWarningFetchJson('api/employee_warning_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        });
        Swal.fire('สำเร็จ', data.id ? 'แก้ไขใบเตือนเรียบร้อยแล้ว' : 'บันทึกใบเตือนเรียบร้อยแล้ว', 'success');
        bootstrap.Modal.getInstance(document.getElementById('employeeWarningModal'))?.hide();
        resetEmployeeWarningForm();
        await refreshEmployeeWarningView();
        if (employeeWarningDetailContext) {
            await openEmployeeWarningDetails(employeeWarningDetailContext.employeeId, employeeWarningDetailContext.employeeName, employeeWarningDetailContext.mode);
        }
    } catch (err) {
        Swal.fire('ผิดพลาด', err.message, 'error');
    }
}

async function searchEmployeeWarnings(event) {
    event?.preventDefault();
    const input = document.getElementById('employeeWarningSearchName');
    const term = String(input?.value || '').trim();
    if (!term) {
        Swal.fire('กรุณาระบุชื่อ', 'กรอกชื่อหรือบางส่วนของชื่อพนักงานที่ต้องการค้นหา', 'warning');
        input?.focus();
        return;
    }
    employeeWarningSearchTerm = term.slice(0, 100);
    await loadEmployeeWarningSearchResults();
}

async function loadEmployeeWarningSearchResults() {
    const tbody = document.getElementById('employeeWarningSummaryBody');
    if (!tbody || !employeeWarningSearchTerm) return;
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">กำลังค้นหา...</td></tr>';
    document.getElementById('employeeWarningTableTitle').textContent = 'ผลการค้นหาประวัติใบเตือนทุกเดือน';
    document.getElementById('employeeWarningTableMode').textContent = `ค้นหา: ${employeeWarningSearchTerm}`;
    document.getElementById('clearEmployeeWarningSearchBtn').classList.remove('d-none');
    try {
        const rows = await employeeWarningFetchJson(`api/employee_warning_api.php?action=search_employee_warnings&q=${encodeURIComponent(employeeWarningSearchTerm)}`);
        renderEmployeeWarningSummaryRows(rows, 'history');
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">${ewEscapeHtml(err.message)}</td></tr>`;
    }
}

async function clearEmployeeWarningSearch() {
    employeeWarningSearchTerm = '';
    employeeWarningDetailContext = null;
    const input = document.getElementById('employeeWarningSearchName');
    if (input) input.value = '';
    document.getElementById('employeeWarningTableTitle').textContent = 'สรุปรายเดือนแยกตามพนักงาน';
    document.getElementById('employeeWarningTableMode').textContent = 'แสดงเฉพาะพนักงานที่มีใบเตือนในเดือนที่เลือก';
    document.getElementById('clearEmployeeWarningSearchBtn').classList.add('d-none');
    await loadEmployeeWarningSummary();
}

async function refreshEmployeeWarningView() {
    if (employeeWarningSearchTerm) {
        await loadEmployeeWarningSearchResults();
    } else {
        await loadEmployeeWarningSummary();
    }
}

function resetEmployeeWarningForm() {
    document.getElementById('employeeWarningForm')?.reset();
    const id = document.getElementById('employeeWarningId');
    const date = document.getElementById('employeeWarningDate');
    const title = document.getElementById('employeeWarningModalTitle');
    const submitLabel = document.getElementById('employeeWarningSubmitLabel');
    if (id) id.value = '';
    if (date) date.value = todayDate();
    if (title) title.textContent = 'เพิ่มใบเตือนพนักงาน';
    if (submitLabel) submitLabel.textContent = 'บันทึกใบเตือน';
}

async function loadWarningTypes() {
    const tbody = document.getElementById('warningTypeTableBody');
    const warningSelect = document.getElementById('employeeWarningType');

    try {
        const types = await employeeWarningFetchJson('api/employee_warning_api.php?action=get_warning_types');
        renderWarningTypeRows(types);
        if (warningSelect) {
            warningSelect.innerHTML = '<option value="">เลือกรายการใบเตือน</option>' + types.map(type => (
                `<option value="${Number.parseInt(type.id, 10)}">${ewEscapeHtml(type.type_name || '-')}</option>`
            )).join('');
        }
    } catch (err) {
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="2" class="text-center text-danger py-3">${ewEscapeHtml(err.message)}</td></tr>`;
        }
    }
}

function renderWarningTypeRows(types) {
    const tbody = document.getElementById('warningTypeTableBody');
    if (!tbody) return;

    if (!types.length) {
        tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">ยังไม่มีรายการใบเตือน</td></tr>';
        return;
    }

    tbody.innerHTML = types.map(type => {
        const payload = ewEscapeAttr(JSON.stringify(type));
        return `
            <tr>
                <td>
                    <div class="fw-semibold">${ewEscapeHtml(type.type_name || '-')}</div>
                    <small class="text-muted">${ewEscapeHtml(type.description || '')}</small>
                </td>
                <td class="text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-primary btn-warning-type-edit" data-info="${payload}">
                        <i class="fas fa-pencil-alt"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-warning-type-delete" data-id="${Number.parseInt(type.id, 10)}">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

async function submitWarningType(event) {
    event.preventDefault();
    const form = event.target;
    const data = Object.fromEntries(new FormData(form).entries());
    data.action = data.id ? 'update_warning_type' : 'create_warning_type';

    try {
        await employeeWarningFetchJson('api/employee_warning_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        });
        Swal.fire('สำเร็จ', 'บันทึกรายการใบเตือนเรียบร้อยแล้ว', 'success');
        resetWarningTypeForm();
        loadWarningTypes();
    } catch (err) {
        Swal.fire('ผิดพลาด', err.message, 'error');
    }
}

function handleWarningTypeTableClick(event) {
    const editBtn = event.target.closest('.btn-warning-type-edit');
    const deleteBtn = event.target.closest('.btn-warning-type-delete');

    if (editBtn) {
        const data = JSON.parse(editBtn.getAttribute('data-info'));
        document.getElementById('warningTypeId').value = data.id || '';
        document.getElementById('warningTypeName').value = data.type_name || '';
        document.getElementById('warningTypeDescription').value = data.description || '';
        document.getElementById('warningTypeName').focus();
    }

    if (deleteBtn) {
        deleteWarningType(Number.parseInt(deleteBtn.dataset.id, 10));
    }
}

async function deleteWarningType(id) {
    const confirm = await Swal.fire({
        title: 'ยืนยันการลบ?',
        text: 'ลบได้เฉพาะรายการที่ยังไม่เคยใช้ในประวัติใบเตือน',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ลบ',
        confirmButtonColor: '#d33',
    });
    if (!confirm.isConfirmed) return;

    try {
        await employeeWarningFetchJson('api/employee_warning_api.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_warning_type', id }),
        });
        Swal.fire('สำเร็จ', 'ลบรายการใบเตือนเรียบร้อยแล้ว', 'success');
        loadWarningTypes();
    } catch (err) {
        Swal.fire('ลบไม่ได้', err.message, 'error');
    }
}

function resetWarningTypeForm() {
    document.getElementById('warningTypeForm')?.reset();
    const idInput = document.getElementById('warningTypeId');
    if (idInput) idInput.value = '';
}

window.openEmployeeWarningDetails = async function(employeeId, employeeName, mode = 'month') {
    const modalEl = document.getElementById('employeeWarningDetailModal');
    const tbody = document.getElementById('employeeWarningDetailBody');
    const title = document.getElementById('employeeWarningDetailTitle');
    const month = document.getElementById('employeeWarningMonth')?.value || currentYearMonth();
    if (!modalEl || !tbody) return;

    employeeWarningDetailContext = { employeeId, employeeName, mode };
    title.textContent = `รายละเอียดใบเตือน: ${employeeName}`;
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">กำลังโหลด...</td></tr>';
    new bootstrap.Modal(modalEl).show();

    try {
        const endpoint = mode === 'history'
            ? `api/employee_warning_api.php?action=employee_warning_history&employee_id=${employeeId}`
            : `api/employee_warning_api.php?action=employee_month_details&employee_id=${employeeId}&month=${encodeURIComponent(month)}`;
        const rows = await employeeWarningFetchJson(endpoint);
        renderEmployeeWarningDetailRows(rows);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">${ewEscapeHtml(err.message)}</td></tr>`;
    }
};

function renderEmployeeWarningDetailRows(rows) {
    const tbody = document.getElementById('employeeWarningDetailBody');
    if (!tbody) return;

    if (!rows.length) {
        employeeWarningDetailRows = new Map();
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">ไม่พบรายละเอียด</td></tr>';
        return;
    }

    employeeWarningDetailRows = new Map(rows.map(row => [Number.parseInt(row.id, 10), row]));
    tbody.innerHTML = rows.map(row => `
        <tr>
            <td>${formatWarningDate(row.warning_date)}</td>
            <td>${ewEscapeHtml(row.type_name || '-')}</td>
            <td>${ewEscapeHtml(row.detail || '-')}</td>
            <td>${ewEscapeHtml(row.created_by_name || '-')}</td>
            <td class="text-nowrap">
                <button type="button" class="btn btn-sm btn-outline-primary btn-employee-warning-edit" data-id="${Number.parseInt(row.id, 10)}" title="แก้ไข">
                    <i class="fas fa-pencil-alt"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger btn-employee-warning-delete" data-id="${Number.parseInt(row.id, 10)}" title="ลบ">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function handleEmployeeWarningDetailClick(event) {
    const editButton = event.target.closest('.btn-employee-warning-edit');
    const deleteButton = event.target.closest('.btn-employee-warning-delete');
    const id = Number.parseInt((editButton || deleteButton)?.dataset.id, 10);
    if (!id) return;

    if (editButton) {
        editEmployeeWarning(employeeWarningDetailRows.get(id));
    } else if (deleteButton) {
        deleteEmployeeWarning(id);
    }
}

function editEmployeeWarning(row) {
    if (!row) return;
    document.getElementById('employeeWarningId').value = row.id || '';
    document.getElementById('employeeWarningEmployee').value = row.employee_id || '';
    document.getElementById('employeeWarningType').value = row.warning_type_id || '';
    document.getElementById('employeeWarningDate').value = String(row.warning_date || '').slice(0, 10);
    document.getElementById('employeeWarningDetail').value = row.detail || '';
    document.getElementById('employeeWarningModalTitle').textContent = 'แก้ไขใบเตือนพนักงาน';
    document.getElementById('employeeWarningSubmitLabel').textContent = 'บันทึกการแก้ไข';
    bootstrap.Modal.getInstance(document.getElementById('employeeWarningDetailModal'))?.hide();
    new bootstrap.Modal(document.getElementById('employeeWarningModal')).show();
}

async function deleteEmployeeWarning(id) {
    const confirmation = await Swal.fire({
        title: 'ยืนยันการลบใบเตือน?',
        text: 'เมื่อลบแล้วจะไม่สามารถเรียกคืนข้อมูลได้',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ลบ',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#d33',
    });
    if (!confirmation.isConfirmed) return;

    try {
        await employeeWarningFetchJson('api/employee_warning_api.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_warning', id }),
        });
        Swal.fire('สำเร็จ', 'ลบใบเตือนเรียบร้อยแล้ว', 'success');
        await refreshEmployeeWarningView();
        if (employeeWarningDetailContext) {
            await openEmployeeWarningDetails(employeeWarningDetailContext.employeeId, employeeWarningDetailContext.employeeName, employeeWarningDetailContext.mode);
        }
    } catch (err) {
        Swal.fire('ลบไม่ได้', err.message, 'error');
    }
}

async function loadMyWarnings() {
    const tbody = document.getElementById('myWarningDetailsBody');
    const month = document.getElementById('myWarningMonth')?.value || currentYearMonth();
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">กำลังโหลด...</td></tr>';
    try {
        const data = await employeeWarningFetchJson(`api/employee_warning_api.php?action=my_monthly_warnings&month=${encodeURIComponent(month)}`);
        renderMyWarningSummary(data.summary || {});
        renderMyWarningRows(data.rows || []);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="3" class="text-center text-danger py-4">${ewEscapeHtml(err.message)}</td></tr>`;
    }
}

function renderMyWarningSummary(summary) {
    document.querySelectorAll('[data-my-warning-summary]').forEach(el => {
        const key = el.getAttribute('data-my-warning-summary');
        el.textContent = summary[key] ?? '0';
    });
}

function renderMyWarningRows(rows) {
    const tbody = document.getElementById('myWarningDetailsBody');
    if (!tbody) return;

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">ไม่พบใบเตือนในเดือนที่เลือก</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map(row => `
        <tr>
            <td>${formatWarningDate(row.warning_date)}</td>
            <td>${ewEscapeHtml(row.type_name || '-')}</td>
            <td>${ewEscapeHtml(row.detail || '-')}</td>
        </tr>
    `).join('');
}

function currentYearMonth() {
    return new Date().toISOString().slice(0, 7);
}

function todayDate() {
    return new Date().toISOString().slice(0, 10);
}

function formatWarningDate(value) {
    if (!value) return '-';
    const parts = String(value).slice(0, 10).split('-');
    if (parts.length !== 3) return value;
    return `${parts[2]}/${parts[1]}/${Number.parseInt(parts[0], 10) + 543}`;
}

function ewEscapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));
}

function ewEscapeAttr(value) {
    return ewEscapeHtml(value).replace(/`/g, '&#096;');
}
