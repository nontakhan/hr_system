/*
 * ไฟล์ JavaScript หลักสำหรับโปรเจกต์ HR System
 * - จัดการ Login (Phase 3.1)
 * - จัดการ Master Data (Phase 3.2)
 * - จัดการ Employee Form (Phase 3.3)
 * - (ใหม่) โหลด Employee List (Phase 3.4)
 */

// (Global variable) สำหรับเก็บข้อมูล Master Data ที่โหลดมา
let masterData = {
    companies: [],
    branches: [],
    departments: [],
    positions: []
};

// ----------------------------------------------------
// (Event Listener) จะเริ่มทำงานเมื่อหน้าเว็บโหลดเสร็จ
// ----------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {

    // --- 1. ตรวจสอบว่านี่คือหน้า Login (index.php) หรือไม่ ---
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        // (ถ้าใช่ ให้เพิ่ม Event การดักจับการ Submit)
        loginForm.addEventListener('submit', handleLoginForm);
    }

    // --- 2. ตรวจสอบว่านี่คือหน้า Master Data (manage_master_data.php) หรือไม่ ---
    const masterDataTabs = document.getElementById('masterDataTabs');
    if (masterDataTabs) {
        // (ถ้าใช่ ให้โหลดข้อมูลทั้งหมดมาแสดงทันที)
        loadAllMasterData();

        // (เพิ่ม Event ให้ฟอร์มใน Modal)
        const modalForm = document.getElementById('masterDataForm');
        modalForm.addEventListener('submit', handleMasterDataSubmit);

        // (เพิ่ม Event ให้ปุ่ม "เพิ่ม" และ "แก้ไข" (Event Delegation))
        document.body.addEventListener('click', (e) => {
            
            // 2.1) ดักฟังปุ่ม "เพิ่ม" (ที่อยู่นอก Modal)
            if (e.target.matches('[data-bs-toggle="modal"][data-type]')) {
                const type = e.target.getAttribute('data-type');
                showModalForm(type); // (เรียกฟังก์ชันแสดง Modal (โหมด 'สร้าง'))
            }

            // 2.2) ดักฟังปุ่ม "แก้ไข" (ดินสอสีเหลือง)
            if (e.target.matches('.btn-edit') || e.target.closest('.btn-edit')) {
                const button = e.target.closest('.btn-edit');
                const type = button.getAttribute('data-type');
                const id = button.getAttribute('data-id');
                showModalForm(type, id); // (เรียกฟังก์ชันแสดง Modal (โหมด 'แก้ไข'))
            }

            // 2.3) ดักฟังปุ่ม "ลบ" (ถังขยะสีแดง)
            if (e.target.matches('.btn-delete') || e.target.closest('.btn-delete')) {
                const button = e.target.closest('.btn-delete');
                const type = button.getAttribute('data-type');
                const id = button.getAttribute('data-id');
                handleDelete(type, id); // (เรียกฟังก์ชันลบ)
            }
        });
    }

    // --- 3. ตรวจสอบว่านี่คือหน้า "เพิ่มพนักงาน" (employee_add.php) หรือไม่ ---
    const addEmployeeForm = document.getElementById('addEmployeeForm');
    if (addEmployeeForm) {
        
        // 3.1) เพิ่ม Event ดักจับการ Submit ฟอร์ม
        addEmployeeForm.addEventListener('submit', handleAddEmployeeForm);
        
        // 3.2) เพิ่ม Event ดักจับการเปลี่ยน "บริษัท" (เพื่อกรอง "สาขา")
        const companySelect = document.getElementById('companySelect');
        const branchSelect = document.getElementById('branchSelect');
        if (companySelect && branchSelect) {
            companySelect.addEventListener('change', () => {
                filterBranches(companySelect, branchSelect);
            });
        }
    }
    
    // --- 4. (ใหม่) ตรวจสอบว่านี่คือหน้า "รายการพนักงาน" (employees.php) หรือไม่ ---
    const employeeTable = document.getElementById('employeeTable');
    if (employeeTable) {
        // (ถ้าใช่ ให้โหลดข้อมูลพนักงานมาแสดง)
        loadEmployeeData();
    }


});

