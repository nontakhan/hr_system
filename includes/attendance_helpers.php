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

function attendanceDayName($date) {
    return date('D', strtotime($date));
}

function attendanceEvaluateStatus($workDate, $checkIn, $checkOut, array $shift, array $holidays = []) {
    if (isset($holidays[$workDate])) {
        return [
            'status' => 'holiday',
            'label' => 'วันหยุด',
            'is_late' => false,
            'holiday_name' => $holidays[$workDate],
        ];
    }

    $workDays = array_filter(array_map('trim', explode(',', (string)($shift['work_days'] ?? ''))));
    $dayName = attendanceDayName($workDate);
    $isWorkday = empty($workDays) || in_array($dayName, $workDays, true);

    if (!$isWorkday) {
        return ['status' => 'holiday', 'label' => 'วันหยุด', 'is_late' => false, 'holiday_name' => null];
    }

    if ($checkIn === null && $checkOut === null) {
        return ['status' => 'absent', 'label' => 'ขาด', 'is_late' => false, 'holiday_name' => null];
    }

    if ($checkIn === null && $checkOut !== null) {
        return ['status' => 'missing_in', 'label' => 'ไม่สแกนเข้า', 'is_late' => false, 'holiday_name' => null];
    }

    if ($checkIn !== null && $checkOut === null) {
        return ['status' => 'missing_out', 'label' => 'ไม่สแกนออก', 'is_late' => false, 'holiday_name' => null];
    }

    $startTime = attendanceNormalizeTime($shift['start_time'] ?? '');
    $lateTolerance = (int)($shift['late_tolerance_mins'] ?? 0);
    if ($startTime !== null) {
        $start = strtotime($workDate . ' ' . $startTime);
        $in = strtotime($workDate . ' ' . $checkIn);
        if ($in > ($start + ($lateTolerance * 60))) {
            return ['status' => 'late', 'label' => 'สาย', 'is_late' => true, 'holiday_name' => null];
        }
    }

    return ['status' => 'present', 'label' => 'ปกติ', 'is_late' => false, 'holiday_name' => null];
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
