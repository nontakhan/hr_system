<?php
require_once 'includes/auth_check.php';

$page_title = 'ประวัติคำขอกิจกรรม';
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">ประวัติคำขอกิจกรรม</h1>
        <p class="text-muted small mb-0">ติดตามสถานะคำขอเข้าร่วมกิจกรรมของคุณ</p>
    </div>
</div>

<div class="training-dashboard-actions mb-4">
    <div class="training-dashboard-actions-main">
        <a href="training_request.php" class="btn training-menu-button training-menu-button-request">
            <i class="fas fa-plus"></i>
            <span>ขอไปทำกิจกรรม</span>
        </a>
    </div>
    <?php if (in_array($_SESSION['role'] ?? '', ['manager', 'admin', 'hr'], true)) : ?>
    <div class="training-dashboard-actions-admin">
        <a href="training_approvals.php" class="btn training-menu-button training-menu-button-approval">
            <i class="fas fa-user-check"></i>
            <span>อนุมัติคำขอกิจกรรม</span>
            <?php echo renderSidebarApprovalBadge($approvalBadgeCounts['training']); ?>
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">รายการคำขอกิจกรรม</h2>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshTrainingRequestHistoryBtn">
                <i class="fas fa-rotate"></i> รีเฟรช
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="trainingRequestHistoryTable">
                <thead class="table-light">
                    <tr>
                        <th>วันที่ส่ง</th>
                        <th>กิจกรรม</th>
                        <th>ช่วงกิจกรรม</th>
                        <th>สถานที่/รูปแบบ</th>
                        <th>สถานะ</th>
                        <th>หมายเหตุ</th>
                    </tr>
                </thead>
                <tbody id="trainingRequestHistoryBody">
                    <tr><td colspan="6" class="text-center text-muted py-4">กำลังโหลด...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
