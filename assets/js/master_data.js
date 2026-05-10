/*
 * Logic สำหรับจัดการ Master Data
 * (Updated: เพิ่ม Color Picker ให้บริษัท)
 */

let masterData = {
    companies: [], branches: [], departments: [], positions: []
};

document.addEventListener('DOMContentLoaded', () => {
    const masterDataTabs = document.getElementById('masterDataTabs');
    if (masterDataTabs) {
        loadAllMasterData();
        const modalForm = document.getElementById('masterDataForm');
        modalForm.addEventListener('submit', handleMasterDataSubmit);

        document.body.addEventListener('click', (e) => {
            if (e.target.matches('[data-bs-toggle="modal"][data-type]')) showModalForm(e.target.getAttribute('data-type'));
            if (e.target.closest('.btn-edit')) {
                const btn = e.target.closest('.btn-edit');
                showModalForm(btn.getAttribute('data-type'), btn.getAttribute('data-id'));
            }
            if (e.target.closest('.btn-delete')) {
                const btn = e.target.closest('.btn-delete');
                handleDelete(btn.getAttribute('data-type'), btn.getAttribute('data-id'));
            }
        });
    }
});

async function loadAllMasterData() {
    try {
        const response = await fetch('api/manage_master.php?type=all');
        const result = await response.json();
        if (result.status === 'success') {
            masterData = result.data;
            renderTables(masterData);
        }
    } catch (error) { console.error('Load Master Data Error:', error); }
}

function renderTables(data) {
    const initDataTable = (tableId) => {
        if ($.fn.DataTable.isDataTable(tableId)) $(tableId).DataTable().destroy();
        $(tableId).DataTable({
            "language": {
                "lengthMenu": "แสดง _MENU_", "zeroRecords": "ไม่พบข้อมูล", "info": "หน้า _PAGE_/_PAGES_", "infoEmpty": "", "infoFiltered": "(กรองจาก _MAX_)", "search": "ค้นหา:", "paginate": { "first": "<<", "last": ">>", "next": ">", "previous": "<" }
            },
            "pageLength": 10, "autoWidth": false
        });
    };

    const renderRow = (item, type, extraCols = '') => `
        <tr>
            <td>${item.id}</td>
            ${extraCols}
            <td>
                <button class="btn btn-warning btn-sm btn-edit" data-type="${type}" data-id="${item.id}"><i class="fas fa-pencil-alt"></i></button>
                <button class="btn btn-danger btn-sm btn-delete" data-type="${type}" data-id="${item.id}"><i class="fas fa-trash-alt"></i></button>
            </td>
        </tr>`;

    // 1. Companies (เพิ่มแสดงสี)
    if ($.fn.DataTable.isDataTable('#companyTable')) $('#companyTable').DataTable().destroy();
    document.getElementById('companyTableBody').innerHTML = data.companies.map(i => {
        // แสดงสีเป็นวงกลม
        const colorBadge = `<span class="d-inline-block rounded-circle me-2" style="width: 15px; height: 15px; background-color: ${i.company_color || '#005A9C'}; vertical-align: middle; border: 1px solid #ddd;"></span>`;
        return renderRow(i, 'company', `<td>${colorBadge} ${i.company_name_th}</td><td>${i.company_address || '-'}</td>`);
    }).join('');
    initDataTable('#companyTable');

    // 2. Branches, 3. Depts, 4. Positions (เหมือนเดิม)
    if ($.fn.DataTable.isDataTable('#branchTable')) $('#branchTable').DataTable().destroy();
    document.getElementById('branchTableBody').innerHTML = data.branches.map(i => renderRow(i, 'branch', `<td>${i.branch_name_th}</td><td>${i.company_name_th}</td>`)).join('');
    initDataTable('#branchTable');

    if ($.fn.DataTable.isDataTable('#departmentTable')) $('#departmentTable').DataTable().destroy();
    document.getElementById('departmentTableBody').innerHTML = data.departments.map(i => renderRow(i, 'department', `<td>${i.dept_name_th}</td><td>${i.dept_name_en || '-'}</td>`)).join('');
    initDataTable('#departmentTable');

    if ($.fn.DataTable.isDataTable('#positionTable')) $('#positionTable').DataTable().destroy();
    document.getElementById('positionTableBody').innerHTML = data.positions.map(i => renderRow(i, 'position', `<td>${i.position_name_th}</td><td>${i.position_name_en || '-'}</td>`)).join('');
    initDataTable('#positionTable');
}

