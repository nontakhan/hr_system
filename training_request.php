<?php
require_once 'includes/auth_check.php';

$page_title = 'ขอไปทำกิจกรรม';
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">ขอไปทำกิจกรรม</h1>
        <p class="text-muted small mb-0">ส่งคำขอเข้าร่วมกิจกรรมเพื่อให้หัวหน้าและ HR พิจารณา</p>
    </div>
    <a href="training_history.php" class="btn btn-outline-secondary training-request-back-link">
        <i class="fas fa-arrow-left me-1"></i> กลับไปประวัติคำขอ
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form id="trainingRequestForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">

            <div class="row g-3">
                <div class="col-12 activity-type-section">
                    <label class="form-label fw-semibold">ประเภทกิจกรรม <span class="text-danger">*</span></label>
                    <input type="hidden" name="activity_type_id" id="activityTypeSelect" required>
                    <div id="activityTypeButtonGrid" class="activity-type-grid" role="radiogroup" aria-label="ประเภทกิจกรรม">
                        <div class="activity-type-loading text-muted">กำลังโหลดประเภทกิจกรรม...</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">ชื่อกิจกรรม/รายละเอียด <span class="text-danger">*</span></label>
                    <input type="text" name="course_name" class="form-control" maxlength="255" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">สถานที่/รูปแบบ</label>
                    <input type="text" name="location" class="form-control" maxlength="255" placeholder="สถานที่ หรือ Online">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">วันที่เริ่ม <span class="text-danger">*</span></label>
                    <input type="date" name="start_date" class="form-control" data-native-date-picker="true" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">วันที่สิ้นสุด <span class="text-danger">*</span></label>
                    <input type="date" name="end_date" class="form-control" data-native-date-picker="true" required>
                </div>
                <div class="col-12 training-day-part-field">
                    <label class="form-label">ช่วงวันเริ่ม</label>
                    <select name="start_day_part" class="form-select">
                        <option value="">เลือกช่วงวัน</option>
                        <option value="full">เต็มวัน</option>
                        <option value="morning">ครึ่งวันเช้า</option>
                        <option value="afternoon">ครึ่งวันบ่าย</option>
                    </select>
                </div>
                <input type="hidden" name="end_day_part" value="">
                <div class="col-12">
                    <label class="form-label fw-semibold">เหตุผล/วัตถุประสงค์ <span class="text-danger">*</span></label>
                    <textarea name="objective" class="form-control" rows="3" required></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">เอกสารแนบ</label>
                    <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    <div class="form-text">รองรับ PDF, JPG, PNG, WEBP ขนาดไม่เกิน 5MB</div>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane me-1"></i> ส่งคำขอกิจกรรม
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
