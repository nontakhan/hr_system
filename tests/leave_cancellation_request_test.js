const fs = require('fs');

function assertIncludes(text, expected, message) {
    if (!text.includes(expected)) {
        console.error(message);
        console.error('Missing:', expected);
        process.exit(1);
    }
}

const historyApi = fs.readFileSync('api/leave_history_api.php', 'utf8');
const approvalApi = fs.readFileSync('api/leave_approval_api.php', 'utf8');
const myLeavesScript = fs.readFileSync('assets/js/my_leaves.js', 'utf8');
const approvalScript = fs.readFileSync('assets/js/leave_approval.js', 'utf8');
const footer = fs.readFileSync('includes/footer.php', 'utf8');

assertIncludes(historyApi, "status IN ('pending','pending_manager','approved')", 'Leave history API should allow approved leave to enter cancellation approval.');
assertIncludes(historyApi, "cancel_reason", 'Leave history API should require and store a cancellation reason.');
assertIncludes(historyApi, "status = 'pending_cancel_hr'", 'Approved leave cancellation should move to the HR/admin cancellation queue.');

assertIncludes(approvalApi, "'pending_cancel_hr'", 'Leave approval API should fetch and process pending cancellation requests.');
assertIncludes(approvalApi, 'AS cancel_reason', 'Leave approval API should expose a stable cancellation reason field.');
assertIncludes(approvalApi, "SET status = 'cancelled'", 'Approving a cancellation request should cancel the leave.');
assertIncludes(approvalApi, "SET status = 'approved'", 'Rejecting a cancellation request should restore the approved leave.');

assertIncludes(myLeavesScript, "pending_cancel_hr", 'My leaves status badges should show cancellation requests awaiting HR/admin.');
assertIncludes(myLeavesScript, "input: 'textarea'", 'Cancelling an approved leave should ask the employee for a reason.');
assertIncludes(myLeavesScript, "cancel_reason", 'My leaves cancellation request should send the cancellation reason.');
assertIncludes(myLeavesScript, "item.status === 'approved'", 'Approved leave rows should show a cancellation request action.');

assertIncludes(approvalScript, "pending_cancel_hr", 'Approval page should render pending cancellation requests.');
assertIncludes(approvalScript, "cancel_reason", 'Approval page should show the employee cancellation reason.');
assertIncludes(approvalScript, "อนุมัติยกเลิก", 'Approval page should label cancellation approval distinctly.');
assertIncludes(approvalScript, "item.cancel_reason || item.cancellation_reason", 'Approval page should read the stable cancellation reason field first.');

assertIncludes(footer, "filemtime(__DIR__ . '/../assets/js/leave_approval.js')", 'Footer should cache-bust leave approval JS so HR sees updated Thai status labels.');

console.log('leave_cancellation_request_test passed');
