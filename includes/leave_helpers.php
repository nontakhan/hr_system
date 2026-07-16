<?php

require_once __DIR__ . '/proxy_request_helpers.php';
require_once __DIR__ . '/date_helpers.php';

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

function leaveDetectHourlyRequestType($typeName) {
    $name = mb_strtolower((string)$typeName, 'UTF-8');
    if (strpos($name, 'มาสาย') !== false || strpos($name, 'ขอสาย') !== false || strpos($name, 'late') !== false) {
        return 'late_arrival';
    }
    if (strpos($name, 'ออกก่อน') !== false || strpos($name, 'early') !== false) {
        return 'early_departure';
    }
    return null;
}

function leaveEnsureHourlyRequestTypes(mysqli $mysqli) {
    leaveEnsureLeaveTypeCalculationColumns($mysqli);
    $defaults = [
        [
            'type_name' => 'ขอมาสาย',
            'description' => 'ขออนุญาตมาสายตามนาทีจริง สูงสุดไม่เกิน 1 ชม. โดยอิงเวลาเริ่มกะของวันนั้น',
        ],
        [
            'type_name' => 'ขอออกก่อน',
            'description' => 'ขออนุญาตออกก่อนเวลาตามนาทีจริง สูงสุดไม่เกิน 1 ชม. โดยอิงเวลาเลิกกะของวันนั้น',
        ],
        [
            'type_name' => 'OT หลังเลิกงาน',
            'description' => 'ขออนุมัติทำงานล่วงเวลาหลังเลิกงาน โดย HR อนุมัติจากผลสแกนออกจริง',
        ],
    ];

    foreach ($defaults as $default) {
        $stmt = $mysqli->prepare("SELECT id FROM leave_types WHERE type_name = ? LIMIT 1");
        if (!$stmt) {
            throw new RuntimeException('Cannot prepare hourly leave type lookup');
        }
        $stmt->bind_param('s', $default['type_name']);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            $hoursPerDay = 1.0;
            $threshold = 0.0;
            $update = $mysqli->prepare("UPDATE leave_types SET calculation_unit = 'hour', hours_per_day = ?, hour_full_day_threshold = ? WHERE id = ?");
            if ($update) {
                $typeId = (int)$exists['id'];
                $update->bind_param('ddi', $hoursPerDay, $threshold, $typeId);
                $update->execute();
                $update->close();
            }
            continue;
        }

        $daysPerYear = 0;
        $requiresFile = 0;
        $insert = $mysqli->prepare("INSERT INTO leave_types (type_name, days_per_year, description, requires_file, calculation_unit, hours_per_day, hour_full_day_threshold, is_actual_leave) VALUES (?, ?, ?, ?, 'hour', 1.00, 0.00, 0)");
        if (!$insert) {
            throw new RuntimeException('Cannot prepare hourly leave type insert');
        }
        $insert->bind_param('sisi', $default['type_name'], $daysPerYear, $default['description'], $requiresFile);
        if (!$insert->execute()) {
            throw new RuntimeException($insert->error ?: 'Cannot create hourly leave type');
        }
        $insert->close();
    }
}

function leaveEnsureLeaveTypeCalculationColumns(mysqli $mysqli) {
    $columns = [];
    $result = $mysqli->query("SHOW COLUMNS FROM leave_types");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = $row;
        }
    }

    if (!isset($columns['calculation_unit'])) {
        $mysqli->query("ALTER TABLE leave_types ADD COLUMN calculation_unit ENUM('day','hour') NOT NULL DEFAULT 'day' AFTER requires_file");
    }

    if (!isset($columns['hours_per_day'])) {
        $mysqli->query("ALTER TABLE leave_types ADD COLUMN hours_per_day DECIMAL(5,2) NOT NULL DEFAULT 8.00 AFTER calculation_unit");
    }

    if (!isset($columns['hour_full_day_threshold'])) {
        $mysqli->query("ALTER TABLE leave_types ADD COLUMN hour_full_day_threshold DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER hours_per_day");
    }

    if (!isset($columns['vacation_min_months_before_leave'])) {
        $mysqli->query("ALTER TABLE leave_types ADD COLUMN vacation_min_months_before_leave INT UNSIGNED NOT NULL DEFAULT 0 AFTER hour_full_day_threshold");
    }

    if (!isset($columns['is_actual_leave'])) {
        $mysqli->query("ALTER TABLE leave_types ADD COLUMN is_actual_leave TINYINT(1) NOT NULL DEFAULT 1 AFTER vacation_min_months_before_leave");
        $mysqli->query("UPDATE leave_types
                        SET is_actual_leave = 0
                        WHERE type_name IN ('ขอมาสาย', 'ขอออกก่อน', 'OT หลังเลิกงาน')");
    }
}

function leaveNormalizeLeaveTypeCalculation(array $input) {
    $unit = ($input['calculation_unit'] ?? 'day') === 'hour' ? 'hour' : 'day';
    $hoursPerDay = (float)($input['hours_per_day'] ?? 8);
    $threshold = (float)($input['hour_full_day_threshold'] ?? 0);

    if ($unit === 'day') {
        $hoursPerDay = 8.0;
        $threshold = 0.0;
    }

    if ($hoursPerDay <= 0) {
        $hoursPerDay = 8.0;
    }
    if ($threshold < 0) {
        $threshold = 0.0;
    }

    return [
        'calculation_unit' => $unit,
        'hours_per_day' => round($hoursPerDay, 2),
        'hour_full_day_threshold' => round($threshold, 2),
        'vacation_min_months_before_leave' => max(0, (int)($input['vacation_min_months_before_leave'] ?? 0)),
        'is_actual_leave' => !empty($input['is_actual_leave']) ? 1 : 0,
    ];
}

