<?php

require_once __DIR__ . '/../includes/employee_request_attendance_report_helpers.php';

function assertEmployeeRequestReportSame($expected, $actual, $message) {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

$shift = ['start_time' => '08:00:00', 'end_time' => '17:00:00', 'late_tolerance_mins' => 10];
$events = employeeRequestReportCalculateScannerEvents('2026-07-03', '08:16:00', '16:40:00', $shift);
assertEmployeeRequestReportSame(16, $events['late_minutes'], 'Late row keeps full deviation after tolerance gate.');
assertEmployeeRequestReportSame(20, $events['early_minutes'], 'Early minutes use shift end minus check-out.');
assertEmployeeRequestReportSame(0, $events['overtime_minutes'], 'Early check-out is not overtime.');

$withinTolerance = employeeRequestReportCalculateScannerEvents('2026-07-03', '08:09:00', '17:45:00', $shift);
assertEmployeeRequestReportSame(0, $withinTolerance['late_minutes'], 'Tolerance suppresses late event.');
assertEmployeeRequestReportSame(45, $withinTolerance['overtime_minutes'], 'OT uses check-out after shift end.');

$missing = employeeRequestReportCalculateScannerEvents('2026-07-03', null, null, $shift);
assertEmployeeRequestReportSame(['late_minutes' => 0, 'early_minutes' => 0, 'overtime_minutes' => 0], $missing, 'Missing scans must not be guessed.');

$overnight = employeeRequestReportCalculateScannerEvents(
    '2026-07-03',
    '20:15:00',
    '05:30:00',
    ['start_time' => '20:00:00', 'end_time' => '05:00:00', 'late_tolerance_mins' => 5]
);
assertEmployeeRequestReportSame(15, $overnight['late_minutes'], 'Overnight shift should calculate late minutes on the start date.');
assertEmployeeRequestReportSame(30, $overnight['overtime_minutes'], 'Overnight shift should calculate OT after next-day shift end.');

$invalid = employeeRequestReportCalculateScannerEvents('2026-07-03', 'bad', '17:45:00', $shift);
assertEmployeeRequestReportSame(['late_minutes' => 0, 'early_minutes' => 0, 'overtime_minutes' => 0], $invalid, 'Invalid scans must not produce partial guessed events.');

$request = employeeRequestReportBuildEvent('leave:7:2026-07-03', '2026-07-03', 'leave', 'approved_request', '-', 1, 'day', 'ลาป่วย', 'อนุมัติแล้ว');
$scanner = employeeRequestReportBuildEvent('scanner:ot:2026-07-03', '2026-07-03', 'actual_overtime', 'scanner', '17:00-17:45', 45, 'minute', 'OT จริง', 'ข้อมูลลงเวลา');
$summary = employeeRequestReportSummarize([$request, $scanner]);
assertEmployeeRequestReportSame(['total_events' => 2, 'approved_requests' => 1, 'scanner_events' => 1, 'actual_overtime_minutes' => 45], $summary, 'Summary follows source and OT semantics.');

$sorted = employeeRequestReportSortEvents([
    employeeRequestReportBuildEvent('scanner:late:2', '2026-07-04', 'actual_late', 'scanner', '-', 5, 'minute', '', 'ข้อมูลลงเวลา'),
    employeeRequestReportBuildEvent('request:late:1', '2026-07-03', 'late_request', 'approved_request', '-', 5, 'minute', '', 'อนุมัติแล้ว'),
    $request,
]);
assertEmployeeRequestReportSame('leave:7:2026-07-03', $sorted[0]['event_key'], 'Leave should precede late request on the same date.');
assertEmployeeRequestReportSame('request:late:1', $sorted[1]['event_key'], 'Late request should remain on its date.');
assertEmployeeRequestReportSame('scanner:late:2', $sorted[2]['event_key'], 'Later dates should sort last.');

echo "employee_request_attendance_report_helpers_test passed" . PHP_EOL;
