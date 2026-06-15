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
    $checkOut = attendanceNormalizeTime($row[18] ?? '');
    if ($checkOut === null) {
        $checkOut = attendanceNormalizeTime($row[19] ?? '');
    }

    return [
        'citizen_id' => trim((string)($row[0] ?? '')),
        'work_date' => attendanceParseThaiDate($row[3] ?? ''),
        'check_in' => attendanceNormalizeTime($row[10] ?? ''),
        'check_out' => $checkOut,
    ];
}

function attendanceImportMonthFromWorkDate($workDate) {
    $workDate = trim((string)$workDate);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate) ? substr($workDate, 0, 7) : null;
}

function attendanceBuildImportUpsertSql() {
    return "INSERT INTO attendance_records
        (employee_id, citizen_id, work_date, check_in, check_out, import_month, source_file)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            check_in = COALESCE(check_in, VALUES(check_in)),
            check_out = COALESCE(check_out, VALUES(check_out))";
}

function attendanceBuildImportBatchUpsertSql($rowCount) {
    $rowCount = max(1, (int)$rowCount);
    $placeholders = implode(',', array_fill(0, $rowCount, '(?, ?, ?, ?, ?, ?, ?)'));
    return "INSERT INTO attendance_records
        (employee_id, citizen_id, work_date, check_in, check_out, import_month, source_file)
        VALUES {$placeholders}
        ON DUPLICATE KEY UPDATE
            check_in = COALESCE(check_in, VALUES(check_in)),
            check_out = COALESCE(check_out, VALUES(check_out))";
}

function attendanceClassifyImportAffectedRows($affectedRows) {
    if ((int)$affectedRows === 1) {
        return 'inserted';
    }
    if ((int)$affectedRows === 2) {
        return 'updated';
    }
    return 'skipped';
}

function attendanceBuildExistingRecordMap(array $rows) {
    $map = [];
    foreach ($rows as $row) {
        $employeeId = (int)($row['employee_id'] ?? 0);
        $workDate = (string)($row['work_date'] ?? '');
        if ($employeeId <= 0 || $workDate === '') {
            continue;
        }
        $map[$employeeId . '|' . $workDate] = [
            'check_in' => attendanceNormalizeTime($row['check_in'] ?? ''),
            'check_out' => attendanceNormalizeTime($row['check_out'] ?? ''),
        ];
    }
    return $map;
}

function attendanceExistingRecordNeedsFill(array $existing, array $row) {
    return (($existing['check_in'] ?? null) === null && ($row['check_in'] ?? null) !== null)
        || (($existing['check_out'] ?? null) === null && ($row['check_out'] ?? null) !== null);
}