function leaveBuildHourlyRequestPayload($timeRequestType, $requestMinutes = 60) {
    $timeRequestType = in_array($timeRequestType, ['late_arrival', 'early_departure', 'overtime_after_work'], true)
        ? $timeRequestType
        : null;
    if ($timeRequestType === null) {
        throw new InvalidArgumentException('Invalid hourly request type');
    }
    $requestMinutes = (int)$requestMinutes;
    $maxMinutes = $timeRequestType === 'overtime_after_work' ? 1440 : 60;
    if ($requestMinutes < 1 || $requestMinutes > $maxMinutes) {
        throw new InvalidArgumentException('Invalid hourly request minutes');
    }

    return [
        'request_unit' => 'hour',
        'time_request_type' => $timeRequestType,
        'request_minutes' => $requestMinutes,
        'total_days' => 0.0,
    ];
}

function leaveFormatRequestTimeRange(array $row) {
    $start = $row['request_start_time'] ?? null;
    $end = $row['request_end_time'] ?? null;
    if (!$start || !$end) {
        return '';
    }
    return substr((string)$start, 0, 5) . '-' . substr((string)$end, 0, 5);
}

function leaveBuildHourlyLeavePayload($requestHours, $hoursPerDay = 8, $fullDayThreshold = 0) {
    $requestHours = round((float)$requestHours, 2);
    $hoursPerDay = round((float)$hoursPerDay, 2);
    $fullDayThreshold = round((float)$fullDayThreshold, 2);

    if ($requestHours <= 0) {
        throw new InvalidArgumentException('Invalid hourly leave hours');
    }
    if ($hoursPerDay <= 0) {
        throw new InvalidArgumentException('Invalid hours per day');
    }
    if ($fullDayThreshold < 0) {
        throw new InvalidArgumentException('Invalid full day threshold');
    }

    $totalDays = $requestHours / $hoursPerDay;
    if ($fullDayThreshold > 0 && $requestHours > $fullDayThreshold) {
        $totalDays = 1.0;
    }

    return [
        'request_unit' => 'hour',
        'time_request_type' => null,
        'request_minutes' => (int)round($requestHours * 60),
        'request_hours' => $requestHours,
        'hours_per_day' => $hoursPerDay,
        'hour_full_day_threshold' => $fullDayThreshold,
        'total_days' => round($totalDays, 2),
    ];
}

function leaveNormalizeClockTime($value) {
    $value = trim((string)$value);
    if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $matches)) {
        throw new InvalidArgumentException('Invalid time format');
    }

    $hours = (int)$matches[1];
    $minutes = (int)$matches[2];
    $seconds = isset($matches[3]) ? (int)$matches[3] : 0;
    if ($hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59 || $seconds < 0 || $seconds > 59) {
        throw new InvalidArgumentException('Invalid time format');
    }

    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

function leaveClockTimeToMinutes($time) {
    $normalized = leaveNormalizeClockTime($time);
    return ((int)substr($normalized, 0, 2) * 60) + (int)substr($normalized, 3, 2);
}

function leaveBuildTimedHourlyLeavePayload($startTime, $endTime, $hoursPerDay = 8, $fullDayThreshold = 0) {
    $normalizedStart = leaveNormalizeClockTime($startTime);
    $normalizedEnd = leaveNormalizeClockTime($endTime);
    $requestMinutes = leaveClockTimeToMinutes($normalizedEnd) - leaveClockTimeToMinutes($normalizedStart);
    if ($requestMinutes <= 0) {
        throw new InvalidArgumentException('End time must be after start time');
    }

    $payload = leaveBuildHourlyLeavePayload($requestMinutes / 60, $hoursPerDay, $fullDayThreshold);
    $payload['request_start_time'] = $normalizedStart;
    $payload['request_end_time'] = $normalizedEnd;
    return $payload;
}

function leaveFormatRequestDuration(array $row) {
    $unit = $row['request_unit'] ?? 'day';
    if ($unit !== 'hour') {
        $days = (float)($row['days'] ?? $row['total_days'] ?? 0);
        return (floor($days) == $days ? (string)(int)$days : number_format($days, 1)) . ' วัน';
    }

    $type = $row['time_request_type'] ?? '';
    if ($type === null || $type === '') {
        $minutes = max(1, (int)($row['request_minutes'] ?? 0));
        $hours = $minutes / 60;
        $hoursText = (floor($hours) == $hours ? (string)(int)$hours : rtrim(rtrim(number_format($hours, 2), '0'), '.'));
        $days = (float)($row['days'] ?? $row['total_days'] ?? 0);
        $daysText = (floor($days) == $days ? (string)(int)$days : rtrim(rtrim(number_format($days, 2), '0'), '.'));
        return $hoursText . ' ชม. (' . $daysText . ' วัน)';
    }
    if ($type === 'overtime_after_work') {
        $minutes = max(1, (int)($row['approved_request_minutes'] ?? $row['request_minutes'] ?? 0));
        $range = leaveFormatRequestTimeRange($row);
        return 'OT หลังเลิกงาน ' . ($range !== '' ? $range . ' ' : '') . leaveFormatHourMinuteDuration($minutes);
    }
    $minutes = max(1, min(60, (int)($row['request_minutes'] ?? 60)));
    $label = $type === 'early_departure' ? 'ขอออกก่อน' : 'ขอมาสาย';
    return $label . ' ' . $minutes . ' นาที';
}

