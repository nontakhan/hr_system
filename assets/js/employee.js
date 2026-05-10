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
        
        const savedProv = editEmployeeForm.getAttribute('data-province');
        const savedDist = editEmployeeForm.getAttribute('data-district');
        loadSouthernProvinces(savedProv, savedDist);
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
