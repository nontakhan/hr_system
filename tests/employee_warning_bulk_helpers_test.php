<?php

require_once __DIR__ . '/../includes/employee_warning_bulk_helpers.php';

function assertBulkSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

assertBulkSame(
    'employee:17|date:2026-07-03',
    employeeWarningBuildSourceKey(EMPLOYEE_WARNING_SOURCE_ATTENDANCE_MISSING, ['employee_id' => 17, 'work_date' => '2026-07-03']),
    'Missing-scan key must identify one employee-day'
);
assertBulkSame(
    'employee:17|date:2026-07-03',
    employeeWarningBuildSourceKey(EMPLOYEE_WARNING_SOURCE_ATTENDANCE_LATE_EARLY, ['employee_id' => 17, 'work_date' => '2026-07-03']),
    'Late/early key must identify one employee-day'
);
assertBulkSame(
    'request:91|date:2026-07-03',
    employeeWarningBuildSourceKey(EMPLOYEE_WARNING_SOURCE_APPROVED_LEAVE, ['id' => 91, 'leave_date' => '2026-07-03']),
    'Leave key must identify one request-day'
);
assertBulkSame(
    ['employee_id' => 17, 'work_date' => '2026-07-03'],
    employeeWarningParseSourceKey(EMPLOYEE_WARNING_SOURCE_ATTENDANCE_MISSING, 'employee:17|date:2026-07-03'),
    'Missing key must parse to trusted lookup values'
);
assertBulkSame(
    ['request_id' => 91, 'leave_date' => '2026-07-03'],
    employeeWarningParseSourceKey(EMPLOYEE_WARNING_SOURCE_APPROVED_LEAVE, 'request:91|date:2026-07-03'),
    'Leave key must parse to trusted lookup values'
);
assertBulkSame('หมายเหตุร่วม', employeeWarningNormalizeSharedNote('  หมายเหตุร่วม  '), 'Shared note must be trimmed');
assertBulkSame(
    "รายละเอียดอัตโนมัติ\nหมายเหตุ: หมายเหตุร่วม",
    employeeWarningAppendSharedNote('รายละเอียดอัตโนมัติ', 'หมายเหตุร่วม'),
    'Shared note must append to trusted detail'
);
assertBulkSame(
    'รายละเอียดอัตโนมัติ',
    employeeWarningAppendSharedNote('รายละเอียดอัตโนมัติ', ''),
    'Blank shared note must not alter detail'
);

foreach ([
    ['unknown', 'employee:17|date:2026-07-03'],
    [EMPLOYEE_WARNING_SOURCE_ATTENDANCE_MISSING, 'employee:0|date:2026-07-03'],
    [EMPLOYEE_WARNING_SOURCE_APPROVED_LEAVE, 'request:91|date:2569-07-03'],
    [EMPLOYEE_WARNING_SOURCE_APPROVED_LEAVE, 'request:91|date:2026-02-30'],
] as [$type, $key]) {
    try {
        employeeWarningParseSourceKey($type, $key);
        throw new RuntimeException('Invalid source key was accepted: ' . $type . ' ' . $key);
    } catch (InvalidArgumentException $expected) {
    }
}

echo "employee warning bulk helpers ok\n";