function leaveFormatHourMinuteDuration($minutes) {
    $minutes = max(0, (int)$minutes);
    $hours = intdiv($minutes, 60);
    $remaining = $minutes % 60;
    $parts = [];
    if ($hours > 0) {
        $parts[] = $hours . ' ชม.';
    }
    if ($remaining > 0 || !$parts) {
        $parts[] = $remaining . ' นาที';
    }
    return implode(' ', $parts);
}

function leaveEnsureSettingsTable(mysqli $mysqli) {
    $mysqli->query("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function leaveEnsurePoliciesTable(mysqli $mysqli) {
    $mysqli->query("CREATE TABLE IF NOT EXISTS leave_policies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        policy_name VARCHAR(150) NOT NULL,
        fiscal_year_start_month TINYINT UNSIGNED NOT NULL DEFAULT 10,
        leave_max_requests_per_year INT UNSIGNED NOT NULL DEFAULT 0,
        vacation_min_months_before_leave INT UNSIGNED NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_leave_policies_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $columns = [];
    $result = $mysqli->query("SHOW COLUMNS FROM leave_policies");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = true;
        }
    }
    if (!isset($columns['vacation_min_months_before_leave'])) {
        $mysqli->query("ALTER TABLE leave_policies ADD COLUMN vacation_min_months_before_leave INT UNSIGNED NOT NULL DEFAULT 0 AFTER leave_max_requests_per_year");
    }

    $count = 0;
    $result = $mysqli->query("SELECT COUNT(*) AS total FROM leave_policies");
    if ($result) {
        $row = $result->fetch_assoc();
        $count = (int)($row['total'] ?? 0);
    }

    if ($count === 0) {
        leaveEnsureSettingsTable($mysqli);
        $startMonth = 10;
        $requestLimit = 0;

        $settings = [];
        $result = $mysqli->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('leave_fiscal_year_start_month', 'leave_max_requests_per_year')");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }

        $configuredMonth = (int)($settings['leave_fiscal_year_start_month'] ?? 10);
        if ($configuredMonth >= 1 && $configuredMonth <= 12) {
            $startMonth = $configuredMonth;
        }
        $requestLimit = max(0, (int)($settings['leave_max_requests_per_year'] ?? 0));

        $stmt = $mysqli->prepare("INSERT INTO leave_policies (policy_name, fiscal_year_start_month, leave_max_requests_per_year, vacation_min_months_before_leave, is_active) VALUES (?, ?, ?, 0, 1)");
        if ($stmt) {
            $name = 'Default leave policy';
            $stmt->bind_param('sii', $name, $startMonth, $requestLimit);
            $stmt->execute();
            $stmt->close();
        }
    }
}

function leaveNormalizePolicyPayload(array $input) {
    $name = trim((string)($input['policy_name'] ?? ''));
    $month = (int)($input['fiscal_year_start_month'] ?? 10);
    $limit = max(0, (int)($input['leave_max_requests_per_year'] ?? 0));
    $vacationMinMonths = max(0, (int)($input['vacation_min_months_before_leave'] ?? 0));

    if ($name === '') {
        throw new InvalidArgumentException('Policy name is required');
    }
    if ($month < 1 || $month > 12) {
        throw new InvalidArgumentException('Invalid fiscal year start month');
    }

    return [
        'policy_name' => $name,
        'fiscal_year_start_month' => $month,
        'leave_max_requests_per_year' => $limit,
        'vacation_min_months_before_leave' => $vacationMinMonths,
    ];
}

function leaveFetchPolicies(mysqli $mysqli) {
    leaveEnsurePoliciesTable($mysqli);

    $result = $mysqli->query("SELECT id, policy_name, fiscal_year_start_month, leave_max_requests_per_year, vacation_min_months_before_leave, is_active, created_at, updated_at
                              FROM leave_policies
                              ORDER BY is_active DESC, id DESC");
    $policies = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['fiscal_year_start_month'] = (int)$row['fiscal_year_start_month'];
            $row['leave_max_requests_per_year'] = (int)$row['leave_max_requests_per_year'];
            $row['vacation_min_months_before_leave'] = (int)$row['vacation_min_months_before_leave'];
            $row['is_active'] = (int)$row['is_active'];
            $row['current_fiscal_year'] = leaveBuildFiscalYearRange($row['fiscal_year_start_month']);
            $policies[] = $row;
        }
    }

    return $policies;
}

