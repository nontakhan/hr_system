<?php
/*
 * หน้าประวัติการลาของฉัน (My Leaves)
 */
require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

$page_title = "ประวัติการลาของฉัน";
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-0 text-gray-800">ประวัติการลาของฉัน</h1>
        <p class="text-muted small">ตรวจสอบสถานะและประวัติการลาทั้งหมด</p>
    </div>
</div>

<div class="leave-dashboard-actions mb-4">
    <div class="leave-dashboard-actions-main">
        <a href="leave_request.php" class="btn leave-menu-button leave-menu-button-request">
            <i class="fas fa-plus"></i>
            <span>ยื่นใบลา</span>
        </a>
    </div>
    <?php if (in_array($_SESSION['role'] ?? '', ['manager', 'admin', 'hr'], true)) : ?>
    <div class="leave-dashboard-actions-admin">
        <a href="leave_approvals.php" class="btn leave-menu-button leave-menu-button-approval">
            <i class="fas fa-user-check"></i>
            <span>อนุมัติการลา</span>
            <?php echo renderSidebarApprovalBadge($approvalBadgeCounts['leave']); ?>
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
            <div>
                <h2 class="h5 mb-1">สรุปการใช้สิทธิ์ลาในปีงบประมาณ</h2>
                <p class="text-muted small mb-0" id="leaveUsageFiscalYearText">กำลังโหลดข้อมูล...</p>
            </div>
            <span class="badge bg-light text-dark border">นับจากรายการที่อนุมัติแล้ว</span>
        </div>
        <div id="leaveUsageOverallGrid" class="leave-usage-overall-grid mb-3">
            <div class="text-muted small">กำลังโหลดข้อมูล...</div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h3 class="h6 mb-0 text-gray-800">แยกตามประเภทการลา</h3>
            <span class="text-muted small">อ้างอิงสิทธิ์จากหน้าตั้งค่าประเภทการลา</span>
        </div>
        <div id="leaveUsageSummaryGrid" class="leave-usage-summary-grid">
            <div class="text-muted small">กำลังโหลดข้อมูล...</div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0" id="leaveHistorySection">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="myLeavesTable">
                <thead class="table-light">
                    <tr>
                        <th>วันที่ยื่น</th>
                        <th>ประเภท</th>
                        <th>ช่วงเวลาที่ลา</th>
                        <th>จำนวนวัน</th>
                        <th>เหตุผล</th>
                        <th>สถานะ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody id="myLeavesTableBody">
                    <tr><td colspan="7" class="text-center text-muted py-4">กำลังโหลดข้อมูล...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
