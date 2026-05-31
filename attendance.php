<?php
require_once 'includes/auth_check.php';

$page_title = "การมาทำงาน";
$use_select2 = true;
$can_manage_attendance = in_array($_SESSION['role'], ['admin', 'hr'], true);
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">การมาทำงาน</h1>
        <p class="text-muted small">ตรวจสอบสถานะรายวันตามกะการทำงานของพนักงาน</p>
    </div>
    <?php if ($can_manage_attendance) : ?>
    <a href="attendance_import.php" class="btn btn-primary">
        <i class="fas fa-file-import me-1"></i> นำเข้า CSV
    </a>
    <?php endif; ?>
</div>

<div class="card shadow-sm border-0 mb-4" id="attendanceFilters" data-can-manage="<?php echo $can_manage_attendance ? '1' : '0'; ?>">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <?php if ($can_manage_attendance) : ?>
            <div class="col-md-5">
                <label class="form-label">พนักงาน</label>
                <select id="attendanceEmployee" class="form-select attendance-select2" data-placeholder="เลือกพนักงาน">
                    <option value="">เลือกพนักงาน</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-4">
                <label class="form-label">เดือน</label>
                <select id="attendanceMonth" class="form-select attendance-select2" data-placeholder="เลือกเดือน">
                    <option value="">เลือกเดือน</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="button" id="attendanceLoadBtn" class="btn btn-outline-primary w-100">
                    <i class="fas fa-search me-1"></i> แสดงข้อมูล
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div id="attendanceSummary" class="mb-3 text-muted">เลือกเดือนเพื่อดูข้อมูลการมาทำงาน</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>วันที่</th>
                        <th>วัน</th>
                        <th>เวลาเข้า</th>
                        <th>เวลาออก</th>
                        <th>สถานะ</th>
                    </tr>
                </thead>
                <tbody id="attendanceTableBody">
                    <tr><td colspan="5" class="text-center py-4 text-muted">ยังไม่มีข้อมูล</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
