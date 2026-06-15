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
    <a href="day_swap_request.php" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> ขอสลับวันหยุด
    </a>
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
            <table class="table table-hover align-middle">
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
