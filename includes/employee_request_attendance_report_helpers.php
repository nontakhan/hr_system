<?php

function employeeRequestReportBuildEvent($key, $date, $type, $source, $timeLabel, $amount, $unit, $detail, $statusLabel, array $extra = []) {
    return array_merge([
        'event_key' => (string)$key,
        'event_date' => (string)$date,
        'event_type' => (string)$type,
        'source' => (string)$source,
        'time_label' => (string)$timeLabel,
        'amount' => (float)$amount,
        'amount_unit' => (string)$unit,
        'detail' => (string)$detail,
        'status_label' => (string)$statusLabel,
    ], $extra);
}

function employeeRequestReportCalculateScannerEvents($workDate, $checkIn, $checkOut, array $shift) {
    $empty = ['late_minutes' => 0, 'early_minutes' => 0, 'overtime_minutes' => 0];
    $startTime = employeeRequestReportNormalizeTime($shift['start_time'] ?? null);
    $endTime = employeeRequestReportNormalizeTime($shift['end_time'] ?? null);
    $checkIn = employeeRequestReportNormalizeTime($checkIn);
    $checkOut = employeeRequestReportNormalizeTime($checkOut);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$workDate) || !$startTime || !$endTime || !$checkIn || !$checkOut) {
        return $empty;
    }

    try {
        $shiftStart = new DateTimeImmutable($workDate . ' ' . $startTime);
        $shiftEnd = new DateTimeImmutable($workDate . ' ' . $endTime);
        if ($shiftEnd <= $shiftStart) {
            $shiftEnd = $shiftEnd->modify('+1 day');
        }

        $actualIn = new DateTimeImmutable($workDate . ' ' . $checkIn);
        $actualOut = new DateTimeImmutable($workDate . ' ' . $checkOut);
        if ($shiftEnd->format('Y-m-d') !== $workDate && $actualOut < $shiftStart) {
            $actualOut = $actualOut->modify('+1 day');
        }
    } catch (Throwable $e) {
        return $empty;
    }

    $rawLate = max(0, employeeRequestReportMinuteDifference($shiftStart, $actualIn));
    $tolerance = max(0, (int)($shift['late_tolerance_mins'] ?? 0));
    $late = $rawLate > $tolerance ? $rawLate : 0;
    $early = $actualOut < $shiftEnd ? employeeRequestReportMinuteDifference($actualOut, $shiftEnd) : 0;
    $overtime = $actualOut > $shiftEnd ? employeeRequestReportMinuteDifference($shiftEnd, $actualOut) : 0;

    return [
        'late_minutes' => max(0, $late),
        'early_minutes' => max(0, $early),
        'overtime_minutes' => max(0, $overtime),
    ];
}

function employeeRequestReportNormalizeTime($value) {
    $value = trim((string)$value);
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value)) {
        return null;
    }
    return strlen($value) === 5 ? $value . ':00' : $value;
}

function employeeRequestReportMinuteDifference(DateTimeImmutable $start, DateTimeImmutable $end) {
    return (int)floor(($end->getTimestamp() - $start->getTimestamp()) / 60);
}

function employeeRequestReportSortEvents(array $events) {
    $order = [
        'leave' => 10,
        'late_request' => 20,
        'actual_late' => 30,
        'early_request' => 40,
        'actual_early' => 50,
        'activity' => 60,
        'shift_swap' => 70,
        'overtime_request' => 80,
        'actual_overtime' => 90,
    ];
    usort($events, function ($left, $right) use ($order) {
        $dateComparison = strcmp((string)($left['event_date'] ?? ''), (string)($right['event_date'] ?? ''));
        if ($dateComparison !== 0) return $dateComparison;
        $typeComparison = ($order[$left['event_type'] ?? ''] ?? 999) <=> ($order[$right['event_type'] ?? ''] ?? 999);
        if ($typeComparison !== 0) return $typeComparison;
        return strcmp((string)($left['event_key'] ?? ''), (string)($right['event_key'] ?? ''));
    });
    return $events;
}

function employeeRequestReportSummarize(array $events) {
    $summary = [
        'total_events' => count($events),
        'approved_requests' => 0,
        'scanner_events' => 0,
        'actual_overtime_minutes' => 0,
    ];
    foreach ($events as $event) {
        if (($event['source'] ?? '') === 'approved_request') $summary['approved_requests']++;
        if (($event['source'] ?? '') === 'scanner') $summary['scanner_events']++;
        if (($event['event_type'] ?? '') === 'actual_overtime') {
            $summary['actual_overtime_minutes'] += max(0, (int)round((float)($event['amount'] ?? 0)));
        }
    }
    return $summary;
}
