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

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h5 mb-3">ส่งคำขอเวลา</h2>
                <form id="lateEarlyRequestForm">
                    <input type="hidden" name="action" value="submit">

                    <div class="mb-3">
                        <label class="form-label">ประเภทคำขอ <span class="text-danger">*</span></label>
                        <select name="time_request_type" id="timeRequestType" class="form-select" required>
                            <option value="late_arrival">ขอมาสาย</option>
                            <option value="early_departure">ขอออกก่อนเวลา</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">วันที่ <span class="text-danger">*</span></label>
                        <input type="date" name="work_date" id="timeRequestDate" class="form-control" data-native-date-picker="true" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">เวลาที่ต้องการ <span class="text-danger">*</span></label>
                        <input type="time" name="request_time" id="timeRequestTime" class="form-control" required>
                        <div class="form-text">มาสายจะอิงเวลาเริ่มกะ, ออกก่อนจะอิงเวลาเลิกกะของวันนั้น</div>
                    </div>

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

    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">ประวัติคำขอเวลา</h2>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshTimeRequestsBtn">
                        <i class="fas fa-rotate"></i> รีเฟรช
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
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
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