// ----------------------------------------------------
// (Phase 3.1) ฟังก์ชันสำหรับจัดการการ Login
// ----------------------------------------------------
async function handleLoginForm(e) {
    e.preventDefault(); // หยุดการ Submit ฟอร์มแบบปกติ (ไม่ให้หน้า Reload)

    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;

    // (แสดง Popup "กำลังโหลด")
    Swal.fire({
        title: 'กำลังตรวจสอบ...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        const response = await fetch('api/login_process.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ username, password })
        });

        const data = await response.json();

        if (data.status === 'success') {
            // (Login สำเร็จ)
            Swal.fire({
                icon: 'success',
                title: 'เข้าสู่ระบบสำเร็จ!',
                text: 'กำลังพาคุณไปยัง Dashboard...',
                timer: 1500, // (หน่วงเวลา 1.5 วินาที)
                showConfirmButton: false
            }).then(() => {
                // (Redirect ไปหน้า Dashboard)
                window.location.href = 'dashboard.php';
            });

        } else {
            // (Login ไม่สำเร็จ)
            Swal.fire({
                icon: 'error',
                title: 'ผิดพลาด',
                text: data.message || 'Username หรือ Password ไม่ถูกต้อง'
            });
        }

    } catch (error) {
        // (Error การเชื่อมต่อ)
        Swal.fire({
            icon: 'error',
            title: 'เชื่อมต่อล้มเหลว',
            text: 'ไม่สามารถเชื่อมต่อ API ได้ (login_process)'
        });
        console.error('Error:', error);
    }
}


// ----------------------------------------------------
// (Phase 3.2) ฟังก์ชันสำหรับหน้า Master Data
// ----------------------------------------------------

/**
 * 1. (Read) โหลดข้อมูล Master Data ทั้งหมดจาก API
 */
async function loadAllMasterData() {
    try {
        const response = await fetch('api/manage_master.php?type=all', {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (result.status === 'success') {
            masterData = result.data; // (เก็บข้อมูลทั้งหมดไว้ใน Global var)
            renderTables(masterData); // (ส่งไปวาดตาราง)
        } else {
            throw new Error(result.message || 'API ตอบกลับ Error');
        }

    } catch (error) {
        // (Error ตอนโหลดข้อมูล)
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด (JS)',
            text: `ไม่สามารถโหลดข้อมูล Master ได้: ${error.message}`
        });
        console.error('Fetch Error:', error);
    }
}

/**
 * 2. (Render) วาดข้อมูลลงในตาราง
 */
function renderTables(data) {
    const companyTable = document.getElementById('companyTableBody');
    const branchTable = document.getElementById('branchTableBody');
    const departmentTable = document.getElementById('departmentTableBody');
    const positionTable = document.getElementById('positionTableBody');
    
    // (ล้างตารางเก่า)
    companyTable.innerHTML = '';
    branchTable.innerHTML = '';
    departmentTable.innerHTML = '';
    positionTable.innerHTML = '';

    // 2.1 Render Companies
    data.companies.forEach(item => {
        companyTable.innerHTML += `
            <tr>
                <td>${item.id}</td>
                <td>${item.company_code}</td>
                <td>${item.company_name_th}</td>
                <td>${item.company_address || '-'}</td>
                <td>${renderActionButtons('company', item.id)}</td>
            </tr>`;
    });

    // 2.2 Render Branches
    data.branches.forEach(item => {
        branchTable.innerHTML += `
            <tr>
                <td>${item.id}</td>
                <td>${item.branch_code}</td>
                <td>${item.branch_name_th}</td>
                <td>${item.company_name_th}</td> <!-- (มาจาก JOIN) -->
                <td>${renderActionButtons('branch', item.id)}</td>
            </tr>`;
    });

    // 2.3 Render Departments
    data.departments.forEach(item => {
        departmentTable.innerHTML += `
            <tr>
                <td>${item.id}</td>
                <td>${item.dept_name_th}</td>
                <td>${item.dept_name_en || '-'}</td>
                <td>${renderActionButtons('department', item.id)}</td>
            </tr>`;
    });

    // 2.4 Render Positions
    data.positions.forEach(item => {
        positionTable.innerHTML += `
            <tr>
                <td>${item.id}</td>
                <td>${item.position_name_th}</td>
                <td>${item.position_name_en || '-'}</td>
                <td>${renderActionButtons('position', item.id)}</td>
            </tr>`;
    });
}

/**
 * (Helper) สร้างปุ่ม "แก้ไข" และ "ลบ"
 */
