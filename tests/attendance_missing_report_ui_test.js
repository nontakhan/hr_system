const fs = require('fs');

function assertIncludes(text, expected, message) {
    if (!text.includes(expected)) {
        console.error(message);
        console.error('Missing:', expected);
        process.exit(1);
    }
}

const page = fs.readFileSync('attendance_missing_report.php', 'utf8');
const script = fs.readFileSync('assets/js/attendance.js', 'utf8');

assertIncludes(page, 'id="attendanceMissingReportPage"', 'Missing scan report page should expose a stable JS mount point.');
assertIncludes(page, 'id="attendanceMissingMonth"', 'Missing scan report should provide a month filter.');
assertIncludes(page, 'id="attendanceMissingCompany"', 'Missing scan report should provide a company filter.');
assertIncludes(page, 'id="attendanceMissingBranch"', 'Missing scan report should provide a branch filter.');
assertIncludes(page, 'id="attendanceMissingType"', 'Missing scan report should provide a missing type filter.');
assertIncludes(page, 'id="attendanceMissingRows"', 'Missing scan report should render rows into a table body.');
assertIncludes(page, 'ไม่สแกนเข้า', 'Missing scan report should include Thai copy for missing check-in.');
assertIncludes(page, 'ไม่สแกนออก', 'Missing scan report should include Thai copy for missing check-out.');
assertIncludes(page, 'ไม่มีสแกนเข้า/ออก', 'Missing scan report should include Thai copy for no scans.');

assertIncludes(script, 'initAttendanceMissingReport', 'Attendance JS should initialize the missing scan report page.');
assertIncludes(script, 'missing_scan_report', 'Attendance JS should call the missing_scan_report API action.');
assertIncludes(script, 'response.text()', 'Missing scan report should read raw response text before parsing JSON.');
assertIncludes(script, 'เซิร์ฟเวอร์ไม่ส่งข้อมูลกลับ', 'Missing scan report should show a readable message when the API response is empty.');
assertIncludes(script, 'attendanceMissingCompany', 'Attendance JS should read the company filter.');
assertIncludes(script, 'attendanceMissingBranch', 'Attendance JS should read the branch filter.');
assertIncludes(script, 'attendanceMissingType', 'Attendance JS should read the missing type filter.');
assertIncludes(script, 'DataTable', 'Missing scan report should use DataTables when available.');

console.log('attendance_missing_report_ui_test passed');