function showModalForm(type, id = null) {
    const modal = new bootstrap.Modal(document.getElementById('masterDataModal'));
    const form = document.getElementById('masterDataForm');
    const content = document.getElementById('modalFormContent');
    
    form.reset();
    document.getElementById('formAction').value = id ? 'update' : 'create';
    document.getElementById('formType').value = type;
    document.getElementById('formEditId').value = id || '';
    
    let item = null;
    if (id) {
        let key = type + 's'; 
        if(type === 'company') key = 'companies';
        if(type === 'branch') key = 'branches';
        item = masterData[key].find(d => d.id == id);
    }

    let html = '';
    if (type === 'company') {
        // (Updated: เพิ่ม Input Color)
        const color = item?.company_color || '#005A9C';
        html = `
            <div class="mb-3">
                <label class="form-label">ชื่อบริษัท</label>
                <input type="text" class="form-control" name="company_name_th" value="${item?.company_name_th || ''}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">ที่อยู่</label>
                <textarea class="form-control" name="company_address">${item?.company_address || ''}</textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">สีประจำบริษัท (สำหรับกราฟ)</label>
                <input type="color" class="form-control form-control-color w-100" name="company_color" value="${color}" title="เลือกสี">
            </div>
        `;
    } else if (type === 'branch') {
        const opts = masterData.companies.map(c => `<option value="${c.id}" ${item?.company_id == c.id ? 'selected' : ''}>${c.company_name_th}</option>`).join('');
        html = `
            <div class="mb-3"><label>สังกัดบริษัท</label><select class="form-select" name="company_id" required><option value="">-- เลือก --</option>${opts}</select></div>
            <div class="mb-3"><label>ชื่อสาขา</label><input type="text" class="form-control" name="branch_name_th" value="${item?.branch_name_th || ''}" required></div>`;
    } else if (type === 'department' || type === 'position') {
        const fieldTH = type === 'department' ? 'dept_name_th' : 'position_name_th';
        const fieldEN = type === 'department' ? 'dept_name_en' : 'position_name_en';
        html = `
            <div class="mb-3"><label>ชื่อ (ไทย)</label><input type="text" class="form-control" name="${fieldTH}" value="${item?.[fieldTH] || ''}" required></div>
            <div class="mb-3"><label>ชื่อ (Eng)</label><input type="text" class="form-control" name="${fieldEN}" value="${item?.[fieldEN] || ''}"></div>`;
    }

    content.innerHTML = html;
    modal.show();
}

async function handleMasterDataSubmit(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());

    try {
        const response = await fetch('api/manage_master.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        
        if (result.status === 'success') {
            Swal.fire('สำเร็จ', result.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('masterDataModal')).hide();
            loadAllMasterData();
        } else {
            Swal.fire('Error', result.message, 'error');
        }
    } catch (error) { Swal.fire('Error', error.message, 'error'); }
}

async function handleDelete(type, id) {
    // (Delete Logic เดิม)
    Swal.fire({
        title: 'ยืนยันการลบ?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'ลบ'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await fetch('api/manage_master.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ type, id })
                });
                const res = await response.json();
                if (res.status === 'success') {
                    Swal.fire('ลบสำเร็จ', '', 'success');
                    loadAllMasterData();
                } else { Swal.fire('Error', res.message, 'error'); }
            } catch (err) { Swal.fire('Error', err.message, 'error'); }
        }
    });
}