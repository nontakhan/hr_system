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

function holidayCalendarBuildEvents(array $companyHolidays, array $regularHolidays) {
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

    ksort($eventsByDate);
    return array_values($eventsByDate);
}

function holidayCalendarBuildSummary(array $events) {
    $summary = [
        'company_holiday' => 0,
        'regular_holiday' => 0,
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
