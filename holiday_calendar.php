<?php
require_once 'includes/auth_check.php';

$page_title = "ปฏิทินวันหยุด";
$use_fullcalendar = true;
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h1 class="h3 mb-0 text-gray-800">ปฏิทินวันหยุด</h1>
        <p class="text-muted small mb-0">ดูวันหยุดบริษัทและวันหยุดประจำสัปดาห์ของคุณ เพื่อวางแผนลาและสลับวันหยุด</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="leave_request.php" class="btn btn-primary">
            <i class="fas fa-paper-plane me-1"></i> ยื่นใบลา
        </a>
        <a href="day_swap_request.php" class="btn btn-outline-primary">
            <i class="fas fa-right-left me-1"></i> ขอสลับวันหยุด
        </a>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">เดือน</label>
                <input type="month" id="holidayCalendarMonth" class="form-control" value="<?php echo date('Y-m'); ?>">
            </div>
            <div class="col-md-3">
                <button type="button" id="holidayCalendarLoadBtn" class="btn btn-outline-primary w-100">
                    <i class="fas fa-search me-1"></i> แสดงข้อมูล
                </button>
            </div>
        </div>
    </div>
</div>

<div id="holidayCalendarSummary" class="mb-3"></div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="holiday-calendar-legend mb-3" aria-label="Holiday calendar colors">
            <span><i class="holiday-calendar-dot holiday-calendar-dot-company"></i> วันหยุดบริษัท</span>
            <span><i class="holiday-calendar-dot holiday-calendar-dot-regular"></i> วันหยุดประจำสัปดาห์</span>
        </div>
        <div class="holiday-calendar-shell">
            <div id="holidayCalendar"></div>
            <div id="holidayCalendarEmpty" class="holiday-calendar-empty text-muted">
                กำลังโหลดปฏิทินวันหยุด...
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
