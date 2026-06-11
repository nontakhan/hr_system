<?php
require_once 'includes/auth_check.php';

$page_title = "การมาทำงาน";
$use_select2 = true;
$use_fullcalendar = true;
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
            <div class="col-md-3">
                <label class="form-label">พนักงาน</label>
                <select id="attendanceEmployee" class="form-select attendance-select2" data-placeholder="เลือกพนักงาน">
                    <option value="">เลือกพนักงาน</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-3">
                <label class="form-label">เดือนเริ่มต้น</label>
                <select id="attendanceMonthStart" class="form-select attendance-select2" data-placeholder="เลือกเดือนเริ่มต้น">
                    <option value="">เลือกเดือน</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">ถึงเดือน <span class="text-muted small">(สูงสุด 12 เดือน)</span></label>
                <select id="attendanceMonthEnd" class="form-select attendance-select2" data-placeholder="เลือกเดือนสิ้นสุด">
                    <option value="">เดือนเดียว</option>
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
        <div class="attendance-calendar-legend mb-3" aria-label="Attendance status colors">
            <span><i class="attendance-legend-dot status-present"></i> ปกติ</span>
            <span><i class="attendance-legend-dot status-late"></i> สาย</span>
            <span><i class="attendance-legend-dot status-absent"></i> ขาด</span>
            <span><i class="attendance-legend-dot status-leave"></i> ลา</span>
            <span><i class="attendance-legend-dot status-holiday"></i> วันหยุด</span>
            <span><i class="attendance-legend-dot status-incomplete"></i> สแกนไม่ครบ</span>
        </div>
        <div class="attendance-calendar-shell">
            <div id="attendanceCalendar"></div>
            <div id="attendanceCalendarEmpty" class="attendance-calendar-empty text-muted">
                เลือกเดือนเพื่อดูปฏิทินการมาทำงาน
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
