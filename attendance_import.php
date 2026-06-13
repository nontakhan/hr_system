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
        <p class="text-muted small">อัปโหลดไฟล์ CSV จากเครื่องสแกนนิ้ว ระบบจะใช้เดือนจากวันที่ในไฟล์และข้ามข้อมูลที่เคยนำเข้าแล้ว</p>
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
                <div class="col-md-8">
                    <label class="form-label">ไฟล์ CSV</label>
                    <input type="file" class="form-control" name="csv_file" accept=".csv,text/csv" required>
                </div>
                <div class="col-md-4">
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
            ใช้คอลัมน์ A เป็นเลขบัตรประชาชน, คอลัมน์ D เป็นวันที่, คอลัมน์ K เป็นเวลาเข้า และคอลัมน์ S เป็นเวลาออก (ถ้า S ว่างจะใช้คอลัมน์ T)
            หากไฟล์มีหลายเดือน ระบบจะแยกเดือนให้อัตโนมัติตามวันที่ของแต่ละแถว
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mt-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h5 mb-0 text-gray-800">สรุปการนำเข้า 6 เดือนย้อนหลัง</h2>
                <div class="text-muted small">ตรวจจากข้อมูลลงเวลาที่บันทึกอยู่ในระบบ</div>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="attendanceImportSummaryRefresh">
                <i class="fas fa-rotate me-1"></i> รีเฟรช
            </button>
        </div>
        <div id="attendanceImportSummary" class="row g-3" aria-live="polite">
            <div class="col-12 text-muted small">กำลังโหลดสรุปการนำเข้า...</div>
        </div>
    </div>
</div>

<div class="modal fade" id="attendanceImportDetailModal" tabindex="-1" aria-labelledby="attendanceImportDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="attendanceImportDetailTitle">รายชื่อพนักงานที่มีข้อมูลนำเข้า</h5>
                    <div class="text-muted small" id="attendanceImportDetailSubtitle">เลือกเดือนจากสรุปด้านบน</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="search" class="form-control" id="attendanceImportDetailSearch" placeholder="ค้นหาชื่อพนักงานหรือเลขบัตรประชาชน">
                </div>
                <div id="attendanceImportDetailStatus" class="text-muted small mb-3"></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>พนักงาน</th>
                                <th>เลขบัตรประชาชน</th>
                                <th class="text-end">รายการ</th>
                                <th>ช่วงวันที่</th>
                            </tr>
                        </thead>
                        <tbody id="attendanceImportDetailRows">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">เลือกเดือนเพื่อดูรายชื่อ</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
