<?php
require_once 'includes/auth_check.php';
if (!in_array($_SESSION['role'], ['admin', 'hr'], true)) {
    header('Location: dashboard.php');
    exit();
}

$page_title = 'รายงานการลา';
$use_select2 = true;
require_once 'includes/header.php';
?>

<div id="approvedLeaveReportPage" class="approved-leave-report-page">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">รายงานการลา</h1>
            <p class="text-muted small mb-0">แสดงเฉพาะรายการลาจริงที่อนุมัติแล้ว แยกเป็นรายวันตามสิทธิ์บริษัทและสาขาของผู้ใช้งาน</p>
        </div>
        <a href="my_leaves.php" class="btn btn-outline-secondary">
            <i class="fas fa-clock-rotate-left me-1"></i> ประวัติการลา
        </a>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">เดือน</label>
                    <input type="month" id="approvedLeaveReportMonth" class="form-control" data-native-date-picker="true">
                </div>
                <div class="col-md-3">
                    <label class="form-label">บริษัท</label>
                    <select id="approvedLeaveReportCompany" class="form-select leave-report-select2" data-placeholder="บริษัททั้งหมด"></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">สาขา</label>
                    <select id="approvedLeaveReportBranch" class="form-select leave-report-select2" data-placeholder="สาขาทั้งหมด"></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">ประเภทการลา</label>
                    <select id="approvedLeaveReportType" class="form-select leave-report-select2" data-placeholder="ประเภทการลาทั้งหมด"></select>
                </div>
                <div class="col-md-3">
                    <button type="button" id="approvedLeaveReportLoadBtn" class="btn btn-primary w-100">
                        <i class="fas fa-magnifying-glass me-1"></i> แสดงรายงาน
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="approvedLeaveReportSummary" class="row g-3 mb-4"></div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
                <div class="text-muted">เลือกแล้ว <strong id="approvedLeaveWarningSelectedCount">0</strong> รายการ</div>
                <button type="button" id="approvedLeaveWarningBulkBtn" class="btn btn-primary" disabled>
                    <i class="fas fa-triangle-exclamation me-1"></i> เพิ่มใบเตือน
                </button>
            </div>
            <div class="table-responsive">
                <table id="approvedLeaveReportTable" class="table table-sm table-hover align-middle w-100">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 44px;"><input type="checkbox" class="form-check-input" id="approvedLeaveWarningSelectAll" aria-label="เลือกเหตุการณ์ทั้งหมด"></th>
                            <th>วันที่ลา</th>
                            <th>เจ้าหน้าที่</th>
                            <th>ตำแหน่ง</th>
                            <th>บริษัท</th>
                            <th>สาขา</th>
                            <th>ประเภทการลา</th>
                            <th>ช่วงวันที่ตามใบลา</th>
                            <th>จำนวนวัน</th>
                            <th>เหตุผล</th>
                        </tr>
                    </thead>
                    <tbody id="approvedLeaveReportRows">
                        <tr><td colspan="10" class="text-center text-muted py-4">เลือกเดือนแล้วแสดงรายงาน</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
