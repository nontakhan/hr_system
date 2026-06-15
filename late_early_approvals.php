<?php
/*
 * หน้าอนุมัติคำขอเวลา (มาสาย/ออกก่อนเวลา)
 */
require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

if (!in_array($_SESSION['role'], ['manager', 'hr', 'admin'])) {
    header("Location: dashboard.php");
    exit();
}

$page_title = "อนุมัติคำขอเวลา";
require_once 'includes/header.php';
?>
<script>
window.leaveApprovalRequestUnit = 'hour';
</script>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">อนุมัติคำขอเวลา</h1>
        <p class="text-muted small">รายการขอมาสาย/ออกก่อนเวลาจากพนักงานในสังกัดของคุณ</p>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <ul class="nav nav-tabs mb-3" id="approvalTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button">
                    <i class="fas fa-clock text-warning"></i> รออนุมัติ
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button">
                    <i class="fas fa-history text-secondary"></i> ประวัติการอนุมัติ
                </button>
            </li>
        </ul>

        <div class="tab-content" id="approvalTabsContent">
            <div class="tab-pane fade show active" id="pending">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="pendingTable">
                        <thead class="table-light">
                            <tr>
                                <th>พนักงาน</th>
                                <th>ประเภทคำขอ</th>
                                <th>วันที่ขอ</th>
                                <th>จำนวนเวลา</th>
                                <th>เหตุผล/เอกสาร</th>
                                <th style="width: 180px;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="pendingTableBody">
                            <tr><td colspan="6" class="text-center text-muted py-4">กำลังโหลดข้อมูล...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="history">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="historyTable">
                        <thead class="table-light">
                            <tr>
                                <th>วันที่ทำรายการ</th>
                                <th>พนักงาน</th>
                                <th>ประเภท</th>
                                <th>วันที่ขอ</th>
                                <th>สถานะ</th>
                                <th>หมายเหตุ</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody">
                            <tr><td colspan="6" class="text-center text-muted py-4">กำลังโหลดข้อมูล...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="approvalForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="actionModalTitle">ดำเนินการ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="request_id" id="requestId">
                    <input type="hidden" name="action_type" id="actionType">
                    <p id="actionMessage"></p>
                    <div class="mb-3" id="rejectReasonDiv" style="display: none;">
                        <label class="form-label">ระบุเหตุผลที่ไม่อนุมัติ <span class="text-danger">*</span></label>
                        <textarea name="reason" id="rejectReason" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn" id="confirmBtn">ยืนยัน</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