function renderActionButtons(type, id) {
    return `
        <button class="btn btn-warning btn-sm btn-edit" data-type="${type}" data-id="${id}" title="แก้ไข">
            <i class="fas fa-pencil-alt"></i>
        </button>
        <button class="btn btn-danger btn-sm btn-delete" data-type="${type}" data-id="${id}" title="ลบ">
            <i class="fas fa-trash-alt"></i>
        </button>
    `;
}

/**
 * 3. (Create/Update) แสดง Modal Form (ทั้งโหมด "เพิ่ม" และ "แก้ไข")
 */
function showModalForm(type, id = null) {
    const modal = new bootstrap.Modal(document.getElementById('masterDataModal'));
    const form = document.getElementById('masterDataForm');
    const title = document.getElementById('modalTitle');
    const content = document.getElementById('modalFormContent');
    
    // (รีเซ็ตฟอร์ม)
    form.reset();
    document.getElementById('formAction').value = id ? 'update' : 'create';
    document.getElementById('formType').value = type;
    document.getElementById('formEditId').value = id || '';
    
    let html = '';
    let item = null; // (ตัวแปรเก็บข้อมูล (ถ้าเป็นโหมดแก้ไข))

    // (ถ้าเป็นโหมดแก้ไข ให้หาข้อมูล)
    if (id) {
        // --- (จุดที่แก้ไข BUG - Phase 3.2) ---
        let dataSetName = type + 's';
        if (type === 'company') dataSetName = 'companies';
        if (type === 'branch') dataSetName = 'branches';
        
        const dataSet = masterData[dataSetName]; // (ใช้ชื่อที่ถูกต้อง)
        // --- (จบการแก้ไข BUG) ---
        
        item = dataSet.find(d => d.id == id);
    }

    // (สร้างฟอร์มตาม Type)
    if (type === 'company') {
        title.innerText = id ? 'แก้ไขบริษัท' : 'เพิ่มบริษัท';
        html = `
            <div class="mb-3">
                <label class="form-label">รหัสบริษัท (1 หลัก)</label>
                <input type="text" class="form-control" name="company_code" maxlength="1" value="${item ? item.company_code : ''}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">ชื่อบริษัท (ไทย)</label>
                <input type="text" class="form-control" name="company_name_th" value="${item ? item.company_name_th : ''}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">ที่อยู่</label>
                <textarea class="form-control" name="company_address">${item ? (item.company_address || '') : ''}</textarea>
            </div>
        `;
    } 
    else if (type === 'branch') {
        title.innerText = id ? 'แก้ไขสาขา' : 'เพิ่มสาขา';
        html = `
            <div class="mb-3">
                <label class="form-label">สังกัดบริษัท</label>
                <select class="form-select" name="company_id" required>
                    <option value="">-- เลือกบริษัท --</option>
                    ${masterData.companies.map(c => 
                        `<option value="${c.id}" ${item && item.company_id == c.id ? 'selected' : ''}>
                            ${c.company_name_th}
                         </option>`
                    ).join('')}
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">รหัสสาขา (1 หลัก)</label>
                <input type="text" class="form-control" name="branch_code" maxlength="1" value="${item ? item.branch_code : ''}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">ชื่อสาขา (ไทย)</label>
                <input type="text" class="form-control" name="branch_name_th" value="${item ? item.branch_name_th : ''}" required>
            </div>
        `;
    }
    else if (type === 'department') {
        title.innerText = id ? 'แก้ไขแผนก' : 'เพิ่มแผนก';
        html = `
            <div class="mb-3">
                <label class="form-label">ชื่อแผนก (ไทย)</label>
                <input type="text" class="form-control" name="dept_name_th" value="${item ? item.dept_name_th : ''}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">ชื่อแผนก (อังกฤษ)</label>
                <input type="text" class="form-control" name="dept_name_en" value="${item ? (item.dept_name_en || '') : ''}">
            </div>
        `;
    }
    else if (type === 'position') {
        title.innerText = id ? 'แก้ไขตำแหน่ง' : 'เพิ่มตำแหน่ง';
         html = `
            <div class="mb-3">
                <label class="form-label">ชื่อตำแหน่ง (ไทย)</label>
                <input type="text" class="form-control" name="position_name_th" value="${item ? item.position_name_th : ''}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">ชื่อตำแหน่ง (อังกฤษ)</label>
                <input type="text" class="form-control" name="position_name_en" value="${item ? (item.position_name_en || '') : ''}">
            </div>
        `;
    }

    content.innerHTML = html; // (ใส่ฟอร์มลงใน Modal)
    modal.show(); // (แสดง Modal)
}

