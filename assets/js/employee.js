/*
 * Logic สำหรับจัดการพนักงาน (List, Add, Edit)
 * อัปเดต: แสดงจำนวนแถวชัดเจนใน DataTables
 */

document.addEventListener('DOMContentLoaded', () => {
    
    // 1. หน้า List
    const employeeTable = document.getElementById('employeeTable');
    if (employeeTable) {
        loadEmployeeData();
        
        employeeTable.addEventListener('click', (e) => {
            if (e.target.closest('.btn-delete')) {
                const btn = e.target.closest('.btn-delete');
                handleDeleteEmployee(btn.getAttribute('data-id'));
            }
        });

        // Filter Logic
        const filterBranch = document.getElementById('filterBranch');
        if (filterBranch) {
            filterBranch.addEventListener('change', () => {
                console.log('Filter Changed to:', filterBranch.value); // Debug
                loadEmployeeData(); 
            });
        }
    }

    // 2. หน้า Add
    const addEmployeeForm = document.getElementById('addEmployeeForm');
    if (addEmployeeForm) {
        addEmployeeForm.addEventListener('submit', handleAddEmployeeForm);
        setupFormInteractions();
        loadSouthernProvinces();
    }

    // 3. หน้า Edit
    const editEmployeeForm = document.getElementById('editEmployeeForm');
    if (editEmployeeForm) {
        editEmployeeForm.addEventListener('submit', handleEditEmployeeForm);
        setupFormInteractions();
        setupEditProfileImageUpload(editEmployeeForm);
        
        const savedProv = editEmployeeForm.getAttribute('data-province');
        const savedDist = editEmployeeForm.getAttribute('data-district');
        loadSouthernProvinces(savedProv, savedDist);
    }

    const transferForm = document.getElementById('transferForm');
    if (transferForm) {
        setupTransferHistoryForm(transferForm);
    }
});

function setupFormInteractions() {
    const profileInput = document.getElementById('profileImageInput');
    const previewImg = document.getElementById('previewImage');
    if (profileInput && previewImg) {
        profileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => previewImg.src = e.target.result;
                reader.readAsDataURL(file);
            }
        });
    }

    const companySelect = document.getElementById('companySelect');
    const branchSelect = document.getElementById('branchSelect');
    if (companySelect && branchSelect) {
        companySelect.addEventListener('change', () => filterBranches(companySelect, branchSelect));
    }
}

// --- ฟังก์ชันสำหรับหน้า List (ใช้ DataTables) ---
async function loadEmployeeData() {
    const tbody = document.getElementById('employeeTableBody');
    const branchId = document.getElementById('filterBranch')?.value || '';

    try {
        console.log('Fetching data with branch_id:', branchId); // Debug: เช็คค่าที่ส่งไป
        const response = await fetch(`api/employee_api.php?branch_id=${branchId}`, { method: 'GET' });
        const result = await response.json();
        
        if (result.status === 'success') {
            console.log('Data received:', result.data.length, 'rows'); // Debug: เช็คจำนวนที่ได้กลับมา
            
            // Destroy Old DataTable
            if ($.fn.DataTable.isDataTable('#employeeTable')) {
                $('#employeeTable').DataTable().destroy();
            }

            if (result.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-3">ไม่พบข้อมูลพนักงาน</td></tr>`;
            } else {
                const rowsHtml = result.data.map(emp => {
                    const empId = Number.parseInt(emp.id, 10) || 0;
                    const firstName = escapeHtml(emp.first_name_th);
                    const lastName = escapeHtml(emp.last_name_th);
                    const fullName = `${firstName} ${lastName}`.trim();
                    const imgSrc = safeUploadPath(emp.profile_img_url, 'assets/img/user.png');
                    const fallback = `https://ui-avatars.com/api/?name=${encodeURIComponent(`${emp.first_name_th || ''} ${emp.last_name_th || ''}`.trim())}&background=random&color=fff&size=128`;
                    const idDisplay = emp.citizen_id ? escapeHtml(emp.citizen_id) : '-';
                    const position = escapeHtml(emp.position_name_th || '-');
                    const department = escapeHtml(emp.dept_name_th || '-');
                    const company = escapeHtml(emp.company_name_th || '-');
                    const branch = escapeHtml(emp.branch_name_th || '-');

                    return `
                        <tr>
                            <td><strong>${idDisplay}</strong></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="${escapeAttr(imgSrc)}" onerror="this.onerror=null;this.src='${escapeAttr(fallback)}'" class="rounded-circle me-2 border" style="width:35px;height:35px;object-fit:cover;">
                                    ${fullName || '-'}
                                </div>
                            </td>
                            <td>${position}</td>
                            <td>${department}</td>
                            <td><div class="fw-semibold">${company}</div><small class="text-muted">${branch}</small></td>
                            <td>${renderEmployeeStatus(emp.status)}</td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="employee_view.php?id=${emp.id}" class="btn btn-info btn-sm text-white" title="ดูข้อมูล">
                                        <i class="fas fa-eye"></i> ดู
                                    </a>
                                    <a href="employee_edit.php?id=${emp.id}" class="btn btn-warning btn-sm" title="แก้ไข">
                                        <i class="fas fa-pencil-alt"></i> แก้ไข
                                    </a>
                                    <button class="btn btn-danger btn-sm btn-delete" data-id="${emp.id}" title="ลบ">
                                        <i class="fas fa-trash-alt"></i> ลบ
                                    </button>
                                </div>
                            </td>
                        </tr>`;
                }).join('');

                tbody.innerHTML = rowsHtml;
            }

            // Re-init DataTable (Config ภาษาไทย และแสดงจำนวน)
            $('#employeeTable').DataTable({
                "language": {
                    "lengthMenu": "แสดง _MENU_ รายการ ต่อหน้า",
                    "zeroRecords": "ไม่พบข้อมูลที่ตรงกัน",
                    // (แก้ไข) แสดงรายละเอียดจำนวนแถวชัดเจน
                    "info": "แสดง _START_ ถึง _END_ จากทั้งหมด _TOTAL_ รายการ",
                    "infoEmpty": "แสดง 0 ถึง 0 จากทั้งหมด 0 รายการ",
                    "infoFiltered": "(กรองจากทั้งหมด _MAX_ รายการ)",
                    "search": "ค้นหา:",
                    "paginate": { "first": "หน้าแรก", "last": "สุดท้าย", "next": "ถัดไป", "previous": "ก่อนหน้า" }
                },
                "order": [[ 0, "asc" ]],
                "pageLength": 10
            });

        }
    } catch (error) { console.error('Load Error:', error); }
}

