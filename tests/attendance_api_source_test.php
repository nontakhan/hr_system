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
    strpos($source, 'function fetchApprovedLeaveAttendanceMapsForMonth') !== false,
    'Attendance API should fetch actual leave once for full-day and partial classification.'
);
assertAttendanceApiSource(
    strpos($normalizedSource, 'SELECT lr.start_date, lr.end_date, lr.start_day_part, lr.end_day_part, lr.request_unit, lr.time_request_type, lr.request_minutes, lr.request_start_time, lr.request_end_time, lr.total_days, lt.type_name') !== false,
    'Attendance actual-leave query should fetch persisted day parts, duration, minutes, and time range.'
);
assertAttendanceApiSource(
    strpos($normalizedSource, "AND (lr.request_unit = 'day' OR (lr.request_unit = 'hour' AND lr.time_request_type IS NULL))") !== false,
    'Attendance actual-leave query should include day leave and hourly actual leave for helper classification.'
);
assertAttendanceApiSource(
    strpos($normalizedSource, 'AND lr.start_date <= ? AND lr.end_date >= ?') !== false,
    'Attendance actual-leave query should include every request overlapping the report month.'
);
assertAttendanceApiSource(
    strpos($normalizedSource, 'AND lr.request_unit = \'hour\' AND lr.time_request_type IS NOT NULL') !== false,
    'Attendance time-request query should keep actual partial leave separate from late, early, and OT labels.'
);
assertAttendanceApiSource(
    strpos($source, "'partial_leave_details' =>") !== false,
    'Monthly attendance rows should expose partial-leave details.'
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
assertAttendanceApiSource(
    strpos($source, "\$action === 'late_early_report'") !== false,
    'Attendance API should expose the late_early_report action.'
);
assertAttendanceApiSource(
    strpos($source, 'buildAttendanceLateEarlyReport') !== false,
    'Late/early report should have a dedicated bulk builder.'
);
assertAttendanceApiSource(
    strpos($source, 'fetchApprovedLateEarlyMinutesForEmployeesMonth') !== false,
    'Late/early report should bulk load approved request minutes.'
);
assertAttendanceApiSource(
    strpos($source, "lr.status = 'approved'") !== false,
    'Only final approved requests should reduce incident minutes.'
);
assertAttendanceApiSource(
    strpos($source, "lr.time_request_type IN ('late_arrival', 'early_departure')") !== false,
    'Only late and early request types should reduce report minutes.'
);
assertAttendanceApiSource(
    strpos($source, 'attendanceCalculateLateEarlyIncident') !== false,
    'Bulk rows should use the tested incident calculator.'
);
assertAttendanceApiSource(
    strpos($source, 'leaveEnsureRequestColumns') === false
        && strpos($source, 'leaveEnsureRequestPartColumns($mysqli);') !== false,
    'Late/early report should initialize request columns through the existing leave helper.'
);

echo "attendance_api_source_test passed" . PHP_EOL;
