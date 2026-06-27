<?php
require_once 'includes/auth_check.php';

$page_title = "ประวัติขอสลับวันหยุด";
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">ประวัติขอสลับวันหยุด</h1>
        <p class="text-muted small mb-0">ตรวจสอบสถานะคำขอสลับวันหยุดที่คุณส่งหรือเป็นคู่สลับ</p>
    </div>
</div>

<div class="day-swap-dashboard-actions mb-4">
    <div class="day-swap-dashboard-actions-main">
        <a href="day_swap_request.php" class="btn day-swap-menu-button day-swap-menu-button-request">
            <i class="fas fa-plus"></i>
            <span>ขอสลับวันหยุด</span>
        </a>
    </div>
    <?php if (in_array($_SESSION['role'] ?? '', ['manager', 'admin', 'hr'], true)) : ?>
    <div class="day-swap-dashboard-actions-admin">
        <a href="day_swap_approvals.php" class="btn day-swap-menu-button day-swap-menu-button-approval">
            <i class="fas fa-user-check"></i>
            <span>อนุมัติสลับวันหยุด</span>
            <?php echo renderSidebarApprovalBadge($approvalBadgeCounts['day_swap']); ?>
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">ประวัติคำขอสลับวันหยุด</h2>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshDaySwapHistoryBtn">
                <i class="fas fa-rotate"></i> รีเฟรช
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="daySwapHistoryTable">
                <thead class="table-light">
                    <tr>
                        <th>วันที่ส่ง</th>
                        <th>คู่สลับ</th>
                        <th>วันที่สลับ</th>
                        <th>สถานะ</th>
                    </tr>
                </thead>
                <tbody id="daySwapHistoryBody">
                    <tr><td colspan="4" class="text-center text-muted py-4">กำลังโหลด...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
