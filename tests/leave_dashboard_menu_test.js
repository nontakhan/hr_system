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
const myLeaves = fs.readFileSync('my_leaves.php', 'utf8');
const styles = fs.readFileSync('assets/style.css', 'utf8');

assertIncludes(header, 'href="my_leaves.php" class="list-group-item list-group-item-action bg-transparent d-flex align-items-center', 'Leave sidebar entry should link directly to the leave dashboard.');
assertIncludes(header, "isActive('leave_request.php')", 'Leave sidebar entry should stay active while on the leave request page.');
assertIncludes(header, "isActive('leave_approvals.php')", 'Leave sidebar entry should stay active while on the leave approval page.');
assertNotIncludes(header, 'href="#leaveSubmenu"', 'Leave sidebar entry should no longer open a submenu.');
assertNotIncludes(header, 'id="leaveSubmenu"', 'Leave sidebar submenu should be removed.');
assertNotIncludes(header, 'data-bs-toggle="collapse" aria-expanded="false" class="list-group-item list-group-item-action bg-transparent dropdown-toggle d-flex align-items-center">\n                <?php echo renderSidebarApprovalBadge($approvalBadgeCounts[\'leave\']); ?>\n                <i class="fas fa-calendar-alt me-2"></i>', 'Leave sidebar entry should not be a collapse toggle.');

assertIncludes(myLeaves, 'leave-dashboard-actions', 'My leaves page should render a dashboard action area.');
assertIncludes(myLeaves, 'leave-dashboard-actions-main', 'Employee leave actions should be grouped together.');
assertIncludes(myLeaves, 'leave-dashboard-actions-admin', 'Admin/HR leave actions should be grouped separately.');
assertIncludes(myLeaves, 'href="leave_request.php" class="btn leave-menu-button leave-menu-button-request"', 'Leave dashboard should include a button-style request shortcut.');
assertNotIncludes(myLeaves, 'leave-menu-button-history', 'Leave dashboard should not show a history button because the page already displays history.');
assertNotIncludes(myLeaves, 'href="#leaveHistorySection"', 'Leave dashboard should not show an in-page history shortcut.');
assertIncludes(myLeaves, "in_array(\$_SESSION['role'] ?? '', ['manager', 'admin', 'hr'], true)", 'Leave approval shortcut should only render for manager, HR, or admin roles.');
assertIncludes(myLeaves, "$approvalBadgeCounts['leave']", 'Leave dashboard approval shortcut should show the existing leave approval badge count.');
assertIncludes(myLeaves, 'leave-menu-button-approval', 'Leave approval shortcut should use its own button color treatment.');

assertIncludes(styles, '.leave-dashboard-actions', 'Styles should include leave dashboard action layout.');
assertIncludes(styles, '.leave-dashboard-actions-admin', 'Styles should include right-aligned admin/HR action layout.');
assertIncludes(styles, 'justify-content: flex-end;', 'Leave dashboard action buttons should align to the right.');
assertNotIncludes(styles, '.leave-menu-button-history', 'Styles should not keep an unused history button class.');
assertIncludes(styles, '.leave-menu-button', 'Styles should include button-style leave actions.');
assertIncludes(styles, '.leave-menu-button-approval', 'Styles should include a distinct approval button color.');

console.log('leave_dashboard_menu_test passed');