function attendanceBuildEmployeeIdMap(array $rows) {
    $map = [];
    foreach ($rows as $row) {
        $citizenId = trim((string)($row['citizen_id'] ?? ''));
        if ($citizenId === '') {
            continue;
        }
        $map[$citizenId] = (int)($row['id'] ?? 0);
    }
    return $map;
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

function attendanceBuildImportEmployeeRows(array $rows) {
    return array_map(function ($row) {
        $firstName = trim((string)($row['first_name_th'] ?? ''));
        $lastName = trim((string)($row['last_name_th'] ?? ''));
        return [
            'employee_id' => (int)($row['employee_id'] ?? 0),
            'citizen_id' => (string)($row['citizen_id'] ?? ''),
            'first_name_th' => $firstName,
            'last_name_th' => $lastName,
            'full_name' => trim($firstName . ' ' . $lastName),
            'position_name_th' => (string)($row['position_name_th'] ?? ''),
            'branch_name_th' => (string)($row['branch_name_th'] ?? ''),
            'company_name_th' => (string)($row['company_name_th'] ?? ''),
            'record_count' => (int)($row['record_count'] ?? 0),
            'first_work_date' => $row['first_work_date'] ?? null,
            'latest_work_date' => $row['latest_work_date'] ?? null,
        ];
    }, $rows);
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

function attendanceWeekdays() {
    return ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
}

function attendanceApplyDayTypeOverride(array $shift, $workDate, $dayType) {
    $dayName = attendanceDayName($workDate);
    $workDays = array_filter(array_map('trim', explode(',', (string)($shift['work_days'] ?? ''))));
    if (empty($workDays)) {
        $workDays = attendanceWeekdays();
    }

    if ($dayType === 'workday' && !in_array($dayName, $workDays, true)) {
        $workDays[] = $dayName;
    }

    if ($dayType === 'holiday') {
        $workDays = array_values(array_filter($workDays, function ($day) use ($dayName) {
            return $day !== $dayName;
        }));
    }

    $orderedDays = [];
    foreach (attendanceWeekdays() as $day) {
        if (in_array($day, $workDays, true)) {
            $orderedDays[] = $day;
        }
    }

    $shift['work_days'] = implode(',', $orderedDays);
    return $shift;
}

function attendanceBuildApprovedDaySwapMap(array $swapRows, $employeeId, $month) {
    $map = [];
    $monthPrefix = $month . '-';

    foreach ($swapRows as $row) {
        $requesterId = (int)($row['requester_employee_id'] ?? 0);
        $targetId = (int)($row['target_employee_id'] ?? 0);
        $requesterDate = (string)($row['requester_date'] ?? '');
        $targetDate = (string)($row['target_date'] ?? '');

        if ($employeeId === $requesterId) {
            if (strpos($requesterDate, $monthPrefix) === 0) {
                $map[$requesterDate] = 'workday';
            }
            if (strpos($targetDate, $monthPrefix) === 0) {
                $map[$targetDate] = 'holiday';
            }
        }

        if ($employeeId === $targetId) {
            if (strpos($requesterDate, $monthPrefix) === 0) {
                $map[$requesterDate] = 'holiday';
            }
            if (strpos($targetDate, $monthPrefix) === 0) {
                $map[$targetDate] = 'workday';
            }
        }
    }

    return $map;
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

function attendanceBuildApprovedHourlyRequestMap(array $leaveRows, $month) {
    $start = new DateTimeImmutable($month . '-01');
    $end = $start->modify('last day of this month');
    $requests = [];

    foreach ($leaveRows as $row) {
        if (($row['request_unit'] ?? 'day') !== 'hour') {
            continue;
        }

        $requestDate = new DateTimeImmutable($row['start_date']);
        if ($requestDate < $start || $requestDate > $end) {
            continue;
        }

        $workDate = $requestDate->format('Y-m-d');
        $type = $row['time_request_type'] ?? '';
        $minutes = max(1, min(60, (int)($row['request_minutes'] ?? 60)));
        $label = $type === 'early_departure'
            ? 'ขอออกก่อน ' . $minutes . ' นาที'
            : 'ขอมาสาย ' . $minutes . ' นาที';

        if (!isset($requests[$workDate])) {
            $requests[$workDate] = [];
        }
        $requests[$workDate][] = $label;
    }

    return $requests;
}

function attendanceCalculateTimeRequestMinutes($timeRequestType, $workDate, $requestedTime, array $shift) {
    $timeRequestType = in_array($timeRequestType, ['late_arrival', 'early_departure'], true) ? $timeRequestType : '';
    if ($timeRequestType === '') {
        return ['valid' => false, 'message' => 'ประเภทคำขอไม่ถูกต้อง', 'request_minutes' => 0];
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$workDate)) {
        return ['valid' => false, 'message' => 'รูปแบบวันที่ไม่ถูกต้อง', 'request_minutes' => 0];
    }

    $requested = attendanceNormalizeTime($requestedTime);
    if ($requested === null) {
        return ['valid' => false, 'message' => 'รูปแบบเวลาไม่ถูกต้อง', 'request_minutes' => 0];
    }

    $workDays = array_filter(array_map('trim', explode(',', (string)($shift['work_days'] ?? ''))));
    $dayName = attendanceDayName($workDate);
    if (!empty($workDays) && !in_array($dayName, $workDays, true)) {
        return ['valid' => false, 'message' => 'วันที่เลือกไม่ใช่วันทำงานตามกะ', 'request_minutes' => 0];
    }

    $anchorTime = $timeRequestType === 'early_departure'
        ? attendanceNormalizeTime($shift['end_time'] ?? '')
        : attendanceNormalizeTime($shift['start_time'] ?? '');
    if ($anchorTime === null) {
        return ['valid' => false, 'message' => 'ยังไม่ได้ตั้งค่าเวลาเริ่มหรือเลิกกะ', 'request_minutes' => 0];
    }

    $anchorTs = strtotime($workDate . ' ' . $anchorTime);
    $requestedTs = strtotime($workDate . ' ' . $requested);
    $seconds = $timeRequestType === 'early_departure'
        ? $anchorTs - $requestedTs
        : $requestedTs - $anchorTs;
    $minutes = (int)ceil($seconds / 60);

    if ($minutes < 1) {
        return ['valid' => false, 'message' => 'เวลาที่ขอต้องอยู่นอกเวลาเริ่มหรือเลิกกะ', 'request_minutes' => 0];
    }
    if ($minutes > 60) {
        return ['valid' => false, 'message' => 'คำขอเวลาต้องไม่เกิน 60 นาที', 'request_minutes' => $minutes];
    }

    return ['valid' => true, 'message' => '', 'request_minutes' => $minutes];
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
