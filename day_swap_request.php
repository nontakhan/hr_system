<?php
require_once 'includes/auth_check.php';

$page_title = "ขอสลับวันหยุด";
$use_select2 = true;
$use_fullcalendar = true;
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">ขอสลับวันหยุด</h1>
        <p class="text-muted small">เลือกวันหยุดปกติจากปฏิทินทั้งสองฝั่งเพื่อส่งให้หัวหน้าอนุมัติ</p>
    </div>
    <a href="day_swap_history.php" class="btn btn-outline-primary">
        <i class="fas fa-clock-rotate-left me-1"></i> ดูประวัติคำขอ
    </a>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <form id="daySwapForm">
                    <input type="hidden" id="requesterDate" name="requester_date" required>
                    <input type="hidden" id="targetDate" name="target_date" required>

                    <div class="row g-3 mb-3 day-swap-compare-grid">
                        <div class="col-lg-6">
                            <div class="day-swap-calendar-card day-swap-side-card day-swap-side-requester">
                                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                    <div>
                                        <div class="text-muted small fw-semibold">ฝั่งซ้าย</div>
                                        <h5 class="mb-0">เรา</h5>
                                    </div>
                                    <span class="badge bg-secondary" id="requesterSelectedLabel">ยังไม่เลือก</span>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">เดือนของวันหยุดเรา</label>
                                    <input type="month" class="form-control" id="requesterMonth" value="<?php echo date('Y-m'); ?>">
                                </div>
                                <div id="requesterHolidayCalendar" class="day-swap-calendar"></div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="day-swap-calendar-card day-swap-side-card day-swap-side-target">
                                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                    <div>
                                        <div class="text-muted small fw-semibold">ฝั่งขวา</div>
                                        <h5 class="mb-0">เพื่อนที่จะแลก</h5>
                                    </div>
                                    <span class="badge bg-secondary" id="targetSelectedLabel">ยังไม่เลือก</span>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-7">
                                        <label class="form-label">พนักงานที่ต้องการสลับ</label>
                                        <select class="form-select day-swap-select2" id="targetEmployee" name="target_employee_id" required>
                                            <option value="">เลือกพนักงาน</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">เดือนของวันหยุดเพื่อน</label>
                                        <input type="month" class="form-control" id="targetMonth" value="<?php echo date('Y-m'); ?>">
                                    </div>
                                </div>
                                <div id="targetHolidayCalendar" class="day-swap-calendar"></div>
                            </div>
                        </div>
                    </div>

                    <div id="daySwapSelectionSummary" class="alert alert-light border mb-3">
                        เลือกวันหยุดจากปฏิทินทั้งสองฝั่งเพื่อสร้างคำขอ
                    </div>

                    <div class="mb-3">
                        <label class="form-label">เหตุผล</label>
                        <textarea class="form-control" id="daySwapReason" name="reason" rows="3" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-1"></i> ส่งคำขอ
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
