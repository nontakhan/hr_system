const fs = require('fs');

function assertIncludes(haystack, needle, message) {
  if (!haystack.includes(needle)) {
    console.error(message);
    console.error(`Expected to find: ${needle}`);
    process.exit(1);
  }
}

const timeScript = fs.readFileSync('assets/js/late_early_request.js', 'utf8');
const approvalScript = fs.readFileSync('assets/js/leave_approval.js', 'utf8');
const attendanceScript = fs.readFileSync('assets/js/attendance.js', 'utf8');
const header = fs.readFileSync('includes/header.php', 'utf8');
const overtimeHistoryPage = fs.readFileSync('overtime_history.php', 'utf8');
const overtimeRequestPage = fs.readFileSync('overtime_request.php', 'utf8');
const overtimeApprovalsPage = fs.readFileSync('overtime_approvals.php', 'utf8');

assertIncludes(timeScript, "type === 'overtime_after_work'", 'History formatting should branch for OT requests.');
assertIncludes(timeScript, 'formatHourMinuteDuration', 'Time request script should format OT as hours and minutes.');
assertIncludes(approvalScript, "time_request_type === 'overtime_after_work'", 'Approval duration formatting should branch for OT requests.');
assertIncludes(approvalScript, 'eligible_overtime_minutes', 'Approval UI should show eligible OT from scan-out.');
assertIncludes(attendanceScript, 'attendanceHourlyRequestLabels', 'Attendance should continue rendering approved hourly request labels.');
assertIncludes(header, 'href="overtime_history.php"', 'OT sidebar menu should link directly to the OT history landing page.');
assertIncludes(header, "isActive('overtime_request.php')", 'OT request page should keep the direct sidebar menu active.');
assertIncludes(header, "isActive('overtime_approvals.php')", 'OT approval page should keep the direct sidebar menu active.');
if (header.includes('overtimeSubmenu')) {
  console.error('OT sidebar menu should not use a submenu.');
  process.exit(1);
}
assertIncludes(overtimeHistoryPage, 'overtime-dashboard-actions', 'OT history page should expose top-right action buttons.');
assertIncludes(overtimeHistoryPage, 'href="overtime_request.php"', 'OT history page should link to the request page.');
assertIncludes(overtimeHistoryPage, 'href="overtime_approvals.php"', 'OT history page should link to the approval page for approvers.');
assertIncludes(overtimeHistoryPage, 'id="overtimeHistoryTable"', 'OT history table should have a stable DataTables id.');
assertIncludes(overtimeRequestPage, "window.timeRequestFixedType = 'overtime_after_work';", 'OT request page should remain scoped to OT submissions.');
assertIncludes(overtimeRequestPage, 'href="overtime_history.php"', 'OT request page should include a back button to the OT history landing page.');
assertIncludes(overtimeRequestPage, 'overtime-request-back-link', 'OT request back button should have a stable class.');
assertIncludes(overtimeApprovalsPage, 'href="overtime_history.php"', 'OT approval page should include a back button to the OT history landing page.');
assertIncludes(overtimeApprovalsPage, 'overtime-approval-back-link', 'OT approval back button should have a stable class.');
assertIncludes(timeScript, 'overtimeHistoryTable', 'Time request script should initialize DataTables for the OT history table.');

console.log('time_request_overtime_ui_test passed');
