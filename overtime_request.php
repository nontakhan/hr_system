<?php
require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

$page_title = 'ขอ OT หลังเลิกงาน';
require_once 'includes/header.php';
?>
<script>
window.timeRequestFixedType = 'overtime_after_work';
</script>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">ขอ OT หลังเลิกงาน</h1>
        <p class="text-muted small mb-0">เลือกวันที่และจำนวนเวลาที่ต้องการทำ OT หลังเลิกงาน เพื่อส่งให้หัวหน้าและ HR อนุมัติจากผลสแกนออกจริง</p>
    </div>
    <a href="overtime_history.php" class="btn btn-outline-primary time-request-history-link">
        <i class="fas fa-clock-rotate-left me-1"></i> ดูประวัติ OT
    </a>
</div>

<div class="time-request-shell">
    <div class="time-request-form-panel">
        <div class="card shadow-sm border-0 time-request-card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
                    <div>
                        <h2 class="h5 mb-1">ส่งคำขอ OT</h2>
                        <p class="text-muted small mb-0">ระบบจะให้ HR ตรวจสอบเวลาออกจริงก่อนอนุมัติยอด OT สุดท้าย</p>
                    </div>
                    <span class="time-request-step">1-480 นาที</span>
                </div>

                <form id="lateEarlyRequestForm">
                    <input type="hidden" name="action" value="submit">
                    <input type="hidden" name="time_request_type" value="overtime_after_work">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">วันที่ทำ OT <span class="text-danger">*</span></label>
                            <input type="date" name="work_date" id="timeRequestDate" class="form-control" data-native-date-picker="true" required>
                        </div>

                        <div class="col-md-6" id="overtimeDurationField">
                            <label class="form-label">จำนวน OT ที่ขอ (นาที) <span class="text-danger">*</span></label>
                            <input type="number" name="overtime_minutes" id="overtimeMinutes" class="form-control" min="1" max="480" step="1" placeholder="เช่น 120" required>
                        </div>
                    </div>
                    <div class="form-text mt-2 mb-3">HR จะอนุมัติได้ไม่เกินเวลาที่สแกนออกจริงหลังเวลาเลิกกะ</div>

                    <div class="alert alert-light border d-none" id="timeRequestCalculation">
                        <div class="small text-muted">ผลการคำนวณ</div>
                        <div class="fw-semibold" id="timeRequestCalculationText"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">เหตุผล <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required placeholder="ระบุเหตุผลที่ต้องการทำ OT"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-paper-plane"></i> ส่งคำขอ OT
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
