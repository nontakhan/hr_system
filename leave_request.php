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
    <a href="my_leaves.php" class="btn btn-outline-secondary leave-request-back-link">
        <i class="fas fa-arrow-left me-1"></i> กลับไปประวัติการลา
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-1">สรุปสิทธิ์ลาในปีงบประมาณ</h2>
                        <p class="text-muted small mb-0" id="leaveUsageFiscalYearText">กำลังโหลดข้อมูล...</p>
                    </div>
                    <span class="badge bg-light text-dark border">เตือนเท่านั้น ไม่บล็อกการลา</span>
                </div>
                <div id="leaveUsageSummaryGrid" class="leave-usage-summary-grid">
                    <div class="text-muted small">กำลังโหลดข้อมูล...</div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <form id="leaveRequestForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">ประเภทการลา <span class="text-danger">*</span></label>
                        <input type="hidden" name="leave_type_id" id="leaveTypeSelect" required>
                        <div id="leaveTypeIconGrid" class="leave-type-grid" role="radiogroup" aria-label="ประเภทการลา"></div>
                        <div id="leaveTypeCondition" class="form-text text-info d-none">
                            <i class="fas fa-info-circle"></i> <span id="conditionText"></span>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6" id="startDateField">
                            <label class="form-label" id="startDateLabel">วันที่เริ่มลา <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" id="startDate" class="form-control leave-date-picker" data-native-date-picker="true" required>
                        </div>
                        <div class="col-md-6 day-leave-field">
                            <label class="form-label">ถึงวันที่ <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" id="endDate" class="form-control leave-date-picker" data-native-date-picker="true" required>
                        </div>
                        <div class="col-md-6 day-leave-field">
                            <label class="form-label">ช่วงเวลาเริ่มลา <span class="text-danger">*</span></label>
                            <select name="start_day_part" id="startDayPart" class="form-select" required>
                                <option value="full">เต็มวัน</option>
                                <option value="morning">ครึ่งวันเช้า</option>
                                <option value="afternoon">ครึ่งวันบ่าย</option>
                            </select>
                        </div>
                        <div class="col-md-6 day-leave-field">
                            <label class="form-label">ช่วงเวลาสิ้นสุดลา <span class="text-danger">*</span></label>
                            <select name="end_day_part" id="endDayPart" class="form-select" required>
                                <option value="full">เต็มวัน</option>
                                <option value="morning">ครึ่งวันเช้า</option>
                                <option value="afternoon">ครึ่งวันบ่าย</option>
                            </select>
                        </div>
                        <div class="col-md-12 d-none" id="hourlyLeaveFields">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">เวลาเริ่มลา <span class="text-danger">*</span></label>
                                    <input type="time" name="request_start_time" id="requestStartTime" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">เวลาสิ้นสุดลา <span class="text-danger">*</span></label>
                                    <input type="time" name="request_end_time" id="requestEndTime" class="form-control">
                                </div>
                            </div>
                            <div class="form-text" id="hourlyLeaveRuleText"></div>
                        </div>
                    </div>

                    <div class="alert alert-light border mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-calendar-day text-primary"></i> จำนวนวันลาทั้งหมด:</span>
                            <span class="fs-5 fw-bold text-primary" id="totalDaysDisplay">0</span>
                        </div>
                        <input type="hidden" name="total_days" id="totalDaysInput">
                        <div id="leaveDateBreakdown" class="small text-muted mt-2"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">เหตุผลการลา <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required placeholder="ระบุสาเหตุที่ต้องการลา..."></textarea>
                    </div>

                    <div class="mb-4 d-none" id="attachmentSection">
                        <label class="form-label">
                            เอกสารแนบ (ถ้ามี)
                        </label>
                        <input type="file" name="attachment" id="attachmentInput" class="form-control">
                        <div class="form-text">รองรับไฟล์ .jpg, .png, .pdf ไม่บังคับอัปโหลด</div>
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
