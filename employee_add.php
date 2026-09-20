<?php
/*
 * หน้าฟอร์มสำหรับ "เพิ่ม" พนักงานใหม่ (Updated for Shift Assignment)
 */
require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';
require_once 'includes/employee_access_helpers.php';
employeeAccessRequirePage($mysqli, null, true);
require_once 'includes/hr_scope_helpers.php';

// ----- ดึงข้อมูล Master Data -----
try {
    $employeeOptions = employeeAccessFormOptions($mysqli);
    $companies = $employeeOptions['companies'];
    $branches = $employeeOptions['branches'];
    @$departments = $mysqli->query("SELECT id, dept_name_th FROM departments ORDER BY dept_name_th")->fetch_all(MYSQLI_ASSOC);
    @$positions = $mysqli->query("SELECT id, position_name_th FROM positions ORDER BY position_name_th")->fetch_all(MYSQLI_ASSOC);
    @$emp_types = $mysqli->query("SELECT id, type_name FROM employment_types ORDER BY type_name")->fetch_all(MYSQLI_ASSOC);
    $supervisors = $employeeOptions['supervisors'];
    $hrCompanies = $_SESSION['role'] === 'admin' ? $companies : [];
    $hrBranches = $_SESSION['role'] === 'admin' ? $branches : [];
    
    // (NEW) ดึงข้อมูลกะการทำงาน
    @$shifts = $mysqli->query("SELECT id, shift_name, start_time, end_time FROM work_shifts ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    $companies = $branches = $departments = $positions = $emp_types = $supervisors = $shifts = $hrCompanies = $hrBranches = [];
    $db_error = $e->getMessage();
}

$page_title = "เพิ่มพนักงานใหม่";
$weekDays = [
    'Mon' => 'Mon',
    'Tue' => 'Tue',
    'Wed' => 'Wed',
    'Thu' => 'Thu',
    'Fri' => 'Fri',
    'Sat' => 'Sat',
    'Sun' => 'Sun',
];

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
                        <input type="file" class="form-control" name="profile_image" id="profileImageInput" aria-label="อัปโหลดรูปโปรไฟล์" accept="image/*">
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
                            <label class="form-label" for="employee_addField1">คำนำหน้า (ไทย) <span class="text-danger">*</span></label>
                            <select id="employee_addField1" name="title_th" class="form-select" required>
                                <option value="นาย">นาย</option>
                                <option value="นาง">นาง</option>
                                <option value="นางสาว">นางสาว</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="employee_addField2">ชื่อ (ไทย) <span class="text-danger">*</span></label>
                            <input id="employee_addField2" type="text" class="form-control" name="first_name_th" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="employee_addField3">นามสกุล (ไทย) <span class="text-danger">*</span></label>
                            <input id="employee_addField3" type="text" class="form-control" name="last_name_th" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="employee_addField4">ชื่อเล่น</label>
                            <input id="employee_addField4" type="text" class="form-control" name="nickname" maxlength="100">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="employee_addField5">คำนำหน้า (Eng)</label>
                            <select id="employee_addField5" name="title_en" class="form-select">
                                <option value="Mr.">Mr.</option>
                                <option value="Mrs.">Mrs.</option>
                                <option value="Miss">Miss</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="employee_addField6">ชื่อ (Eng)</label>
                            <input id="employee_addField6" type="text" class="form-control" name="first_name_en">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="employee_addField7">นามสกุล (Eng)</label>
                            <input id="employee_addField7" type="text" class="form-control" name="last_name_en">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="employee_addField8">เลขบัตรประชาชน <span class="text-danger">*</span></label>
                            <input id="employee_addField8" type="text" class="form-control" name="citizen_id" maxlength="13" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="employee_addField9">วันเกิด <span class="text-danger">*</span></label>
                            <input id="employee_addField9" type="date" class="form-control" name="birth_date" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="employee_addField10">เพศ <span class="text-danger">*</span></label>
                            <select id="employee_addField10" name="gender" class="form-select" required>
                                <option value="male">ชาย</option>
                                <option value="female">หญิง</option>
                                <option value="other">อื่นๆ</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="employee_addField11">สถานภาพสมรส</label>
                            <select id="employee_addField11" name="marital_status" class="form-select">
                                <option value="single">โสด</option>
                                <option value="married">สมรส</option>
                                <option value="divorced">หย่าร้าง</option>
                                <option value="widowed">หม้าย</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="employee_addField12">ศาสนา</label>
                            <select id="employee_addField12" name="religion" class="form-select">
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
                            <label class="form-label" for="employee_addField13">กรุ๊ปเลือด</label>
                            <select id="employee_addField13" name="blood_group" class="form-select">
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
                            <label class="form-label" for="employee_addField14">ที่อยู่ (บ้านเลขที่, หมู่, ถนน)</label>
                            <textarea id="employee_addField14" class="form-control" name="current_address" rows="1" placeholder="เช่น 123/45 หมู่ 1 ต.บ้านนา"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="provinceSelect">จังหวัด</label>
                            <select id="provinceSelect" name="province" class="form-select">
                                <option value="">-- เลือกจังหวัด --</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="districtSelect">อำเภอ/เขต</label>
                            <select id="districtSelect" name="district" class="form-select" disabled>
                                <option value="">-- เลือกอำเภอ --</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="employee_addField15">เบอร์โทรศัพท์มือถือ</label>
                            <input id="employee_addField15" type="text" class="form-control" name="phone_number" maxlength="10">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="employee_addField16">รหัสไปรษณีย์</label>
                            <input id="employee_addField16" type="text" class="form-control" name="postal_code" maxlength="10" inputmode="numeric">
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
                            <label class="form-label" for="companySelect">บริษัท <span class="text-danger">*</span></label>
                            <select id="companySelect" name="company_id" class="form-select" required>
                                <option value="">-- เลือกบริษัท --</option>
                                <?php foreach ($companies as $item): ?>
                                    <option value="<?php echo $item['id']; ?>"><?php echo $item['company_name_th']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="branchSelect">สาขา <span class="text-danger">*</span></label>
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
                            <label class="form-label" for="employee_addField17">แผนก <span class="text-danger">*</span></label>
                            <select id="employee_addField17" name="department_id" class="form-select" required>
                                <?php foreach ($departments as $item): ?><option value="<?php echo $item['id']; ?>"><?php echo $item['dept_name_th']; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="employee_addField18">ตำแหน่ง <span class="text-danger">*</span></label>
                            <select id="employee_addField18" name="position_id" class="form-select" required>
                                <?php foreach ($positions as $item): ?><option value="<?php echo $item['id']; ?>"><?php echo $item['position_name_th']; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="employee_addField19">ประเภทการจ้างงาน <span class="text-danger">*</span></label>
                            <select id="employee_addField19" name="employment_type_id" class="form-select" required>
                                <?php foreach ($emp_types as $item): ?><option value="<?php echo $item['id']; ?>"><?php echo $item['type_name']; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="employee_addField20">หัวหน้างาน</label>
                            <select id="employee_addField20" name="supervisor_id" class="form-select">
                                <option value="">-- ไม่มีหัวหน้างาน --</option>
                                <?php foreach ($supervisors as $item): ?><option value="<?php echo $item['id']; ?>"><?php echo $item['first_name_th'] . ' ' . $item['last_name_th']; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- (NEW) กะการทำงาน (Default Shift) -->
                        <div class="col-md-6">
                            <label class="form-label" for="employee_addField21">กะการทำงาน (Default Shift) <span class="text-danger">*</span></label>
                            <select id="employee_addField21" name="default_shift_id" class="form-select" required>
                                <option value="">-- เลือกกะการทำงาน --</option>
                                <?php foreach ($shifts as $s): ?>
                                    <option value="<?php echo $s['id']; ?>">
                                        <?php echo $s['shift_name'] . ' (' . substr($s['start_time'],0,5) . '-' . substr($s['end_time'],0,5) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="employee_addField22">วันที่เริ่มใช้กะ <span class="text-danger">*</span></label>
                            <input id="employee_addField22" type="date" class="form-control" name="shift_effective_from" data-shift-effective-from required>
                            <small class="text-muted">สำหรับพนักงานใหม่ควรตรงกับวันที่เริ่มงาน</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="employee_addField23">เหตุผลการกำหนดกะ</label>
                            <input id="employee_addField23" type="text" class="form-control" name="shift_assignment_reason" value="Initial shift assignment">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" for="employee_addField24">วันที่เริ่มงาน <span class="text-danger">*</span></label>
                            <input id="employee_addField24" type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="employee_addField25">สถานะพนักงาน <span class="text-danger">*</span></label>
                            <select id="employee_addField25" name="status" class="form-select" required>
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
                            <label class="form-label" for="employee_addField26">ระดับการศึกษา</label>
                            <select id="employee_addField26" name="education_level" class="form-select">
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
                            <label class="form-label" for="employee_addField27">บุคคลติดต่อฉุกเฉิน</label>
                            <input id="employee_addField27" type="text" class="form-control" name="emergency_contact_name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="employee_addField28">เบอร์โทรผู้ติดต่อฉุกเฉิน</label>
                            <input id="employee_addField28" type="text" class="form-control" name="emergency_contact_phone" maxlength="10">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-light">Weekly Shift Override</div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Use when this employee has different work time on selected weekday(s). Leave weekdays blank if the default shift always applies.</p>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Override weekdays</label>
                            <div class="d-flex flex-wrap gap-3">
                                <?php foreach ($weekDays as $dayValue => $dayLabel): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="shift_override_days[]" value="<?php echo $dayValue; ?>" id="shiftOverride<?php echo $dayValue; ?>">
                                        <label class="form-check-label" for="shiftOverride<?php echo $dayValue; ?>"><?php echo $dayLabel; ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="shiftOverrideStartTime">เวลาเริ่มงาน</label>
                            <input type="time" class="form-control" name="shift_override_start_time" id="shiftOverrideStartTime">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="employee_addField29">เวลาเลิกงาน</label>
                            <input id="employee_addField29" type="time" class="form-control" name="shift_override_end_time">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="employee_addField30">ผ่อนผันมาสาย (นาที)</label>
                            <input id="employee_addField30" type="number" min="0" class="form-control" name="shift_override_late_tolerance_mins" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="employee_addField31">เริ่มมีผลวันที่</label>
                            <input id="employee_addField31" type="date" class="form-control" name="shift_override_effective_from" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="employee_addField32">Effective to</label>
                            <input id="employee_addField32" type="date" class="form-control" name="shift_override_effective_to">
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Account -->
            <div class="card mb-3">
                <div class="card-header bg-light" id="accountPermissions" tabindex="-1">บัญชีผู้ใช้และสิทธิ์</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="employee_addField33">ชื่อผู้ใช้</label>
                            <input id="employee_addField33" type="text" class="form-control" name="username">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="employee_addField34">Password</label>
                            <input id="employee_addField34" type="password" class="form-control" name="password">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="employee_addField35">สิทธิ์การใช้งาน</label>
                            <select id="employee_addField35" name="role" class="form-select" aria-describedby="accountRoleHelp">
                                <option value="employee">พนักงาน</option>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                <option value="manager">หัวหน้างาน</option>
                                <option value="hr">ฝ่ายบุคคล</option>
                                <option value="admin">ผู้ดูแลระบบ</option>
                                <?php endif; ?>
                            </select>
                            <p class="small text-muted mt-2 mb-0" id="accountRoleHelp" role="status"></p>
                        </div>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <div class="col-12 hr-scope-section" style="display: none;">
                            <div class="border rounded p-3 bg-light">
                                <div class="fw-semibold mb-2">ขอบเขตสิทธิ์ HR</div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label" for="employee_addField36">HR บริษัท</label>
                                        <select id="employee_addField36" name="hr_company_ids[]" class="form-select" multiple size="6">
                                            <?php foreach ($hrCompanies as $company): ?>
                                                <option value="<?php echo (int)$company['id']; ?>">
                                                    <?php echo htmlspecialchars($company['company_name_th']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">เลือกได้มากกว่า 1 บริษัท</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="employee_addField37">HR สาขา</label>
                                        <select id="employee_addField37" name="hr_branch_ids[]" class="form-select" multiple size="6">
                                            <?php foreach ($hrBranches as $branch): ?>
                                                <option value="<?php echo (int)$branch['id']; ?>" data-company-id="<?php echo (int)$branch['company_id']; ?>">
                                                    <?php echo htmlspecialchars($branch['company_name_th'] . ' - ' . $branch['branch_name_th']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">เลือกได้มากกว่า 1 สาขา</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    var startDate = document.querySelector('input[name="start_date"]');
    var shiftEffectiveFrom = document.querySelector('[data-shift-effective-from]');
    if (!startDate || !shiftEffectiveFrom) return;
    startDate.addEventListener('change', function () {
        if (!shiftEffectiveFrom.value) {
            shiftEffectiveFrom.value = startDate.value;
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
