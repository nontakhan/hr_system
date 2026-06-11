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
    $defaults = [
        [
            'type_name' => 'ขอมาสาย',
            'description' => 'ขออนุญาตมาสายได้ไม่เกิน 1 ชม. และนับเป็น 1 ครั้งในปีงบประมาณ',
        ],
        [
            'type_name' => 'ขอออกก่อน',
            'description' => 'ขออนุญาตออกก่อนเวลาได้ไม่เกิน 1 ชม. และนับเป็น 1 ครั้งในปีงบประมาณ',
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
            continue;
        }

        $daysPerYear = 0;
        $requiresFile = 0;
        $insert = $mysqli->prepare("INSERT INTO leave_types (type_name, days_per_year, description, requires_file) VALUES (?, ?, ?, ?)");
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

function leaveBuildHourlyRequestPayload($timeRequestType) {
    $timeRequestType = in_array($timeRequestType, ['late_arrival', 'early_departure'], true)
        ? $timeRequestType
        : null;
    if ($timeRequestType === null) {
        throw new InvalidArgumentException('Invalid hourly request type');
    }

    return [
        'request_unit' => 'hour',
        'time_request_type' => $timeRequestType,
        'request_minutes' => 60,
        'total_days' => 0.0,
    ];
}

function leaveFormatRequestDuration(array $row) {
    $unit = $row['request_unit'] ?? 'day';
    if ($unit !== 'hour') {
        $days = (float)($row['days'] ?? $row['total_days'] ?? 0);
        return (floor($days) == $days ? (string)(int)$days : number_format($days, 1)) . ' วัน';
    }

    $type = $row['time_request_type'] ?? '';
    $label = $type === 'early_departure' ? 'ขอออกก่อนไม่เกิน 1 ชม.' : 'ขอมาสายไม่เกิน 1 ชม.';
    return $label;
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
        is_active TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_leave_policies_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

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

        $stmt = $mysqli->prepare("INSERT INTO leave_policies (policy_name, fiscal_year_start_month, leave_max_requests_per_year, is_active) VALUES (?, ?, ?, 1)");
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
    ];
}

function leaveFetchPolicies(mysqli $mysqli) {
    leaveEnsurePoliciesTable($mysqli);

    $result = $mysqli->query("SELECT id, policy_name, fiscal_year_start_month, leave_max_requests_per_year, is_active, created_at, updated_at
                              FROM leave_policies
                              ORDER BY is_active DESC, id DESC");
    $policies = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['fiscal_year_start_month'] = (int)$row['fiscal_year_start_month'];
            $row['leave_max_requests_per_year'] = (int)$row['leave_max_requests_per_year'];
            $row['is_active'] = (int)$row['is_active'];
            $row['current_fiscal_year'] = leaveBuildFiscalYearRange($row['fiscal_year_start_month']);
            $policies[] = $row;
        }
    }

    return $policies;
}

