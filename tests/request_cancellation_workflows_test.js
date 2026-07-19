const fs = require('fs');
const assert = require('assert');

const read = path => fs.readFileSync(path, 'utf8');
const dayHelper = read('includes/day_swap_helpers.php');
const trainingHelper = read('includes/training_request_helpers.php');

for (const [name, source] of [['day swap', dayHelper], ['training', trainingHelper]]) {
    assert(source.includes('pending_cancel_hr'), `${name} schema must support pending cancellation`);
    assert(source.includes('cancellation_reason'), `${name} schema must store cancellation reason`);
}

const contracts = [
    ['time request API', read('api/late_early_request_api.php'), ['cancellation_reason', 'requestCancellationEmployeeTransition', "time_request_type IN ('late_arrival','early_departure')", "time_request_type = 'overtime_after_work'", 'affected_rows']],
    ['day swap API', read('api/day_swap_api.php'), ['cancellation_reason', 'requestCancellationEmployeeTransition', 'requestCancellationReviewerTransition', "status = 'pending_cancel_hr'", 'affected_rows']],
    ['training API', read('api/training_request_api.php'), ['cancellation_reason', 'requestCancellationEmployeeTransition', 'requestCancellationReviewerTransition', "status = 'pending_cancel_hr'", 'affected_rows']],
    ['time request UI', read('assets/js/late_early_request.js'), ["input: 'textarea'", 'กรุณาระบุเหตุผลการยกเลิก', 'pending_cancel_hr', 'ขอยกเลิก']],
    ['day swap UI', read('assets/js/day_swap.js'), ["input: 'textarea'", 'เหตุผลการยกเลิก', 'pending_cancel_hr', 'อนุมัติยกเลิก']],
    ['training UI', read('assets/js/training_request.js'), ["input: 'textarea'", 'เหตุผลการยกเลิก', 'pending_cancel_hr', 'อนุมัติยกเลิก']],
];
for (const [name, source, tokens] of contracts) {
    for (const token of tokens) assert(source.includes(token), `${name} missing ${token}`);
}

const attendanceApi = read('api/attendance_api.php');
const warningHelper = read('includes/attendance_warning_source_helpers.php');
const badgeHelper = read('includes/approval_badge_helpers.php');
assert((attendanceApi.match(/pending_cancel_hr/g) || []).length >= 6, 'attendance consumers must retain pending cancellations');
assert(warningHelper.includes('pending_cancel_hr'), 'warning sources must retain pending cancellations');
assert(badgeHelper.includes("['pending_hr', 'pending_cancel_hr']"), 'HR badges must count cancellations');

console.log('request_cancellation_workflows_test passed');