function renderEmployeeStatus(status) {
    const badges = {
        'active': ['bg-success', 'ปฏิบัติงาน'],
        'probation': ['bg-info', 'ทดลองงาน'],
        'resigned': ['bg-danger', 'ลาออก']
    };
    const [cls, text] = badges[status] || ['bg-secondary', status];
    return `<span class="badge ${cls}">${text}</span>`;
}

function handleDeleteEmployee(id) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: "ข้อมูลพนักงานจะถูกลบถาวร!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'ใช่, ลบเลย',
        cancelButtonText: 'ยกเลิก'
    }).then(async (result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'กำลังลบ...', didOpen: () => Swal.showLoading() });
            try {
                const response = await fetch('api/employee_api.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                const res = await response.json();
                if (res.status === 'success') {
                    Swal.fire('สำเร็จ', 'ลบข้อมูลเรียบร้อย', 'success').then(() => loadEmployeeData());
                } else {
                    Swal.fire('ลบไม่สำเร็จ', res.message, 'error');
                }
            } catch (err) { Swal.fire('Error', 'เชื่อมต่อ Server ไม่ได้', 'error'); }
        }
    });
}

async function handleAddEmployeeForm(e) {
    e.preventDefault();
    submitEmployeeForm(e.target, 'create_employee', 'เพิ่มพนักงานสำเร็จ');
}

async function handleEditEmployeeForm(e) {
    e.preventDefault();
    submitEmployeeForm(e.target, 'update_employee', 'แก้ไขข้อมูลสำเร็จ');
}

async function submitEmployeeForm(form, action, successTitle) {
    const formData = new FormData(form);
    formData.append('action', action);

    if (action === 'create_employee' && formData.get('username') && !formData.get('password')) {
        Swal.fire('ผิดพลาด', 'กรุณากรอก Password', 'error'); return;
    }

    Swal.fire({
        title: 'กำลังบันทึก...', text: 'กรุณารอสักครู่',
        allowOutsideClick: false, didOpen: () => Swal.showLoading()
    });

    try {
        const response = await fetch('api/employee_api.php', { method: 'POST', body: formData });
        const result = await response.json();

        if (result.status === 'success') {
            Swal.fire({
                icon: 'success', title: successTitle, text: result.message
            }).then(() => window.location.href = 'employees.php');
        } else {
            Swal.fire('ผิดพลาด', result.message, 'error');
        }
    } catch (error) { Swal.fire('Error', error.message, 'error'); }
}

