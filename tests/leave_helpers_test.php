<?php
require_once __DIR__ . '/../includes/leave_helpers.php';

function assertLeaveSame($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$summary = leaveBuildDateSummary(
    '2026-01-02',
    '2026-01-06',
    'full',
    'morning',
    'Mon,Tue,Fri',
    ['2026-01-05' => 'Company outing']
);

assertLeaveSame(1.5, $summary['total_days'], 'Leave total should count only eligible workdays and support a half-day end date.');
assertLeaveSame(['2026-01-02', '2026-01-06'], array_column($summary['included_dates'], 'date'), 'Only eligible leave dates should be included.');
assertLeaveSame(1.0, $summary['included_dates'][0]['days'], 'A full eligible start date should count as one day.');
assertLeaveSame(0.5, $summary['included_dates'][1]['days'], 'A half-day eligible end date should count as half a day.');
$excludedByDate = array_column($summary['excluded_dates'], null, 'date');
assertLeaveSame('company_holiday', $excludedByDate['2026-01-05']['type'], 'Company holidays should be excluded with their own reason type.');
assertLeaveSame('Company outing', $excludedByDate['2026-01-05']['reason'], 'Company holiday names should be returned.');

$invalidMultiDayStart = leaveBuildDateSummary(
    '2026-01-02',
    '2026-01-06',
    'morning',
    'full',
    'Mon,Tue,Fri',
    []
);
assertLeaveSame(false, $invalidMultiDayStart['valid'], 'Multi-day leave cannot start with morning half-day.');

$invalidMultiDayEnd = leaveBuildDateSummary(
    '2026-01-02',
    '2026-01-06',
    'full',
    'afternoon',
    'Mon,Tue,Fri',
    []
);
assertLeaveSame(false, $invalidMultiDayEnd['valid'], 'Multi-day leave cannot end with afternoon half-day.');

$sameDayMorning = leaveBuildDateSummary(
    '2026-01-07',
    '2026-01-07',
    'morning',
    'morning',
    'Mon,Tue,Wed,Thu,Fri',
    []
);
assertLeaveSame(0.5, $sameDayMorning['total_days'], 'Single-day morning leave should count as half a day.');

$invalid = leaveBuildDateSummary(
    '2026-01-07',
    '2026-01-07',
    'afternoon',
    'morning',
    'Mon,Tue,Wed,Thu,Fri',
    []
);
assertLeaveSame(false, $invalid['valid'], 'A single date cannot end before it starts when using half-day parts.');

echo "leave_helpers_test passed" . PHP_EOL;
