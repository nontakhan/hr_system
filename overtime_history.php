<?php
require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

$page_title = 'ประวัติ OT หลังเลิกงาน';
require_once 'includes/header.php';
?>
<script>
window.timeRequestHistoryType = 'overtime_after_work';
</script>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">ประวัติ OT หลังเลิกงาน</h1>
        <p class="text-muted small mb-0">ตรวจสอบสถานะคำขอ OT หลังเลิกงานของคุณ</p>
    </div>
</div>

<div class="overtime-dashboard-actions mb-4">
    <div class="overtime-dashboard-actions-main">
        <a href="overtime_request.php" class="btn overtime-menu-button overtime-menu-button-request">
            <i class="fas fa-plus"></i>
            <span>ส่งคำขอ OT</span>
        </a>
    </div>
    <?php if (in_array($_SESSION['role'] ?? '', ['manager', 'admin', 'hr'], true)) : ?>
    <div class="overtime-dashboard-actions-admin">
        <a href="overtime_approvals.php" class="btn overtime-menu-button overtime-menu-button-approval">
            <i class="fas fa-user-check"></i>
            <span>อนุมัติ OT</span>
            <?php echo renderSidebarApprovalBadge($approvalBadgeCounts['overtime']); ?>
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">ประวัติคำขอ OT</h2>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshTimeRequestsBtn">
                <i class="fas fa-rotate"></i> รีเฟรช
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="overtimeHistoryTable">
                <thead>
                    <tr>
                        <th>วันที่ส่ง</th>
                        <th>ประเภท</th>
                        <th>วันที่ขอ</th>
                        <th>เวลา</th>
                        <th>สถานะ</th>
                    </tr>
                </thead>
                <tbody id="lateEarlyHistoryBody">
                    <tr><td colspan="5" class="text-muted text-center">กำลังโหลดข้อมูล...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
