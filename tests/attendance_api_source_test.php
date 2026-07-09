<?php

$source = file_get_contents(__DIR__ . '/../api/attendance_api.php');

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

echo "attendance_api_source_test passed" . PHP_EOL;
