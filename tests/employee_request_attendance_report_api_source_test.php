<?php

$source = file_get_contents(__DIR__ . '/../api/attendance_api.php');

function assertEmployeeRequestReportApi($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}

assertEmployeeRequestReportApi(strpos($source, "\$action === 'employee_request_attendance_report'") !== false, 'API action is required.');
assertEmployeeRequestReportApi(strpos($source, 'fetchEmployeeRequestAttendanceReportEmployee') !== false, 'Scoped employee loader is required.');
assertEmployeeRequestReportApi(strpos($source, 'buildEmployeeRequestAttendanceReport') !== false, 'Report builder is required.');
assertEmployeeRequestReportApi(strpos($source, 'hrScopeBuildEmployeeWhereClause') !== false, 'HR scope helper must protect employee selection.');
assertEmployeeRequestReportApi(strpos($source, "lr.status = 'approved'") !== false, 'Leave/time requests must be final-approved.');
assertEmployeeRequestReportApi(strpos($source, "tr.status = 'approved'") !== false, 'Activity requests must be final-approved.');
assertEmployeeRequestReportApi(strpos($source, "dsr.status = 'approved'") !== false, 'Shift swaps must be final-approved.');
assertEmployeeRequestReportApi(strpos($source, "time_request_type IN ('late_arrival','early_departure','overtime_after_work')") !== false, 'Hourly request types must be bounded.');
assertEmployeeRequestReportApi(strpos($source, 'dsr.requester_employee_id = ? OR dsr.target_employee_id = ?') !== false, 'Both sides of a shift swap must be included.');
assertEmployeeRequestReportApi(strpos($source, 'leaveExpandApprovedRequestForMonth') !== false, 'Approved leave must use existing month expansion.');
assertEmployeeRequestReportApi(strpos($source, 'employeeRequestReportCalculateScannerEvents') !== false, 'Scanner events must use the pure calculator.');
assertEmployeeRequestReportApi(strpos($source, 'fetchAttendanceOverridesForEmployeesMonth') !== false, 'Effective scans must include HR overrides.');
assertEmployeeRequestReportApi(strpos($source, 'fetchShiftAssignmentsForEmployeesMonth') !== false, 'Effective shifts must include dated assignments.');

$leaveLoaderStart = strpos($source, 'function fetchEmployeeRequestReportLeaveEvents');
$leaveLoaderEnd = strpos($source, 'function fetchEmployeeRequestReportHourlyEvents', $leaveLoaderStart);
$leaveLoader = substr($source, $leaveLoaderStart, $leaveLoaderEnd - $leaveLoaderStart);
$fetchRowsPosition = strpos($leaveLoader, '$requestRows = $stmt->get_result()->fetch_all');
$closePosition = strpos($leaveLoader, '$stmt->close()');
$workDaysPosition = strpos($leaveLoader, 'leaveFetchEmployeeWorkDays');
assertEmployeeRequestReportApi($fetchRowsPosition !== false && $closePosition !== false && $workDaysPosition !== false, 'Leave loader must buffer and close its result before another query.');
assertEmployeeRequestReportApi($fetchRowsPosition < $closePosition && $closePosition < $workDaysPosition, 'Leave result lifecycle must finish before the workday lookup to avoid Commands out of sync.');

echo "employee_request_attendance_report_api_source_test passed" . PHP_EOL;
