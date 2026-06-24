<?php
/*
 * หน้าฟอร์มสำหรับ "แก้ไข" ข้อมูลพนักงาน (Updated for Create User)
 */
require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';
require_once 'includes/hr_scope_helpers.php';
require_once 'includes/employee_shift_assignment_helpers.php';

// 1. รับค่า ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: employees.php");
    exit();
}

// 2. ดึงข้อมูลพนักงานเดิม
try {
    $sql = "SELECT e.*, u.id AS user_id, u.username, u.role
            FROM employees e
            LEFT JOIN users u ON e.id = u.employee_id
            WHERE e.id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $emp = $result->fetch_assoc();

    if (!$emp) die("ไม่พบข้อมูลพนักงาน");

    // ดึง Master Data
    @$companies = $mysqli->query("SELECT id, company_name_th FROM companies ORDER BY company_name_th")->fetch_all(MYSQLI_ASSOC);
    @$branches = $mysqli->query("SELECT id, branch_name_th, company_id FROM branches ORDER BY branch_name_th")->fetch_all(MYSQLI_ASSOC);
    @$departments = $mysqli->query("SELECT id, dept_name_th FROM departments ORDER BY dept_name_th")->fetch_all(MYSQLI_ASSOC);
    @$positions = $mysqli->query("SELECT id, position_name_th FROM positions ORDER BY position_name_th")->fetch_all(MYSQLI_ASSOC);
    @$emp_types = $mysqli->query("SELECT id, type_name FROM employment_types ORDER BY type_name")->fetch_all(MYSQLI_ASSOC);
    @$supervisors = $mysqli->query("SELECT id, first_name_th, last_name_th FROM employees WHERE status = 'active' AND id != $id ORDER BY first_name_th")->fetch_all(MYSQLI_ASSOC);
    @$shifts = $mysqli->query("SELECT id, shift_name, start_time, end_time FROM work_shifts ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
    @$hrCompanies = $mysqli->query("SELECT id, company_name_th FROM companies ORDER BY company_name_th")->fetch_all(MYSQLI_ASSOC);
    @$hrBranches = $mysqli->query("SELECT b.id, b.branch_name_th, b.company_id, c.company_name_th
                                   FROM branches b
                                   JOIN companies c ON b.company_id = c.id
                                   ORDER BY c.company_name_th, b.branch_name_th")->fetch_all(MYSQLI_ASSOC);
    $hrScopes = !empty($emp['user_id']) ? hrScopeFetchForUser($mysqli, (int)$emp['user_id']) : ['company_ids' => [], 'branch_ids' => []];
    $shiftOverride = null;
    $shiftOverrideStmt = $mysqli->prepare("SELECT day_of_week, start_time, end_time, late_tolerance_mins, effective_from, effective_to
                                           FROM employee_shift_overrides
                                           WHERE employee_id = ? AND is_active = 1
                                           ORDER BY effective_from DESC, id DESC
                                           LIMIT 1");
    if ($shiftOverrideStmt) {
        $shiftOverrideStmt->bind_param('i', $id);
        $shiftOverrideStmt->execute();
        $shiftOverride = $shiftOverrideStmt->get_result()->fetch_assoc();
    }
    $shiftAssignmentHistory = employeeShiftAssignmentsFetchHistory($mysqli, $id);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

$page_title = "แก้ไขข้อมูล: " . $emp['first_name_th'];
$shiftOverrideDays = array_filter(array_map('trim', explode(',', (string)($shiftOverride['day_of_week'] ?? ''))));
$weekDays = [
    'Mon' => 'จันทร์',
    'Tue' => 'อังคาร',
    'Wed' => 'พุธ',
    'Thu' => 'พฤหัสบดี',
    'Fri' => 'ศุกร์',
    'Sat' => 'เสาร์',
    'Sun' => 'อาทิตย์',
];

$use_select2 = true;
require_once 'includes/header.php';
?>

<style>
    .employee-general-card {
        border-color: #f0dada;
        overflow: hidden;
    }

    .employee-general-card .card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        background: #fffafa;
        border-bottom-color: #f5caca;
    }

    .employee-section-note {
        margin: 0.25rem 0 0;
        color: #6b7280;
        font-size: 0.9rem;
        font-weight: 400;
    }

    .employee-general-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 1rem;
        align-items: end;
    }

    .employee-general-grid .form-label {
        margin-bottom: 0.4rem;
        color: #374151;
        font-size: 0.92rem;
        font-weight: 600;
    }

    .employee-general-grid .form-control,
    .employee-general-grid .form-select,
    .employee-general-grid .select2-container--default .select2-selection--single {
        min-height: 42px;
    }

    .field-span-1 { grid-column: span 1; }
    .field-span-2 { grid-column: span 2; }
    .field-span-3 { grid-column: span 3; }

    .employee-field-break {
        grid-column: 1 / -1;
        height: 1px;
        background: #f3e7e7;
        margin: 0.2rem 0;
    }

    @media (max-width: 1199.98px) {
        .employee-general-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .employee-general-grid {
            grid-template-columns: 1fr;
        }

        .field-span-1,
        .field-span-2,
        .field-span-3 {
            grid-column: 1 / -1;
        }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-0">แก้ไขข้อมูลพนักงาน</h1>
        <p class="text-muted small">เลขบัตรประชาชน: <?php echo $emp['citizen_id']; ?></p>
    </div>
    <a href="employees.php" class="btn btn-outline-secondary">
        <i class="fas fa-chevron-left"></i> กลับไปหน้ารายการ
    </a>
</div>

<div class="card">
    <div class="card-body">
        
        <form id="editEmployeeForm" enctype="multipart/form-data"
              data-province="<?php echo htmlspecialchars($emp['province'] ?? ''); ?>"
              data-district="<?php echo htmlspecialchars($emp['district'] ?? ''); ?>">
            
            <input type="hidden" name="id" value="<?php echo $emp['id']; ?>">
            <input type="hidden" name="old_profile_image" value="<?php echo htmlspecialchars($emp['profile_img_url'] ?? 'assets/img/user.png'); ?>">

            <!-- รูปโปรไฟล์ -->
             <div class="card mb-3 border-warning">
                <div class="card-header bg-warning text-dark">
                    <i class="fas fa-camera"></i> รูปโปรไฟล์
                </div>
                <div class="card-body text-center">
                    <div class="mb-3">
                        <?php 
                            $img_src = (!empty($emp['profile_img_url']) && $emp['profile_img_url'] !== 'default.png') ? $emp['profile_img_url'] : 'assets/img/user.png';
                        ?>
                        <img id="previewImage" src="<?php echo htmlspecialchars($img_src); ?>" class="img-thumbnail rounded-circle" style="width: 150px; height: 150px; object-fit: cover;" onerror="this.onerror=null;this.src='assets/img/user.png';">
                    </div>
                    <div class="col-md-6 mx-auto">
                        <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
                        <input type="file" class="form-control" name="profile_image" id="profileImageInput" accept="image/jpeg,image/png,image/webp,image/gif">
                        <small class="text-muted">อัปโหลดใหม่เพื่อเปลี่ยนรูป (ถ้าไม่เลือก จะใช้รูปเดิม)</small>
                    </div>
                </div>
            </div>

            <!-- ข้อมูลส่วนตัว -->
            <div class="card mb-3 employee-general-card">
                <div class="card-header">
                    <div>
                        <div>ข้อมูลส่วนตัว</div>
                        <p class="employee-section-note">จัดกลุ่มชื่อไทย ชื่ออังกฤษ และข้อมูลประจำตัวให้อ่านง่ายขึ้น</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="employee-general-grid">
                        <div class="field-span-1">
                            <label class="form-label">คำนำหน้า (ไทย) <span class="text-danger">*</span></label>
                            <select name="title_th" class="form-select" required>
                                <?php foreach(['นาย','นาง','นางสาว'] as $v) echo "<option value='$v' ".($emp['prefix_th']==$v?'selected':'').">$v</option>"; ?>
                            </select>
                        </div>
                        <div class="field-span-2">
                            <label class="form-label">ชื่อ (ไทย) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="first_name_th" value="<?php echo $emp['first_name_th']; ?>" required>
                        </div>
                        <div class="field-span-2">
                            <label class="form-label">นามสกุล (ไทย) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="last_name_th" value="<?php echo $emp['last_name_th']; ?>" required>
                        </div>
                        <div class="field-span-1">
                            <label class="form-label">ชื่อเล่น</label>
                            <input type="text" class="form-control" name="nickname" maxlength="100" value="<?php echo htmlspecialchars($emp['nickname'] ?? ''); ?>">
                        </div>

                        <div class="employee-field-break"></div>

                        <div class="field-span-1">
                            <label class="form-label">คำนำหน้า (Eng)</label>
                            <select name="title_en" class="form-select">
                                <?php foreach(['Mr.','Mrs.','Miss'] as $v) echo "<option value='$v' ".($emp['prefix_en']==$v?'selected':'').">$v</option>"; ?>
                            </select>
                        </div>
                        <div class="field-span-2">
                            <label class="form-label">ชื่อ (Eng)</label>
                            <input type="text" class="form-control" name="first_name_en" value="<?php echo $emp['first_name_en']; ?>">
                        </div>
                        <div class="field-span-3">
                            <label class="form-label">นามสกุล (Eng)</label>
                            <input type="text" class="form-control" name="last_name_en" value="<?php echo $emp['last_name_en']; ?>">
                        </div>

                        <div class="employee-field-break"></div>

                        <div class="field-span-2">
                            <label class="form-label">เลขบัตรประชาชน <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="citizen_id" maxlength="13" value="<?php echo $emp['citizen_id']; ?>" required>
                        </div>
                        <div class="field-span-2">
                            <label class="form-label">วันเกิด <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="birth_date" value="<?php echo $emp['birth_date']; ?>" required>
                        </div>
                        <div class="field-span-1">
                            <label class="form-label">เพศ <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select" required>
                                <option value="male" <?php echo $emp['gender']=='male'?'selected':''; ?>>ชาย</option>
                                <option value="female" <?php echo $emp['gender']=='female'?'selected':''; ?>>หญิง</option>
                                <option value="other" <?php echo $emp['gender']=='other'?'selected':''; ?>>อื่นๆ</option>
                            </select>
                        </div>

                        <div class="field-span-1">
                            <label class="form-label">สถานภาพสมรส</label>
                            <select name="marital_status" class="form-select">
                                <?php 
                                $statuses = ['single'=>'โสด', 'married'=>'สมรส', 'divorced'=>'หย่าร้าง', 'widowed'=>'หม้าย'];
                                foreach($statuses as $k=>$v) echo "<option value='$k' ".($emp['marital_status']==$k?'selected':'').">$v</option>"; 
                                ?>
                            </select>
                        </div>
                        <div class="field-span-2">
                            <label class="form-label">ศาสนา</label>
                            <select name="religion" class="form-select">
                                <option value="">-- ระบุศาสนา --</option>
                                <?php foreach(['พุทธ','อิสลาม','คริสต์','ฮินดู','ซิกข์','ไม่มีศาสนา','อื่นๆ'] as $v) echo "<option value='$v' ".($emp['religion']==$v?'selected':'').">$v</option>"; ?>
                            </select>
                        </div>
                        <div class="field-span-1">
                            <label class="form-label">กรุ๊ปเลือด</label>
                            <select name="blood_group" class="form-select">
                                <option value="">-- ไม่ระบุ --</option>
                                <?php foreach(['A','B','O','AB'] as $v) echo "<option value='$v' ".($emp['blood_group']==$v?'selected':'').">$v</option>"; ?>
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
                            <label class="form-label">ที่อยู่</label>
                            <textarea class="form-control" name="current_address" rows="1"><?php echo $emp['current_address']; ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">จังหวัด</label>
                            <select id="provinceSelect" name="province" class="form-select">
                                <option value="">-- เลือกจังหวัด --</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">อำเภอ/เขต</label>
                            <select id="districtSelect" name="district" class="form-select">
                                <option value="">-- เลือกอำเภอ --</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">เบอร์โทรศัพท์มือถือ</label>
                            <input type="text" class="form-control" name="phone_number" maxlength="10" value="<?php echo $emp['phone_number']; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">รหัสไปรษณีย์</label>
                            <input type="text" class="form-control" name="postal_code" maxlength="10" inputmode="numeric" value="<?php echo htmlspecialchars($emp['postal_code'] ?? ''); ?>">
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
                                <?php foreach ($companies as $item): ?>
                                    <option value="<?php echo $item['id']; ?>" <?php echo ($emp['company_id']==$item['id'])?'selected':''; ?>>
                                        <?php echo $item['company_name_th']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">สาขา <span class="text-danger">*</span></label>
                            <select id="branchSelect" name="branch_id" class="form-select" required>
                                <?php foreach ($branches as $item): ?>
                                    <option value="<?php echo $item['id']; ?>" 
                                            data-company-id="<?php echo $item['company_id']; ?>" 
                                            <?php echo ($emp['branch_id']==$item['id'])?'selected':''; ?>
                                            style="<?php echo ($emp['company_id']==$item['company_id'])?'':'display:none'; ?>">
                                        <?php echo $item['branch_name_th']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">แผนก</label>
                            <select name="department_id" class="form-select" required>
                                <?php foreach ($departments as $item): ?>
                                    <option value="<?php echo $item['id']; ?>" <?php echo ($emp['department_id']==$item['id'])?'selected':''; ?>><?php echo $item['dept_name_th']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ตำแหน่ง</label>
                            <select name="position_id" class="form-select" required>
                                <?php foreach ($positions as $item): ?>
                                    <option value="<?php echo $item['id']; ?>" <?php echo ($emp['position_id']==$item['id'])?'selected':''; ?>><?php echo $item['position_name_th']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ประเภทการจ้างงาน</label>
                            <select name="employment_type_id" class="form-select" required>
                                <?php foreach ($emp_types as $item): ?>
                                    <option value="<?php echo $item['id']; ?>" <?php echo ($emp['employment_type_id']==$item['id'])?'selected':''; ?>><?php echo $item['type_name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">หัวหน้างาน</label>
                            <select name="supervisor_id" class="form-select">
                                <option value="">-- ไม่มีหัวหน้างาน --</option>
                                <?php foreach ($supervisors as $item): ?>
                                    <option value="<?php echo $item['id']; ?>" <?php echo ($emp['supervisor_id']==$item['id'])?'selected':''; ?>><?php echo $item['first_name_th'].' '.$item['last_name_th']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">กะการทำงาน (Default Shift) <span class="text-danger">*</span></label>
                            <select name="default_shift_id" class="form-select" required>
                                <option value="">-- เลือกกะการทำงาน --</option>
                                <?php foreach ($shifts as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo ($emp['default_shift_id']==$s['id'])?'selected':''; ?>>
                                        <?php echo $s['shift_name'] . ' (' . substr($s['start_time'],0,5) . '-' . substr($s['end_time'],0,5) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">วันที่เริ่มใช้กะใหม่</label>
                            <input type="date" class="form-control" name="shift_effective_from" value="<?php echo date('Y-m-d'); ?>" data-native-date-picker="true">
                            <small class="text-muted">ใช้เมื่อเปลี่ยนกะ เพื่อไม่กระทบย้อนหลัง</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">เหตุผลการเปลี่ยนกะ</label>
                            <input type="text" class="form-control" name="shift_assignment_reason" placeholder="เช่น ย้ายแผนก / เปลี่ยนรอบงาน">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">วันที่เริ่มงาน</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo $emp['start_date']; ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">สถานะ</label>
                            <select name="status" class="form-select" required>
                                <option value="active" <?php echo $emp['status']=='active'?'selected':''; ?>>Active (ปฏิบัติงาน)</option>
                                <option value="probation" <?php echo $emp['status']=='probation'?'selected':''; ?>>Probation (ทดลองงาน)</option>
                                <option value="resigned" <?php echo $emp['status']=='resigned'?'selected':''; ?>>Resigned (ลาออก)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-light">ประวัติกะการทำงาน</div>
                <div class="card-body">
                    <?php if (empty($shiftAssignmentHistory)): ?>
                        <div class="text-muted">ยังไม่มีประวัติกะ ระบบจะสร้างจากกะปัจจุบันเมื่อบันทึกครั้งถัดไป</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>กะ</th>
                                        <th>เริ่มใช้</th>
                                        <th>สิ้นสุด</th>
                                        <th>เหตุผล</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($shiftAssignmentHistory as $assignment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($assignment['shift_name'] . ' (' . substr((string)$assignment['start_time'], 0, 5) . '-' . substr((string)$assignment['end_time'], 0, 5) . ')'); ?></td>
                                            <td><?php echo htmlspecialchars((string)$assignment['effective_from']); ?></td>
                                            <td><?php echo htmlspecialchars((string)($assignment['effective_to'] ?: 'ปัจจุบัน')); ?></td>
                                            <td><?php echo htmlspecialchars((string)($assignment['reason'] ?? '')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
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
                                <?php foreach(['ต่ำกว่าปริญญาตรี','ปวช.','ปวส.','ปริญญาตรี','ปริญญาโท','ปริญญาเอก'] as $v) echo "<option value='$v' ".($emp['education_level']==$v?'selected':'').">$v</option>"; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">บุคคลติดต่อฉุกเฉิน</label>
                            <input type="text" class="form-control" name="emergency_contact_name" value="<?php echo $emp['emergency_contact_name']; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">เบอร์โทรผู้ติดต่อฉุกเฉิน</label>
                            <input type="text" class="form-control" name="emergency_contact_phone" maxlength="10" value="<?php echo $emp['emergency_contact_phone']; ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-light">กำหนดกะพิเศษรายสัปดาห์</div>
                <div class="card-body">
                    <p class="text-muted small mb-3">ใช้เมื่อพนักงานมีเวลาทำงานแตกต่างจากกะปกติในวันประจำสัปดาห์ที่เลือก หากต้องการลบกะพิเศษ ให้ไม่ต้องเลือกวันใด ๆ</p>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">วันที่ต้องการใช้กะพิเศษ</label>
                            <div class="d-flex flex-wrap gap-3">
                                <?php foreach ($weekDays as $dayValue => $dayLabel): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="shift_override_days[]" value="<?php echo $dayValue; ?>" id="shiftOverride<?php echo $dayValue; ?>" <?php echo in_array($dayValue, $shiftOverrideDays, true) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="shiftOverride<?php echo $dayValue; ?>"><?php echo $dayLabel; ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">เวลาเริ่มงาน</label>
                            <input type="time" class="form-control" name="shift_override_start_time" value="<?php echo htmlspecialchars(substr((string)($shiftOverride['start_time'] ?? ''), 0, 5)); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">เวลาเลิกงาน</label>
                            <input type="time" class="form-control" name="shift_override_end_time" value="<?php echo htmlspecialchars(substr((string)($shiftOverride['end_time'] ?? ''), 0, 5)); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">อนุโลมสาย (นาที)</label>
                            <input type="number" min="0" class="form-control" name="shift_override_late_tolerance_mins" value="<?php echo htmlspecialchars((string)($shiftOverride['late_tolerance_mins'] ?? '0')); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">วันที่เริ่มใช้</label>
                            <input type="date" class="form-control" name="shift_override_effective_from" value="<?php echo htmlspecialchars((string)($shiftOverride['effective_from'] ?? date('Y-m-d'))); ?>" data-native-date-picker="true">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">วันที่สิ้นสุด</label>
                            <input type="date" class="form-control" name="shift_override_effective_to" value="<?php echo htmlspecialchars((string)($shiftOverride['effective_to'] ?? '')); ?>" data-native-date-picker="true">
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Account -->
            <div class="card mb-3">
                <div class="card-header bg-light">User Account (แก้ไข Login)</div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- (แก้ไข) ตรวจสอบว่ามี Username หรือยัง -->
                        <div class="col-md-4">
                            <label class="form-label">Username</label>
                            <?php if (!empty($emp['username'])): ?>
                                <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($emp['username']); ?>">
                                <small class="text-muted">สามารถแก้ไข Username ได้</small>
                            <?php else: ?>
                                <input type="text" class="form-control" name="username" placeholder="กำหนด Username ใหม่">
                                <small class="text-success">ยังไม่มีบัญชี สามารถกำหนดใหม่ได้</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" autocomplete="new-password" placeholder="ปล่อยว่างถ้าไม่เปลี่ยน">
                            <?php if (empty($emp['username'])): ?>
                            <small class="text-danger">* จำเป็นต้องกรอกหากสร้าง User ใหม่</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select">
                                <?php foreach(['employee','manager','hr','admin'] as $r) echo "<option value='$r' ".($emp['role']==$r?'selected':'').">".ucfirst($r)."</option>"; ?>
                            </select>
                        </div>
                        <div class="col-12 hr-scope-section" style="display: none;">
                            <div class="border rounded p-3 bg-light">
                                <div class="fw-semibold mb-2">ขอบเขตสิทธิ์ HR</div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">HR บริษัท</label>
                                        <select name="hr_company_ids[]" class="form-select" multiple size="6">
                                            <?php foreach ($hrCompanies as $company): ?>
                                                <option value="<?php echo (int)$company['id']; ?>" <?php echo in_array((int)$company['id'], $hrScopes['company_ids'], true) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($company['company_name_th']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">เลือกได้มากกว่า 1 บริษัท</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">HR สาขา</label>
                                        <select name="hr_branch_ids[]" class="form-select" multiple size="6">
                                            <?php foreach ($hrBranches as $branch): ?>
                                                <option value="<?php echo (int)$branch['id']; ?>" data-company-id="<?php echo (int)$branch['company_id']; ?>" <?php echo in_array((int)$branch['id'], $hrScopes['branch_ids'], true) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($branch['company_name_th'] . ' - ' . $branch['branch_name_th']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">เลือกได้มากกว่า 1 สาขา</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4 mb-5">
                <button type="submit" class="btn btn-warning btn-lg px-5"><i class="fas fa-save"></i> บันทึกการแก้ไข</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
