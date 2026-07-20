const fs = require('fs');
const assert = require('assert');

const read = path => fs.readFileSync(path, 'utf8');
const pages = [
    'leave_approvals.php',
    'late_early_approvals.php',
    'overtime_approvals.php',
    'day_swap_approvals.php',
    'training_approvals.php',
];
for (const page of pages) {
    const source = read(page);
    assert(source.includes('reviewer-cancel-action-column'), `${page} missing reviewer cancellation action column`);
    assert(source.includes('colspan="7"'), `${page} history empty row must span the new action column`);
}

const scripts = [
    ['leave/time/OT', read('assets/js/leave_approval.js'), 'loadHistoryLeaves'],
    ['day swap', read('assets/js/day_swap.js'), 'loadDaySwapApprovalHistory'],
    ['training', read('assets/js/training_request.js'), 'loadTrainingRequestApprovalHistory'],
];
for (const [name, source, reloadFunction] of scripts) {
    assert(source.includes('can_reviewer_cancel'), `${name} must use the server-authorized action flag`);
    assert(source.includes('reviewer-cancel-request-button'), `${name} missing compact reviewer cancellation button`);
    assert(source.includes("action: 'reviewer_cancel'"), `${name} missing reviewer_cancel submission`);
    assert(source.includes("input: 'textarea'"), `${name} cancellation dialog must collect a reason`);
    assert(source.includes('cancellation_reason'), `${name} must send the cancellation reason`);
    assert(source.includes(reloadFunction), `${name} must reload its approval history after success`);
    assert(source.includes('cancelled_by_name'), `${name} must render cancellation audit identity`);
    assert(source.includes('cancelled_at'), `${name} must render cancellation audit time`);
}

const stylesheet = read('assets/style.css');
assert(stylesheet.includes('.reviewer-cancel-request-button'), 'Shared stylesheet missing reviewer cancellation button style');

console.log('hr_admin_direct_request_cancellation_ui_test passed');
