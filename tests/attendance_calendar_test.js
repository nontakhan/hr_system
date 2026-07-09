const fs = require('fs');
const vm = require('vm');

global.document = {
    addEventListener() {},
};
global.window = {
    addEventListener() {},
};

vm.runInThisContext(fs.readFileSync('assets/js/utils.js', 'utf8'));
vm.runInThisContext(fs.readFileSync('assets/js/attendance.js', 'utf8'));

function assertSame(expected, actual, message) {
    if (expected !== actual) {
        console.error(message);
        console.error('Expected:', expected);
        console.error('Actual:  ', actual);
        process.exit(1);
    }
}

function assertIncludes(haystack, needle, message) {
    if (!String(haystack).includes(needle)) {
        console.error(message);
        console.error('Expected to include:', needle);
        console.error('Actual:             ', haystack);
        process.exit(1);
    }
}

function assertNotSame(unexpected, actual, message) {
    if (unexpected === actual) {
        console.error(message);
        console.error('Unexpected:', unexpected);
        console.error('Actual:    ', actual);
        process.exit(1);
    }
}

const absentEvent = buildAttendanceCalendarEvent({
    work_date: '2026-01-05',
    day_name: 'Mon',
    check_in: null,
    check_out: null,
    status: 'absent',
    status_label: 'ขาด',
});
assertSame('ขาด', absentEvent.title, 'Calendar event title should use the attendance status label.');
assertSame('2026-01-05', absentEvent.start, 'Calendar event should be placed on the work date.');
assertSame('#fecaca', absentEvent.backgroundColor, 'Absent calendar days should use a clear red-tinted background.');
assertSame('#991b1b', absentEvent.textColor, 'Absent calendar days should keep a clear red status color.');
assertSame('absent', absentEvent.extendedProps.row.status, 'Calendar event should retain the original row for popup details.');

const missingOutColors = attendanceCalendarStatusColor('missing_out');
const holidayColors = attendanceCalendarStatusColor('holiday');
const companyHolidayColors = attendanceCalendarStatusColor('company_holiday');
const lateColors = attendanceCalendarStatusColor('late');
const trainingColors = attendanceCalendarStatusColor('training');
assertSame('#fed7aa', lateColors.background, 'Late days should use an orange background.');
assertSame('#fef08a', missingOutColors.background, 'Incomplete scan days should use a yellow background.');
assertSame('#e5e7eb', holidayColors.background, 'Regular holidays should use a gray background.');
assertSame('#bfdbfe', companyHolidayColors.background, 'Company holidays should use a blue background.');
assertSame('#ddd6fe', trainingColors.background, 'Approved training should use a purple background.');
assertNotSame(missingOutColors.background, holidayColors.background, 'Incomplete scan and holiday colors should be clearly different.');
assertNotSame(holidayColors.background, companyHolidayColors.background, 'Regular and company holidays should use different colors.');

const regularHolidayEvent = buildAttendanceCalendarEvent({
    work_date: '2026-01-06',
    status: 'holiday',
    status_label: 'วันหยุด',
    holiday_name: null,
});
const companyHolidayEvent = buildAttendanceCalendarEvent({
    work_date: '2026-01-07',
    status: 'holiday',
    status_label: 'วันหยุด',
    holiday_name: 'วันหยุดบริษัท',
});
assertSame('วันหยุดปกติ', regularHolidayEvent.title, 'Regular holiday calendar text should be explicit.');
assertSame('วันหยุดบริษัท', companyHolidayEvent.title, 'Company holiday calendar text should be explicit.');

const incompleteCard = attendanceSummaryCard('สแกนไม่ครบ', 1, 'attendance-incomplete', 'fa-triangle-exclamation');
const holidayCard = attendanceSummaryCard('วันหยุดปกติ', 1, 'attendance-holiday', 'fa-calendar-day');
const companyHolidayCard = attendanceSummaryCard('วันหยุดบริษัท', 1, 'attendance-company-holiday', 'fa-building-circle-check');
const lateCard = attendanceSummaryCard('สาย', 1, 'attendance-late', 'fa-clock');
assertIncludes(incompleteCard, 'attendance-summary-card-incomplete', 'Incomplete summary card should use the matching custom color class.');
assertIncludes(holidayCard, 'attendance-summary-card-holiday', 'Holiday summary card should use the matching custom color class.');
assertIncludes(companyHolidayCard, 'attendance-summary-card-company-holiday', 'Company holiday summary card should use the matching custom color class.');
assertIncludes(lateCard, 'attendance-summary-card-late', 'Late summary card should use the matching orange color class.');

