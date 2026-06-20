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

$helperSource = file_get_contents(__DIR__ . '/../includes/holiday_calendar_helpers.php');
assertHolidaySame(false, strpos($helperSource, 'lt.leave_name'), 'Approved leave query should not reference a non-existent leave_name column.');
assertHolidaySame(true, strpos($helperSource, 'lt.type_name AS leave_name') !== false, 'Approved leave query should alias leave_types.type_name for calendar titles.');

$events = holidayCalendarBuildEvents(
    [
        '2026-06-03' => 'Company Foundation Day',
        '2026-06-14' => 'Company Outing',
    ],
    [
        ['date' => '2026-06-07', 'day_name' => 'Sun', 'label' => '07/06/2026'],
        ['date' => '2026-06-14', 'day_name' => 'Sun', 'label' => '14/06/2026'],
    ],
    [
        [
            'id' => 12,
            'start_date' => '2026-06-09',
            'end_date' => '2026-06-10',
            'leave_name' => 'Annual Leave',
            'reason' => 'Family trip',
            'total_days' => '2.00',
            'status' => 'approved',
        ],
    ]
);

assertHolidaySame(5, count($events), 'Company holidays, regular holidays, and approved leave should be combined.');
assertHolidaySame('company_holiday', $events[0]['type'], 'Company holidays should be first after date sorting.');
assertHolidaySame('Company Foundation Day', $events[0]['title'], 'Company holiday title should use the configured name.');
assertHolidaySame('regular_holiday', $events[1]['type'], 'Regular shift holidays should be included.');
assertHolidaySame('วันหยุดประจำสัปดาห์', $events[1]['title'], 'Regular holiday title should be explicit.');
assertHolidaySame('approved_leave', $events[2]['type'], 'Approved leave should be included as a calendar event.');
assertHolidaySame('Annual Leave', $events[2]['title'], 'Approved leave title should use the leave type name.');
assertHolidaySame('Family trip', $events[2]['reason'], 'Approved leave event should retain its reason for detail display.');
assertHolidaySame('company_holiday', $events[4]['type'], 'Company holidays should take precedence when dates overlap.');
assertHolidaySame('Company Outing', $events[4]['title'], 'Overlapping date should retain company holiday name.');

$summary = holidayCalendarBuildSummary($events);
assertHolidaySame(2, $summary['company_holiday'], 'Summary should count company holidays.');
assertHolidaySame(1, $summary['regular_holiday'], 'Summary should count regular holidays.');
assertHolidaySame(2, $summary['approved_leave'], 'Summary should count approved leave days.');
assertHolidaySame(5, $summary['total'], 'Summary should count total calendar events.');

echo "holiday_calendar_helpers_test passed\n";
