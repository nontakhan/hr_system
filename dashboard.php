<?php
require_once 'includes/auth_check.php';
$page_title = "Dashboard - ภาพรวมระบบ";
require_once 'includes/header.php';
?>

<!-- Welcome Banner -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0 bg-primary text-white">
            <div class="card-body p-4">
                <h2 class="mb-1">สวัสดี, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
                <p class="mb-0 opacity-75">ยินดีต้อนรับสู่ระบบบริหารทรัพยากรบุคคล (HR System)</p>
            </div>
        </div>
    </div>
</div>

<!-- (NEW) ส่วนแสดงจำนวนพนักงาน แยกตามบริษัทและสาขา -->
<?php if (in_array($_SESSION['role'], ['admin', 'hr', 'manager'])) : ?>
<div class="row g-4 mb-4" id="companyBranchStatsContainer">
    <div class="col-12 text-center text-muted py-4">กำลังโหลดข้อมูล...</div>
</div>
<?php endif; ?>

<div class="row">
    <!-- Left Column: Employee Types Chart -->
    <div class="col-md-8 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 text-primary"><i class="fas fa-chart-bar me-2"></i> สรุปจำนวนพนักงานตามประเภท</h5>
            </div>
            <div class="card-body">
                <div id="employeeTypeSummaryContainer">
                    <div class="text-center text-muted py-5">กำลังโหลดข้อมูล...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Company Summary List -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-building me-2"></i> สรุปภาพรวม</h5>
                <small class="text-muted"><?php echo date('d/m/Y'); ?></small>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush" id="todayLeaveList">
                    <div class="text-center p-4 text-muted">กำลังโหลด...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>