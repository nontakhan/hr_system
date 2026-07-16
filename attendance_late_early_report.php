<?php
require_once 'includes/auth_check.php';
if (!in_array($_SESSION['role'], ['admin', 'hr'], true)) {
    header('Location: dashboard.php');
    exit();
}

$page_title = 'รายงานมาสาย/ออกก่อน';
$use_select2 = true;
require_once 'includes/header.php';
?>

<div id="attendanceLateEarlyReportPage" class="attendance-late-early-report-page">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">รายงานมาสาย/ออกก่อน</h1>
            <p class="text-muted small mb-0">ตรวจสอบเวลาที่เกินกะหลังหักนาทีคำขอมาสายหรือออกก่อนที่อนุมัติแล้ว ตามสิทธิ์บริษัทและสาขา</p>
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
                    <input type="month" id="attendanceLateEarlyMonth" class="form-control" data-native-date-picker="true">
                </div>
                <div class="col-md-3">
                    <label class="form-label">บริษัท</label>
                    <select id="attendanceLateEarlyCompany" class="form-select attendance-select2" data-placeholder="บริษัททั้งหมด"></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">สาขา</label>
                    <select id="attendanceLateEarlyBranch" class="form-select attendance-select2" data-placeholder="สาขาทั้งหมด"></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">ประเภท</label>
                    <select id="attendanceLateEarlyType" class="form-select attendance-select2">
                        <option value="all">ทั้งหมด</option>
                        <option value="late">มาสาย</option>
                        <option value="early">ออกก่อน</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="button" id="attendanceLateEarlyLoadBtn" class="btn btn-primary w-100">
                        <i class="fas fa-magnifying-glass me-1"></i> แสดงรายงาน
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="attendanceLateEarlySummary" class="row g-3 mb-4"></div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table id="attendanceLateEarlyTable" class="table table-sm table-hover align-middle w-100">
                    <thead>
                        <tr>
                            <th>วันที่</th>
                            <th>พนักงาน</th>
                            <th>ตำแหน่ง</th>
                            <th>บริษัท</th>
                            <th>สาขา</th>
                            <th>เวลาเข้าตามกะ</th>
                            <th>สแกนเข้า</th>
                            <th>มาสาย</th>
                            <th>เวลาออกตามกะ</th>
                            <th>สแกนออก</th>
                            <th>ออกก่อน</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody id="attendanceLateEarlyRows">
                        <tr><td colspan="12" class="text-center text-muted py-4">เลือกเดือนแล้วแสดงรายงาน</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
