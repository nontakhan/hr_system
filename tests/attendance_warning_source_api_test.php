<?php

$source = file_get_contents(__DIR__ . '/../api/attendance_api.php')
    . file_get_contents(__DIR__ . '/../includes/employee_warning_bulk_helpers.php');

$requireAttendanceWarningSource = function (string $needle, string $message) use ($source): void {
    if (strpos($source, $needle) === false) {
        throw new RuntimeException($message . ': ' . $needle);
    }
};

$requireAttendanceWarningSource('EMPLOYEE_WARNING_SOURCE_ATTENDANCE_MISSING', 'Missing rows need stable warning identity');
$requireAttendanceWarningSource('EMPLOYEE_WARNING_SOURCE_ATTENDANCE_LATE_EARLY', 'Late/early rows need stable warning identity');
$requireAttendanceWarningSource('employeeWarningAnnotateReportRows', 'Attendance reports must use one duplicate annotator');
$requireAttendanceWarningSource('already_warned', 'Attendance reports must expose duplicate state');

echo "attendance warning source API test passed\n";
