<?php
require_once __DIR__ . '/../includes/holiday_calendar_helpers.php';

function assertHolidaySame($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$events = holidayCalendarBuildEvents(
    [
        '2026-06-03' => 'Company Foundation Day',
        '2026-06-14' => 'Company Outing',
    ],
    [
        ['date' => '2026-06-07', 'day_name' => 'Sun', 'label' => '07/06/2026'],
        ['date' => '2026-06-14', 'day_name' => 'Sun', 'label' => '14/06/2026'],
    ]
);

assertHolidaySame(3, count($events), 'Company holidays and regular holidays should be combined with duplicates collapsed.');
assertHolidaySame('company_holiday', $events[0]['type'], 'Company holidays should be first after date sorting.');
assertHolidaySame('Company Foundation Day', $events[0]['title'], 'Company holiday title should use the configured name.');
assertHolidaySame('regular_holiday', $events[1]['type'], 'Regular shift holidays should be included.');
assertHolidaySame('วันหยุดประจำสัปดาห์', $events[1]['title'], 'Regular holiday title should be explicit.');
assertHolidaySame('company_holiday', $events[2]['type'], 'Company holidays should take precedence when dates overlap.');
assertHolidaySame('Company Outing', $events[2]['title'], 'Overlapping date should retain company holiday name.');

$summary = holidayCalendarBuildSummary($events);
assertHolidaySame(2, $summary['company_holiday'], 'Summary should count company holidays.');
assertHolidaySame(1, $summary['regular_holiday'], 'Summary should count regular holidays.');
assertHolidaySame(3, $summary['total'], 'Summary should count total calendar events.');

echo "holiday_calendar_helpers_test passed\n";
