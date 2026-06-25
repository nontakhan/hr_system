<?php
require_once 'includes/auth_check.php';

$page_title = 'ใบเตือนของฉัน';
require_once 'includes/header.php';
$currentMonth = date('Y-m');
$myEmployeeId = (int)($_SESSION['employee_id'] ?? 0);
?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">ใบเตือนของฉัน</h1>
        <p class="text-muted small mb-0">ตรวจสอบประวัติใบเตือนของคุณในแต่ละเดือน</p>
    </div>
    <?php if ($myEmployeeId > 0) : ?>
    <input type="month" class="form-control" id="myWarningMonth" value="<?php echo htmlspecialchars($currentMonth); ?>" style="max-width: 220px;">
    <?php endif; ?>
</div>

<?php if ($myEmployeeId <= 0) : ?>
<div class="alert alert-warning">
    ไม่พบข้อมูลพนักงานที่เชื่อมกับบัญชีผู้ใช้งานนี้ กรุณาติดต่อ HR
</div>
<?php else : ?>
<div class="row g-3 mb-4">
    <div class="col-12 col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">ใบเตือนทั้งหมดในเดือนนี้</div>
                <div class="h3 mb-0" data-my-warning-summary="total_warnings">0</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">จำนวนประเภทรายการ</div>
                <div class="h3 mb-0" data-my-warning-summary="distinct_type_count">0</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">รายละเอียดใบเตือน</h2>
            <span class="badge bg-light text-dark border">อ่านอย่างเดียว</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>วันที่เกิดเหตุ</th>
                        <th>รายการใบเตือน</th>
                        <th>รายละเอียด</th>
                    </tr>
                </thead>
                <tbody id="myWarningDetailsBody">
                    <tr><td colspan="3" class="text-center text-muted py-4">กำลังโหลด...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="assets/js/employee_warnings.js?v=<?php echo filemtime(__DIR__ . '/assets/js/employee_warnings.js'); ?>"></script>
<?php require_once 'includes/footer.php'; ?>
