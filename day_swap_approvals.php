<?php
require_once 'includes/auth_check.php';

if (!in_array($_SESSION['role'], ['manager', 'hr', 'admin'], true)) {
    header("Location: dashboard.php");
    exit();
}

$page_title = "อนุมัติสลับวันหยุด";
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">อนุมัติสลับวันหยุด</h1>
        <p class="text-muted small">อนุมัติแล้วจึงมีผลกับรายงานลงเวลาของพนักงานทั้งสองฝั่ง</p>
    </div>
    <a href="day_swap_history.php" class="btn btn-outline-secondary day-swap-approval-back-link">
        <i class="fas fa-arrow-left me-1"></i> กลับไปประวัติคำขอ
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <ul class="nav nav-tabs mb-3" id="daySwapApprovalTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="day-swap-pending-tab" data-bs-toggle="tab" data-bs-target="#daySwapPending" type="button">
                    <i class="fas fa-clock text-warning"></i> รออนุมัติ
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="day-swap-history-tab" data-bs-toggle="tab" data-bs-target="#daySwapHistory" type="button">
                    <i class="fas fa-history text-secondary"></i> ประวัติ
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="daySwapPending">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="daySwapPendingTable">
                        <thead class="table-light">
                            <tr>
                                <th>ผู้ขอ</th>
                                <th>คู่สลับ</th>
                                <th>วันที่สลับ</th>
                                <th>เหตุผล</th>
                                <th style="width: 180px;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="daySwapPendingBody">
                            <tr><td colspan="5" class="text-center text-muted py-4">กำลังโหลด...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="daySwapHistory">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="daySwapApprovalHistoryTable">
                        <thead class="table-light">
                            <tr>
                                <th>วันที่พิจารณา</th>
                                <th>ผู้ขอ</th>
                                <th>คู่สลับ</th>
                                <th>วันที่สลับ</th>
                                <th>สถานะ</th>
                                <th>หมายเหตุ</th>
                            </tr>
                        </thead>
                        <tbody id="daySwapApprovalHistoryBody">
                            <tr><td colspan="6" class="text-center text-muted py-4">กำลังโหลด...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="daySwapActionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="daySwapApprovalForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="daySwapActionTitle">ดำเนินการ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="request_id" id="daySwapRequestId">
                    <input type="hidden" name="action_type" id="daySwapActionType">
                    <p id="daySwapActionMessage"></p>
                    <div class="mb-3" id="daySwapRejectReasonWrap" style="display: none;">
                        <label class="form-label">เหตุผลที่ไม่อนุมัติ <span class="text-danger">*</span></label>
                        <textarea name="reason" id="daySwapRejectReason" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn" id="daySwapConfirmBtn">ยืนยัน</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
