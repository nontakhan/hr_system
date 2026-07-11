<?php
require_once 'includes/auth_check.php';
if (!in_array($_SESSION['role'], ['admin', 'hr'], true)) {
    header('Location: dashboard.php');
    exit();
}

$page_title = "รายงานไม่สแกนเข้า/ออก";
$use_select2 = true;
require_once 'includes/header.php';
?>

<div id="attendanceMissingReportPage" class="attendance-missing-report-page">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">รายงานไม่สแกนเข้า/ออก</h1>
            <p class="text-muted small mb-0">ตรวจสอบพนักงานที่ไม่มีสแกนเข้า/ออก หรือสแกนไม่ครบ ตามสิทธิ์บริษัทและสาขาของผู้ใช้งาน</p>
        </div>
        <a href="attendance.php" class="btn btn-outline-secondary">
            <i class="fas fa-calendar-days me-1"></i> ดูปฏิทินรายคน
        </a>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">เดือน</label>
                    <input type="month" id="attendanceMissingMonth" class="form-control" data-native-date-picker="true">
                </div>
                <div class="col-md-3">
                    <label class="form-label">บริษัท</label>
                    <select id="attendanceMissingCompany" class="form-select attendance-select2" data-placeholder="บริษัททั้งหมด"></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">สาขา</label>
                    <select id="attendanceMissingBranch" class="form-select attendance-select2" data-placeholder="สาขาทั้งหมด"></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">ประเภท</label>
                    <select id="attendanceMissingType" class="form-select attendance-select2">
                        <option value="all">ทั้งหมด</option>
                        <option value="absent">ไม่มีสแกนเข้า/ออก</option>
                        <option value="missing_in">ไม่สแกนเข้า</option>
                        <option value="missing_out">ไม่สแกนออก</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="button" id="attendanceMissingLoadBtn" class="btn btn-primary w-100">
                        <i class="fas fa-magnifying-glass me-1"></i> แสดงรายงาน
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="attendanceMissingSummary" class="row g-3 mb-4"></div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table id="attendanceMissingTable" class="table table-sm table-hover align-middle w-100">
                    <thead>
                        <tr>
                            <th>วันที่</th>
                            <th>พนักงาน</th>
                            <th>ตำแหน่ง</th>
                            <th>บริษัท</th>
                            <th>สาขา</th>
                            <th>เวลาเข้า</th>
                            <th>เวลาออก</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody id="attendanceMissingRows">
                        <tr><td colspan="8" class="text-center text-muted py-4">เลือกเดือนแล้วแสดงรายงาน</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
