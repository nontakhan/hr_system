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

$conflictingDates = leaveFindConflictingLeaveDates([
    [
        'start_date' => '2026-01-07',
        'end_date' => '2026-01-07',
        'request_unit' => 'day',
        'status' => 'approved',
    ],
    [
        'start_date' => '2026-01-08',
        'end_date' => '2026-01-08',
        'request_unit' => 'hour',
        'status' => 'approved',
    ],
    [
        'start_date' => '2026-01-09',
        'end_date' => '2026-01-09',
        'request_unit' => 'day',
        'status' => 'cancelled',
    ],
], ['2026-01-07', '2026-01-08', '2026-01-09']);
assertLeaveSame(['2026-01-07'], $conflictingDates, 'Day leave should not be submitted again on a date with an active day-leave request.');

$rangeConflictingDates = leaveFindConflictingLeaveDates([
    [
        'start_date' => '2026-01-05',
        'end_date' => '2026-01-07',
        'request_unit' => 'day',
        'status' => 'pending_hr',
    ],
], ['2026-01-06']);
assertLeaveSame(['2026-01-06'], $rangeConflictingDates, 'Overlapping multi-day active requests should conflict with included requested dates.');

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

$vacationEligible = leaveBuildVacationEligibilityStatus('2025-06-01', '2026-06-01', 12);
assertLeaveSame(true, $vacationEligible['eligible'], 'Vacation leave should be allowed once employee tenure reaches the configured month threshold.');
assertLeaveSame(12, $vacationEligible['completed_months'], 'Vacation eligibility should count completed calendar months from employee start date.');

$vacationTooSoon = leaveBuildVacationEligibilityStatus('2025-07-01', '2026-06-30', 12);
assertLeaveSame(false, $vacationTooSoon['eligible'], 'Vacation leave should be blocked before the configured month threshold is reached.');
assertLeaveSame(11, $vacationTooSoon['completed_months'], 'Vacation eligibility should not round up incomplete months.');

$vacationDisabled = leaveBuildVacationEligibilityStatus('2026-06-01', '2026-06-01', 0);
assertLeaveSame(true, $vacationDisabled['eligible'], 'Vacation eligibility should be disabled when the policy threshold is zero.');

assertLeaveSame(true, leaveIsVacationLeaveType('Annual vacation'), 'Vacation type detection should support English annual/vacation names.');
assertLeaveSame(true, leaveIsVacationLeaveType('ลาพักร้อน'), 'Vacation type detection should support Thai vacation leave names used in the system.');
assertLeaveSame(false, leaveIsVacationLeaveType('Sick leave'), 'Vacation type detection should not match unrelated leave names.');

$perTypeSummary = leaveBuildUsageSummaryItems([
    [
        'id' => 1,
        'type_name' => 'Annual leave',
        'days_per_year' => 6,
    ],
    [
        'id' => 2,
        'type_name' => 'Sick leave',
        'days_per_year' => 30,
    ],
    [
        'id' => 3,
        'type_name' => 'ขอมาสาย',
        'days_per_year' => 0,
        'is_actual_leave' => 0,
    ],
    [
        'id' => 4,
        'type_name' => 'OT หลังเลิกงาน',
        'days_per_year' => 0,
        'is_actual_leave' => 0,
    ],
], [
    [
        'leave_type_id' => 1,
        'type_name' => 'Annual leave',
        'status' => 'approved',
        'start_date' => '2026-01-05',
        'end_date' => '2026-01-06',
        'days' => 2.0,
        'duration_label' => '2 days',
    ],
    [
        'leave_type_id' => 1,
        'type_name' => 'Annual leave',
        'status' => 'pending_hr',
        'start_date' => '2026-02-02',
        'end_date' => '2026-02-02',
        'days' => 1.0,
        'duration_label' => '1 day',
    ],
    [
        'leave_type_id' => 2,
        'type_name' => 'Sick leave',
        'status' => 'pending_cancel_hr',
        'start_date' => '2026-03-03',
        'end_date' => '2026-03-03',
        'days' => 0.5,
        'duration_label' => '0.5 day',
    ],
]);