function filterBranches(compSelect, branchSelect) {
    const compId = compSelect.value;
    let hasSelection = false;
    Array.from(branchSelect.options).forEach(opt => {
        if (opt.value) {
            if (opt.dataset.companyId === compId) {
                opt.style.display = 'block';
                if (opt.selected) hasSelection = true;
            } else {
                opt.style.display = 'none';
                if (opt.selected) opt.selected = false;
            }
        }
    });
    if (!hasSelection && compId) branchSelect.value = '';
    branchSelect.disabled = !compId;
}

const southernProvincesData = [
    { "name_th": "ปัตตานี", "amphure": ["เมืองปัตตานี", "โคกโพธิ์", "หนองจิก", "ปะนาเระ", "มายอ", "ทุ่งยางแดง", "สายบุรี", "ไม้แก่น", "ยะหริ่ง", "ยะรัง", "กะพ้อ", "แม่ลาน"] },
    { "name_th": "ยะลา", "amphure": ["เมืองยะลา", "เบตง", "บันนังสตา", "ธารโต", "ยะหา", "รามัน", "กาบัง", "กรงปินัง"] },
    { "name_th": "นราธิวาส", "amphure": ["เมืองนราธิวาส", "ตากใบ", "บาเจาะ", "ยี่งอ", "ระแงะ", "รือเสาะ", "ศรีสาคร", "แว้ง", "สุคิริน", "สุไหงโก-ลก", "สุไหงปาดี", "จะแนะ", "เจาะไอร้อง"] },
    { "name_th": "สงขลา", "amphure": ["เมืองสงขลา", "สทิงพระ", "จะนะ", "นาทวี", "เทพา", "สะบ้าย้อย", "ระโนด", "กระแสสินธุ์", "รัตภูมิ", "สะเดา", "หาดใหญ่", "นาหม่อม", "ควนเนียง", "บางกล่ำ", "สิงหนคร", "คลองหอยโข่ง"] }
];

function loadSouthernProvinces(defaultProv = null, defaultDist = null) {
    const pSelect = document.getElementById('provinceSelect');
    const dSelect = document.getElementById('districtSelect');
    if (!pSelect || !dSelect) return;

    pSelect.innerHTML = '<option value="">-- เลือกจังหวัด --</option>';
    southernProvincesData.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.name_th; opt.textContent = p.name_th;
        if (p.name_th === defaultProv) opt.selected = true;
        pSelect.appendChild(opt);
    });

    const populateDistricts = (provName, selectedDist = null) => {
        dSelect.innerHTML = '<option value="">-- เลือกอำเภอ --</option>';
        dSelect.disabled = true;
        if (!provName) return;

        const provData = southernProvincesData.find(p => p.name_th === provName);
        if (provData) {
            provData.amphure.forEach(ampName => {
                const opt = document.createElement('option');
                opt.value = ampName; opt.textContent = ampName;
                if (ampName === selectedDist) opt.selected = true;
                dSelect.appendChild(opt);
            });
            dSelect.disabled = false;
        }
    };

    if (defaultProv) populateDistricts(defaultProv, defaultDist);

    pSelect.addEventListener('change', function() {
        populateDistricts(this.value);
    });
}

