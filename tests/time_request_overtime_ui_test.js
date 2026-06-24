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

assertIncludes(timeScript, "type === 'overtime_after_work'", 'History formatting should branch for OT requests.');
assertIncludes(timeScript, 'formatHourMinuteDuration', 'Time request script should format OT as hours and minutes.');
assertIncludes(approvalScript, "time_request_type === 'overtime_after_work'", 'Approval duration formatting should branch for OT requests.');
assertIncludes(approvalScript, 'eligible_overtime_minutes', 'Approval UI should show eligible OT from scan-out.');
assertIncludes(attendanceScript, 'attendanceHourlyRequestLabels', 'Attendance should continue rendering approved hourly request labels.');

console.log('time_request_overtime_ui_test passed');
