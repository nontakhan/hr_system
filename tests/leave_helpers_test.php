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

$octoberFiscal = leaveBuildFiscalYearRange(10, '2026-06-10');
assertLeaveSame('2025-10-01', $octoberFiscal['start_date'], 'October fiscal year should start in the previous calendar year before October.');
assertLeaveSame('2026-09-30', $octoberFiscal['end_date'], 'October fiscal year should end on September 30.');

$aprilFiscal = leaveBuildFiscalYearRange(4, '2026-06-10');
assertLeaveSame('2026-04-01', $aprilFiscal['start_date'], 'Custom April fiscal year should start in the same calendar year after April.');
assertLeaveSame('2027-03-31', $aprilFiscal['end_date'], 'Custom April fiscal year should end on March 31.');

$nearByRequests = leaveBuildUsageWarningStatus(4.0, 5);
assertLeaveSame('near', $nearByRequests, 'Leave warning status should turn near when approved leave days reach 80 percent.');

$overByRequests = leaveBuildUsageWarningStatus(5.5, 5);
assertLeaveSame('over', $overByRequests, 'Leave warning status should turn over when approved leave days exceed the fiscal year limit.');

$lateHourlyType = leaveDetectHourlyRequestType('ขอมาสาย');
assertLeaveSame('late_arrival', $lateHourlyType, 'Late arrival leave type names should be detected as hourly requests.');

$shortLateHourlyType = leaveDetectHourlyRequestType('ขอสาย');
assertLeaveSame('late_arrival', $shortLateHourlyType, 'Short late request names should be detected as hourly requests.');

$earlyHourlyType = leaveDetectHourlyRequestType('ขอออกก่อน');
assertLeaveSame('early_departure', $earlyHourlyType, 'Early departure leave type names should be detected as hourly requests.');

$normalHourlyType = leaveDetectHourlyRequestType('ลาป่วย');
assertLeaveSame(null, $normalHourlyType, 'Normal leave type names should not be detected as hourly requests.');

$hourlyPayload = leaveBuildHourlyRequestPayload('late_arrival', 35);
assertLeaveSame('hour', $hourlyPayload['request_unit'], 'Hourly leave payload should use hour request unit.');
assertLeaveSame(35, $hourlyPayload['request_minutes'], 'Hourly leave payload should store the requested late/early minutes.');
assertLeaveSame(0.0, $hourlyPayload['total_days'], 'Hourly leave payload should not add leave days.');
assertLeaveSame('ขอมาสาย 35 นาที', leaveFormatRequestDuration($hourlyPayload), 'Hourly request duration should show the requested minutes.');

$earlyHourlyPayload = leaveBuildHourlyRequestPayload('early_departure', 40);
assertLeaveSame(40, $earlyHourlyPayload['request_minutes'], 'Early departure payload should store the requested minutes.');
assertLeaveSame('ขอออกก่อน 40 นาที', leaveFormatRequestDuration($earlyHourlyPayload), 'Early departure duration should show the requested minutes.');

try {
    leaveBuildHourlyRequestPayload('late_arrival', 61);
    assertLeaveSame(true, false, 'Hourly requests over 60 minutes should be rejected.');
} catch (InvalidArgumentException $e) {
    assertLeaveSame(true, strpos($e->getMessage(), 'minutes') !== false, 'Hourly request validation should mention minutes.');
}

echo "leave_helpers_test passed" . PHP_EOL;
