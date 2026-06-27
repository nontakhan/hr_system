<?php
require_once 'includes/auth_check.php';

$page_title = 'ขอไปอบรม';
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">ขอไปอบรม</h1>
        <p class="text-muted small mb-0">ส่งคำขอเข้าร่วมอบรมเพื่อให้หัวหน้าและ HR พิจารณา</p>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form id="trainingRequestForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-semibold">ชื่อหลักสูตร <span class="text-danger">*</span></label>
                    <input type="text" name="course_name" class="form-control" maxlength="255" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">ประเภทอบรม</label>
                    <input type="text" name="training_type" class="form-control" maxlength="100" placeholder="ภายใน, ภายนอก, Online">
                </div>
                <div class="col-md-6">
                    <label class="form-label">ผู้จัด/สถาบัน</label>
                    <input type="text" name="provider" class="form-control" maxlength="255">
                </div>
                <div class="col-md-6">
                    <label class="form-label">สถานที่/รูปแบบ</label>
                    <input type="text" name="location" class="form-control" maxlength="255" placeholder="สถานที่ หรือ Online">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">วันที่เริ่ม <span class="text-danger">*</span></label>
                    <input type="date" name="start_date" class="form-control" data-native-date-picker="true" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">วันที่สิ้นสุด <span class="text-danger">*</span></label>
                    <input type="date" name="end_date" class="form-control" data-native-date-picker="true" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">ค่าใช้จ่ายโดยประมาณ</label>
                    <input type="number" name="estimated_cost" class="form-control" min="0" step="0.01">
                </div>
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
                    <i class="fas fa-paper-plane me-1"></i> ส่งคำขออบรม
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