/**
 * 4. (Submit) จัดการการ Submit ฟอร์ม (ทั้ง Create และ Update)
 */
async function handleMasterDataSubmit(e) {
    e.preventDefault(); // (หยุดการ Reload)
    
    // (ดึงข้อมูลจากฟอร์ม)
    const form = e.target;
    const formData = new FormData(form);
    
    // (แปลง FormData เป็น Object)
    const data = {};
    formData.forEach((value, key) => {
        data[key] = value;
    });

    // (แสดง Loading)
    Swal.fire({
        title: 'กำลังบันทึก...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const response = await fetch('api/manage_master.php', {
            method: 'POST', // (API จะเช็ค 'action' ใน body เอง)
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ!',
                text: result.message
            });
            
            // (ซ่อน Modal)
            const modal = bootstrap.Modal.getInstance(document.getElementById('masterDataModal'));
            modal.hide();

            // (โหลดข้อมูลตารางใหม่)
            loadAllMasterData(); 

        } else {
            Swal.fire({
                icon: 'error',
                title: 'ผิดพลาด',
                text: result.message || 'API Error'
            });
        }
    } catch (error) {
         Swal.fire({
            icon: 'error',
            title: 'เชื่อมต่อล้มเหลว',
            text: `ไม่สามารถเชื่อมต่อ API (Submit) ได้: ${error.message}`
        });
    }
}

/**
 * 5. (Delete) จัดการการลบข้อมูล
 */
