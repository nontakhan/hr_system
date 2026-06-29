<?php
$files = [
    'leave' => __DIR__ . '/../includes/leave_helpers.php',
    'day_swap' => __DIR__ . '/../includes/day_swap_helpers.php',
    'training' => __DIR__ . '/../includes/training_request_helpers.php',
];

foreach ($files as $name => $file) {
    $source = file_get_contents($file);
    if (strpos($source, "require_once __DIR__ . '/proxy_request_helpers.php'") === false) {
        fwrite(STDERR, "{$name} helper must require proxy_request_helpers.php\n");
        exit(1);
    }
}

$expectations = [
    'leave' => 'proxyRequestEnsureAuditColumns($mysqli, \'leave_requests\')',
    'day_swap' => 'proxyRequestEnsureAuditColumns($mysqli, \'day_swap_requests\')',
    'training' => 'proxyRequestEnsureAuditColumns($mysqli, \'training_requests\')',
];

foreach ($expectations as $name => $needle) {
    $source = file_get_contents($files[$name]);
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, "{$name} helper must ensure proxy audit columns with {$needle}\n");
        exit(1);
    }
}
