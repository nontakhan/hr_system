const fs = require('fs');

function assertIncludes(text, expected, message) {
    if (!text.includes(expected)) {
        console.error(message);
        console.error('Missing:', expected);
        process.exit(1);
    }
}

function assertInOrder(text, expectedParts, message) {
    let lastIndex = -1;
    for (const part of expectedParts) {
        const index = text.indexOf(part);
        if (index === -1 || index <= lastIndex) {
            console.error(message);
            console.error('Out of order or missing:', part);
            process.exit(1);
        }
        lastIndex = index;
    }
}

const header = fs.readFileSync('includes/header.php', 'utf8');

assertInOrder(header, [
    'sidebar-section-label">ภาพรวม',
    'sidebar-section-label">ของฉัน',
    'sidebar-section-label">ศูนย์คำขอ',
    'sidebar-section-label">อนุมัติคำขอ',
    'sidebar-section-label">รายงาน',
    'sidebar-section-label">บริหารบุคลากร',
], 'Sidebar should present the new hybrid menu sections in the approved order.');

assertIncludes(header, 'href="#requestCenterSubmenu"', 'Request workflows should be grouped under a single request center submenu.');
assertIncludes(header, 'id="requestCenterSubmenu"', 'Request center submenu should have a stable collapse id.');
assertIncludes(header, 'href="#approvalCenterSubmenu"', 'Approval workflows should be grouped under a single approval center submenu.');
assertIncludes(header, 'id="approvalCenterSubmenu"', 'Approval center submenu should have a stable collapse id.');

assertIncludes(header, 'href="my_leaves.php"', 'Request center should link to the leave workflow landing page.');
assertIncludes(header, 'href="late_early_history.php"', 'Request center should link to the late/early workflow landing page.');
assertIncludes(header, 'href="overtime_history.php"', 'Request center should link to the overtime workflow landing page.');
assertIncludes(header, 'href="day_swap_history.php"', 'Request center should link to the day-swap workflow landing page.');
assertIncludes(header, 'href="training_history.php"', 'Request center should link to the training workflow landing page.');
assertIncludes(header, 'href="request_proxy.php"', 'Request center should include proxy requests for HR/admin.');

assertIncludes(header, 'href="leave_approvals.php"', 'Approval center should link to leave approvals.');
assertIncludes(header, 'href="late_early_approvals.php"', 'Approval center should link to late/early approvals.');
assertIncludes(header, 'href="overtime_approvals.php"', 'Approval center should link to overtime approvals.');
assertIncludes(header, 'href="day_swap_approvals.php"', 'Approval center should link to day-swap approvals.');
assertIncludes(header, 'href="training_approvals.php"', 'Approval center should link to training approvals.');
assertIncludes(header, 'href="#reportCenterSubmenu"', 'Reports should be grouped under a dedicated report submenu.');
assertIncludes(header, 'id="reportCenterSubmenu"', 'Report submenu should have a stable collapse id.');
assertIncludes(header, 'href="attendance_missing_report.php"', 'Report submenu should link to the missing scan report.');
assertIncludes(header, "isActive('attendance_missing_report.php')", 'Report submenu should stay active on the missing scan report.');
assertIncludes(header, 'href="attendance_late_early_report.php"', 'Report submenu should link to the late/early report.');
assertIncludes(header, "isActive('attendance_late_early_report.php')", 'Report submenu should stay active on the late/early report.');

assertIncludes(header, "$approvalBadgeCounts['total']", 'Approval center should show the total pending approval badge.');
assertIncludes(header, "$approvalBadgeCounts['leave']", 'Leave approval submenu item should keep its badge.');
assertIncludes(header, "$approvalBadgeCounts['time_request']", 'Late/early approval submenu item should keep its badge.');
assertIncludes(header, "$approvalBadgeCounts['overtime']", 'Overtime approval submenu item should keep its badge.');
assertIncludes(header, "$approvalBadgeCounts['day_swap']", 'Day-swap approval submenu item should keep its badge.');
assertIncludes(header, "$approvalBadgeCounts['training']", 'Training approval submenu item should keep its badge.');

assertIncludes(header, "isActive('leave_request.php')", 'Request center should stay active while creating leave requests.');
assertIncludes(header, "isActive('late_early_request.php')", 'Request center should stay active while creating late/early requests.');
assertIncludes(header, "isActive('overtime_request.php')", 'Request center should stay active while creating overtime requests.');
assertIncludes(header, "isActive('day_swap_request.php')", 'Request center should stay active while creating day-swap requests.');
assertIncludes(header, "isActive('training_request.php')", 'Request center should stay active while creating training requests.');
assertIncludes(header, "isActive('leave_approvals.php')", 'Approval center should stay active on leave approvals.');
assertIncludes(header, "isActive('late_early_approvals.php')", 'Approval center should stay active on late/early approvals.');
assertIncludes(header, "isActive('overtime_approvals.php')", 'Approval center should stay active on overtime approvals.');
assertIncludes(header, "isActive('day_swap_approvals.php')", 'Approval center should stay active on day-swap approvals.');
assertIncludes(header, "isActive('training_approvals.php')", 'Approval center should stay active on training approvals.');

console.log('sidebar_hybrid_menu_test passed');