async function handleDelete(type, id) {
    
    // (ยืนยันการลบ)
    Swal.fire({
        title: 'คุณแน่ใจหรือไม่?',
        text: `คุณต้องการลบข้อมูลนี้ (ID: ${id}) จริงหรือ?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ใช่, ลบเลย!',
        cancelButtonText: 'ยกเลิก'
    }).then(async (result) => {
        if (result.isConfirmed) {
            
            // (แสดง Loading)
            Swal.fire({
                title: 'กำลังลบ...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            try {
                const response = await fetch('api/manage_master.php', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ type, id })
                });

                const result = await response.json();

                if (result.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'ลบสำเร็จ!',
                        text: result.message
                    });
                    
                    // (โหลดข้อมูลตารางใหม่)
                    loadAllMasterData();

                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'ลบไม่สำเร็จ',
                        text: result.message || 'API Error (Delete)'
                    });
                }

            } catch (error) {
                 Swal.fire({
                    icon: 'error',
                    title: 'เชื่อมต่อล้มเหลว',
                    text: `ไม่สามารถเชื่อมต่อ API (Delete) ได้: ${error.message}`
                });
            }
        }
    });
}


// ----------------------------------------------------
// (Phase 3.3) ฟังก์ชันสำหรับหน้า "เพิ่มพนักงาน"
// ----------------------------------------------------

/**
 * (Helper) กรอง Dropdown สาขา
 */
function filterBranches(companySelect, branchSelect) {
    const selectedCompanyId = companySelect.value;
    
    // (Reset สาขา)
    branchSelect.value = '';
    branchSelect.disabled = true;
    
    // (ซ่อน Option ทั้งหมด)
    Array.from(branchSelect.options).forEach(option => {
        if (option.value !== '') { // (ยกเว้น Option "เลือก...")
            option.style.display = 'none';
        }
    });

    if (selectedCompanyId) {
        // (ถ้าเลือกบริษัทแล้ว)
        // (แสดงเฉพาะสาขาที่ตรงกัน)
        Array.from(branchSelect.options).forEach(option => {
            if (option.getAttribute('data-company-id') === selectedCompanyId) {
                option.style.display = 'block';
            }
        });
        branchSelect.disabled = false;
        branchSelect.querySelector('option[value=""]').innerText = '-- เลือกสาขา --';
    } else {
        // (ถ้ายังไม่เลือกบริษัท)
        branchSelect.querySelector('option[value=""]').innerText = '-- (กรุณาเลือกบริษัทก่อน) --';
    }
}

/**
 * (Create) จัดการการ Submit ฟอร์มเพิ่มพนักงาน
 */
async function handleAddEmployeeForm(e) {
    e.preventDefault(); // (หยุด Reload)

    const form = e.target;
    const formData = new FormData(form);
    
    // (แปลง FormData เป็น Object)
    const data = {};
    formData.forEach((value, key) => {
        data[key] = value;
    });

    // (ตรวจสอบ Password ถ้ากรอก Username)
    if (data.username && !data.password) {
        Swal.fire({
            icon: 'error',
            title: 'ผิดพลาด',
            text: 'คุณกรอก Username แต่ลืมกรอก Password'
        });
        return;
    }

    // (แสดง Loading)
    Swal.fire({
        title: 'กำลังบันทึก...',
        text: 'กำลังสร้างรหัสพนักงานและบันทึกข้อมูล',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const response = await fetch('api/employee_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            // (เราต้องห่อ data ด้วย action)
            body: JSON.stringify({
                action: 'create_employee',
                data: data 
            })
        });

        const result = await response.json();

        if (result.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'เพิ่มพนักงานสำเร็จ!',
                text: `รหัสพนักงานใหม่คือ: ${result.new_emp_id}`,
            }).then(() => {
                // (เมื่อสำเร็จ ให้พาไปหน้ารายการ)
                window.location.href = 'employees.php';
            });
            
        } else {
            Swal.fire({
                icon: 'error',
                title: 'บันทึกไม่สำเร็จ',
                text: result.message || 'API Error'
            });
        }
    } catch (error) {
         Swal.fire({
            icon: 'error',
            title: 'เชื่อมต่อล้มเหลว',
            text: `ไม่สามารถเชื่อมต่อ API (Employee) ได้: ${error.message}`
        });
    }
}


// ----------------------------------------------------
// (Phase 3.4 - ใหม่) ฟังก์ชันสำหรับหน้า "รายการพนักงาน"
// ----------------------------------------------------

/**
 * (Read) โหลดข้อมูลพนักงานมาใส่ตาราง
 */
async function loadEmployeeData() {
    const tbody = document.getElementById('employeeTableBody');
    if (!tbody) return; // (Safety check)

    try {
        const response = await fetch('api/employee_api.php', {
            method: 'GET', // (Method GET จะถูกเรียกโดยอัตโนมัติ)
            headers: {
                'Accept': 'application/json'
            }
        });
        
        const result = await response.json();
        
        if (result.status === 'success') {
            tbody.innerHTML = ''; // (ล้าง "กำลังโหลด...")
            
            if (result.data.length === 0) {
                // (ถ้าไม่มีข้อมูล)
                tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-3">ยังไม่มีข้อมูลพนักงาน</td></tr>`;
                return;
            }

            // (วนลูปสร้างตาราง)
            result.data.forEach(emp => {
                tbody.innerHTML += `
                    <tr>
                        <td><strong>${emp.emp_id}</strong></td>
                        <td>${emp.first_name_th} ${emp.last_name_th}</td>
                        <td>${emp.position_name_th || '<span class="text-muted">-</span>'}</td>
                        <td>${emp.dept_name_th || '<span class="text-muted">-</span>'}</td>
                        <td>${(emp.company_name_th || '')} / ${emp.branch_name_th || ''}</td>
                        <td>${renderEmployeeStatus(emp.status)}</td>
                        <td>
                            <!-- (เราจะสร้างหน้าเหล่านี้ใน Phase ต่อไป) -->
                            <a href="employee_view.php?id=${emp.id}" class="btn btn-info btn-sm" title="ดูข้อมูล">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="employee_edit.php?id=${emp.id}" class="btn btn-warning btn-sm" title="แก้ไข">
                                <i class="fas fa-pencil-alt"></i>
                            </a>
                            <button class="btn btn-danger btn-sm btn-delete-employee" data-id="${emp.id}" title="ลบ">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

        } else {
            throw new Error(result.message || 'API ตอบกลับ Error');
        }

    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-3">เกิดข้อผิดพลาด: ${error.message}</td></tr>`;
        console.error('Fetch Error (Load Employees):', error);
    }
}

/**
 * (Helper) สร้าง Badge สถานะพนักงาน
 */
function renderEmployeeStatus(status) {
    let badgeClass = 'bg-secondary';
    let statusText = status.charAt(0).toUpperCase() + status.slice(1); // (e.g., Active)

    switch (status) {
        case 'active':
            badgeClass = 'bg-success';
            statusText = 'ปฏิบัติงาน';
            break;
        case 'probation':
            badgeClass = 'bg-info';
            statusText = 'ทดลองงาน';
            break;
        case 'resigned':
            badgeClass = 'bg-danger';
            statusText = 'ลาออก';
            break;
    }
    return `<span class="badge ${badgeClass}">${statusText}</span>`;
}