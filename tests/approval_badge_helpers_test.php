<?php
require_once __DIR__ . '/../includes/approval_badge_helpers.php';

function assertApprovalBadgeSame($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$managerStages = approvalBadgePendingStagesForRole('manager');
assertApprovalBadgeSame(['pending', 'pending_manager'], $managerStages, 'Manager badges should count manager-stage requests.');

$hrStages = approvalBadgePendingStagesForRole('hr');
assertApprovalBadgeSame(['pending_hr', 'pending_cancel_hr'], $hrStages, 'HR badges should count HR-stage requests and cancellation requests.');

$adminStages = approvalBadgePendingStagesForRole('admin');
assertApprovalBadgeSame(['pending_hr', 'pending_cancel_hr'], $adminStages, 'Admin badges should count requests ready for HR/admin approval and cancellation requests.');

$employeeStages = approvalBadgePendingStagesForRole('employee');
assertApprovalBadgeSame([], $employeeStages, 'Employees should not receive approval badge stages.');

$counts = approvalBadgeNormalizeCounts([
    'leave' => 3,
    'time_request' => '2',
    'day_swap' => null,
]);
assertApprovalBadgeSame(3, $counts['leave'], 'Leave badge count should stay numeric.');
assertApprovalBadgeSame(2, $counts['time_request'], 'Time request badge count should be cast to an integer.');
assertApprovalBadgeSame(0, $counts['day_swap'], 'Missing day swap count should default to zero.');
assertApprovalBadgeSame(5, $counts['total'], 'Total badge count should sum all approval types.');

echo "approval_badge_helpers_test passed" . PHP_EOL;
