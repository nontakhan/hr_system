<?php
require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

$page_title = 'ขอมาสาย/ออกก่อนเวลา';
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">ขอมาสาย/ออกก่อนเวลา</h1>
        <p class="text-muted small mb-0">ระบุเวลาจริงที่ต้องการ ระบบจะคำนวณนาทีจากกะของวันนั้น สูงสุดไม่เกิน 1 ชม.</p>
    </div>
</div>

<div class="time-request-shell">
    <div class="time-request-form-panel">
        <div class="card shadow-sm border-0 time-request-card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
                    <div>
                        <h2 class="h5 mb-1">ส่งคำขอเวลา</h2>
                        <p class="text-muted small mb-0">เลือกประเภท วันที่ และเวลาที่ต้องการให้หัวหน้างานพิจารณา</p>
                    </div>
                    <span class="time-request-step">1-60 นาที</span>
                </div>

                <form id="lateEarlyRequestForm">
                    <input type="hidden" name="action" value="submit">

                    <div class="mb-3">
                        <label class="form-label">ประเภทคำขอ <span class="text-danger">*</span></label>
                        <div class="btn-group time-request-type-group w-100" role="group" aria-label="ประเภทคำขอ">
                            <input type="radio" name="time_request_type" id="timeRequestLate" value="late_arrival" class="btn-check time-request-type-option" autocomplete="off" checked required>
                            <label class="btn btn-outline-primary py-3 time-request-type-btn" for="timeRequestLate">
                                <i class="fas fa-clock me-1"></i>
                                <span>ขอมาสาย</span>
                            </label>

                            <input type="radio" name="time_request_type" id="timeRequestEarly" value="early_departure" class="btn-check time-request-type-option" autocomplete="off" required>
                            <label class="btn btn-outline-primary py-3 time-request-type-btn" for="timeRequestEarly">
                                <i class="fas fa-person-walking-arrow-right me-1"></i>
                                <span>ขอออกก่อนเวลา</span>
                            </label>

                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">วันที่ <span class="text-danger">*</span></label>
                            <input type="date" name="work_date" id="timeRequestDate" class="form-control" data-native-date-picker="true" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">เวลาที่ต้องการ <span class="text-danger">*</span></label>
                            <input type="time" name="request_time" id="timeRequestTime" class="form-control" required>
                        </div>

                    </div>
                    <div class="form-text mt-2 mb-3">มาสายจะอิงเวลาเริ่มกะ, ออกก่อนจะอิงเวลาเลิกกะของวันนั้น</div>

                    <div class="alert alert-light border d-none" id="timeRequestCalculation">
                        <div class="small text-muted">ผลการคำนวณ</div>
                        <div class="fw-semibold" id="timeRequestCalculationText"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">เหตุผล <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required placeholder="ระบุเหตุผลที่ต้องการขอมาสายหรือออกก่อนเวลา"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-paper-plane"></i> ส่งคำขอ
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
