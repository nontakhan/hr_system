<?php
require_once 'includes/auth_check.php';

if (!in_array($_SESSION['role'], ['admin', 'hr'], true)) {
    header("Location: dashboard.php");
    exit();
}

$page_title = "วันหยุดพิเศษบริษัท";
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">วันหยุดพิเศษบริษัท</h1>
        <p class="text-muted small">กำหนดวันหยุดเพิ่มเติมเพื่อใช้คำนวณสถานะการมาทำงาน</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#holidayModal" data-action="create">
        <i class="fas fa-plus me-1"></i> เพิ่มวันหยุด
    </button>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">ปี</label>
                <input type="number" id="holidayYear" class="form-control" min="2543" max="2643" value="<?php echo formatThaiYear(date('Y')); ?>">
            </div>
            <div class="col-md-3">
                <button type="button" id="holidayLoadBtn" class="btn btn-outline-primary w-100">
                    <i class="fas fa-search me-1"></i> แสดงข้อมูล
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>วันที่</th>
                        <th>ชื่อวันหยุด</th>
                        <th>หมายเหตุ</th>
                        <th style="width: 150px;">จัดการ</th>
                    </tr>
                </thead>
                <tbody id="holidayTableBody">
                    <tr><td colspan="4" class="text-center py-4 text-muted">กำลังโหลด...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="holidayModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="holidayModalTitle">เพิ่มวันหยุดพิเศษ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="holidayForm">
                <div class="modal-body">
                    <input type="hidden" id="holidayId">
                    <div class="mb-3">
                        <label class="form-label">วันที่ <span class="text-danger">*</span></label>
                        <input type="date" id="holidayDate" class="form-control" data-native-date-picker="true" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ชื่อวันหยุด <span class="text-danger">*</span></label>
                        <input type="text" id="holidayName" class="form-control" placeholder="เช่น วันหยุดประจำปีบริษัท" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">หมายเหตุ</label>
                        <textarea id="holidayNotes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