const holidayDetails = buildAttendanceCalendarDetails({
    work_date: '2026-01-06',
    day_name: 'Tue',
    check_in: null,
    check_out: null,
    status: 'holiday',
    status_label: 'วันหยุด',
    holiday_name: 'วันหยุดบริษัท',
});
assertIncludes(holidayDetails, 'วันหยุดบริษัท', 'Holiday popup details should include the holiday name.');
assertIncludes(holidayDetails, 'เวลาเข้า', 'Popup details should include work time labels.');

const leaveDetails = buildAttendanceCalendarDetails({
    work_date: '2026-01-07',
    day_name: 'Wed',
    check_in: '08:31:00',
    check_out: '17:02:00',
    status: 'leave',
    status_label: 'ลา',
    leave_name: 'ลาป่วย',
});
assertIncludes(leaveDetails, 'ลาป่วย', 'Leave popup details should include the leave type.');
assertIncludes(leaveDetails, '08:31', 'Popup details should show check-in time.');

const personalLeaveEvent = buildAttendanceCalendarEvent({
    work_date: '2026-01-10',
    status: 'leave',
    status_label: 'ลา',
    leave_name: 'ลากิจ',
});
assertSame('ลา + ลากิจ', personalLeaveEvent.title, 'Leave days should append the leave type in the calendar title.');
assertIncludes(personalLeaveEvent.title, 'ลากิจ', 'Calendar event title should mention personal leave requests.');

const trainingEvent = buildAttendanceCalendarEvent({
    work_date: '2026-01-12',
    status: 'present',
    status_label: 'ปกติ',
    training_name: 'Safety Training',
});
assertSame('ปกติ + Safety Training', trainingEvent.title, 'Activity days should keep the normal attendance title and append the approved activity.');
assertIncludes(trainingEvent.title, 'Safety Training', 'Activity days should mention the approved activity in the calendar title.');
assertSame('#bbf7d0', trainingEvent.backgroundColor, 'Activity days without scans should use the normal workday color.');
assertIncludes(trainingEvent.classNames.join(' '), 'attendance-event-present', 'Activity events should receive the normal present class.');

const trainingDetails = buildAttendanceCalendarDetails({
    work_date: '2026-01-12',
    day_name: 'Mon',
    check_in: null,
    check_out: null,
    status: 'present',
    status_label: 'ปกติ',
    training_name: 'Safety Training',
});
assertIncludes(trainingDetails, 'Safety Training', 'Training popup details should include the training course.');

const overrideDetails = buildAttendanceCalendarDetails({
    work_date: '2026-01-09',
    day_name: 'Fri',
    check_in: '08:00:00',
    check_out: '17:00:00',
    raw_check_in: null,
    raw_check_out: '17:00:00',
    status: 'present',
    status_label: 'ปกติ',
    has_override: true,
    override_check_in: '08:00:00',
    override_check_out: null,
    override_reason: 'เครื่องสแกนเสียช่วงเช้า',
    override_created_by_name: 'ฝ่ายบุคคล',
});
assertIncludes(overrideDetails, 'ปรับโดย HR', 'Popup details should disclose HR attendance corrections.');
assertIncludes(overrideDetails, 'เครื่องสแกนเสียช่วงเช้า', 'Popup details should include the override reason.');
assertIncludes(overrideDetails, 'ฝ่ายบุคคล', 'Popup details should include the adjusting HR user when available.');

const hourlyRequestEvent = buildAttendanceCalendarEvent({
    work_date: '2026-01-08',
    status: 'present',
    status_label: 'ปกติ',
    hourly_requests: ['ขอมาสาย 35 นาที', 'OT หลังเลิกงาน 1 ชม. 30 นาที'],
});
assertIncludes(hourlyRequestEvent.title, 'ขอมาสาย 35 นาที', 'Calendar event title should mention approved hourly requests.');
assertIncludes(hourlyRequestEvent.title, 'OT หลังเลิกงาน 1 ชม. 30 นาที', 'Calendar event title should mention approved OT requests.');

const hourlyRequestDetails = buildAttendanceCalendarDetails({
    work_date: '2026-01-08',
    day_name: 'Thu',
    check_in: '08:31:00',
    check_out: '17:02:00',
    status: 'present',
    status_label: 'ปกติ',
    hourly_requests: ['ขอมาสาย 35 นาที', 'ขอออกก่อน 40 นาที'],
});
assertIncludes(hourlyRequestDetails, 'คำขอเวลา', 'Popup details should include an hourly request section.');
assertIncludes(hourlyRequestDetails, 'ขอออกก่อน 40 นาที', 'Popup details should list approved early departure requests.');

