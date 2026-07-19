const fs = require('fs');

const page = fs.readFileSync('employee_request_attendance_report.php', 'utf8');
const script = fs.readFileSync('assets/js/employee_request_attendance_report.js', 'utf8');
const header = fs.readFileSync('includes/header.php', 'utf8');
const footer = fs.readFileSync('includes/footer.php', 'utf8');

function assertIncludes(source, needle, message) {
    if (!source.includes(needle)) throw new Error(message);
}

assertIncludes(page, 'id="employeeRequestAttendanceReportPage"', 'Stable page mount is required.');
assertIncludes(page, 'id="employeeRequestAttendanceReportEmployee"', 'Employee Select2 is required.');
assertIncludes(page, 'id="employeeRequestAttendanceReportMonth"', 'Month filter is required.');
assertIncludes(page, 'id="employeeRequestAttendanceReportLoad"', 'Manual load button is required.');
assertIncludes(page, 'id="employeeRequestAttendanceReportType"', 'Type filter is required.');
assertIncludes(page, 'id="employeeRequestAttendanceReportSource"', 'Source filter is required.');
assertIncludes(page, 'รายงานคำขอและเหตุการณ์พนักงาน', 'Thai report title is required.');
assertIncludes(page, 'วันที่', 'Date heading is required.');
assertIncludes(page, 'แหล่งข้อมูล', 'Source heading is required.');
assertIncludes(page, 'สถานะ', 'Status heading is required.');
assertIncludes(page, 'employeeRequestAttendanceReportTotal', 'Total summary is required.');
assertIncludes(page, 'employeeRequestAttendanceReportApproved', 'Approved summary is required.');
assertIncludes(page, 'employeeRequestAttendanceReportScanner', 'Scanner summary is required.');
assertIncludes(page, 'employeeRequestAttendanceReportOvertime', 'OT summary is required.');

assertIncludes(script, 'initEmployeeRequestAttendanceReport', 'Page initializer is required.');
assertIncludes(script, "params.set('action', 'employee_request_attendance_report')", 'Report action is required.');
assertIncludes(script, "params.set('action', 'employees')", 'Scoped employee options are required.');
assertIncludes(script, "addEventListener('click'", 'Only the load button should trigger the report fetch.');
assertIncludes(script, 'response.text()', 'Empty response must be detected safely.');
assertIncludes(script, 'เซิร์ฟเวอร์ไม่ส่งข้อมูลกลับ', 'Empty response needs Thai copy.');
assertIncludes(script, 'escapeEmployeeRequestAttendanceHtml', 'Rendered data must be escaped with a page-scoped helper.');
assertIncludes(script, 'DataTable', 'Long results must use DataTables.');
assertIncludes(script, 'destroy()', 'Existing DataTable must be destroyed before rerendering.');
assertIncludes(header, 'employee_request_attendance_report.php', 'Report navigation is required.');
assertIncludes(footer, 'assets/js/employee_request_attendance_report.js', 'Page script include is required.');

console.log('employee_request_attendance_report_ui_test passed');
