<?php
require_once 'includes/auth_check.php';

if (!in_array($_SESSION['role'] ?? '', ['admin', 'hr'], true)) {
    header('Location: dashboard.php');
    exit();
}

$page_title = 'ทำรายการแทนพนักงาน';
$use_select2 = true;
require_once 'includes/header.php';
?>

<style>
    .proxy-request-type-grid {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .proxy-type-btn {
        --proxy-accent: #b91c1c;
        --proxy-accent-soft: rgba(185, 28, 28, 0.1);
        display: inline-flex;
        align-items: center;
        gap: 0.65rem;
        min-height: 50px;
        padding: 0.78rem 1.15rem;
        border: 1px solid var(--proxy-accent);
        border-radius: 10px;
        background: var(--proxy-accent-soft);
        color: var(--proxy-accent);
        font-size: 1.02rem;
        font-weight: 600;
        transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
    }

    .proxy-type-btn:hover,
    .proxy-type-btn:focus {
        box-shadow: 0 0 0 0.2rem rgba(185, 28, 28, 0.12);
    }

    .proxy-type-btn.active {
        background: var(--proxy-accent);
        border-color: var(--proxy-accent);
        color: #ffffff;
    }

    .proxy-type-btn i {
        width: 1.2rem;
        font-size: 1.05rem;
        text-align: center;
    }

    .proxy-type-leave { --proxy-accent: #b91c1c; --proxy-accent-soft: rgba(185, 28, 28, 0.1); }
    .proxy-type-time { --proxy-accent: #b45309; --proxy-accent-soft: rgba(180, 83, 9, 0.12); }
    .proxy-type-ot { --proxy-accent: #1d4ed8; --proxy-accent-soft: rgba(29, 78, 216, 0.1); }
    .proxy-type-swap { --proxy-accent: #047857; --proxy-accent-soft: rgba(4, 120, 87, 0.1); }
    .proxy-type-training { --proxy-accent: #6d28d9; --proxy-accent-soft: rgba(109, 40, 217, 0.1); }

    .proxy-request-employee-card .select2-container {
        width: 100% !important;
    }

    @media (max-width: 575.98px) {
        .proxy-type-btn {
            flex: 1 1 100%;
            justify-content: center;
        }
    }
</style>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1 text-gray-800">ทำรายการแทนพนักงาน</h1>
        <p class="text-muted small mb-0">บันทึกคำขอในนามพนักงานและอนุมัติทันที พร้อมเก็บประวัติผู้ทำรายการ</p>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4 proxy-request-employee-card">
    <div class="card-body">
        <label class="form-label" for="proxyEmployeeId">พนักงาน <span class="text-danger">*</span></label>
        <select id="proxyEmployeeId" class="form-select" required disabled></select>
        <div id="proxyEmployeeLoadStatus" class="mt-2"></div>
        <p id="proxyActingContext" class="small mt-3 mb-0" role="status">เลือกพนักงานที่ต้องการทำรายการให้</p>
    </div>
</div>

<div class="proxy-request-type-grid" id="proxyRequestTabs" role="group" aria-label="เลือกประเภทรายการแทนพนักงาน">
    <button class="proxy-type-btn proxy-type-leave active" data-proxy-tab="leave" type="button" aria-pressed="true">
        <i class="fas fa-calendar-check"></i> ลา
    </button>
    <button class="proxy-type-btn proxy-type-time" data-proxy-tab="late_early" type="button" aria-pressed="false">
        <i class="fas fa-business-time"></i> มาสาย/ออกก่อน
    </button>
    <button class="proxy-type-btn proxy-type-ot" data-proxy-tab="overtime" type="button" aria-pressed="false">
        <i class="fas fa-clock"></i> OT
    </button>
    <button class="proxy-type-btn proxy-type-swap" data-proxy-tab="day_swap" type="button" aria-pressed="false">
        <i class="fas fa-right-left"></i> สลับวันหยุด
    </button>
    <button class="proxy-type-btn proxy-type-training" data-proxy-tab="training" type="button" aria-pressed="false">
        <i class="fas fa-people-arrows"></i> กิจกรรม
    </button>
</div>

<div class="proxy-request-panels">
    <form class="card shadow-sm border-0 proxy-panel" data-proxy-panel="leave" data-action="create_leave">
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label" for="proxyLeaveTypeId">ประเภทการลา <span class="text-danger">*</span></label>
                <select name="leave_type_id" id="proxyLeaveTypeId" class="form-select" required disabled></select>
                <div id="proxyLeaveTypeLoadStatus" class="mt-2"></div>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="request_proxyField1">วันที่เริ่ม <span class="text-danger">*</span></label>
                <input id="request_proxyField1" type="date" name="start_date" class="form-control" required>
            </div>
            <div class="col-md-3 proxy-day-leave-field">
                <label class="form-label" for="request_proxyField2">วันที่สิ้นสุด <span class="text-danger">*</span></label>
                <input id="request_proxyField2" type="date" name="end_date" class="form-control" required>
            </div>
            <div class="col-md-12 d-none" id="proxyHourlyLeaveFields">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="request_proxyField3">เวลาเริ่มลา <span class="text-danger">*</span></label>
                        <input id="request_proxyField3" type="time" name="request_start_time" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="request_proxyField4">เวลาสิ้นสุดลา <span class="text-danger">*</span></label>
                        <input id="request_proxyField4" type="time" name="request_end_time" class="form-control">
                    </div>
                    <div class="col-12"><div class="alert alert-light border d-none mb-0" id="proxyHourlyLeaveDuration"></div></div>
                </div>
            </div>
            <div class="col-md-6 proxy-day-leave-field">
                <label class="form-label" for="request_proxyField5">ช่วงวันเริ่ม</label>
                <select id="request_proxyField5" name="start_day_part" class="form-select">
                    <option value="full">เต็มวัน</option>
                    <option value="morning">ครึ่งวันเช้า</option>
                    <option value="afternoon">ครึ่งวันบ่าย</option>
                </select>
            </div>
            <div class="col-md-6 proxy-day-leave-field">
                <label class="form-label" for="request_proxyField6">ช่วงวันสิ้นสุด</label>
                <select id="request_proxyField6" name="end_day_part" class="form-select">
                    <option value="full">เต็มวัน</option>
                    <option value="morning">ครึ่งวันเช้า</option>
                    <option value="afternoon">ครึ่งวันบ่าย</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="request_proxyField7">เหตุผล <span class="text-danger">*</span></label>
                <textarea id="request_proxyField7" name="reason" class="form-control" rows="3" required></textarea>
            </div>
            <div class="col-12">
                <label class="form-label" for="request_proxyField8">หมายเหตุ HR/Admin</label>
                <textarea id="request_proxyField8" name="proxy_note" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-12"><button class="btn btn-primary" type="submit">บันทึกและอนุมัติทันที</button></div>
        </div>
    </form>

    <form class="card shadow-sm border-0 proxy-panel d-none" data-proxy-panel="late_early" data-action="create_late_early">
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label" for="request_proxyField9">ประเภท <span class="text-danger">*</span></label>
                <select id="request_proxyField9" name="time_request_type" class="form-select" required>
                    <option value="late_arrival">มาสาย</option>
                    <option value="early_departure">ออกก่อน</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="request_proxyField10">วันที่ <span class="text-danger">*</span></label>
                <input id="request_proxyField10" type="date" name="work_date" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="request_proxyField11">เวลา <span class="text-danger">*</span></label>
                <input id="request_proxyField11" type="time" name="request_time" class="form-control" required>
            </div>
            <div class="col-12"><label class="form-label" for="request_proxyField12">เหตุผล <span class="text-danger">*</span></label><textarea id="request_proxyField12" name="reason" class="form-control" rows="3" required></textarea></div>
            <div class="col-12"><label class="form-label" for="request_proxyField13">หมายเหตุ HR/Admin</label><textarea id="request_proxyField13" name="proxy_note" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">บันทึกและอนุมัติทันที</button></div>
        </div>
    </form>

    <form class="card shadow-sm border-0 proxy-panel d-none" data-proxy-panel="overtime" data-action="create_overtime">
        <div class="card-body row g-3">
            <div class="col-md-4"><label class="form-label" for="request_proxyField14">วันที่ทำ OT <span class="text-danger">*</span></label><input id="request_proxyField14" type="date" name="work_date" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label" for="request_proxyField15">เวลาเริ่ม OT <span class="text-danger">*</span></label><input id="request_proxyField15" type="time" name="overtime_start_time" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label" for="request_proxyField16">เวลาสิ้นสุด OT <span class="text-danger">*</span></label><input id="request_proxyField16" type="time" name="overtime_end_time" class="form-control" required></div>
            <div class="col-12"><div class="alert alert-light border d-none mb-0" id="proxyOvertimeDateContext"></div></div>
            <div class="col-12"><div class="alert alert-light border d-none mb-0" id="proxyOvertimeDuration"></div></div>
            <div class="col-12"><label class="form-label" for="request_proxyField17">เหตุผล <span class="text-danger">*</span></label><textarea id="request_proxyField17" name="reason" class="form-control" rows="3" required></textarea></div>
            <div class="col-12"><label class="form-label" for="request_proxyField18">หมายเหตุ HR/Admin</label><textarea id="request_proxyField18" name="proxy_note" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">บันทึกและอนุมัติทันที</button></div>
        </div>
    </form>

    <form class="card shadow-sm border-0 proxy-panel d-none" data-proxy-panel="day_swap" data-action="create_day_swap">
        <div class="card-body row g-3">
            <div class="col-md-4"><label class="form-label" for="proxyTargetEmployeeId">พนักงานคู่สลับ <span class="text-danger">*</span></label><select name="target_employee_id" id="proxyTargetEmployeeId" class="form-select" required></select></div>
            <div class="col-md-4"><label class="form-label" for="request_proxyField19">วันหยุดของพนักงานหลัก <span class="text-danger">*</span></label><input id="request_proxyField19" type="date" name="requester_date" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label" for="request_proxyField20">วันหยุดของคู่สลับ <span class="text-danger">*</span></label><input id="request_proxyField20" type="date" name="target_date" class="form-control" required></div>
            <div class="col-12"><label class="form-label" for="request_proxyField21">เหตุผล <span class="text-danger">*</span></label><textarea id="request_proxyField21" name="reason" class="form-control" rows="3" required></textarea></div>
            <div class="col-12"><label class="form-label" for="request_proxyField22">หมายเหตุ HR/Admin</label><textarea id="request_proxyField22" name="proxy_note" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">บันทึกและอนุมัติทันที</button></div>
        </div>
    </form>

    <form class="card shadow-sm border-0 proxy-panel d-none" data-proxy-panel="training" data-action="create_training" enctype="multipart/form-data">
        <div class="card-body row g-3">
            <div class="col-md-6"><label class="form-label" for="proxyActivityTypeId">ประเภทกิจกรรม <span class="text-danger">*</span></label><select name="activity_type_id" id="proxyActivityTypeId" class="form-select" required><option value="">กำลังโหลดประเภทกิจกรรม...</option></select><div id="proxyActivityTypeLoadStatus" class="mt-2"></div></div>
            <div class="col-md-6"><label class="form-label" for="request_proxyField23">ชื่อกิจกรรม/รายละเอียด <span class="text-danger">*</span></label><input id="request_proxyField23" type="text" name="course_name" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label" for="request_proxyField24">สถานที่/รูปแบบ</label><input id="request_proxyField24" type="text" name="location" class="form-control"></div>
            <div class="col-md-6"><label class="form-label" for="request_proxyField25">วันที่เริ่ม <span class="text-danger">*</span></label><input id="request_proxyField25" type="date" name="start_date" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label" for="request_proxyField26">วันที่สิ้นสุด <span class="text-danger">*</span></label><input id="request_proxyField26" type="date" name="end_date" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label" for="request_proxyField27">ช่วงวันเริ่ม</label><select id="request_proxyField27" name="start_day_part" class="form-select"><option value="full">เต็มวัน</option><option value="morning">ครึ่งวันเช้า</option><option value="afternoon">ครึ่งวันบ่าย</option></select></div>
            <div class="col-md-3"><label class="form-label" for="request_proxyField28">ช่วงวันสิ้นสุด</label><select id="request_proxyField28" name="end_day_part" class="form-select"><option value="full">เต็มวัน</option><option value="morning">ครึ่งวันเช้า</option><option value="afternoon">ครึ่งวันบ่าย</option></select></div>
            <div class="col-12"><label class="form-label" for="request_proxyField29">วัตถุประสงค์ <span class="text-danger">*</span></label><textarea id="request_proxyField29" name="objective" class="form-control" rows="3" required></textarea></div>
            <div class="col-12"><label class="form-label" for="request_proxyField30">ไฟล์แนบ</label><input id="request_proxyField30" type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
            <div class="col-12"><label class="form-label" for="request_proxyField31">หมายเหตุ HR/Admin</label><textarea id="request_proxyField31" name="proxy_note" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">บันทึกและอนุมัติทันที</button></div>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