function leaveGetActivePolicy(mysqli $mysqli) {
    leaveEnsurePoliciesTable($mysqli);

    $result = $mysqli->query("SELECT id, policy_name, fiscal_year_start_month, leave_max_requests_per_year, vacation_min_months_before_leave, is_active
                              FROM leave_policies
                              WHERE is_active = 1
                              ORDER BY id DESC
                              LIMIT 1");
    $row = $result ? $result->fetch_assoc() : null;

    if (!$row) {
        $result = $mysqli->query("SELECT id, policy_name, fiscal_year_start_month, leave_max_requests_per_year, vacation_min_months_before_leave, is_active
                                  FROM leave_policies
                                  ORDER BY id DESC
                                  LIMIT 1");
        $row = $result ? $result->fetch_assoc() : null;
        if ($row) {
            leaveActivatePolicy($mysqli, (int)$row['id']);
            $row['is_active'] = 1;
        }
    }

    if (!$row) {
        return [
            'id' => 0,
            'policy_name' => 'Default leave policy',
            'fiscal_year_start_month' => 10,
            'leave_max_requests_per_year' => 0,
            'vacation_min_months_before_leave' => 0,
            'is_active' => 1,
        ];
    }

    $row['id'] = (int)$row['id'];
    $row['fiscal_year_start_month'] = (int)$row['fiscal_year_start_month'];
    $row['leave_max_requests_per_year'] = (int)$row['leave_max_requests_per_year'];
    $row['vacation_min_months_before_leave'] = (int)$row['vacation_min_months_before_leave'];
    $row['is_active'] = (int)$row['is_active'];
    $row['current_fiscal_year'] = leaveBuildFiscalYearRange($row['fiscal_year_start_month']);

    return $row;
}

function leaveSavePolicy(mysqli $mysqli, array $input) {
    leaveEnsurePoliciesTable($mysqli);
    $payload = leaveNormalizePolicyPayload($input);
    $id = (int)($input['id'] ?? 0);
    $isActive = !empty($input['is_active']) ? 1 : 0;

    if ($id > 0) {
        $stmt = $mysqli->prepare("UPDATE leave_policies
                                  SET policy_name = ?, fiscal_year_start_month = ?, leave_max_requests_per_year = ?, vacation_min_months_before_leave = ?
                                  WHERE id = ?");
        if (!$stmt) {
            throw new RuntimeException('Cannot prepare leave policy update');
        }
        $stmt->bind_param('siiii', $payload['policy_name'], $payload['fiscal_year_start_month'], $payload['leave_max_requests_per_year'], $payload['vacation_min_months_before_leave'], $id);
        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error ?: 'Cannot save leave policy');
        }
        $stmt->close();
    } else {
        $stmt = $mysqli->prepare("INSERT INTO leave_policies (policy_name, fiscal_year_start_month, leave_max_requests_per_year, vacation_min_months_before_leave, is_active)
                                  VALUES (?, ?, ?, ?, 0)");
        if (!$stmt) {
            throw new RuntimeException('Cannot prepare leave policy insert');
        }
        $stmt->bind_param('siii', $payload['policy_name'], $payload['fiscal_year_start_month'], $payload['leave_max_requests_per_year'], $payload['vacation_min_months_before_leave']);
        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error ?: 'Cannot save leave policy');
        }
        $id = (int)$mysqli->insert_id;
        $stmt->close();
    }

    if ($isActive) {
        leaveActivatePolicy($mysqli, $id);
    }

    return leaveGetActivePolicy($mysqli);
}

function leaveActivatePolicy(mysqli $mysqli, $id) {
    leaveEnsurePoliciesTable($mysqli);
    $id = (int)$id;
    if ($id <= 0) {
        throw new InvalidArgumentException('Invalid leave policy');
    }

    $check = $mysqli->prepare("SELECT id FROM leave_policies WHERE id = ? LIMIT 1");
    if (!$check) {
        throw new RuntimeException('Cannot prepare leave policy lookup');
    }
    $check->bind_param('i', $id);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    $check->close();
    if (!$exists) {
        throw new RuntimeException('Leave policy not found');
    }

    $mysqli->begin_transaction();
    try {
        if (!$mysqli->query("UPDATE leave_policies SET is_active = 0")) {
            throw new RuntimeException($mysqli->error ?: 'Cannot deactivate leave policies');
        }
        $stmt = $mysqli->prepare("UPDATE leave_policies SET is_active = 1 WHERE id = ?");
        if (!$stmt) {
            throw new RuntimeException('Cannot prepare leave policy activation');
        }
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error ?: 'Cannot activate leave policy');
        }
        $stmt->close();
        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();
        throw $e;
    }
}

function leaveDeletePolicy(mysqli $mysqli, $id) {
    leaveEnsurePoliciesTable($mysqli);
    $id = (int)$id;
    if ($id <= 0) {
        throw new InvalidArgumentException('Invalid leave policy');
    }

    $stmt = $mysqli->prepare("SELECT is_active FROM leave_policies WHERE id = ? LIMIT 1");
    if (!$stmt) {
        throw new RuntimeException('Cannot prepare leave policy lookup');
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $policy = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$policy) {
        throw new RuntimeException('Leave policy not found');
    }
    if ((int)$policy['is_active'] === 1) {
        throw new RuntimeException('Cannot delete active leave policy');
    }

    $stmt = $mysqli->prepare("DELETE FROM leave_policies WHERE id = ?");
    if (!$stmt) {
        throw new RuntimeException('Cannot prepare leave policy delete');
    }
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error ?: 'Cannot delete leave policy');
    }
    $stmt->close();
}

function leaveGetFiscalYearStartMonth(mysqli $mysqli) {
    $policy = leaveGetActivePolicy($mysqli);
    return (int)$policy['fiscal_year_start_month'];
}

function leaveGetMaxRequestsPerFiscalYear(mysqli $mysqli) {
    $policy = leaveGetActivePolicy($mysqli);
    return max(0, (int)$policy['leave_max_requests_per_year']);
}