function leaveGetActivePolicy(mysqli $mysqli) {
    leaveEnsurePoliciesTable($mysqli);

    $result = $mysqli->query("SELECT id, policy_name, fiscal_year_start_month, leave_max_requests_per_year, is_active
                              FROM leave_policies
                              WHERE is_active = 1
                              ORDER BY id DESC
                              LIMIT 1");
    $row = $result ? $result->fetch_assoc() : null;

    if (!$row) {
        $result = $mysqli->query("SELECT id, policy_name, fiscal_year_start_month, leave_max_requests_per_year, is_active
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
            'is_active' => 1,
        ];
    }

    $row['id'] = (int)$row['id'];
    $row['fiscal_year_start_month'] = (int)$row['fiscal_year_start_month'];
    $row['leave_max_requests_per_year'] = (int)$row['leave_max_requests_per_year'];
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
                                  SET policy_name = ?, fiscal_year_start_month = ?, leave_max_requests_per_year = ?
                                  WHERE id = ?");
        if (!$stmt) {
            throw new RuntimeException('Cannot prepare leave policy update');
        }
        $stmt->bind_param('siii', $payload['policy_name'], $payload['fiscal_year_start_month'], $payload['leave_max_requests_per_year'], $id);
        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error ?: 'Cannot save leave policy');
        }
        $stmt->close();
    } else {
        $stmt = $mysqli->prepare("INSERT INTO leave_policies (policy_name, fiscal_year_start_month, leave_max_requests_per_year, is_active)
                                  VALUES (?, ?, ?, 0)");
        if (!$stmt) {
            throw new RuntimeException('Cannot prepare leave policy insert');
        }
        $stmt->bind_param('sii', $payload['policy_name'], $payload['fiscal_year_start_month'], $payload['leave_max_requests_per_year']);
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

function leaveBuildUsageWarningStatus($usedRequests, $requestLimit) {
    $requestLimit = (int)$requestLimit;
    if ($requestLimit <= 0) {
        return 'normal';
    }

    $requestPercent = (((int)$usedRequests / $requestLimit) * 100);
    if ($requestPercent > 100) {
        return 'over';
    }
    if ($requestPercent >= 80) {
        return 'near';
    }
    return 'normal';
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
        'status' => 'normal',
        'entries' => [],
    ];

    $workDays = leaveFetchEmployeeWorkDays($mysqli, $employeeId);
    $holidays = leaveFetchCompanyHolidays($mysqli, $fiscal['start_date'], $fiscal['end_date']);

    $sql = "SELECT lr.status,
                   lr.start_date,
                   lr.end_date,
                   lr.start_day_part,
                   lr.end_day_part,
                   lr.request_unit,
                   lr.time_request_type,
                   lr.request_minutes,
                   lr.total_days,
                   lt.type_name
            FROM leave_requests lr
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.employee_id = ?
              AND lr.status IN ('approved', 'pending', 'pending_manager', 'pending_hr')
              AND lr.start_date <= ?
              AND lr.end_date >= ?
            ORDER BY lr.start_date ASC, lr.id ASC";
    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('iss', $employeeId, $fiscal['end_date'], $fiscal['start_date']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $isHourlyRequest = ($row['request_unit'] ?? 'day') === 'hour';
            $countedDays = $isHourlyRequest ? 0.0 : (float)$row['total_days'];
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

            if ($row['status'] === 'approved') {
                $summaryItem['approved_days'] += $countedDays;
                $summaryItem['approved_requests']++;
            } elseif (in_array($row['status'], ['pending', 'pending_manager', 'pending_hr'], true)) {
                $summaryItem['pending_days'] += $countedDays;
                $summaryItem['pending_requests']++;
            }

            $summaryItem['entries'][] = [
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
        }
        $stmt->close();
    }

    if ($requestLimit > 0) {
        $summaryItem['request_usage_percent'] = round(($summaryItem['approved_requests'] / $requestLimit) * 100, 1);
        $summaryItem['remaining_requests'] = $requestLimit - $summaryItem['approved_requests'];
    }
    $summaryItem['status'] = leaveBuildUsageWarningStatus($summaryItem['approved_requests'], $requestLimit);

    return [
        'fiscal_year' => $fiscal,
        'policy' => $policy,
        'overall' => $summaryItem,
        'items' => [$summaryItem],
    ];
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

    if (!isset($columns['request_unit'])) {
        $mysqli->query("ALTER TABLE leave_requests ADD COLUMN request_unit ENUM('day','hour') NOT NULL DEFAULT 'day' AFTER end_day_part");
    }

    if (!isset($columns['time_request_type'])) {
        $mysqli->query("ALTER TABLE leave_requests ADD COLUMN time_request_type ENUM('late_arrival','early_departure') NULL AFTER request_unit");
    }

    if (!isset($columns['request_minutes'])) {
        $mysqli->query("ALTER TABLE leave_requests ADD COLUMN request_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER time_request_type");
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

    if (isset($columns['status']) && strpos($columns['status']['Type'], 'pending_manager') === false) {
        $mysqli->query("ALTER TABLE leave_requests MODIFY status ENUM('pending','pending_manager','pending_hr','approved','rejected','cancelled') NOT NULL DEFAULT 'pending_manager'");
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
}
