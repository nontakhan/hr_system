<?php
$file = __DIR__ . '/../api/proxy_request_api.php';
if (!is_file($file)) {
    fwrite(STDERR, "api/proxy_request_api.php must exist\n");
    exit(1);
}

$source = file_get_contents($file);
$needles = [
    "require_once '../includes/proxy_request_helpers.php'",
    'proxyRequestRequireAccess()',
    'function proxyRequestCanAccessEmployee',
    'function proxyRequestCreateLeave',
    'function proxyRequestCreateTimeRequest',
    'function proxyRequestCreateDaySwap',
    'function proxyRequestCreateTraining',
    "'approved'",
    "'admin_proxy'",
    'trainingRequestCreateHistoryRecord',
    'daySwapHasPendingOrApprovedConflict',
    'leaveFetchConflictingLeaveDates',
];

foreach ($needles as $needle) {
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, "proxy API missing required source marker: {$needle}\n");
        exit(1);
    }
}