function leaveSetFiscalYearStartMonth(mysqli $mysqli, $month) {
    $month = (int)$month;
    if ($month < 1 || $month > 12) {
        throw new InvalidArgumentException('Invalid fiscal year start month');
    }

    leaveEnsureSettingsTable($mysqli);
    $key = 'leave_fiscal_year_start_month';
    $value = (string)$month;
    $stmt = $mysqli->prepare("INSERT INTO system_settings (setting_key, setting_value)
                              VALUES (?, ?)
                              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    if (!$stmt) {
        throw new RuntimeException('Cannot prepare fiscal year setting update');
    }

    $stmt->bind_param('ss', $key, $value);
    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error ?: 'Cannot save fiscal year setting');
    }
}

function leaveSetMaxRequestsPerFiscalYear(mysqli $mysqli, $limit) {
    $limit = max(0, (int)$limit);
    leaveEnsureSettingsTable($mysqli);

    $key = 'leave_max_requests_per_year';
    $value = (string)$limit;
    $stmt = $mysqli->prepare("INSERT INTO system_settings (setting_key, setting_value)
                              VALUES (?, ?)
                              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    if (!$stmt) {
        throw new RuntimeException('Cannot prepare leave request limit update');
    }

    $stmt->bind_param('ss', $key, $value);
    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error ?: 'Cannot save leave request limit');
    }
}

function leaveBuildFiscalYearRange($startMonth, $referenceDate = null) {
    $startMonth = (int)$startMonth;
    if ($startMonth < 1 || $startMonth > 12) {
        $startMonth = 10;
    }

    $reference = $referenceDate instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($referenceDate)
        : new DateTimeImmutable($referenceDate ?: 'today');

    $year = (int)$reference->format('Y');
    $month = (int)$reference->format('n');
    $startYear = $month >= $startMonth ? $year : $year - 1;
    $start = DateTimeImmutable::createFromFormat('!Y-n-j', $startYear . '-' . $startMonth . '-1');
    $end = $start->modify('+1 year')->modify('-1 day');

    return [
        'start_date' => $start->format('Y-m-d'),
        'end_date' => $end->format('Y-m-d'),
        'start_month' => $startMonth,
        'label_year' => (int)$end->format('Y'),
    ];
}

function leaveBuildUsageWarningStatus($usedDays, $requestLimit) {
    $requestLimit = (int)$requestLimit;
    if ($requestLimit <= 0) {
        return 'normal';
    }

    $requestPercent = (((float)$usedDays / $requestLimit) * 100);
    if ($requestPercent > 100) {
        return 'over';
    }
    if ($requestPercent >= 80) {
        return 'near';
    }
    return 'normal';
}

function leaveIsVacationLeaveType($typeName) {
    $name = mb_strtolower((string)$typeName, 'UTF-8');
    return strpos($name, 'พักผ่อน') !== false
        || strpos($name, 'พักร้อน') !== false
        || strpos($name, 'annual') !== false
        || strpos($name, 'vacation') !== false;
}

function leaveBuildVacationEligibilityStatus($employeeStartDate, $requestStartDate, $minMonths) {
    $minMonths = max(0, (int)$minMonths);
    if ($minMonths <= 0) {
        return [
            'eligible' => true,
            'required_months' => 0,
            'completed_months' => null,
            'eligible_date' => null,
        ];
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$employeeStartDate)
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$requestStartDate)) {
        return [
            'eligible' => false,
            'required_months' => $minMonths,
            'completed_months' => 0,
            'eligible_date' => null,
        ];
    }

    $start = new DateTimeImmutable((string)$employeeStartDate);
    $request = new DateTimeImmutable((string)$requestStartDate);
    if ($request < $start) {
        return [
            'eligible' => false,
            'required_months' => $minMonths,
            'completed_months' => 0,
            'eligible_date' => $start->modify('+' . $minMonths . ' months')->format('Y-m-d'),
        ];
    }

    $diff = $start->diff($request);
    $completedMonths = ($diff->y * 12) + $diff->m;
    $eligibleDate = $start->modify('+' . $minMonths . ' months')->format('Y-m-d');

    return [
        'eligible' => $completedMonths >= $minMonths,
        'required_months' => $minMonths,
        'completed_months' => $completedMonths,
        'eligible_date' => $eligibleDate,
    ];
}

function leaveApplyUsageLimitFields(array &$item, $usedDays, $limitDays, $remainingKey = 'remaining_days') {
    $usedDays = (float)$usedDays;
    $limitDays = (float)$limitDays;
    $remaining = null;
    $overLimitDays = 0.0;

    if ($limitDays > 0) {
        $remaining = $limitDays - $usedDays;
        if ($remaining < 0) {
            $overLimitDays = abs($remaining);
        }
    }

    $item[$remainingKey] = $remaining;
    $item['over_limit_days'] = $overLimitDays;
    $item['is_over_limit'] = $overLimitDays > 0;
}

