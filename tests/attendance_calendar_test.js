const fs = require('fs');
const vm = require('vm');

global.document = {
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
assertSame('#fee2e2', absentEvent.backgroundColor, 'Absent calendar days should use a clear red-tinted background.');
assertSame('#b91c1c', absentEvent.textColor, 'Absent calendar days should keep a clear red status color.');
assertSame('absent', absentEvent.extendedProps.row.status, 'Calendar event should retain the original row for popup details.');

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

const counts = countAttendanceReportStatuses([
    { status: 'holiday', holiday_name: null },
    { status: 'holiday', holiday_name: 'วันหยุดบริษัท' },
    { status: 'holiday', holiday_name: '' },
    { status: 'present' },
]);
assertSame(2, counts.regular_holiday, 'Shift holidays should be counted separately from company holidays.');
assertSame(1, counts.company_holiday, 'Company holidays should be counted separately when holiday_name is present.');
assertSame(1, counts.present, 'Existing status counts should still work.');

const calendarOptions = buildAttendanceCalendarOptions();
assertSame(1, calendarOptions.firstDay, 'Attendance calendar should start weeks on Monday.');

console.log('attendance_calendar_test passed');
