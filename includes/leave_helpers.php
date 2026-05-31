<?php

function leaveDayName($date) {
    return date('D', strtotime($date));
}

function leaveThaiWeekdayName($date) {
    $names = [
        'Mon' => 'วันจันทร์',
        'Tue' => 'วันอังคาร',
        'Wed' => 'วันพุธ',
        'Thu' => 'วันพฤหัสบดี',
        'Fri' => 'วันศุกร์',
        'Sat' => 'วันเสาร์',
        'Sun' => 'วันอาทิตย์',
    ];

    $day = leaveDayName($date);
    return $names[$day] ?? $day;
}

function leaveDayPartLabel($part) {
    $labels = [
        'full' => 'เต็มวัน',
        'morning' => 'ครึ่งวันเช้า',
        'afternoon' => 'ครึ่งวันบ่าย',
    ];

    return $labels[$part] ?? $labels['full'];
}

function leaveNormalizeDayPart($part) {
    return in_array($part, ['full', 'morning', 'afternoon'], true) ? $part : 'full';
}

function leaveBuildDateSummary($startDate, $endDate, $startPart, $endPart, $workDays, array $companyHolidays) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$endDate)) {
        return ['valid' => false, 'message' => 'รูปแบบวันที่ไม่ถูกต้อง', 'total_days' => 0.0, 'included_dates' => [], 'excluded_dates' => []];
    }

    $start = new DateTimeImmutable($startDate);
    $end = new DateTimeImmutable($endDate);
    if ($end < $start) {
        return ['valid' => false, 'message' => 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่ม', 'total_days' => 0.0, 'included_dates' => [], 'excluded_dates' => []];
    }

    $startPart = leaveNormalizeDayPart($startPart);
    $endPart = leaveNormalizeDayPart($endPart);
    if ($startDate === $endDate && $startPart === 'afternoon' && $endPart === 'morning') {
        return ['valid' => false, 'message' => 'ช่วงครึ่งวันไม่ถูกต้อง', 'total_days' => 0.0, 'included_dates' => [], 'excluded_dates' => []];
    }

    if ($startDate !== $endDate && $startPart === 'morning') {
        return ['valid' => false, 'message' => 'การลาหลายวัน วันแรกเลือกครึ่งวันได้เฉพาะช่วงบ่าย', 'total_days' => 0.0, 'included_dates' => [], 'excluded_dates' => []];
    }
    if ($startDate !== $endDate && $endPart === 'afternoon') {
        return ['valid' => false, 'message' => 'การลาหลายวัน วันสุดท้ายเลือกครึ่งวันได้เฉพาะช่วงเช้า', 'total_days' => 0.0, 'included_dates' => [], 'excluded_dates' => []];
    }

    $workDayList = array_filter(array_map('trim', explode(',', (string)$workDays)));
    $included = [];
    $excluded = [];
    $total = 0.0;

    for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
        $workDate = $date->format('Y-m-d');
        $dayName = leaveDayName($workDate);

        if (isset($companyHolidays[$workDate])) {
            $excluded[] = [
                'date' => $workDate,
                'type' => 'company_holiday',
                'reason' => $companyHolidays[$workDate],
            ];
            continue;
        }

        if (!empty($workDayList) && !in_array($dayName, $workDayList, true)) {
            $excluded[] = [
                'date' => $workDate,
                'type' => 'employee_holiday',
                'reason' => 'ตรงกับวันหยุดประจำกะ (' . leaveThaiWeekdayName($workDate) . ')',
            ];
            continue;
        }

        $days = 1.0;
        $parts = [];
        if ($startDate === $endDate) {
            if ($startPart !== 'full' || $endPart !== 'full') {
                $days = 0.5;
                $parts[] = $startPart !== 'full' ? $startPart : $endPart;
            }
        } else {
            if ($workDate === $startDate && $startPart !== 'full') {
                $days = 0.5;
                $parts[] = $startPart;
            } elseif ($workDate === $endDate && $endPart !== 'full') {
                $days = 0.5;
                $parts[] = $endPart;
            }
        }

        $total += $days;
        $included[] = [
            'date' => $workDate,
            'days' => $days,
            'part' => $parts[0] ?? 'full',
            'label' => leaveDayPartLabel($parts[0] ?? 'full'),
        ];
    }

    return [
        'valid' => true,
        'message' => '',
        'total_days' => $total,
        'included_dates' => $included,
        'excluded_dates' => $excluded,
    ];
}

function leaveFetchEmployeeWorkDays(mysqli $mysqli, $employeeId) {
    $stmt = $mysqli->prepare("SELECT ws.work_days FROM employees e LEFT JOIN work_shifts ws ON e.default_shift_id = ws.id WHERE e.id = ?");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (string)($row['work_days'] ?? '');
}

function leaveFetchCompanyHolidays(mysqli $mysqli, $startDate, $endDate) {
    $stmt = $mysqli->prepare("SELECT holiday_date, holiday_name FROM company_holidays WHERE holiday_date BETWEEN ? AND ? ORDER BY holiday_date");
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $holidays = [];
    while ($row = $result->fetch_assoc()) {
        $holidays[$row['holiday_date']] = $row['holiday_name'];
    }

    return $holidays;
}

function leaveEnsureRequestPartColumns(mysqli $mysqli) {
    $columns = [];
    $result = $mysqli->query("SHOW COLUMNS FROM leave_requests");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = true;
        }
    }

    if (!isset($columns['start_day_part'])) {
        $mysqli->query("ALTER TABLE leave_requests ADD COLUMN start_day_part ENUM('full','morning','afternoon') NOT NULL DEFAULT 'full' AFTER end_date");
    }

    if (!isset($columns['end_day_part'])) {
        $mysqli->query("ALTER TABLE leave_requests ADD COLUMN end_day_part ENUM('full','morning','afternoon') NOT NULL DEFAULT 'full' AFTER start_day_part");
    }
}
