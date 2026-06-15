const fs = require('fs');

function assertIncludes(text, expected, message) {
    if (!text.includes(expected)) {
        console.error(message);
        console.error('Missing:', expected);
        process.exit(1);
    }
}

function assertNotIncludes(text, unexpected, message) {
    if (text.includes(unexpected)) {
        console.error(message);
        console.error('Unexpected:', unexpected);
        process.exit(1);
    }
}

const header = fs.readFileSync('includes/header.php', 'utf8');
const timeApprovalsPage = fs.readFileSync('late_early_approvals.php', 'utf8');
const leaveApprovalsPage = fs.readFileSync('leave_approvals.php', 'utf8');
const script = fs.readFileSync('assets/js/leave_approval.js', 'utf8');
const api = fs.readFileSync('api/leave_approval_api.php', 'utf8');

assertIncludes(header, 'href="late_early_approvals.php"', 'Time request approval menu should link to the time approval page.');
assertNotIncludes(header, 'href="leave_approvals.php" class="list-group-item list-group-item-action bg-transparent border-0 ps-5 d-flex align-items-center <?php echo isActive(\'leave_approvals.php\'); ?>">\n                    <?php echo renderSidebarApprovalBadge($approvalBadgeCounts[\'time_request\']); ?>\n                    <small>อนุมัติคำขอเวลา</small>', 'Time request approval menu should not link to leave approvals.');
assertIncludes(header, 'isActive(\'late_early_approvals.php\')', 'Time request approval page should open the time request menu group.');

assertIncludes(timeApprovalsPage, "window.leaveApprovalRequestUnit = 'hour';", 'Time request approval page should request only hourly time requests.');
assertIncludes(leaveApprovalsPage, "window.leaveApprovalRequestUnit = 'day';", 'Leave approval page should request only day-based leave.');
assertIncludes(timeApprovalsPage, 'อนุมัติคำขอเวลา', 'Time request approval page should have time request wording.');

assertIncludes(script, 'getLeaveApprovalRequestUnit', 'Approval JS should read page-level approval scope.');
assertIncludes(script, 'getLeaveApprovalRequestLabel', 'Approval JS should use time request wording on the time approval page.');
assertIncludes(script, 'request_unit', 'Approval JS should send request_unit to the API.');
assertIncludes(api, "request_unit = 'hour'", 'Approval API should support filtering hourly time requests.');
assertIncludes(api, "request_unit <> 'hour'", 'Approval API should support excluding hourly rows for leave approvals.');

console.log('time_request_approval_menu_test passed');
