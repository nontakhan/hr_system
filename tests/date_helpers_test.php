<?php
require_once __DIR__ . '/../includes/date_helpers.php';

function assertSameValue($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

assertSameValue('03/01/2569', formatThaiDate('2026-01-03'), 'Gregorian dates should display as Buddhist Era dates.');
assertSameValue('03/01/2569 07:46', formatThaiDateTime('2026-01-03 07:46:22'), 'Date-times should display Buddhist Era dates and time.');
assertSameValue('2569', formatThaiYear('2026'), 'Gregorian years should display as Buddhist Era years.');
assertSameValue('-', formatThaiDate(null), 'Empty dates should use the fallback.');

echo "date_helpers_test passed" . PHP_EOL;
