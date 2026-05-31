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