function setupEditProfileImageUpload(form) {
    const input = document.getElementById('profileImageInput');
    const preview = document.getElementById('previewImage');
    if (!input || !preview) return;

    input.addEventListener('change', async () => {
        const file = input.files[0];
        if (!file) return;

        const originalSrc = preview.src;
        preview.src = URL.createObjectURL(file);

        const formData = new FormData(form);
        formData.append('action', 'update_profile_image');

        Swal.fire({
            title: 'กำลังอัปโหลดรูป...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        try {
            const response = await fetch('api/employee_api.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.status === 'success') {
                preview.src = `${result.profile_img_url}?t=${Date.now()}`;
                const oldImageInput = form.querySelector('input[name="old_profile_image"]');
                if (oldImageInput) oldImageInput.value = result.profile_img_url;
                Swal.fire('สำเร็จ', result.message, 'success');
            } else {
                preview.src = originalSrc;
                Swal.fire('อัปโหลดไม่สำเร็จ', result.message, 'error');
            }
        } catch (error) {
            preview.src = originalSrc;
            Swal.fire('Error', error.message, 'error');
        } finally {
            input.value = '';
        }
    });
}

function setupTransferHistoryForm(form) {
    const companySelect = document.getElementById('trans_company');
    const branchSelect = document.getElementById('trans_branch');

    if (companySelect && branchSelect) {
        companySelect.addEventListener('change', () => filterBranches(companySelect, branchSelect));
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(form);
        const logId = formData.get('transfer_log_id');
        formData.append('action', logId ? 'update_transfer_history' : 'transfer_employee');

        Swal.fire({
            title: 'กำลังบันทึก...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        try {
            const response = await fetch('api/employee_api.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.status === 'success') {
                Swal.fire('สำเร็จ', result.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('transferModal')).hide();
                loadTransferHistory(Number.parseInt(formData.get('employee_id'), 10) || 0);
            } else {
                Swal.fire('บันทึกไม่สำเร็จ', result.message, 'error');
            }
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    document.getElementById('transferModal')?.addEventListener('hidden.bs.modal', () => {
        resetTransferForm(form);
    });
}

function resetTransferForm(form) {
    document.getElementById('transferLogId').value = '';
    document.getElementById('transferModalTitle').innerHTML = '<i class="fas fa-exchange-alt"></i> บันทึกการโยกย้าย/ปรับตำแหน่ง';
    document.getElementById('transferSubmitBtn').textContent = 'บันทึกการโยกย้าย';
    document.getElementById('transferEffectiveDate').value = new Date().toISOString().slice(0, 10);
    document.getElementById('transferType').value = 'transfer';
    document.getElementById('transferNotes').value = '';
}

function parseTransferNote(notes) {
    const text = String(notes || '');
    const match = text.match(/^\[(transfer|promote|demote)\]\s*(.*)$/);
    return {
        type: match ? match[1] : 'transfer',
        note: match ? match[2] : text
    };
}

window.loadTransferHistory = async function(empId) {
    const tbody = document.querySelector('#transferHistoryTable tbody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">กำลังโหลดข้อมูล...</td></tr>';

    try {
        const response = await fetch(`api/employee_api.php?action=get_history&employee_id=${encodeURIComponent(empId)}`);
        const result = await response.json();

        if (result.status !== 'success') {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${escapeHtml(result.message || 'โหลดข้อมูลไม่สำเร็จ')}</td></tr>`;
            return;
        }

        if (result.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">ยังไม่มีประวัติการโยกย้าย</td></tr>';
            return;
        }

        tbody.innerHTML = result.data.map(row => {
            const noteInfo = parseTransferNote(row.notes);
            const payload = escapeAttr(JSON.stringify(row));
            const fromText = `${escapeHtml(row.from_comp || '-')}/${escapeHtml(row.from_branch || '-')}<br><small>${escapeHtml(row.from_dept || '-')} - ${escapeHtml(row.from_pos || '-')}</small>`;
            const toText = `${escapeHtml(row.to_comp || '-')}/${escapeHtml(row.to_branch || '-')}<br><small>${escapeHtml(row.to_dept || '-')} - ${escapeHtml(row.to_pos || '-')}</small>`;

            return `
                <tr>
                    <td>${escapeHtml(row.effective_date || '-')}</td>
                    <td>${escapeHtml(noteInfo.type)}</td>
                    <td>${fromText}</td>
                    <td>${toText}</td>
                    <td>${escapeHtml(noteInfo.note || '-')}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-warning" data-history="${payload}" onclick="openTransferHistoryEdit(this)">
                            <i class="fas fa-pencil-alt"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    } catch (error) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">เกิดข้อผิดพลาดในการโหลดข้อมูล</td></tr>';
    }
}

window.openTransferHistoryEdit = function(button) {
    const row = JSON.parse(button.dataset.history);
    const noteInfo = parseTransferNote(row.notes);

    document.getElementById('transferLogId').value = row.id || '';
    document.getElementById('transferEffectiveDate').value = row.effective_date || new Date().toISOString().slice(0, 10);
    document.getElementById('transferType').value = noteInfo.type;
    document.getElementById('trans_company').value = row.to_company_id || '';
    document.getElementById('trans_branch').value = row.to_branch_id || '';
    document.getElementById('trans_department').value = row.to_department_id || '';
    document.getElementById('trans_position').value = row.to_position_id || '';
    document.getElementById('transferNotes').value = noteInfo.note || '';
    document.getElementById('transferModalTitle').innerHTML = '<i class="fas fa-pencil-alt"></i> แก้ไขประวัติการโยกย้าย';
    document.getElementById('transferSubmitBtn').textContent = 'บันทึกการแก้ไข';

    const companySelect = document.getElementById('trans_company');
    const branchSelect = document.getElementById('trans_branch');
    if (companySelect && branchSelect) filterBranches(companySelect, branchSelect);
    document.getElementById('trans_branch').value = row.to_branch_id || '';

    new bootstrap.Modal(document.getElementById('transferModal')).show();
}