const counts = countAttendanceReportStatuses([
    { status: 'holiday', holiday_name: null },
    { status: 'holiday', holiday_name: 'วันหยุดบริษัท' },
    { status: 'holiday', holiday_name: '' },
    { status: 'present' },
    { status: 'present', training_name: 'Safety Training' },
]);
assertSame(2, counts.regular_holiday, 'Shift holidays should be counted separately from company holidays.');
assertSame(1, counts.company_holiday, 'Company holidays should be counted separately when holiday_name is present.');
assertSame(2, counts.present, 'Approved activity should count as normal attendance.');
assertSame(1, counts.training, 'Approved activity should still be counted as an activity summary.');

const calendarOptions = buildAttendanceCalendarOptions();
assertSame(1, calendarOptions.firstDay, 'Attendance calendar should start weeks on Monday.');

assertSame('2026-01', normalizeAttendanceRangeEnd('', '2026-01'), 'Blank range end should default to the start month.');
assertSame('2026-03', normalizeAttendanceRangeEnd('2026-03', '2026-01'), 'Range end should keep the selected end month.');
assertSame(3, countAttendanceRangeMonths('2026-01', '2026-03'), 'Range helper should count inclusive months.');
assertSame(true, isAttendanceRangeValid('2026-01', '2026-03'), 'Forward month ranges should be valid.');
assertSame(false, isAttendanceRangeValid('2026-03', '2026-01'), 'End month before start month should be invalid.');
assertSame(false, isAttendanceRangeValid('2026-01', '2027-02'), 'Ranges longer than 12 months should be invalid.');

const rangeLabel = formatAttendanceReportRangeLabel({ month: '2026-01', start_month: '2026-01', end_month: '2026-03' });
assertIncludes(rangeLabel, formatThaiMonth('2026-01'), 'Range label should include the first month.');
assertIncludes(rangeLabel, formatThaiMonth('2026-03'), 'Range label should include the last month.');

const multiMonthOptions = buildAttendanceCalendarOptions('2026-01-01', [], 3);
assertSame('multiMonth', multiMonthOptions.initialView, 'Multi-month ranges should use a duration-based FullCalendar view.');
assertSame(3, multiMonthOptions.duration.months, 'Multi-month calendar should only render the requested number of months.');
assertSame(3, multiMonthOptions.multiMonthMaxColumns, 'Multi-month calendar should keep a compact column count.');
assertSame(3, multiMonthOptions.visibleRange().end.getMonth(), 'Visible range should end after the requested final month.');

assertSame('2026-07', getAttendanceCurrentMonth(new Date('2026-07-05T12:00:00')), 'Self-service attendance should be able to default to the current month.');
assertSame(false, canSubmitAttendanceReport(true, '', '2026-07'), 'Admin and HR attendance reports should require an employee before loading.');
assertSame(false, canSubmitAttendanceReport(true, '15', ''), 'Admin and HR attendance reports should require a month before loading.');
assertSame(true, canSubmitAttendanceReport(false, '', '2026-07'), 'Self-service attendance reports should not require an employee selection.');

const loadingHtml = buildAttendanceReportLoadingHtml();
assertIncludes(loadingHtml, 'spinner-border', 'Attendance loading state should show a spinner.');
assertIncludes(loadingHtml, 'กำลังโหลด', 'Attendance loading state should explain that data is loading.');

attendanceCalendarDayClassMap = buildAttendanceCalendarDayClassMap([
    { work_date: '2026-01-05', status: 'absent' },
    { work_date: '2026-01-06', status: 'present' },
    { work_date: '2026-01-07', status: 'holiday', holiday_name: 'วันหยุดบริษัท' },
    { work_date: '2026-01-08', status: 'present', training_name: 'Safety Training' },
]);
const absentDayClasses = calendarOptions.dayCellClassNames({ date: new Date(2026, 0, 5) });
const companyHolidayDayClasses = calendarOptions.dayCellClassNames({ date: new Date(2026, 0, 7) });
const trainingDayClasses = calendarOptions.dayCellClassNames({ date: new Date(2026, 0, 8) });
assertIncludes(absentDayClasses.join(' '), 'attendance-day-absent', 'Calendar day cells should receive a status class for full-cell coloring.');
assertIncludes(companyHolidayDayClasses.join(' '), 'attendance-day-company_holiday', 'Company holiday cells should receive a distinct color class.');
assertIncludes(trainingDayClasses.join(' '), 'attendance-day-present', 'Activity cells should receive the normal present color class.');

console.log('attendance_calendar_test passed');
