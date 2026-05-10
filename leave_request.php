<?php
/*
 * หน้าฟอร์มยื่นใบลา (สำหรับพนักงานทุกคน)
 */
require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

$page_title = "ยื่นใบลา";
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">ยื่นใบลา</h1>
        <p class="text-muted small">กรุณากรอกข้อมูลให้ครบถ้วนเพื่อส่งให้หัวหน้าอนุมัติ</p>
    </div>
    <a href="my_leaves.php" class="btn btn-outline-primary">
        <i class="fas fa-list"></i> ประวัติการลาของฉัน
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <form id="leaveRequestForm" enctype="multipart/form-data">
                    
                    <!-- ส่วนเลือกประเภท -->
                    <div class="mb-3">
                        <label class="form-label">ประเภทการลา <span class="text-danger">*</span></label>
                        <select name="leave_type_id" id="leaveTypeSelect" class="form-select" required>
                            <option value="">-- เลือกประเภทการลา --</option>
                            <!-- JS จะดึงข้อมูลมาใส่ตรงนี้ -->
                        </select>
                        <div id="leaveTypeCondition" class="form-text text-info d-none">
                            <i class="fas fa-info-circle"></i> <span id="conditionText"></span>
                        </div>
                    </div>

                    <!-- ส่วนเลือกวันที่ -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">วันที่เริ่มลา <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" id="startDate" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ถึงวันที่ <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" id="endDate" class="form-control" required>
                        </div>
                    </div>

                    <!-- แสดงจำนวนวันรวม -->
                    <div class="alert alert-light border mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-calendar-day text-primary"></i> จำนวนวันลาทั้งหมด:</span>
                            <span class="fs-5 fw-bold text-primary" id="totalDaysDisplay">0</span>
                        </div>
                        <input type="hidden" name="total_days" id="totalDaysInput">
                    </div>

                    <!-- เหตุผล -->
                    <div class="mb-3">
                        <label class="form-label">เหตุผลการลา <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required placeholder="ระบุสาเหตุที่ต้องการลา..."></textarea>
                    </div>

                    <!-- ไฟล์แนบ (แสดงเมื่อจำเป็น) -->
                    <div class="mb-4 d-none" id="attachmentSection">
                        <label class="form-label text-danger">
                            เอกสารแนบ (จำเป็นสำหรับประเภทนี้) <span class="text-danger">*</span>
                        </label>
                        <input type="file" name="attachment" id="attachmentInput" class="form-control">
                        <div class="form-text">รองรับไฟล์ .jpg, .png, .pdf</div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane"></i> ส่งใบลา
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>