<?php
require_once 'includes/auth_check.php';

if (!in_array($_SESSION['role'] ?? '', ['admin', 'hr'], true)) {
    header('Location: dashboard.php');
    exit();
}

$page_title = 'ทำรายการแทนพนักงาน';
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1 text-gray-800">ทำรายการแทนพนักงาน</h1>
        <p class="text-muted small mb-0">บันทึกคำขอในนามพนักงานและอนุมัติทันที พร้อมเก็บประวัติผู้ทำรายการ</p>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <label class="form-label" for="proxyEmployeeId">พนักงาน <span class="text-danger">*</span></label>
        <select id="proxyEmployeeId" class="form-select" required></select>
    </div>
</div>

<ul class="nav nav-pills mb-3" id="proxyRequestTabs">
    <li class="nav-item"><button class="nav-link active" data-proxy-tab="leave" type="button">ลา</button></li>
    <li class="nav-item"><button class="nav-link" data-proxy-tab="late_early" type="button">มาสาย/ออกก่อน</button></li>
    <li class="nav-item"><button class="nav-link" data-proxy-tab="overtime" type="button">OT</button></li>
    <li class="nav-item"><button class="nav-link" data-proxy-tab="day_swap" type="button">สลับวันหยุด</button></li>
    <li class="nav-item"><button class="nav-link" data-proxy-tab="training" type="button">อบรม</button></li>
</ul>

<div class="proxy-request-panels">
    <form class="card shadow-sm border-0 proxy-panel" data-proxy-panel="leave" data-action="create_leave">
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">ประเภทการลา <span class="text-danger">*</span></label>
                <select name="leave_type_id" id="proxyLeaveTypeId" class="form-select" required></select>
            </div>
            <div class="col-md-3">
                <label class="form-label">วันที่เริ่ม <span class="text-danger">*</span></label>
                <input type="date" name="start_date" class="form-control" data-native-date-picker="true" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">วันที่สิ้นสุด <span class="text-danger">*</span></label>
                <input type="date" name="end_date" class="form-control" data-native-date-picker="true" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">ช่วงวันเริ่ม</label>
                <select name="start_day_part" class="form-select">
                    <option value="full">เต็มวัน</option>
                    <option value="morning">ครึ่งวันเช้า</option>
                    <option value="afternoon">ครึ่งวันบ่าย</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">ช่วงวันสิ้นสุด</label>
                <select name="end_day_part" class="form-select">
                    <option value="full">เต็มวัน</option>
                    <option value="morning">ครึ่งวันเช้า</option>
                    <option value="afternoon">ครึ่งวันบ่าย</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">เหตุผล <span class="text-danger">*</span></label>
                <textarea name="reason" class="form-control" rows="3" required></textarea>
            </div>
            <div class="col-12">
                <label class="form-label">หมายเหตุ HR/Admin</label>
                <textarea name="proxy_note" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-12"><button class="btn btn-primary" type="submit">บันทึกและอนุมัติทันที</button></div>
        </div>
    </form>

    <form class="card shadow-sm border-0 proxy-panel d-none" data-proxy-panel="late_early" data-action="create_late_early">
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label">ประเภท <span class="text-danger">*</span></label>
                <select name="time_request_type" class="form-select" required>
                    <option value="late_arrival">มาสาย</option>
                    <option value="early_departure">ออกก่อน</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">วันที่ <span class="text-danger">*</span></label>
                <input type="date" name="work_date" class="form-control" data-native-date-picker="true" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">เวลา <span class="text-danger">*</span></label>
                <input type="time" name="request_time" class="form-control" required>
            </div>
            <div class="col-12"><label class="form-label">เหตุผล <span class="text-danger">*</span></label><textarea name="reason" class="form-control" rows="3" required></textarea></div>
            <div class="col-12"><label class="form-label">หมายเหตุ HR/Admin</label><textarea name="proxy_note" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">บันทึกและอนุมัติทันที</button></div>
        </div>
    </form>

    <form class="card shadow-sm border-0 proxy-panel d-none" data-proxy-panel="overtime" data-action="create_overtime">
        <div class="card-body row g-3">
            <div class="col-md-6"><label class="form-label">วันที่ทำ OT <span class="text-danger">*</span></label><input type="date" name="work_date" class="form-control" data-native-date-picker="true" required></div>
            <div class="col-md-6"><label class="form-label">จำนวนนาที <span class="text-danger">*</span></label><input type="number" name="overtime_minutes" class="form-control" min="1" max="480" required></div>
            <div class="col-12"><label class="form-label">เหตุผล <span class="text-danger">*</span></label><textarea name="reason" class="form-control" rows="3" required></textarea></div>
            <div class="col-12"><label class="form-label">หมายเหตุ HR/Admin</label><textarea name="proxy_note" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">บันทึกและอนุมัติทันที</button></div>
        </div>
    </form>

    <form class="card shadow-sm border-0 proxy-panel d-none" data-proxy-panel="day_swap" data-action="create_day_swap">
        <div class="card-body row g-3">
            <div class="col-md-4"><label class="form-label">พนักงานคู่สลับ <span class="text-danger">*</span></label><select name="target_employee_id" id="proxyTargetEmployeeId" class="form-select" required></select></div>
            <div class="col-md-4"><label class="form-label">วันหยุดของพนักงานหลัก <span class="text-danger">*</span></label><input type="date" name="requester_date" class="form-control" data-native-date-picker="true" required></div>
            <div class="col-md-4"><label class="form-label">วันหยุดของคู่สลับ <span class="text-danger">*</span></label><input type="date" name="target_date" class="form-control" data-native-date-picker="true" required></div>
            <div class="col-12"><label class="form-label">เหตุผล <span class="text-danger">*</span></label><textarea name="reason" class="form-control" rows="3" required></textarea></div>
            <div class="col-12"><label class="form-label">หมายเหตุ HR/Admin</label><textarea name="proxy_note" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">บันทึกและอนุมัติทันที</button></div>
        </div>
    </form>

    <form class="card shadow-sm border-0 proxy-panel d-none" data-proxy-panel="training" data-action="create_training" enctype="multipart/form-data">
        <div class="card-body row g-3">
            <div class="col-md-6"><label class="form-label">หลักสูตร <span class="text-danger">*</span></label><input type="text" name="course_name" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">ผู้จัด/สถาบัน</label><input type="text" name="provider" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">ประเภทอบรม</label><input type="text" name="training_type" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">วันที่เริ่ม <span class="text-danger">*</span></label><input type="date" name="start_date" class="form-control" data-native-date-picker="true" required></div>
            <div class="col-md-4"><label class="form-label">วันที่สิ้นสุด <span class="text-danger">*</span></label><input type="date" name="end_date" class="form-control" data-native-date-picker="true" required></div>
            <div class="col-md-6"><label class="form-label">สถานที่/รูปแบบ</label><input type="text" name="location" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">ค่าใช้จ่ายประมาณ</label><input type="number" name="estimated_cost" class="form-control" min="0" step="0.01"></div>
            <div class="col-12"><label class="form-label">วัตถุประสงค์ <span class="text-danger">*</span></label><textarea name="objective" class="form-control" rows="3" required></textarea></div>
            <div class="col-12"><label class="form-label">ไฟล์แนบ</label><input type="file" name="attachment" class="form-control"></div>
            <div class="col-12"><label class="form-label">หมายเหตุ HR/Admin</label><textarea name="proxy_note" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">บันทึกและอนุมัติทันที</button></div>
        </div>
    </form>
</div>

<script src="assets/js/proxy_request.js"></script>
<?php require_once 'includes/footer.php'; ?>