function leaveFetchLeaveTypeLimits(mysqli $mysqli) {
    leaveEnsureLeaveTypeCalculationColumns($mysqli);
    $types = [];
    $result = $mysqli->query("SELECT id, type_name, days_per_year, calculation_unit, hours_per_day, hour_full_day_threshold, is_actual_leave FROM leave_types ORDER BY id ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ((int)($row['is_actual_leave'] ?? 1) !== 1) {
                continue;
            }

            $types[] = [
                'id' => (int)$row['id'],
                'type_name' => $row['type_name'],
                'days_per_year' => (float)$row['days_per_year'],
                'calculation_unit' => $row['calculation_unit'] ?? 'day',
                'hours_per_day' => (float)($row['hours_per_day'] ?? 8),
                'hour_full_day_threshold' => (float)($row['hour_full_day_threshold'] ?? 0),
                'is_actual_leave' => (int)($row['is_actual_leave'] ?? 1),
            ];
        }
    }

    return $types;
}

function leaveBuildUsageSummaryItems(array $leaveTypes, array $entries) {
    $items = [];
    foreach ($leaveTypes as $type) {
        $typeId = (int)($type['id'] ?? $type['leave_type_id'] ?? 0);
        if ($typeId <= 0) {
            continue;
        }

        $typeName = (string)($type['type_name'] ?? '');
        if ((int)($type['is_actual_leave'] ?? 1) !== 1) {
            continue;
        }

        $limitDays = (float)($type['days_per_year'] ?? $type['limit_days'] ?? 0);
        $items[$typeId] = [
            'leave_type_id' => $typeId,
            'type_name' => $typeName,
            'limit_days' => $limitDays,
            'request_limit' => $limitDays,
            'approved_days' => 0.0,
            'approved_requests' => 0,
            'pending_days' => 0.0,
            'pending_requests' => 0,
            'usage_percent' => 0.0,
            'request_usage_percent' => 0.0,
            'remaining_days' => $limitDays > 0 ? $limitDays : null,
            'remaining_requests' => $limitDays > 0 ? $limitDays : null,
            'over_limit_days' => 0.0,
            'is_over_limit' => false,
            'status' => 'normal',
            'entries' => [],
        ];
    }

    foreach ($entries as $entry) {
        $typeId = (int)($entry['leave_type_id'] ?? 0);
        if ($typeId <= 0) {
            continue;
        }

        if (!isset($items[$typeId])) {
            continue;
        }

        $days = (float)($entry['days'] ?? 0);
        $status = (string)($entry['status'] ?? '');
        if (in_array($status, ['approved', 'pending_cancel_hr'], true)) {
            $items[$typeId]['approved_days'] += $days;
            $items[$typeId]['approved_requests']++;
        } elseif (in_array($status, ['pending', 'pending_manager', 'pending_hr'], true)) {
            $items[$typeId]['pending_days'] += $days;
            $items[$typeId]['pending_requests']++;
        }

        $items[$typeId]['entries'][] = $entry;
    }

    foreach ($items as &$item) {
        $limitDays = (float)$item['limit_days'];
        if ($limitDays > 0) {
            $item['usage_percent'] = round(((float)$item['approved_days'] / $limitDays) * 100, 1);
            $item['request_usage_percent'] = $item['usage_percent'];
        }
        leaveApplyUsageLimitFields($item, $item['approved_days'], $limitDays, 'remaining_days');
        $item['remaining_requests'] = $item['remaining_days'];
        $item['status'] = leaveBuildUsageWarningStatus($item['approved_days'], $limitDays);
    }
    unset($item);

    return array_values($items);
}

