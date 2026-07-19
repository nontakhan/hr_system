<?php
require_once 'includes/auth_check.php';

if (!in_array($_SESSION['role'], ['admin', 'hr'], true)) {
    header('Location: dashboard.php');
    exit();
}

$page_title = 'ใบเตือนพนักงาน';
require_once 'includes/header.php';
$currentMonth = date('Y-m');
$today = date('Y-m-d');
?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">ใบเตือนพนักงาน</h1>
        <p class="text-muted small mb-0">บันทึกและตรวจสอบประวัติใบเตือนรายเดือนสำหรับใช้ประกอบการพิจารณาประจำปี</p>
    </div>
    <div class="d-flex flex-column flex-sm-row gap-2">
        <input type="month" class="form-control" id="employeeWarningMonth" value="<?php echo htmlspecialchars($currentMonth); ?>">
        <button type="button" class="btn btn-outline-secondary" id="refreshEmployeeWarningsBtn">
            <i class="fas fa-rotate"></i> รีเฟรช
        </button>
        <button type="button" class="btn btn-primary" id="addEmployeeWarningBtn" data-bs-toggle="modal" data-bs-target="#employeeWarningModal">
            <i class="fas fa-plus"></i> เพิ่มใบเตือน
        </button>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">ใบเตือนทั้งหมด</div>
                <div class="h3 mb-0" data-warning-summary="total_warnings">0</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">พนักงานที่ได้รับใบเตือน</div>
                <div class="h3 mb-0" data-warning-summary="employee_count">0</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">จำนวนประเภทรายการ</div>
                <div class="h3 mb-0" data-warning-summary="distinct_type_count">0</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">รายการที่พบบ่อยสุด</div>
                <div class="h6 mb-0" data-warning-summary="top_warning_type">-</div>
                <div class="small text-muted"><span data-warning-summary="top_warning_count">0</span> ครั้ง</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <form id="employeeWarningSearchForm" class="row g-2 mb-3">
                    <div class="col-12 col-md">
                        <label class="visually-hidden" for="employeeWarningSearchName">ค้นหาชื่อพนักงาน</label>
                        <input type="search" class="form-control" id="employeeWarningSearchName" maxlength="100" placeholder="ค้นหาชื่อพนักงานจากประวัติทุกเดือน">
                    </div>
                    <div class="col-12 col-md-auto d-flex gap-2">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i> ค้นหา
                        </button>
                        <button type="button" class="btn btn-outline-secondary d-none" id="clearEmployeeWarningSearchBtn">
                            <i class="fas fa-times"></i> ล้างการค้นหา
                        </button>
                    </div>
                </form>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0" id="employeeWarningTableTitle">สรุปรายเดือนแยกตามพนักงาน</h2>
                    <span class="badge bg-light text-dark border" id="employeeWarningTableMode">แสดงเฉพาะพนักงานที่มีใบเตือนในเดือนที่เลือก</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>พนักงาน</th>
                                <th>หน่วยงาน</th>
                                <th>จำนวนครั้ง</th>
                                <th>รายการใบเตือน</th>
                                <th style="width: 110px;">รายละเอียด</th>
                            </tr>
                        </thead>
                        <tbody id="employeeWarningSummaryBody">
                            <tr><td colspan="5" class="text-center text-muted py-4">กำลังโหลด...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h5 mb-3">ตั้งค่ารายการใบเตือน</h2>
                <form id="warningTypeForm" class="mb-3">
                    <input type="hidden" name="id" id="warningTypeId">
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="warningTypeName">รายการใบเตือน <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="warningTypeName" name="type_name" required placeholder="เช่น แต่งกายไม่เรียบร้อย">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="warningTypeDescription">คำอธิบาย</label>
                        <textarea class="form-control" id="warningTypeDescription" name="description" rows="2"></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> บันทึก
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="resetWarningTypeFormBtn">ล้างฟอร์ม</button>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>รายการ</th>
                                <th style="width: 92px;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="warningTypeTableBody">
                            <tr><td colspan="2" class="text-center text-muted py-3">กำลังโหลด...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="employeeWarningModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="employeeWarningModalTitle">เพิ่มใบเตือนพนักงาน</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="employeeWarningForm">
                <input type="hidden" name="id" id="employeeWarningId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="employeeWarningEmployee">พนักงาน <span class="text-danger">*</span></label>
                        <select class="form-select" id="employeeWarningEmployee" name="employee_id" required>
                            <option value="">กำลังโหลดรายชื่อ...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="employeeWarningType">รายการใบเตือน <span class="text-danger">*</span></label>
                        <select class="form-select" id="employeeWarningType" name="warning_type_id" required>
                            <option value="">เลือกรายการใบเตือน</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="employeeWarningDate">วันที่เกิดเหตุ <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="employeeWarningDate" name="warning_date" value="<?php echo htmlspecialchars($today); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="employeeWarningDetail">รายละเอียด</label>
                        <textarea class="form-control" id="employeeWarningDetail" name="detail" rows="3" placeholder="ไม่บังคับ"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary"><span id="employeeWarningSubmitLabel">บันทึกใบเตือน</span></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="employeeWarningDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="employeeWarningDetailTitle">รายละเอียดใบเตือน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>วันที่เกิดเหตุ</th>
                                <th>รายการใบเตือน</th>
                                <th>รายละเอียด</th>
                                <th>ผู้บันทึก</th>
                                <th style="width: 100px;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="employeeWarningDetailBody">
                            <tr><td colspan="5" class="text-center text-muted py-4">กำลังโหลด...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/employee_warnings.js?v=<?php echo filemtime(__DIR__ . '/assets/js/employee_warnings.js'); ?>"></script>
<?php require_once 'includes/footer.php'; ?>
