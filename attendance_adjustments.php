<?php
require_once 'includes/auth_check.php';
if (!in_array($_SESSION['role'], ['admin', 'hr'], true)) {
    header('Location: dashboard.php');
    exit();
}

$page_title = "ปรับแก้เวลาสแกน";
$use_select2 = true;
require_once 'includes/header.php';
?>

<div id="attendanceAdjustmentPage" class="attendance-adjustments-page">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">ปรับแก้เวลาสแกน</h1>
            <p class="text-muted small mb-0">แก้ไขเวลาเข้าออกเมื่อเครื่องสแกนไม่บันทึก โดยไม่ทับข้อมูลสแกนจริง</p>
        </div>
        <a href="attendance.php" class="btn btn-outline-secondary">
            <i class="fas fa-calendar-days me-1"></i> ดูปฏิทินเวลา
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">แก้รายคน</h2>
                    <form id="attendanceSingleSaveForm">
                        <input type="hidden" name="action" value="save_adjustment">
                        <div class="mb-3">
                            <label class="form-label">พนักงาน</label>
                            <select id="attendanceSingleEmployee" name="employee_id" class="form-select attendance-select2" data-placeholder="เลือกพนักงาน"></select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">วันที่</label>
                            <input type="date" id="attendanceSingleDate" name="work_date" class="form-control" data-native-date-picker="true" required>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">เวลาเข้า</label>
                                <input type="time" name="override_check_in" class="form-control">
                            </div>
                            <div class="col-6">
                                <label class="form-label">เวลาออก</label>
                                <input type="time" name="override_check_out" class="form-control">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">เหตุผล</label>
                            <textarea name="reason" class="form-control" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mt-3">
                            <i class="fas fa-floppy-disk me-1"></i> บันทึกรายคน
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h2 class="h5 mb-3">แก้หลายคน</h2>
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-4">
                            <label class="form-label">วันที่</label>
                            <input type="date" id="attendanceBulkDate" class="form-control" data-native-date-picker="true">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ค้นหา</label>
                            <input type="search" id="attendanceAdjustmentSearch" class="form-control" placeholder="ชื่อ หรือเลขบัตร">
                        </div>
                        <div class="col-md-4">
                            <button type="button" id="attendanceAdjustmentLoadBtn" class="btn btn-outline-primary w-100">
                                <i class="fas fa-search me-1"></i> โหลดรายชื่อ
                            </button>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-4"><select id="attendanceAdjustmentCompany" class="form-select attendance-select2" data-placeholder="บริษัท"></select></div>
                        <div class="col-md-4"><select id="attendanceAdjustmentBranch" class="form-select attendance-select2" data-placeholder="สาขา"></select></div>
                        <div class="col-md-4"><select id="attendanceAdjustmentPosition" class="form-select attendance-select2" data-placeholder="ตำแหน่ง"></select></div>
                    </div>

                    <div class="table-responsive">
                        <table id="attendanceAdjustmentTable" class="table table-sm table-hover align-middle">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="attendanceAdjustmentSelectAll"></th>
                                    <th>พนักงาน</th>
                                    <th>ตำแหน่ง</th>
                                    <th>สาขา</th>
                                    <th>บริษัท</th>
                                    <th>ข้อมูลสแกน/ปรับแก้</th>
                                </tr>
                            </thead>
                            <tbody id="attendanceAdjustmentRows">
                                <tr><td colspan="6" class="text-center text-muted py-4">เลือกวันที่แล้วโหลดรายชื่อ</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <form id="attendanceBulkSaveForm" class="border-top pt-3 mt-3">
                        <div class="row g-2">
                            <div class="col-md-3"><input type="time" name="override_check_in" class="form-control" aria-label="เวลาเข้า"></div>
                            <div class="col-md-3"><input type="time" name="override_check_out" class="form-control" aria-label="เวลาออก"></div>
                            <div class="col-md-4"><input type="text" name="reason" class="form-control" placeholder="เหตุผล" required></div>
                            <div class="col-md-2">
                                <button type="submit" id="attendanceBulkSaveBtn" class="btn btn-primary w-100">
                                    <i class="fas fa-users-gear me-1"></i> บันทึก
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
