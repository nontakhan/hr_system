<?php
$source = file_get_contents(__DIR__ . '/../api/leave_api.php')
    . file_get_contents(__DIR__ . '/../includes/employee_warning_bulk_helpers.php');

function assertLeaveReportSource($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

assertLeaveReportSource(strpos($source, "'approved_leave_report'") !== false, 'Leave API should expose the approved report action.');
assertLeaveReportSource(strpos($source, "'approved_leave_report_filters'") !== false, 'Leave API should expose scoped filter options.');
assertLeaveReportSource(strpos($source, "lr.status = 'approved'") !== false, 'Report query should include only approved requests.');
assertLeaveReportSource(strpos($source, 'lt.is_actual_leave = 1') !== false, 'Report query should include only actual leave types.');
assertLeaveReportSource(strpos($source, 'lr.start_date <= ?') !== false && strpos($source, 'lr.end_date >= ?') !== false, 'Report query should use month overlap conditions.');
assertLeaveReportSource(strpos($source, 'hrScopeBuildEmployeeWhereClause') !== false, 'HR report results should use employee scope.');
assertLeaveReportSource(strpos($source, 'leaveExpandApprovedRequestForMonth') !== false, 'API should use the tested date-expansion helper.');
assertLeaveReportSource(strpos($source, 'leaveCountApprovedReportRows') !== false, 'API should use the tested summary helper.');
assertLeaveReportSource(strpos($source, 'EMPLOYEE_WARNING_SOURCE_APPROVED_LEAVE') !== false, 'Leave report must use approved-leave warning identity.');
assertLeaveReportSource(strpos($source, 'warning_source_key') !== false, 'Leave report must expose stable source keys.');
assertLeaveReportSource(strpos($source, 'already_warned') !== false, 'Leave report must expose duplicate warning state.');

echo "leave_report_api_source_test passed" . PHP_EOL;