assertLeaveSame(2, count($perTypeSummary), 'Per-type usage summary should include configured day-leave types only.');
$annualSummary = $perTypeSummary[0];
assertLeaveSame(1, $annualSummary['leave_type_id'], 'Per-type summary should preserve leave type id.');
assertLeaveSame('Annual leave', $annualSummary['type_name'], 'Per-type summary should preserve leave type name.');
assertLeaveSame(6.0, $annualSummary['limit_days'], 'Per-type summary should use days_per_year from leave type settings.');
assertLeaveSame(2.0, $annualSummary['approved_days'], 'Per-type summary should count approved days for that type only.');
assertLeaveSame(1.0, $annualSummary['pending_days'], 'Per-type summary should count pending days for that type only.');
assertLeaveSame(4.0, $annualSummary['remaining_days'], 'Per-type summary should subtract approved days from the configured type limit.');
assertLeaveSame(33.3, $annualSummary['usage_percent'], 'Per-type summary should calculate usage percent from approved days.');
assertLeaveSame(2, count($annualSummary['entries']), 'Per-type summary should include entries for that type only.');
$sickSummary = $perTypeSummary[1];
assertLeaveSame(0.5, $sickSummary['approved_days'], 'Pending cancellation should still count as approved usage by type.');
assertLeaveSame(29.5, $sickSummary['remaining_days'], 'Per-type remaining days should support half-day approved usage.');

$overLimitSummary = leaveBuildUsageSummaryItems([[
    'id' => 5,
    'type_name' => 'Personal leave',
    'days_per_year' => 3,
]], [[
    'leave_type_id' => 5,
    'type_name' => 'Personal leave',
    'status' => 'approved',
    'start_date' => '2026-04-01',
    'end_date' => '2026-04-04',
    'days' => 4.0,
    'duration_label' => '4 days',
]]);
$overLimitItem = $overLimitSummary[0];
assertLeaveSame('over', $overLimitItem['status'], 'Per-type summary should warn when approved leave days exceed the configured type limit.');
assertLeaveSame(true, $overLimitItem['is_over_limit'], 'Per-type summary should mark over-limit usage as warning-only.');
assertLeaveSame(1.0, $overLimitItem['over_limit_days'], 'Per-type summary should report how many days exceed the configured limit.');
assertLeaveSame(-1.0, $overLimitItem['remaining_days'], 'Per-type summary should keep negative balance for backward-compatible consumers.');

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

$hourlyLeavePayload = leaveBuildHourlyLeavePayload(2.5, 8, 0);
assertLeaveSame('hour', $hourlyLeavePayload['request_unit'], 'Admin-configured hourly leave should still store hour request unit.');
assertLeaveSame(150, $hourlyLeavePayload['request_minutes'], 'Admin-configured hourly leave should store requested hours as minutes.');
assertLeaveSame(0.31, $hourlyLeavePayload['total_days'], 'Hourly leave should convert requested hours into quota days.');
assertLeaveSame('2.5 ชม. (0.31 วัน)', leaveFormatRequestDuration($hourlyLeavePayload), 'Hourly leave duration should show both hours and quota days.');

$thresholdHourlyLeavePayload = leaveBuildHourlyLeavePayload(4.5, 8, 4);
assertLeaveSame(1.0, $thresholdHourlyLeavePayload['total_days'], 'Hourly leave over the configured threshold should count as one full day.');

try {
    leaveBuildHourlyRequestPayload('late_arrival', 61);
    assertLeaveSame(true, false, 'Hourly requests over 60 minutes should be rejected.');
} catch (InvalidArgumentException $e) {
    assertLeaveSame(true, strpos($e->getMessage(), 'minutes') !== false, 'Hourly request validation should mention minutes.');
}

echo "leave_helpers_test passed" . PHP_EOL;
