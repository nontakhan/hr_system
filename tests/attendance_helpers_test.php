<?php
require_once __DIR__ . '/../includes/attendance_helpers.php';

function assertSameValue($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$date = attendanceParseThaiDate('03/01/2569');
assertSameValue('2026-01-03', $date, 'Thai Buddhist year dates should be converted to Gregorian dates.');

$record = attendanceMapCsvRow([
    '5941000029584',
    'มูฮัมหมัด',
    'Office',
    '03/01/2569',
    'วันทำงาน',
    '',
    '0',
    '',
    '00:31',
    '07:30 ~12:00',
    '07:46',
    '',
    '00:16',
    '',
    'ไม่สแกนออก',
    'สาย',
    '13:00 ~17:00',
    '',
    '18:01',
]);
assertSameValue('5941000029584', $record['citizen_id'], 'CSV column A should map to citizen ID.');
assertSameValue('2026-01-03', $record['work_date'], 'CSV date should be normalized.');
assertSameValue('07:46:00', $record['check_in'], 'CSV column K should map to check-in time.');
assertSameValue('18:01:00', $record['check_out'], 'CSV column S should map to check-out time.');
assertSameValue('2026-01', attendanceImportMonthFromWorkDate($record['work_date']), 'Import month should come from each CSV row date.');
assertSameValue('2026-02', attendanceImportMonthFromWorkDate('2026-02-01'), 'CSV rows from another month should keep their own import month.');

$summary = attendanceBuildImportSummaryMonths([
    [
        'import_month' => '2026-05',
        'record_count' => '42',
        'employee_count' => '3',
        'latest_work_date' => '2026-05-29',
    ],
    [
        'import_month' => '2026-03',
        'record_count' => '10',
        'employee_count' => '2',
        'latest_work_date' => '2026-03-15',
    ],
], '2026-05-31', 6);
assertSameValue(6, count($summary), 'Import summary should include six months.');
assertSameValue('2026-05', $summary[0]['import_month'], 'Import summary should start from the current month.');
assertSameValue(true, $summary[0]['has_data'], 'Months with attendance rows should be marked as imported.');
assertSameValue(42, $summary[0]['record_count'], 'Record counts should be returned as integers.');
assertSameValue('2025-12', $summary[5]['import_month'], 'Import summary should include the sixth month back.');
assertSameValue(false, $summary[5]['has_data'], 'Months without attendance rows should be marked as empty.');

$status = attendanceEvaluateStatus(
    '2026-01-03',
    '07:46:00',
    '18:01:00',
    [
        'start_time' => '07:30:00',
        'end_time' => '17:00:00',
        'late_tolerance_mins' => 15,
        'work_days' => 'Mon,Tue,Wed,Thu,Fri,Sat',
    ]
);
assertSameValue('late', $status['status'], 'Check-in after shift start plus tolerance should be late.');
assertSameValue(true, $status['is_late'], 'Late rows should be marked for highlighting.');

$baseShift = [
    'start_time' => '08:00:00',
    'end_time' => '16:00:00',
    'late_tolerance_mins' => 15,
    'work_days' => 'Mon,Tue,Wed,Thu,Fri,Sat',
];
$overrideShift = attendanceResolveShiftForDate($baseShift, [
    [
        'day_of_week' => 'Tue,Thu',
        'start_time' => '07:30:00',
        'end_time' => '16:00:00',
        'late_tolerance_mins' => 15,
        'effective_from' => '2026-01-01',
        'effective_to' => null,
    ],
], '2026-01-06');
assertSameValue('07:30:00', $overrideShift['start_time'], 'Weekly employee shift override should apply to any selected matching day.');
assertSameValue('Tue,Thu', $overrideShift['work_days'], 'Resolved override shift should only treat the selected override days as work days.');

$normalShift = attendanceResolveShiftForDate($baseShift, [
    [
        'day_of_week' => 'Tue,Thu',
        'start_time' => '07:30:00',
        'end_time' => '16:00:00',
        'late_tolerance_mins' => 15,
        'effective_from' => '2026-01-01',
        'effective_to' => null,
    ],
], '2026-01-07');
assertSameValue('08:00:00', $normalShift['start_time'], 'Base shift should be used on days without an override.');

$overrideLate = attendanceEvaluateStatus(
    '2026-01-06',
    '07:46:00',
    '16:01:00',
    $overrideShift
);
assertSameValue('late', $overrideLate['status'], 'Override start time should be used when evaluating lateness.');

$absent = attendanceEvaluateStatus(
    '2026-01-05',
    null,
    null,
    [
        'start_time' => '07:30:00',
        'end_time' => '17:00:00',
        'late_tolerance_mins' => 15,
        'work_days' => 'Mon,Tue,Wed,Thu,Fri',
    ]
);
assertSameValue('absent', $absent['status'], 'A workday with no scans should be absent.');

$leaveMap = attendanceBuildApprovedLeaveMap([
    [
        'start_date' => '2026-01-05',
        'end_date' => '2026-01-06',
        'type_name' => 'Annual leave',
    ],
    [
        'start_date' => '2026-02-01',
        'end_date' => '2026-02-01',
        'type_name' => 'Next month leave',
    ],
], '2026-01');
assertSameValue('Annual leave', $leaveMap['2026-01-05'], 'Approved leave map should include the first day of a leave range.');
assertSameValue('Annual leave', $leaveMap['2026-01-06'], 'Approved leave map should include the last day of a leave range.');
assertSameValue(false, isset($leaveMap['2026-02-01']), 'Approved leave map should not include dates outside the report month.');

$approvedLeave = attendanceEvaluateStatus(
    '2026-01-05',
    null,
    null,
    [
        'start_time' => '07:30:00',
        'end_time' => '17:00:00',
        'late_tolerance_mins' => 15,
        'work_days' => 'Mon,Tue,Wed,Thu,Fri',
    ],
    [],
    $leaveMap
);
assertSameValue('leave', $approvedLeave['status'], 'Approved leave should be shown instead of absent.');
assertSameValue('Annual leave', $approvedLeave['leave_name'], 'Approved leave type should be returned for reports.');

$specialHoliday = attendanceEvaluateStatus(
    '2026-01-05',
    null,
    null,
    [
        'start_time' => '07:30:00',
        'end_time' => '17:00:00',
        'late_tolerance_mins' => 15,
        'work_days' => 'Mon,Tue,Wed,Thu,Fri',
    ],
    ['2026-01-05' => 'Company holiday']
);
assertSameValue('holiday', $specialHoliday['status'], 'Special company holidays should override normal workdays.');
assertSameValue('Company holiday', $specialHoliday['holiday_name'], 'Special holiday names should be returned for reports.');

echo "attendance_helpers_test passed" . PHP_EOL;
