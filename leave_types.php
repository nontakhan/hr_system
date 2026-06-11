<?php
/*
 * หน้าจัดการประเภทการลา (Master Data)
 * เฉพาะ Admin และ HR เท่านั้น
 */
require_once 'includes/auth_check.php';

// เช็คสิทธิ์
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'hr') {
    header("Location: dashboard.php");
    exit();
}

$page_title = "จัดการประเภทการลา";
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">จัดการประเภทการลา</h1>
        <p class="text-muted small">กำหนดประเภทและจำนวนวันลาต่อปี</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#leaveTypeModal" data-action="create">
        <i class="fas fa-plus"></i> เพิ่มประเภทการลา
    </button>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h5 mb-1">นโยบายสิทธิ์ลาและปีงบประมาณ</h2>
                <p class="text-muted small mb-0">บันทึกได้หลายชุด และเลือก 1 ชุดที่ใช้งานจริงกับสรุปยอดลาในระบบ</p>
            </div>
            <div id="leaveFiscalYearPreview" class="small text-muted"></div>
        </div>

        <form id="leaveSettingsForm" class="row g-3 align-items-end mb-3">
            <input type="hidden" id="leavePolicyId" name="id">
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label small mb-1" for="leavePolicyName">ชื่อชุดนโยบาย</label>
                <input type="text" class="form-control" id="leavePolicyName" name="policy_name" placeholder="เช่น ปีงบประมาณ 2569" required>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label small mb-1" for="fiscalYearStartMonth">เดือนเริ่มปีงบประมาณ</label>
                <select class="form-select" id="fiscalYearStartMonth" name="fiscal_year_start_month">
                    <option value="1">มกราคม</option>
                    <option value="2">กุมภาพันธ์</option>
                    <option value="3">มีนาคม</option>
                    <option value="4">เมษายน</option>
                    <option value="5">พฤษภาคม</option>
                    <option value="6">มิถุนายน</option>
                    <option value="7">กรกฎาคม</option>
                    <option value="8">สิงหาคม</option>
                    <option value="9">กันยายน</option>
                    <option value="10">ตุลาคม</option>
                    <option value="11">พฤศจิกายน</option>
                    <option value="12">ธันวาคม</option>
                </select>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label small mb-1" for="leaveMaxRequestsPerYear">จำนวนครั้งที่ลาได้ต่อปีงบประมาณ</label>
                <input type="number" class="form-control" id="leaveMaxRequestsPerYear" name="leave_max_requests_per_year" value="0" min="0" title="ใส่ 0 หากไม่จำกัดจำนวนครั้ง">
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label small mb-1 d-none d-md-block">&nbsp;</label>
                <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-2">
                    <div class="form-check me-sm-auto">
                        <input class="form-check-input" type="checkbox" id="leavePolicyActive" name="is_active" value="1">
                        <label class="form-check-label small" for="leavePolicyActive">ตั้งเป็นชุดที่ใช้งาน</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="leavePolicyResetBtn">ล้างฟอร์ม</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> บันทึก
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" id="leavePolicyTable">
                <thead class="table-light">
                    <tr>
                        <th>ชุดนโยบาย</th>
                        <th>ปีงบประมาณ</th>
                        <th>จำนวนครั้ง/ปีงบ</th>
                        <th>สถานะ</th>
                        <th class="text-nowrap" style="width: 230px;">จัดการ</th>
                    </tr>
                </thead>
                <tbody id="leavePolicyTableBody">
                    <tr><td colspan="5" class="text-center text-muted py-3">กำลังโหลดข้อมูล...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="leaveTypesTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>ชื่อประเภทการลา</th>
                        <th>จำนวนวัน/ปี</th>
                        <th>เงื่อนไขเพิ่มเติม</th>
                        <th style="width: 150px;">จัดการ</th>
                    </tr>
                </thead>
                <tbody id="leaveTypesTableBody">
                    <tr><td colspan="5" class="text-center text-muted py-4">กำลังโหลดข้อมูล...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Form -->
<div class="modal fade" id="leaveTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">เพิ่มประเภทการลา</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="leaveTypeForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="type_id">
                    
                    <div class="mb-3">
                        <label class="form-label">ชื่อประเภทการลา <span class="text-danger">*</span></label>
                        <input type="text" name="type_name" class="form-control" placeholder="เช่น ลาพักร้อน" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">จำนวนวันที่ให้ลาได้ต่อปี (วัน) <span class="text-danger">*</span></label>
                        <input type="number" name="days_per_year" class="form-control" value="30" min="0" required>
                        <div class="form-text">ใส่ 0 หากไม่จำกัดจำนวนวัน</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">คำอธิบายเพิ่มเติม</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="requires_file" id="requiresFile" value="1">
                        <label class="form-check-label" for="requiresFile">ต้องแนบไฟล์หลักฐาน (เช่น ใบรับรองแพทย์)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
