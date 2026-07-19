<?php
require_once 'includes/auth_check.php';
if (!in_array($_SESSION['role'], ['admin', 'hr'], true)) {
    header('Location: dashboard.php');
    exit();
}
$page_title = 'รายงานคำขอและเหตุการณ์พนักงาน';
$use_select2 = true;
require_once 'includes/header.php';
?>

<div id="employeeRequestAttendanceReportPage">
    <div class="mb-4">
        <h1 class="h3 mb-1 text-gray-800">รายงานคำขอและเหตุการณ์พนักงาน</h1>
        <p class="text-muted small mb-0">เปรียบเทียบคำขอที่อนุมัติแล้วกับเหตุการณ์ลงเวลาจริงของพนักงานในเดือนที่เลือก</p>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-lg-5">
                    <label for="employeeRequestAttendanceReportEmployee" class="form-label">พนักงาน</label>
                    <select id="employeeRequestAttendanceReportEmployee" class="form-select" data-placeholder="เลือกพนักงาน"></select>
                </div>
                <div class="col-lg-3">
                    <label for="employeeRequestAttendanceReportMonth" class="form-label">เดือน</label>
                    <input type="month" id="employeeRequestAttendanceReportMonth" class="form-control" data-native-date-picker="true" value="<?php echo date('Y-m'); ?>">
                </div>
                <div class="col-lg-4">
                    <button type="button" id="employeeRequestAttendanceReportLoad" class="btn btn-primary w-100">
                        <i class="fas fa-magnifying-glass me-1"></i> แสดงรายงาน
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="card border-0 bg-light h-100"><div class="card-body"><div class="small text-muted">เหตุการณ์ทั้งหมด</div><div class="h4 mb-0" id="employeeRequestAttendanceReportTotal">0</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 bg-light h-100"><div class="card-body"><div class="small text-muted">คำขออนุมัติ</div><div class="h4 mb-0 text-success" id="employeeRequestAttendanceReportApproved">0</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 bg-light h-100"><div class="card-body"><div class="small text-muted">ข้อมูลเครื่องสแกน</div><div class="h4 mb-0 text-primary" id="employeeRequestAttendanceReportScanner">0</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 bg-light h-100"><div class="card-body"><div class="small text-muted">OT จริง (นาที)</div><div class="h4 mb-0 text-warning" id="employeeRequestAttendanceReportOvertime">0</div></div></div></div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="employeeRequestAttendanceReportType" class="form-label">ประเภท</label>
                    <select id="employeeRequestAttendanceReportType" class="form-select" disabled><option value="">ทั้งหมด</option></select>
                </div>
                <div class="col-md-6">
                    <label for="employeeRequestAttendanceReportSource" class="form-label">แหล่งข้อมูล</label>
                    <select id="employeeRequestAttendanceReportSource" class="form-select" disabled>
                        <option value="">ทั้งหมด</option><option value="approved_request">คำขออนุมัติ</option><option value="scanner">เครื่องสแกน</option>
                    </select>
                </div>
            </div>
            <div id="employeeRequestAttendanceReportStatus" class="small text-muted mb-3" role="status">เลือกพนักงานและเดือน แล้วกดแสดงรายงาน</div>
            <div class="table-responsive">
                <table id="employeeRequestAttendanceReportTable" class="table table-sm table-hover align-middle w-100">
                    <thead><tr><th>วันที่</th><th>ประเภท</th><th>แหล่งข้อมูล</th><th>ช่วงเวลา</th><th>จำนวน</th><th>รายละเอียด</th><th>สถานะ</th></tr></thead>
                    <tbody id="employeeRequestAttendanceReportRows"><tr><td colspan="7" class="text-center text-muted py-4">ยังไม่ได้โหลดรายงาน</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
