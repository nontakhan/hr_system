<?php

$source = file_get_contents(__DIR__ . '/../api/attendance_api.php');
$normalizedSource = preg_replace('/\s+/', ' ', $source);

function assertAttendanceApiSource($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

assertAttendanceApiSource(
    strpos($source, 'lr.request_start_time, lr.request_end_time, lt.type_name') !== false,
    'Hourly attendance request query should select leave_types.type_name for hourly leave labels.'
);
assertAttendanceApiSource(
    strpos($source, 'JOIN leave_types lt ON lr.leave_type_id = lt.id') !== false,
    'Hourly attendance request query should join leave_types for hourly leave labels.'
);
assertAttendanceApiSource(
    strpos($normalizedSource, "AND (lr.request_unit = 'day' OR (lr.request_unit = 'hour' AND lr.time_request_type IS NULL AND COALESCE(lr.total_days, 0) >= 1))") !== false,
    'Approved attendance leave query should include hourly leave already calculated as one full day.'
);
assertAttendanceApiSource(
    strpos($normalizedSource, "AND NOT (lr.time_request_type IS NULL AND COALESCE(lr.total_days, 0) >= 1)") !== false,
    'Hourly attendance request query should exclude hourly leave already calculated as one full day.'
);
assertAttendanceApiSource(
    strpos($source, "\$action === 'missing_scan_report'") !== false,
    'Attendance API should expose a missing_scan_report action for admin and HR.'
);
assertAttendanceApiSource(
    strpos($source, 'fetchAttendanceMissingScanEmployees') !== false,
    'Missing scan report should fetch scoped employees before building rows.'
);
assertAttendanceApiSource(
    strpos($source, 'attendanceFilterMissingScanReportRows') !== false,
    'Missing scan report should filter absent, missing-in, and missing-out statuses through the helper.'
);
assertAttendanceApiSource(
    strpos($source, 'fetchAttendanceMissingScanReportRows') !== false,
    'Missing scan report should use a bulk report row query instead of per-employee monthly report building.'
);
assertAttendanceApiSource(
    strpos($source, 'LEFT JOIN attendance_records ar ON ar.employee_id = e.id') !== false,
    'Missing scan report should bulk join attendance records for the selected month.'
);
assertAttendanceApiSource(
    strpos($source, 'LEFT JOIN attendance_record_overrides aro ON aro.employee_id = e.id') !== false,
    'Missing scan report should bulk join HR attendance overrides.'
);
assertAttendanceApiSource(
    strpos($source, '$employeeRows = buildMonthlyAttendanceReport($mysqli, $employee, $month)') === false,
    'Missing scan report must not call the per-employee monthly report builder because it times out on company-wide reports.'
);
assertAttendanceApiSource(
    strpos($source, "hrScopeBuildEmployeeWhereClause(\$role, hrScopeCurrentSessionScopes(), 'e')") !== false,
    'Missing scan report should reuse HR employee scope filtering.'
);

echo "attendance_api_source_test passed" . PHP_EOL;