function leaveFetchUsageSummary(mysqli $mysqli, $employeeId, $referenceDate = null) {
    $employeeId = (int)$employeeId;
    $policy = leaveGetActivePolicy($mysqli);
    $fiscal = leaveBuildFiscalYearRange($policy['fiscal_year_start_month'], $referenceDate);
    leaveEnsureRequestPartColumns($mysqli);
    $requestLimit = (int)$policy['leave_max_requests_per_year'];
    $summaryItem = [
        'leave_type_id' => 0,
        'type_name' => 'รวมการลาทั้งหมด',
        'limit_days' => 0.0,
        'request_limit' => $requestLimit,
        'approved_days' => 0.0,
        'approved_requests' => 0,
        'pending_days' => 0.0,
        'pending_requests' => 0,
        'usage_percent' => 0.0,
        'request_usage_percent' => 0.0,
        'remaining_days' => null,
        'remaining_requests' => $requestLimit > 0 ? $requestLimit : null,
        'over_limit_days' => 0.0,
        'is_over_limit' => false,
        'status' => 'normal',
        'entries' => [],
    ];

    $workDays = leaveFetchEmployeeWorkDays($mysqli, $employeeId);
    $holidays = leaveFetchCompanyHolidays($mysqli, $fiscal['start_date'], $fiscal['end_date']);
    $leaveTypes = leaveFetchLeaveTypeLimits($mysqli);
    $entries = [];

    $sql = "SELECT lr.status,
                   lr.leave_type_id,
                   lr.start_date,
                   lr.end_date,
                   lr.start_day_part,
                   lr.end_day_part,
                   lr.request_unit,
                   lr.time_request_type,
                   lr.request_minutes,
                   lr.total_days,
                   lt.type_name,
                   lt.days_per_year
            FROM leave_requests lr
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.employee_id = ?
              AND lr.status IN ('approved', 'pending_cancel_hr', 'pending', 'pending_manager', 'pending_hr')
              AND (lr.request_unit = 'day' OR (lr.request_unit = 'hour' AND lr.time_request_type IS NULL))
              AND lr.start_date <= ?
              AND lr.end_date >= ?
            ORDER BY lr.start_date ASC, lr.id ASC";
    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('iss', $employeeId, $fiscal['end_date'], $fiscal['start_date']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $isTimeRequest = ($row['request_unit'] ?? 'day') === 'hour' && !empty($row['time_request_type']);
            $countedDays = $isTimeRequest ? 0.0 : (float)$row['total_days'];
            if ($row['start_date'] < $fiscal['start_date'] || $row['end_date'] > $fiscal['end_date']) {
                $clippedStart = max($row['start_date'], $fiscal['start_date']);
                $clippedEnd = min($row['end_date'], $fiscal['end_date']);
                $summary = leaveBuildDateSummary(
                    $clippedStart,
                    $clippedEnd,
                    $clippedStart === $row['start_date'] ? $row['start_day_part'] : 'full',
                    $clippedEnd === $row['end_date'] ? $row['end_day_part'] : 'full',
                    $workDays,
                    $holidays
                );
                $countedDays = $summary['valid'] ? (float)$summary['total_days'] : 0.0;
            }

            if (in_array($row['status'], ['approved', 'pending_cancel_hr'], true)) {
                $summaryItem['approved_days'] += $countedDays;
                $summaryItem['approved_requests']++;
            } elseif (in_array($row['status'], ['pending', 'pending_manager', 'pending_hr'], true)) {
                $summaryItem['pending_days'] += $countedDays;
                $summaryItem['pending_requests']++;
            }

            $entry = [
                'leave_type_id' => (int)$row['leave_type_id'],
                'status' => $row['status'],
                'start_date' => max($row['start_date'], $fiscal['start_date']),
                'end_date' => min($row['end_date'], $fiscal['end_date']),
                'days' => $countedDays,
                'type_name' => $row['type_name'],
                'request_unit' => $row['request_unit'] ?? 'day',
                'time_request_type' => $row['time_request_type'] ?? null,
                'request_minutes' => (int)($row['request_minutes'] ?? 0),
                'duration_label' => leaveFormatRequestDuration([
                    'request_unit' => $row['request_unit'] ?? 'day',
                    'time_request_type' => $row['time_request_type'] ?? null,
                    'request_minutes' => (int)($row['request_minutes'] ?? 0),
                    'days' => $countedDays,
                ]),
            ];
            $summaryItem['entries'][] = $entry;
            $entries[] = $entry;
        }
        $stmt->close();
    }

    if ($requestLimit > 0) {
        $summaryItem['request_usage_percent'] = round(($summaryItem['approved_days'] / $requestLimit) * 100, 1);
    }
    leaveApplyUsageLimitFields($summaryItem, $summaryItem['approved_days'], $requestLimit, 'remaining_requests');
    $summaryItem['status'] = leaveBuildUsageWarningStatus($summaryItem['approved_days'], $requestLimit);

    return [
        'fiscal_year' => $fiscal,
        'policy' => $policy,
        'overall' => $summaryItem,
        'items' => leaveBuildUsageSummaryItems($leaveTypes, $entries),
    ];
}

