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
const timeHistoryPage = fs.readFileSync('late_early_history.php', 'utf8');
const overtimeHistoryPage = fs.readFileSync('overtime_history.php', 'utf8');
const timeApprovalsPage = fs.readFileSync('late_early_approvals.php', 'utf8');
const overtimeApprovalsPage = fs.readFileSync('overtime_approvals.php', 'utf8');
const leaveApprovalsPage = fs.readFileSync('leave_approvals.php', 'utf8');
const script = fs.readFileSync('assets/js/leave_approval.js', 'utf8');
const api = fs.readFileSync('api/leave_approval_api.php', 'utf8');

assertIncludes(header, 'href="late_early_history.php"', 'Time request sidebar menu should link directly to the history page.');
assertIncludes(timeHistoryPage, 'href="late_early_approvals.php"', 'Time request history page should link to the time approval page.');
assertIncludes(header, 'href="overtime_history.php"', 'Overtime sidebar menu should link directly to the OT history page.');
assertIncludes(overtimeHistoryPage, 'href="overtime_approvals.php"', 'Overtime history page should link to the OT approval page.');
assertNotIncludes(header, 'href="leave_approvals.php" class="list-group-item list-group-item-action bg-transparent border-0 ps-5 d-flex align-items-center <?php echo isActive(\'leave_approvals.php\'); ?>">\n                    <?php echo renderSidebarApprovalBadge($approvalBadgeCounts[\'time_request\']); ?>\n                    <small>อนุมัติคำขอเวลา</small>', 'Time request approval menu should not link to leave approvals.');
assertIncludes(header, "isActive('late_early_approvals.php')", 'Time request approval page should keep the direct sidebar item active.');
assertIncludes(header, 'isActive(\'overtime_approvals.php\')', 'Overtime approval page should keep the direct sidebar item active.');

assertIncludes(timeApprovalsPage, "window.leaveApprovalRequestUnit = 'hour';", 'Time request approval page should request only hourly time requests.');
assertIncludes(timeApprovalsPage, "window.leaveApprovalTimeRequestType = 'late_early';", 'Time request approval page should exclude OT rows.');
assertIncludes(timeApprovalsPage, 'href="late_early_history.php"', 'Time request approval page should include a back button to the history landing page.');
assertIncludes(timeApprovalsPage, 'time-request-approval-back-link', 'Time request approval back button should have a stable class.');
assertIncludes(overtimeApprovalsPage, "window.leaveApprovalTimeRequestType = 'overtime_after_work';", 'Overtime approval page should request only OT rows.');
assertIncludes(overtimeApprovalsPage, 'href="overtime_history.php"', 'Overtime approval page should include a back button to the OT history landing page.');
assertIncludes(overtimeApprovalsPage, 'overtime-approval-back-link', 'Overtime approval back button should have a stable class.');
assertIncludes(leaveApprovalsPage, "window.leaveApprovalRequestUnit = 'day';", 'Leave approval page should request only day-based leave.');
assertIncludes(timeApprovalsPage, 'อนุมัติคำขอเวลา', 'Time request approval page should have time request wording.');
assertIncludes(overtimeApprovalsPage, 'อนุมัติ OT หลังเลิกงาน', 'Overtime approval page should have OT wording.');

assertIncludes(script, 'getLeaveApprovalRequestUnit', 'Approval JS should read page-level approval scope.');
assertIncludes(script, 'getLeaveApprovalRequestLabel', 'Approval JS should use time request wording on the time approval page.');
assertIncludes(script, 'getLeaveApprovalTimeRequestType', 'Approval JS should read page-level hourly request type scope.');
assertIncludes(script, 'initLeaveApprovalDataTable', 'Approval JS should initialize DataTables after rendering approval rows.');
assertIncludes(script, 'resetLeaveApprovalDataTable', 'Approval JS should destroy existing DataTables before replacing rows.');
assertIncludes(script, "initLeaveApprovalDataTable('pendingTable', 'pending'", 'Pending approval rows should use DataTables.');
assertIncludes(script, "initLeaveApprovalDataTable('historyTable', 'history'", 'History approval rows should use DataTables.');
assertIncludes(script, '$(selector).DataTable', 'Approval table helper should initialize DataTables through jQuery.');
assertIncludes(script, 'renderEmployeeAvatar(item.profile_img_url)', 'Approval rows should render employee photos through the shared default-image fallback.');
assertIncludes(api, 'e.profile_img_url', 'Approval API should return employee profile images.');
assertIncludes(script, 'request_unit', 'Approval JS should send request_unit to the API.');
assertIncludes(script, 'time_request_type', 'Approval JS should send time_request_type to the API.');
assertIncludes(api, "request_unit = 'hour'", 'Approval API should support filtering hourly time requests.');
assertIncludes(api, "time_request_type = 'overtime_after_work'", 'Approval API should support filtering OT hourly requests.');
assertIncludes(api, "request_unit <> 'hour'", 'Approval API should support excluding hourly rows for leave approvals.');

console.log('time_request_approval_menu_test passed');
