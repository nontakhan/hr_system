const fs = require('fs');
const assert = require('assert');

const read = path => fs.readFileSync(path, 'utf8');
const endpoints = [
    ['leave/time/OT', read('api/leave_approval_api.php'), 'leave_requests'],
    ['day swap', read('api/day_swap_api.php'), 'day_swap_requests'],
    ['training', read('api/training_request_api.php'), 'training_requests'],
];

for (const [name, source, table] of endpoints) {
    assert(source.includes("reviewer_cancel"), `${name} missing reviewer_cancel action`);
    assert(source.includes('requestCancellationReviewerDirectTransition'), `${name} missing direct reviewer transition guard`);
    assert(source.includes("['hr', 'admin']"), `${name} missing HR/Admin role gate`);
    assert(source.includes('hrScopeBuildEmployeeWhereClause'), `${name} missing HR scope enforcement`);
    assert(source.includes('cancellation_reason'), `${name} missing mandatory cancellation reason`);
    assert(source.includes('cancelled_by_user_id'), `${name} missing cancelling user audit`);
    assert(source.includes('cancelled_by_employee_id'), `${name} missing cancelling employee audit`);
    assert(source.includes('cancelled_by_role'), `${name} missing cancelling role audit`);
    assert(source.includes('cancelled_at'), `${name} missing cancellation timestamp audit`);
    assert(source.includes("status = 'approved'"), `${name} update must be atomic from approved status`);
    assert(source.includes('affected_rows !== 1'), `${name} must reject stale or repeated cancellation`);
    assert(source.includes(`UPDATE ${table}`), `${name} missing update for ${table}`);
    assert(source.includes('can_reviewer_cancel'), `${name} history must expose the server-authorized action flag`);
}

const leave = endpoints[0][1];
assert(leave.includes("time_request_type = 'overtime_after_work'"), 'leave endpoint must retain the OT discriminator');
assert(leave.includes("time_request_type IN ('late_arrival','early_departure')"), 'leave endpoint must retain the late/early discriminator');

const trainingApi = endpoints[2][1];
assert(trainingApi.includes('training_record_id'), 'training cancellation must trace its generated employee training record');
assert(trainingApi.includes('UPDATE employee_training_records'), 'training cancellation must revoke the generated training history status');
assert(trainingApi.includes('begin_transaction'), 'training request and generated history record must change in one transaction');

const historySources = [
    ['leave/time/OT history', leave],
    ['day swap history', endpoints[1][1]],
    ['training history', read('includes/training_request_helpers.php')],
];
for (const [name, source] of historySources) {
    assert(source.includes('cancelled_by_name'), `${name} missing canceller display name`);
    assert(source.includes('cancelled_by_employee_id'), `${name} missing canceller employee join`);
    assert(source.includes('cancelled_at'), `${name} missing cancellation timestamp`);
}

console.log('hr_admin_direct_request_cancellation_api_test passed');
