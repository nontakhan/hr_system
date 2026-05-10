<?php
/*
 * หน้าฟอร์มสำหรับ "เพิ่ม" พนักงานใหม่ (Updated for Shift Assignment)
 */
require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php'; 

// ----- ดึงข้อมูล Master Data -----
try {
    @$companies = $mysqli->query("SELECT id, company_name_th FROM companies ORDER BY company_name_th")->fetch_all(MYSQLI_ASSOC);
    @$branches = $mysqli->query("SELECT id, branch_name_th, company_id FROM branches ORDER BY branch_name_th")->fetch_all(MYSQLI_ASSOC);
    @$departments = $mysqli->query("SELECT id, dept_name_th FROM departments ORDER BY dept_name_th")->fetch_all(MYSQLI_ASSOC);
    @$positions = $mysqli->query("SELECT id, position_name_th FROM positions ORDER BY position_name_th")->fetch_all(MYSQLI_ASSOC);
    @$emp_types = $mysqli->query("SELECT id, type_name FROM employment_types ORDER BY type_name")->fetch_all(MYSQLI_ASSOC);
    @$supervisors = $mysqli->query("SELECT id, first_name_th, last_name_th FROM employees WHERE status = 'active' ORDER BY first_name_th")->fetch_all(MYSQLI_ASSOC);
    
    // (NEW) ดึงข้อมูลกะการทำงาน
    @$shifts = $mysqli->query("SELECT id, shift_name, start_time, end_time FROM work_shifts ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    $companies = $branches = $departments = $positions = $emp_types = $supervisors = $shifts = [];
    $db_error = $e->getMessage();
}

$page_title = "เพิ่มพนักงานใหม่";
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
    <a href="employees.php" class="btn btn-outline-secondary">
        <i class="fas fa-chevron-left"></i> กลับไปหน้ารายการ
    </a>
</div>

<?php if (isset($db_error)): ?>
    <div class="alert alert-danger">DB Error: <?php echo $db_error; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <h5 class="card-title mb-4">กรุณากรอกข้อมูลพนักงาน</h5>

        <form id="addEmployeeForm" enctype="multipart/form-data">

            <!-- รูปโปรไฟล์ -->
             <div class="card mb-3 border-primary">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-camera"></i> รูปโปรไฟล์
                </div>
                <div class="card-body text-center">
                    <div class="mb-3">
                        <img id="previewImage" src="assets/img/user.png" class="img-thumbnail rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                    </div>
                    <div class="col-md-6 mx-auto">
                        <input type="file" class="form-control" name="profile_image" id="profileImageInput" accept="image/*">
                        <small class="text-muted">รองรับ .jpg, .png</small>
                    </div>
                </div>
            </div>

            <!-- ข้อมูลส่วนตัว -->
            <div class="card mb-3">
                <div class="card-header bg-light">ข้อมูลส่วนตัว</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">คำนำหน้า (ไทย) <span class="text-danger">*</span></label>
                            <select name="title_th" class="form-select" required>
                                <option value="นาย">นาย</option>
                                <option value="นาง">นาง</option>
                                <option value="นางสาว">นางสาว</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">ชื่อ (ไทย) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="first_name_th" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">นามสกุล (ไทย) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="last_name_th" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">คำนำหน้า (Eng)</label>
                            <select name="title_en" class="form-select">
                                <option value="Mr.">Mr.</option>
                                <option value="Mrs.">Mrs.</option>
                                <option value="Miss">Miss</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">ชื่อ (Eng)</label>
                            <input type="text" class="form-control" name="first_name_en">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">นามสกุล (Eng)</label>
                            <input type="text" class="form-control" name="last_name_en">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">เลขบัตรประชาชน <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="citizen_id" maxlength="13" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">วันเกิด <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="birth_date" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">เพศ <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select" required>
                                <option value="male">ชาย</option>
                                <option value="female">หญิง</option>
                                <option value="other">อื่นๆ</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">สถานภาพสมรส</label>
                            <select name="marital_status" class="form-select">
                                <option value="single">โสด</option>
                                <option value="married">สมรส</option>
                                <option value="divorced">หย่าร้าง</option>
                                <option value="widowed">หม้าย</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ศาสนา</label>
                            <select name="religion" class="form-select">
                                <option value="">-- ระบุศาสนา --</option>
                                <option value="พุทธ">พุทธ</option>
                                <option value="อิสลาม">อิสลาม</option>
                                <option value="คริสต์">คริสต์</option>
                                <option value="ฮินดู">ฮินดู</option>
                                <option value="ซิกข์">ซิกข์</option>
                                <option value="ไม่มีศาสนา">ไม่มีศาสนา</option>
                                <option value="อื่นๆ">อื่นๆ</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">กรุ๊ปเลือด</label>
                            <select name="blood_group" class="form-select">
                                <option value="">-- ไม่ระบุ --</option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="O">O</option>
                                <option value="AB">AB</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ข้อมูลติดต่อ -->
            <div class="card mb-3">
                <div class="card-header bg-light">ข้อมูลติดต่อ</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">ที่อยู่ (บ้านเลขที่, หมู่, ถนน)</label>
                            <textarea class="form-control" name="current_address" rows="1" placeholder="เช่น 123/45 หมู่ 1 ต.บ้านนา"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">จังหวัด</label>
                            <select id="provinceSelect" name="province" class="form-select">
                                <option value="">-- เลือกจังหวัด --</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">อำเภอ/เขต</label>
                            <select id="districtSelect" name="district" class="form-select" disabled>
                                <option value="">-- เลือกอำเภอ --</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">เบอร์โทรศัพท์มือถือ</label>
                            <input type="text" class="form-control" name="phone_number" maxlength="10">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ข้อมูลการจ้างงาน -->
            <div class="card mb-3">
                <div class="card-header bg-light">ข้อมูลการจ้างงาน</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">บริษัท <span class="text-danger">*</span></label>
                            <select id="companySelect" name="company_id" class="form-select" required>
                                <option value="">-- เลือกบริษัท --</option>
                                <?php foreach ($companies as $item): ?>
                                    <option value="<?php echo $item['id']; ?>"><?php echo $item['company_name_th']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">สาขา <span class="text-danger">*</span></label>
                            <select id="branchSelect" name="branch_id" class="form-select" required disabled>
                                <option value="">-- (กรุณาเลือกบริษัทก่อน) --</option>
                                <?php foreach ($branches as $item): ?>
                                    <option value="<?php echo $item['id']; ?>" data-company-id="<?php echo $item['company_id']; ?>" style="display: none;">
                                        <?php echo $item['branch_name_th']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">แผนก <span class="text-danger">*</span></label>
                            <select name="department_id" class="form-select" required>
                                <?php foreach ($departments as $item): ?><option value="<?php echo $item['id']; ?>"><?php echo $item['dept_name_th']; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ตำแหน่ง <span class="text-danger">*</span></label>
                            <select name="position_id" class="form-select" required>
                                <?php foreach ($positions as $item): ?><option value="<?php echo $item['id']; ?>"><?php echo $item['position_name_th']; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ประเภทการจ้างงาน <span class="text-danger">*</span></label>
                            <select name="employment_type_id" class="form-select" required>
                                <?php foreach ($emp_types as $item): ?><option value="<?php echo $item['id']; ?>"><?php echo $item['type_name']; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">หัวหน้างาน</label>
                            <select name="supervisor_id" class="form-select">
                                <option value="">-- ไม่มีหัวหน้างาน --</option>
                                <?php foreach ($supervisors as $item): ?><option value="<?php echo $item['id']; ?>"><?php echo $item['first_name_th'] . ' ' . $item['last_name_th']; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- (NEW) กะการทำงาน (Default Shift) -->
                        <div class="col-md-6">
                            <label class="form-label">กะการทำงาน (Default Shift) <span class="text-danger">*</span></label>
                            <select name="default_shift_id" class="form-select" required>
                                <option value="">-- เลือกกะการทำงาน --</option>
                                <?php foreach ($shifts as $s): ?>
                                    <option value="<?php echo $s['id']; ?>">
                                        <?php echo $s['shift_name'] . ' (' . substr($s['start_time'],0,5) . '-' . substr($s['end_time'],0,5) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">วันที่เริ่มงาน <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">สถานะพนักงาน <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="probation">Probation (ทดลองงาน)</option>
                                <option value="active">Active (ปฏิบัติงาน)</option>
                                <option value="resigned">Resigned (ลาออก)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ข้อมูลเพิ่มเติม -->
            <div class="card mb-3">
                <div class="card-header bg-light">ข้อมูลเพิ่มเติม</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">ระดับการศึกษา</label>
                            <select name="education_level" class="form-select">
                                <option value="">-- ระบุระดับการศึกษา --</option>
                                <option value="ต่ำกว่าปริญญาตรี">ต่ำกว่าปริญญาตรี</option>
                                <option value="ปวช.">ปวช.</option>
                                <option value="ปวส.">ปวส.</option>
                                <option value="ปริญญาตรี">ปริญญาตรี</option>
                                <option value="ปริญญาโท">ปริญญาโท</option>
                                <option value="ปริญญาเอก">ปริญญาเอก</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">บุคคลติดต่อฉุกเฉิน</label>
                            <input type="text" class="form-control" name="emergency_contact_name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">เบอร์โทรผู้ติดต่อฉุกเฉิน</label>
                            <input type="text" class="form-control" name="emergency_contact_phone" maxlength="10">
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Account -->
            <div class="card mb-3">
                <div class="card-header bg-light">User Account (สำหรับ Login)</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select">
                                <option value="employee">Employee</option>
                                <option value="manager">Manager</option>
                                <option value="hr">HR</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4 mb-5">
                <button type="submit" class="btn btn-primary btn-lg px-5"><i class="fas fa-save"></i> บันทึกข้อมูล</button>
                <button type="reset" class="btn btn-outline-secondary px-4">ล้างฟอร์ม</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>