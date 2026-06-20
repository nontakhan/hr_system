<?php

function holidayCalendarFetchCompanyHolidaysForMonth($mysqli, $month) {
    $start = $month . '-01';
    $end = (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');

    $stmt = $mysqli->prepare("SELECT holiday_date, holiday_name FROM company_holidays WHERE holiday_date BETWEEN ? AND ? ORDER BY holiday_date");
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();

    $holidays = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $holidays[$row['holiday_date']] = $row['holiday_name'];
    }

    return $holidays;
}

function holidayCalendarFetchApprovedLeavesForMonth($mysqli, $employeeId, $month) {
    $start = $month . '-01';
    $end = (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');

    $stmt = $mysqli->prepare("SELECT lr.id,
                                     lr.start_date,
                                     lr.end_date,
                                     lr.total_days,
                                     lr.reason,
                                     lr.status,
                                     lt.type_name AS leave_name
                              FROM leave_requests lr
                              LEFT JOIN leave_types lt ON lr.leave_type_id = lt.id
                              WHERE lr.employee_id = ?
                                AND lr.status IN ('approved', 'pending_cancel_hr')
                                AND (lr.request_unit IS NULL OR lr.request_unit <> 'hour')
                                AND lr.start_date <= ?
                                AND lr.end_date >= ?
                              ORDER BY lr.start_date, lr.id");
    $stmt->bind_param('iss', $employeeId, $end, $start);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function holidayCalendarNormalizeLeaveDateRange(array $leave) {
    $start = (string)($leave['start_date'] ?? '');
    $end = (string)($leave['end_date'] ?? '');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
        return null;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        $end = $start;
    }

    if ($end < $start) {
        $end = $start;
    }

    return [$start, $end];
}

function holidayCalendarBuildApprovedLeaveEvents(array $approvedLeaves) {
    $events = [];

    foreach ($approvedLeaves as $leave) {
        $range = holidayCalendarNormalizeLeaveDateRange($leave);
        if ($range === null) {
            continue;
        }

        [$start, $end] = $range;
        $current = new DateTimeImmutable($start);
        $last = new DateTimeImmutable($end);
        $title = trim((string)($leave['leave_name'] ?? '')) ?: 'Approved Leave';

        while ($current <= $last) {
            $date = $current->format('Y-m-d');
            $events[] = [
                'date' => $date,
                'type' => 'approved_leave',
                'title' => $title,
                'description' => 'Approved leave',
                'leave_id' => (int)($leave['id'] ?? 0),
                'start_date' => $start,
                'end_date' => $end,
                'total_days' => (float)($leave['total_days'] ?? 0),
                'reason' => (string)($leave['reason'] ?? ''),
                'status' => (string)($leave['status'] ?? 'approved'),
            ];
            $current = $current->modify('+1 day');
        }
    }

    return $events;
}

function holidayCalendarBuildEvents(array $companyHolidays, array $regularHolidays, array $approvedLeaves = []) {
    $eventsByDate = [];

    foreach ($regularHolidays as $day) {
        $date = (string)($day['date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            continue;
        }

        $eventsByDate[$date] = [
            'date' => $date,
            'type' => 'regular_holiday',
            'title' => 'วันหยุดประจำสัปดาห์',
            'description' => 'วันหยุดตามกะการทำงานของคุณ',
        ];
    }

    foreach ($companyHolidays as $date => $name) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date)) {
            continue;
        }

        $title = trim((string)$name) ?: 'วันหยุดบริษัท';
        $eventsByDate[$date] = [
            'date' => (string)$date,
            'type' => 'company_holiday',
            'title' => $title,
            'description' => 'วันหยุดพิเศษของบริษัท',
        ];
    }

    $events = array_merge(array_values($eventsByDate), holidayCalendarBuildApprovedLeaveEvents($approvedLeaves));
    usort($events, function ($left, $right) {
        $dateCompare = strcmp((string)($left['date'] ?? ''), (string)($right['date'] ?? ''));
        if ($dateCompare !== 0) {
            return $dateCompare;
        }

        $priority = [
            'company_holiday' => 0,
            'approved_leave' => 1,
            'regular_holiday' => 2,
        ];

        return ($priority[$left['type'] ?? ''] ?? 9) <=> ($priority[$right['type'] ?? ''] ?? 9);
    });

    return $events;
}

function holidayCalendarBuildSummary(array $events) {
    $summary = [
        'company_holiday' => 0,
        'regular_holiday' => 0,
        'approved_leave' => 0,
        'total' => 0,
    ];

    foreach ($events as $event) {
        $type = $event['type'] ?? '';
        if (isset($summary[$type])) {
            $summary[$type]++;
        }
        $summary['total']++;
    }

    return $summary;
}
