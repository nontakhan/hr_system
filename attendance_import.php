<?php
require_once 'includes/auth_check.php';

if (!in_array($_SESSION['role'], ['admin', 'hr'], true)) {
    header("Location: dashboard.php");
    exit();
}

$page_title = "นำเข้าข้อมูลลงเวลา";
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">นำเข้าข้อมูลลงเวลา</h1>
        <p class="text-muted small">อัปโหลดไฟล์ CSV จากเครื่องสแกนนิ้ว ระบบจะข้ามข้อมูลที่เคยนำเข้าแล้ว</p>
    </div>
    <a href="attendance.php" class="btn btn-outline-primary">
        <i class="fas fa-calendar-check me-1"></i> ดูการมาทำงาน
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form id="attendanceImportForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="import">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">เดือนที่ต้องการนำเข้า</label>
                    <input type="month" class="form-control" name="month" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label">ไฟล์ CSV</label>
                    <input type="file" class="form-control" name="csv_file" accept=".csv,text/csv" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-file-import me-1"></i> นำเข้าไฟล์
                    </button>
                </div>
            </div>
        </form>
        <div id="attendanceImportProgress" class="mt-4 d-none" aria-live="polite">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold" id="attendanceImportProgressLabel">กำลังเตรียมนำเข้าไฟล์</span>
                <span class="text-muted small" id="attendanceImportProgressPercent">0%</span>
            </div>
            <div class="progress" role="progressbar" aria-label="Import progress" aria-valuemin="0" aria-valuemax="100">
                <div id="attendanceImportProgressBar" class="progress-bar progress-bar-striped" style="width: 0%">0%</div>
            </div>
        </div>
        <div class="alert alert-info mt-4 mb-0">
            ใช้คอลัมน์ A เป็นเลขบัตรประชาชน, คอลัมน์ K เป็นเวลาเข้า และคอลัมน์ S เป็นเวลาออก
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
