<?php
require_once 'includes/auth_check.php';

if (!in_array($_SESSION['role'], ['manager', 'hr', 'admin'], true)) {
    header("Location: dashboard.php");
    exit();
}

$page_title = 'อนุมัติคำขอกิจกรรม';
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">อนุมัติคำขอกิจกรรม</h1>
        <p class="text-muted small mb-0">หัวหน้าอนุมัติก่อน จากนั้น HR อนุมัติและระบบจะสร้างประวัติกิจกรรมให้อัตโนมัติ</p>
    </div>
    <a href="training_history.php" class="btn btn-outline-secondary training-approval-back-link">
        <i class="fas fa-arrow-left me-1"></i> กลับไปประวัติคำขอ
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <ul class="nav nav-tabs mb-3" id="trainingRequestApprovalTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="training-request-pending-tab" data-bs-toggle="tab" data-bs-target="#trainingRequestPending" type="button">
                    <i class="fas fa-clock text-warning"></i> รออนุมัติ
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="training-request-history-tab" data-bs-toggle="tab" data-bs-target="#trainingRequestApprovalHistory" type="button">
                    <i class="fas fa-history text-secondary"></i> ประวัติ
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="trainingRequestPending">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="trainingRequestPendingTable">
                        <thead class="table-light">
                            <tr>
                                <th>พนักงาน</th>
                                <th>กิจกรรม</th>
                                <th>ช่วงกิจกรรม</th>
                                <th>รายละเอียด</th>
                                <th style="width: 180px;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="trainingRequestPendingBody">
                            <tr><td colspan="5" class="text-center text-muted py-4">กำลังโหลด...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="trainingRequestApprovalHistory">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="trainingRequestApprovalHistoryTable">
                        <thead class="table-light">
                            <tr>
                                <th>วันที่ทำรายการ</th>
                                <th>พนักงาน</th>
                                <th>กิจกรรม</th>
                                <th>ช่วงกิจกรรม</th>
                                <th>สถานะ</th>
                                <th>หมายเหตุ</th>
                                <th class="reviewer-cancel-action-column">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="trainingRequestApprovalHistoryBody">
                            <tr><td colspan="7" class="text-center text-muted py-4">กำลังโหลด...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="trainingRequestActionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="trainingRequestApprovalForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="trainingRequestActionTitle">ดำเนินการ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="request_id" id="trainingRequestId">
                    <input type="hidden" name="action_type" id="trainingRequestActionType">
                    <p id="trainingRequestActionMessage"></p>
                    <div class="mb-3" id="trainingRequestRejectReasonWrap" style="display: none;">
                        <label class="form-label">เหตุผลที่ไม่อนุมัติ <span class="text-danger">*</span></label>
                        <textarea name="reason" id="trainingRequestRejectReason" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn" id="trainingRequestConfirmBtn">ยืนยัน</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
