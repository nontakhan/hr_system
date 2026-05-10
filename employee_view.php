<?php
/*
 * หน้าแสดงรายละเอียดพนักงาน (Full Version)
 * - แสดงข้อมูลครบถ้วนในหน้าเดียว (No Tabs)
 * - รองรับการแสดงผลกะการทำงาน (Default Shift)
 * - เปลี่ยนการแสดงผลจาก รหัสพนักงาน เป็น เลขบัตรประชาชน
 */
require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    // Query ข้อมูลทั้งหมด รวมถึง work_shifts
    // (ตัด emp_id ออกจากจินตนาการ เพราะใน DB ไม่มีแล้ว)
    $sql = "SELECT e.*, 
            p.position_name_th, 
            d.dept_name_th, 
            c.company_name_th, 
            b.branch_name_th, 
            et.type_name,
            ws.shift_name, ws.start_time, ws.end_time,
            u.username, 
            CONCAT(s.first_name_th, ' ', s.last_name_th) AS supervisor_name
            FROM employees e
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN companies c ON e.company_id = c.id
            LEFT JOIN branches b ON e.branch_id = b.id
            LEFT JOIN employment_types et ON e.employment_type_id = et.id
            LEFT JOIN work_shifts ws ON e.default_shift_id = ws.id
            LEFT JOIN users u ON e.id = u.employee_id
            LEFT JOIN employees s ON e.supervisor_id = s.id
            WHERE e.id = ?";
            
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $emp = $stmt->get_result()->fetch_assoc();
}

if (!$emp) { header("Location: employees.php"); exit(); }

// Helper สำหรับคำนวณอายุ
function calculateAge($dob) {
    if (!$dob) return '-';
    $diff = date_diff(date_create($dob), date_create('today'));
    return $diff->y;
}

// Helper สำหรับแปลงสถานะสมรส
function getMaritalStatus($status) {
    $map = ['single' => 'โสด', 'married' => 'สมรส', 'divorced' => 'หย่าร้าง', 'widowed' => 'หม้าย'];
    return $map[$status] ?? '-';
}

// Helper สำหรับแปลงเพศ
function getGender($g) {
    if ($g == 'male') return 'ชาย';
    if ($g == 'female') return 'หญิง';
    return 'อื่นๆ';
}

// ดึง Master Data สำหรับ Modal โยกย้าย
try {
    @$companies = $mysqli->query("SELECT id, company_name_th FROM companies ORDER BY company_name_th")->fetch_all(MYSQLI_ASSOC);
    @$branches = $mysqli->query("SELECT id, branch_name_th, company_id FROM branches ORDER BY branch_name_th")->fetch_all(MYSQLI_ASSOC);
    @$departments = $mysqli->query("SELECT id, dept_name_th FROM departments ORDER BY dept_name_th")->fetch_all(MYSQLI_ASSOC);
    @$positions = $mysqli->query("SELECT id, position_name_th FROM positions ORDER BY position_name_th")->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) { /* Ignore */ }

$page_title = "ข้อมูลพนักงาน: " . $emp['first_name_th'];
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">ข้อมูลพนักงาน</h1>
        <!-- (แก้ไข) แสดงเลขบัตรประชาชนแทนรหัสพนักงาน -->
        <p class="text-muted small mb-0"><i class="fas fa-id-card"></i> เลขบัตร: <strong><?php echo $emp['citizen_id']; ?></strong></p>
    </div>
    <div>
        <a href="employees.php" class="btn btn-outline-secondary me-2"><i class="fas fa-arrow-left"></i> ย้อนกลับ</a>
        
        <?php if($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'hr'): ?>
        <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#transferModal">
            <i class="fas fa-exchange-alt"></i> โยกย้าย/ปรับตำแหน่ง
        </button>
        <?php endif; ?>

        <a href="employee_edit.php?id=<?php echo $emp['id']; ?>" class="btn btn-warning"><i class="fas fa-pencil-alt"></i> แก้ไขข้อมูล</a>
    </div>
</div>

