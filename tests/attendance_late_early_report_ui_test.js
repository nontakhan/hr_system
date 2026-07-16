const fs = require('fs');

function assertIncludes(text, expected, message) {
    if (!text.includes(expected)) {
        console.error(message);
        console.error('Missing:', expected);
        process.exit(1);
    }
}

const page = fs.readFileSync('attendance_late_early_report.php', 'utf8');
const script = fs.readFileSync('assets/js/attendance.js', 'utf8');

assertIncludes(page, 'id="attendanceLateEarlyReportPage"', 'Late/early report should expose a stable mount point.');
assertIncludes(page, 'id="attendanceLateEarlyMonth"', 'Late/early report should provide a month filter.');
assertIncludes(page, 'id="attendanceLateEarlyCompany"', 'Late/early report should provide a company filter.');
assertIncludes(page, 'id="attendanceLateEarlyBranch"', 'Late/early report should provide a branch filter.');
assertIncludes(page, 'id="attendanceLateEarlyType"', 'Late/early report should provide an incident type filter.');
assertIncludes(page, 'id="attendanceLateEarlyRows"', 'Late/early report should provide a table row target.');
assertIncludes(page, 'colspan="12"', 'Late/early report states should span all twelve columns.');
assertIncludes(page, 'รายงานมาสาย/ออกก่อน', 'Late/early report should include its Thai title.');
assertIncludes(page, 'มาสาย', 'Late/early report should include late-arrival copy.');
assertIncludes(page, 'ออกก่อน', 'Late/early report should include early-departure copy.');

assertIncludes(script, 'initAttendanceLateEarlyReport', 'Attendance JS should initialize the late/early report.');
assertIncludes(script, "action: 'late_early_report'", 'Attendance JS should call the late_early_report action.');
assertIncludes(script, 'attendanceLateEarlyCompany', 'Attendance JS should read the company filter.');
assertIncludes(script, 'attendanceLateEarlyBranch', 'Attendance JS should read the branch filter.');
assertIncludes(script, 'attendanceLateEarlyType', 'Attendance JS should read the incident type filter.');
assertIncludes(script, 'response.text()', 'Late/early report should parse response text safely.');
assertIncludes(script, 'เซิร์ฟเวอร์ไม่ส่งข้อมูลกลับ', 'Late/early report should show a readable empty-response error.');
assertIncludes(script, 'escapeHtml', 'Late/early report should escape rendered values.');
assertIncludes(script, 'DataTable', 'Late/early report should use DataTables for long results.');

console.log('attendance_late_early_report_ui_test passed');
