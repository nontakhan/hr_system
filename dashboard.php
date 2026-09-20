<?php
require_once 'includes/auth_check.php';
$page_title = "Dashboard - ภาพรวมระบบ";
$isEmployeeDashboard = ($_SESSION['role'] ?? '') === 'employee';
$dashboardName = trim($_SESSION['full_name'] ?? '') ?: ($_SESSION['username'] ?? '');
$dashboardPosition = trim($_SESSION['position_name'] ?? '') ?: '-';
require_once 'includes/header.php';
?>

<!-- Welcome Banner -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0 theme-welcome text-white">
            <div class="card-body p-4">
                <h2 class="mb-1">สวัสดี, <?php echo htmlspecialchars($dashboardName); ?>!</h2>
                <p class="mb-0 opacity-75"><?php echo htmlspecialchars($dashboardPosition); ?> | ยินดีต้อนรับสู่ระบบบริหารทรัพยากรบุคคล (HR System)</p>
            </div>
        </div>
    </div>
</div>

<?php if (!$isEmployeeDashboard && in_array($_SESSION['role'] ?? '', ['manager', 'hr', 'admin'], true)) : ?>
<section class="card border-0 shadow-sm mb-4" aria-labelledby="dashboardWorkTitle">
    <div class="card-body">
        <h2 class="h5" id="dashboardWorkTitle">งานที่รอคุณดำเนินการ</h2>
        <p class="text-muted small">รายการรอพิจารณาตามบทบาทและขอบเขตที่คุณดูแล ณ เวลาที่เปิดหน้านี้</p>
        <div class="list-group list-group-flush">
            <?php foreach ([['leave','leave_approvals.php','การลา'], ['time_request','late_early_approvals.php','มาสาย / ออกก่อน'], ['overtime','overtime_approvals.php','OT หลังเลิกงาน'], ['day_swap','day_swap_approvals.php','สลับวันหยุด'], ['training','training_approvals.php','กิจกรรม']] as [$key, $url, $label]): ?>
            <a href="<?php echo $url; ?>" class="list-group-item list-group-item-action d-flex justify-content-between gap-2"><span><?php echo $label; ?></span><span><?php echo (int)$approvalBadgeCounts[$key]; ?> รายการรอพิจารณา</span></a>
            <?php endforeach; ?>
        </div>
        <?php if (in_array($_SESSION['role'], ['hr','admin'], true)): ?>
        <div class="d-flex gap-2 flex-wrap mt-3"><a href="employees.php" class="btn btn-outline-primary">ข้อมูลพนักงาน</a><a href="request_proxy.php" class="btn btn-outline-primary">ทำรายการแทนพนักงาน</a><a href="attendance.php?view=team" class="btn btn-outline-primary">ตรวจเวลาพนักงาน</a></div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>
<?php if (!empty($_SESSION['employee_id'])): ?>
<section class="card border-0 shadow-sm mb-4" aria-labelledby="myRequestTitle">
    <div class="card-body">
        <h2 class="h5" id="myRequestTitle">คำขอของฉัน</h2>
        <p class="text-muted small">เลือกประเภทเพื่อดูสถานะ ติดตามผล หรือสร้างคำขอใหม่</p>
        <div class="d-flex flex-wrap gap-2">
            <a href="my_leaves.php" class="btn btn-outline-primary">การลา</a>
            <a href="late_early_history.php" class="btn btn-outline-primary">มาสาย / ออกก่อน</a>
            <a href="overtime_history.php" class="btn btn-outline-primary">OT หลังเลิกงาน</a>
            <a href="day_swap_history.php" class="btn btn-outline-primary">สลับวันหยุด</a>
            <a href="training_history.php" class="btn btn-outline-primary">กิจกรรม</a>
        </div>
    </div>
</section>
<?php endif; ?>
<?php if ($isEmployeeDashboard) : ?>
<div class="row g-4 mb-4" id="employeeDashboardContainer">
    <div class="col-12 text-center text-muted py-4">กำลังโหลดข้อมูลส่วนตัว...</div>
</div>
<?php else : ?>
<!-- (NEW) ส่วนแสดงจำนวนพนักงาน แยกตามบริษัทและสาขา -->
<?php if (in_array($_SESSION['role'], ['admin', 'hr', 'manager'])) : ?>
<div class="row g-4 mb-4" id="companyBranchStatsContainer">
    <div class="col-12 text-center text-muted py-4">กำลังโหลดข้อมูล...</div>
</div>
<?php endif; ?>

<div class="row">
    <!-- Left Column: Employee Types Chart -->
    <div class="col-lg-8 mb-4">
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
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-building me-2"></i> สรุปภาพรวม</h5>
                <small class="text-muted"><?php echo formatThaiDate(date('Y-m-d')); ?></small>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush" id="todayLeaveList">
                    <div class="text-center p-4 text-muted">กำลังโหลด...</div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