<div class="row">
    <!-- Left Column (รูปโปรไฟล์และการ์ดสรุป) -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-0 sticky-top" style="top: 100px; z-index: 1;">
            <div class="card-body text-center pt-5 pb-4">
                <?php 
                    // Logic รูปภาพ (แก้ปัญหา default.png)
                    $fallback_avatar = "https://ui-avatars.com/api/?name=" . urlencode($emp['first_name_th']) . "&background=random&color=fff&size=256";
                    $img_src = (!empty($emp['profile_img_url']) && $emp['profile_img_url'] !== 'default.png') ? $emp['profile_img_url'] : 'assets/img/user.png'; 
                ?>
                <img src="<?php echo $img_src; ?>" 
                     onerror="this.onerror=null; this.src='<?php echo $fallback_avatar; ?>';" 
                     id="profileHistoryImage"
                     class="rounded-circle img-thumbnail mb-3 shadow-sm" 
                     style="width: 180px; height: 180px; object-fit: cover;">

                <h4 class="mb-1 text-dark fw-bold"><?php echo $emp['prefix_th'] . $emp['first_name_th'] . ' ' . $emp['last_name_th']; ?></h4>
                <p class="text-muted mb-2"><?php echo $emp['first_name_en'] . ' ' . $emp['last_name_en']; ?></p>
                
                <div class="badge bg-primary mb-3 px-3 py-2 fs-6 rounded-pill">
                    <?php echo $emp['position_name_th']; ?>
                </div>
                
                <div class="d-flex justify-content-center gap-2 mb-3">
                    <?php 
                        $status_color = ($emp['status']=='active'?'success':($emp['status']=='resigned'?'danger':'info'));
                        $status_label = ($emp['status']=='active'?'ปฏิบัติงาน':($emp['status']=='resigned'?'ลาออก':'ทดลองงาน'));
                    ?>
                    <span class="badge bg-<?php echo $status_color; ?>"><?php echo $status_label; ?></span>
                    <span class="badge bg-light text-dark border"><?php echo $emp['type_name']; ?></span>
                </div>
            </div>
            
            <div class="card-footer bg-white p-0">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between px-4 py-3">
                        <span class="text-muted"><i class="fas fa-building me-2"></i> สังกัด</span>
                        <span class="fw-bold text-end"><?php echo $emp['dept_name_th']; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-4 py-3">
                        <span class="text-muted"><i class="fas fa-calendar-check me-2"></i> เริ่มงาน</span>
                        <span><?php echo date('d/m/Y', strtotime($emp['start_date'])); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-4 py-3">
                        <span class="text-muted"><i class="fas fa-hourglass-half me-2"></i> อายุงาน</span>
                        <span>
                            <?php 
                                $diff = date_diff(date_create($emp['start_date']), date_create('today'));
                                echo $diff->y . " ปี " . $diff->m . " เดือน";
                            ?>
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Right Column (ข้อมูลละเอียด แบบ Single View) -->
    <div class="col-md-8">
        
        <!-- 1. ข้อมูลส่วนบุคคล -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 text-primary"><i class="fas fa-user-circle me-2"></i> ข้อมูลส่วนบุคคล</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6 col-md-4">
                        <label class="text-muted small">เลขบัตรประชาชน</label>
                        <div class="fw-bold text-dark"><?php echo $emp['citizen_id']; ?></div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <label class="text-muted small">วันเกิด</label>
                        <div class="fw-bold text-dark">
                            <?php echo date('d/m/Y', strtotime($emp['birth_date'])); ?> 
                            <span class="text-muted fw-normal">(อายุ <?php echo calculateAge($emp['birth_date']); ?> ปี)</span>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <label class="text-muted small">เพศ</label>
                        <div class="fw-bold text-dark"><?php echo getGender($emp['gender']); ?></div>
                    </div>
                    
                    <div class="col-sm-6 col-md-4">
                        <label class="text-muted small">หมู่เลือด</label>
                        <div class="fw-bold text-dark"><?php echo $emp['blood_group'] ?: '-'; ?></div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <label class="text-muted small">ศาสนา</label>
                        <div class="fw-bold text-dark"><?php echo $emp['religion'] ?: '-'; ?></div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <label class="text-muted small">สถานภาพสมรส</label>
                        <div class="fw-bold text-dark"><?php echo getMaritalStatus($emp['marital_status']); ?></div>
                    </div>
                    
                    <div class="col-12"><hr class="my-2 text-muted opacity-25"></div>
                    
                    <div class="col-12">
                        <label class="text-muted small">ระดับการศึกษา</label>
                        <div class="fw-bold text-dark"><?php echo $emp['education_level'] ?: '- ไม่ระบุ -'; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. ข้อมูลการติดต่อ -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 text-primary"><i class="fas fa-address-book me-2"></i> ข้อมูลการติดต่อ</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="text-muted small">เบอร์โทรศัพท์มือถือ</label>
                        <div class="fw-bold text-dark"><?php echo $emp['phone_number'] ?: '-'; ?></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small">อีเมล</label>
                        <div class="fw-bold text-dark"><?php echo $emp['email'] ?: '-'; ?></div>
                    </div>
                    <div class="col-12">
                        <label class="text-muted small">ที่อยู่ปัจจุบัน</label>
                        <div class="text-dark">
                            <?php 
                                echo $emp['current_address'] ?: '-';
                                if ($emp['district']) echo " อ." . $emp['district'];
                                if ($emp['province']) echo " จ." . $emp['province'];
                            ?>
                        </div>
                    </div>
                    
                    <div class="col-12"><hr class="my-2 text-muted opacity-25"></div>
                    
                    <div class="col-12">
                        <h6 class="text-secondary small fw-bold mb-2">ผู้ติดต่อฉุกเฉิน</h6>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small">ชื่อผู้ติดต่อ</label>
                        <div class="fw-bold text-dark"><?php echo $emp['emergency_contact_name'] ?: '-'; ?></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small">เบอร์โทรฉุกเฉิน</label>
                        <div class="fw-bold text-danger"><?php echo $emp['emergency_contact_phone'] ?: '-'; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. ข้อมูลการทำงาน -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 text-primary"><i class="fas fa-briefcase me-2"></i> ข้อมูลการทำงาน</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="text-muted small">บริษัท</label>
                        <div class="fw-bold text-dark"><?php echo $emp['company_name_th']; ?></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small">สาขา</label>
                        <div class="fw-bold text-dark"><?php echo $emp['branch_name_th']; ?></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small">แผนก/ฝ่าย</label>
                        <div class="fw-bold text-dark"><?php echo $emp['dept_name_th']; ?></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small">ตำแหน่ง</label>
                        <div class="fw-bold text-dark"><?php echo $emp['position_name_th']; ?></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small">ประเภทพนักงาน</label>
                        <div class="fw-bold text-dark"><?php echo $emp['type_name']; ?></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small">หัวหน้างาน (Supervisor)</label>
                        <div class="fw-bold text-dark">
                            <?php if($emp['supervisor_name']): ?>
                                <i class="fas fa-user-tie text-muted me-1"></i> <?php echo $emp['supervisor_name']; ?>
                            <?php else: ?>
                                - ไม่มี -
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-12"><hr class="my-2 text-muted opacity-25"></div>

                    <!-- (NEW) กะการทำงาน -->
                    <div class="col-12">
                        <label class="text-muted small">กะการทำงานปกติ (Default Shift)</label>
                        <div>
                            <?php if($emp['shift_name']): ?>
                                <span class="badge bg-info text-dark border">
                                    <i class="fas fa-clock"></i> <?php echo $emp['shift_name']; ?>
                                </span>
                                <span class="text-muted small ms-2">
                                    (<?php echo substr($emp['start_time'], 0, 5) . ' - ' . substr($emp['end_time'], 0, 5); ?>)
                                </span>
                            <?php else: ?>
                                <span class="text-muted">- ไม่ระบุ -</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. ข้อมูลระบบ -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 text-primary"><i class="fas fa-server me-2"></i> ข้อมูลระบบ</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="text-muted small">ชื่อผู้ใช้งาน (Username)</label>
                        <div class="fw-bold text-dark font-monospace"><?php echo $emp['username'] ?: '<span class="text-muted fst-italic">ยังไม่มีบัญชี</span>'; ?></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small">วันที่บันทึกข้อมูลเข้าระบบ</label>
                        <div class="text-dark"><?php echo date('d/m/Y H:i', strtotime($emp['created_at'])); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. ประวัติการโยกย้าย -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-primary"><i class="fas fa-history me-2"></i> ประวัติการทำงาน/โยกย้าย</h5>
                <button class="btn btn-sm btn-outline-primary" onclick="loadTransferHistory(<?php echo $emp['id']; ?>)">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0" id="transferHistoryTable">
                        <thead class="table-light">
                            <tr>
                                <th>วันที่เริ่มผล</th>
                                <th>ประเภท</th>
                                <th>จาก</th>
                                <th>ไปเป็น</th>
                                <th>หมายเหตุ</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="6" class="text-center text-muted py-4">กำลังโหลดข้อมูล...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Transfer Modal -->
