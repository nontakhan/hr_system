<?php

function attendanceNormalizeTime($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $matches)) {
        $hour = (int)$matches[1];
        $minute = (int)$matches[2];
        $second = isset($matches[3]) ? (int)$matches[3] : 0;

        if ($hour >= 0 && $hour <= 23 && $minute <= 59 && $second <= 59) {
            return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
        }
    }

    return null;
}

function attendanceParseThaiDate($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $parts = preg_split('/[\/\-]/', $value);
    if (count($parts) !== 3) {
        return null;
    }

    $day = (int)$parts[0];
    $month = (int)$parts[1];
    $year = (int)$parts[2];
    if ($year > 2400) {
        $year -= 543;
    }

    if (!checkdate($month, $day, $year)) {
        return null;
    }

    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

function attendanceMapCsvRow(array $row) {
    return [
        'citizen_id' => trim((string)($row[0] ?? '')),
        'work_date' => attendanceParseThaiDate($row[3] ?? ''),
        'check_in' => attendanceNormalizeTime($row[10] ?? ''),
        'check_out' => attendanceNormalizeTime($row[18] ?? ''),
    ];
}

function attendanceImportMonthFromWorkDate($workDate) {
    $workDate = trim((string)$workDate);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate) ? substr($workDate, 0, 7) : null;
}

function attendanceBuildImportSummaryMonths(array $monthlyRows, $baseDate = 'now', $limit = 6) {
    $indexed = [];
    foreach ($monthlyRows as $row) {
        $month = (string)($row['import_month'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            continue;
        }

        $indexed[$month] = [
            'import_month' => $month,
            'record_count' => (int)($row['record_count'] ?? 0),
            'employee_count' => (int)($row['employee_count'] ?? 0),
            'latest_work_date' => $row['latest_work_date'] ?? null,
        ];
    }

    $months = [];
    $start = new DateTimeImmutable($baseDate);
    $start = $start->modify('first day of this month');
    for ($i = 0; $i < $limit; $i++) {
        $month = $start->modify("-{$i} months")->format('Y-m');
        $data = $indexed[$month] ?? [
            'import_month' => $month,
            'record_count' => 0,
            'employee_count' => 0,
            'latest_work_date' => null,
        ];
        $data['has_data'] = $data['record_count'] > 0;
        $months[] = $data;
    }

    return $months;
}

function attendanceDayName($date) {
    return date('D', strtotime($date));
}

function attendanceResolveShiftForDate(array $baseShift, array $overrides, $workDate) {
    $dayName = attendanceDayName($workDate);

    foreach ($overrides as $override) {
        $days = array_filter(array_map('trim', explode(',', (string)($override['day_of_week'] ?? ''))));
        if (!in_array($dayName, $days, true)) {
            continue;
        }

        $effectiveFrom = trim((string)($override['effective_from'] ?? ''));
        $effectiveTo = trim((string)($override['effective_to'] ?? ''));
        if ($effectiveFrom !== '' && $workDate < $effectiveFrom) {
            continue;
        }
        if ($effectiveTo !== '' && $workDate > $effectiveTo) {
            continue;
        }

        return [
            'start_time' => $override['start_time'] ?? $baseShift['start_time'] ?? null,
            'end_time' => $override['end_time'] ?? $baseShift['end_time'] ?? null,
            'late_tolerance_mins' => $override['late_tolerance_mins'] ?? $baseShift['late_tolerance_mins'] ?? 0,
            'work_days' => implode(',', $days),
        ];
    }

    return $baseShift;
}

function attendanceBuildApprovedLeaveMap(array $leaveRows, $month) {
    $start = new DateTimeImmutable($month . '-01');
    $end = $start->modify('last day of this month');
    $leaves = [];

    foreach ($leaveRows as $row) {
        $leaveStart = new DateTimeImmutable($row['start_date']);
        $leaveEnd = new DateTimeImmutable($row['end_date']);
        $typeName = (string)($row['type_name'] ?? 'ลา');

        if ($leaveEnd < $start || $leaveStart > $end) {
            continue;
        }

        $from = $leaveStart < $start ? $start : $leaveStart;
        $to = $leaveEnd > $end ? $end : $leaveEnd;
        for ($date = $from; $date <= $to; $date = $date->modify('+1 day')) {
            $workDate = $date->format('Y-m-d');
            if (!isset($leaves[$workDate])) {
                $leaves[$workDate] = $typeName;
            }
        }
    }

    return $leaves;
}

function attendanceEvaluateStatus($workDate, $checkIn, $checkOut, array $shift, array $holidays = [], array $leaves = []) {
    if (isset($holidays[$workDate])) {
        return [
            'status' => 'holiday',
            'label' => 'วันหยุด',
            'is_late' => false,
            'holiday_name' => $holidays[$workDate],
            'leave_name' => null,
        ];
    }

    $workDays = array_filter(array_map('trim', explode(',', (string)($shift['work_days'] ?? ''))));
    $dayName = attendanceDayName($workDate);
    $isWorkday = empty($workDays) || in_array($dayName, $workDays, true);

    if (!$isWorkday) {
        return ['status' => 'holiday', 'label' => 'วันหยุด', 'is_late' => false, 'holiday_name' => null, 'leave_name' => null];
    }

    if (isset($leaves[$workDate])) {
        return ['status' => 'leave', 'label' => 'ลา', 'is_late' => false, 'holiday_name' => null, 'leave_name' => $leaves[$workDate]];
    }

    if ($checkIn === null && $checkOut === null) {
        return ['status' => 'absent', 'label' => 'ขาด', 'is_late' => false, 'holiday_name' => null, 'leave_name' => null];
    }

    if ($checkIn === null && $checkOut !== null) {
        return ['status' => 'missing_in', 'label' => 'ไม่สแกนเข้า', 'is_late' => false, 'holiday_name' => null, 'leave_name' => null];
    }

    if ($checkIn !== null && $checkOut === null) {
        return ['status' => 'missing_out', 'label' => 'ไม่สแกนออก', 'is_late' => false, 'holiday_name' => null, 'leave_name' => null];
    }

    $startTime = attendanceNormalizeTime($shift['start_time'] ?? '');
    $lateTolerance = (int)($shift['late_tolerance_mins'] ?? 0);
    if ($startTime !== null) {
        $start = strtotime($workDate . ' ' . $startTime);
        $in = strtotime($workDate . ' ' . $checkIn);
        if ($in > ($start + ($lateTolerance * 60))) {
            return ['status' => 'late', 'label' => 'สาย', 'is_late' => true, 'holiday_name' => null, 'leave_name' => null];
        }
    }

    return ['status' => 'present', 'label' => 'ปกติ', 'is_late' => false, 'holiday_name' => null, 'leave_name' => null];
}

function attendanceReadCsvRows($filePath) {
    $rows = [];
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        return $rows;
    }

    $headerSkipped = false;
    while (($row = fgetcsv($handle)) !== false) {
        if (!$headerSkipped) {
            $headerSkipped = true;
            continue;
        }

        $mapped = attendanceMapCsvRow($row);
        if ($mapped['citizen_id'] !== '' && $mapped['work_date'] !== null) {
            $rows[] = $mapped;
        }
    }

    fclose($handle);
    return $rows;
}