function leaveBuildDateSummary($startDate, $endDate, $startPart, $endPart, $workDays, array $companyHolidays) {
    $startDate = normalizeGregorianDateInput($startDate);
    $endDate = normalizeGregorianDateInput($endDate);

    if ($startDate === '' || $endDate === '') {
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

function leaveExpandApprovedRequestForMonth(array $request, $month, $workDays, array $companyHolidays) {
    if (!preg_match('/^\d{4}-\d{2}$/', (string)$month)) {
        return [];
    }

    $monthStart = $month . '-01';
    $monthEnd = date('Y-m-t', strtotime($monthStart));
    $originalStart = normalizeGregorianDateInput($request['start_date'] ?? '');
    $originalEnd = normalizeGregorianDateInput($request['end_date'] ?? '');
    if ($originalStart === '' || $originalEnd === '' || $originalStart > $monthEnd || $originalEnd < $monthStart) {
        return [];
    }

    $clippedStart = max($originalStart, $monthStart);
    $clippedEnd = min($originalEnd, $monthEnd);
    $summary = leaveBuildDateSummary(
        $clippedStart,
        $clippedEnd,
        $clippedStart === $originalStart ? ($request['start_day_part'] ?? 'full') : 'full',
        $clippedEnd === $originalEnd ? ($request['end_day_part'] ?? 'full') : 'full',
        $workDays,
        $companyHolidays
    );
    if (empty($summary['valid'])) {
        return [];
    }

    $rows = [];
    foreach ($summary['included_dates'] as $included) {
        $rows[] = array_merge($request, [
            'leave_date' => $included['date'],
            'leave_days' => (float)$included['days'],
            'day_part' => $included['part'],
            'day_part_label' => $included['label'],
        ]);
    }
    return $rows;
}

function leaveCountApprovedReportRows(array $rows) {
    $employeeIds = [];
    $days = 0.0;
    foreach ($rows as $row) {
        $days += (float)($row['leave_days'] ?? 0);
        $employeeId = (int)($row['employee_id'] ?? 0);
        if ($employeeId > 0) {
            $employeeIds[$employeeId] = true;
        }
    }
    return [
        'total_rows' => count($rows),
        'total_days' => round($days, 2),
        'employee_count' => count($employeeIds),
    ];
}

function leaveFindConflictingLeaveDates(array $existingRequests, array $requestedDates) {
    $requestedDateSet = [];
    foreach ($requestedDates as $date) {
        if (is_array($date)) {
            $date = $date['date'] ?? '';
        }
        $date = (string)$date;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $requestedDateSet[$date] = true;
        }
    }

    if (empty($requestedDateSet)) {
        return [];
    }

    $activeStatuses = ['pending', 'pending_manager', 'pending_hr', 'approved', 'pending_cancel_hr'];
    $conflicts = [];
    foreach ($existingRequests as $request) {
        $requestUnit = $request['request_unit'] ?? 'day';
        if ($requestUnit !== 'day') {
            continue;
        }

        $status = (string)($request['status'] ?? '');
        if (!in_array($status, $activeStatuses, true)) {
            continue;
        }

        $startDate = (string)($request['start_date'] ?? '');
        $endDate = (string)($request['end_date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            continue;
        }

        $start = new DateTimeImmutable($startDate);
        $end = new DateTimeImmutable($endDate);
        if ($end < $start) {
            continue;
        }

        for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
            $workDate = $date->format('Y-m-d');
            if (isset($requestedDateSet[$workDate])) {
                $conflicts[$workDate] = true;
            }
        }
    }

    $dates = array_keys($conflicts);
    sort($dates);
    return $dates;
}

function leaveFetchConflictingLeaveDates(mysqli $mysqli, $employeeId, $startDate, $endDate, array $requestedDates) {
    $employeeId = (int)$employeeId;
    $requestedDates = array_values(array_filter($requestedDates, function ($date) {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date);
    }));
    if ($employeeId <= 0 || empty($requestedDates)) {
        return [];
    }

    leaveEnsureRequestPartColumns($mysqli);
    leaveEnsureTwoStepApprovalColumns($mysqli);

    $sql = "SELECT start_date, end_date, request_unit, status
            FROM leave_requests
            WHERE employee_id = ?
              AND request_unit = 'day'
              AND status IN ('pending', 'pending_manager', 'pending_hr', 'approved', 'pending_cancel_hr')
              AND start_date <= ?
              AND end_date >= ?
            ORDER BY start_date ASC, id ASC";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Cannot prepare leave conflict lookup');
    }
    $stmt->bind_param('iss', $employeeId, $endDate, $startDate);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return leaveFindConflictingLeaveDates($rows, $requestedDates);
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

    if (!isset($columns['request_unit'])) {
        $mysqli->query("ALTER TABLE leave_requests ADD COLUMN request_unit ENUM('day','hour') NOT NULL DEFAULT 'day' AFTER end_day_part");
    }

    if (!isset($columns['time_request_type'])) {
        $mysqli->query("ALTER TABLE leave_requests ADD COLUMN time_request_type ENUM('late_arrival','early_departure','overtime_after_work') NULL AFTER request_unit");
    } else {
        $mysqli->query("ALTER TABLE leave_requests MODIFY time_request_type ENUM('late_arrival','early_departure','overtime_after_work') NULL");
    }

    if (!isset($columns['request_minutes'])) {
        $mysqli->query("ALTER TABLE leave_requests ADD COLUMN request_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER time_request_type");
    }

    if (!isset($columns['approved_request_minutes'])) {
        $mysqli->query("ALTER TABLE leave_requests ADD COLUMN approved_request_minutes SMALLINT UNSIGNED NULL AFTER request_minutes");
    }

    if (!isset($columns['request_start_time'])) {
        $mysqli->query("ALTER TABLE leave_requests ADD COLUMN request_start_time TIME NULL AFTER approved_request_minutes");
    }

    if (!isset($columns['request_end_time'])) {
        $mysqli->query("ALTER TABLE leave_requests ADD COLUMN request_end_time TIME NULL AFTER request_start_time");
    }
}

function leaveEnsureTwoStepApprovalColumns(mysqli $mysqli) {
    leaveEnsureRequestPartColumns($mysqli);

    $columns = [];
    $result = $mysqli->query("SHOW COLUMNS FROM leave_requests");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = $row;
        }
    }

    proxyRequestEnsureAuditColumns($mysqli, 'leave_requests');

    if (isset($columns['status']) && strpos($columns['status']['Type'], 'pending_cancel_hr') === false) {
        $mysqli->query("ALTER TABLE leave_requests MODIFY status ENUM('pending','pending_manager','pending_hr','approved','pending_cancel_hr','rejected','cancelled') NOT NULL DEFAULT 'pending_manager'");
    }

    if (!isset($columns['manager_approver_id'])) {
        $mysqli->query("ALTER TABLE leave_requests ADD COLUMN manager_approver_id INT NULL AFTER approver_id");
    }

    if (!isset($columns['manager_approval_date'])) {
        $mysqli->query("ALTER TABLE leave_requests ADD COLUMN manager_approval_date DATETIME NULL AFTER manager_approver_id");
    }

    if (!isset($columns['hr_approver_id'])) {
        $mysqli->query("ALTER TABLE leave_requests ADD COLUMN hr_approver_id INT NULL AFTER manager_approval_date");
    }

    if (!isset($columns['hr_approval_date'])) {
        $mysqli->query("ALTER TABLE leave_requests ADD COLUMN hr_approval_date DATETIME NULL AFTER hr_approver_id");
    }

    if (!isset($columns['cancellation_reason'])) {
        $mysqli->query("ALTER TABLE leave_requests ADD COLUMN cancellation_reason TEXT NULL AFTER rejection_reason");
    }
}