<div class="modal fade" id="transferModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="transferModalTitle"><i class="fas fa-exchange-alt"></i> บันทึกการโยกย้าย/ปรับตำแหน่ง</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="transferForm">
                <div class="modal-body">
                    <input type="hidden" name="employee_id" value="<?php echo $emp['id']; ?>">
                    <input type="hidden" name="transfer_log_id" id="transferLogId" value="">
                    
                    <div class="alert alert-info py-2">
                        <small><strong>ตำแหน่งปัจจุบัน:</strong> <?php echo $emp['position_name_th']; ?> | <strong>แผนก:</strong> <?php echo $emp['dept_name_th']; ?></small>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">วันที่เริ่มมีผล <span class="text-danger">*</span></label>
                            <input type="date" name="effective_date" id="transferEffectiveDate" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">ประเภทการเคลื่อนไหว</label>
                            <select name="transfer_type" id="transferType" class="form-select">
                                <option value="transfer">โยกย้าย (Transfer)</option>
                                <option value="promote">เลื่อนตำแหน่ง (Promote)</option>
                                <option value="demote">ลดตำแหน่ง (Demote)</option>
                            </select>
                        </div>

                        <div class="col-12"><hr class="my-2"></div>
                        <p class="mb-0 fw-bold text-primary">ข้อมูลใหม่ (เลือกเฉพาะที่เปลี่ยนแปลง)</p>

                        <div class="col-md-6">
                            <label class="form-label">บริษัทใหม่</label>
                            <select id="trans_company" name="new_company_id" class="form-select">
                                <option value="<?php echo $emp['company_id']; ?>">(เดิม) <?php echo $emp['company_name_th']; ?></option>
                                <?php foreach ($companies as $c) echo "<option value='{$c['id']}'>{$c['company_name_th']}</option>"; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">สาขาใหม่</label>
                            <select id="trans_branch" name="new_branch_id" class="form-select">
                                <option value="<?php echo $emp['branch_id']; ?>" data-company-id="<?php echo $emp['company_id']; ?>">(เดิม) <?php echo $emp['branch_name_th']; ?></option>
                                <?php foreach ($branches as $b) echo "<option value='{$b['id']}' data-company-id='{$b['company_id']}' style='display:none'>{$b['branch_name_th']}</option>"; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">แผนกใหม่</label>
                            <select name="new_department_id" id="trans_department" class="form-select">
                                <option value="<?php echo $emp['department_id']; ?>">(เดิม) <?php echo $emp['dept_name_th']; ?></option>
                                <?php foreach ($departments as $d) echo "<option value='{$d['id']}'>{$d['dept_name_th']}</option>"; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ตำแหน่งใหม่</label>
                            <select name="new_position_id" id="trans_position" class="form-select">
                                <option value="<?php echo $emp['position_id']; ?>">(เดิม) <?php echo $emp['position_name_th']; ?></option>
                                <?php foreach ($positions as $p) echo "<option value='{$p['id']}'>{$p['position_name_th']}</option>"; ?>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">หมายเหตุ/สาเหตุ</label>
                            <textarea name="notes" id="transferNotes" class="form-control" rows="2" placeholder="เช่น ย้ายตามโครงสร้างองค์กรใหม่, เลื่อนตำแหน่งประจำปี"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary" id="transferSubmitBtn">บันทึกการโยกย้าย</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if(typeof loadTransferHistory === 'function') {
            loadTransferHistory(<?php echo $emp['id']; ?>);
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>
