<?php

require_once __DIR__ . '/attendance_helpers.php';
require_once __DIR__ . '/query_helpers.php';
require_once __DIR__ . '/leave_helpers.php';
require_once __DIR__ . '/employee_shift_assignment_helpers.php';

function attendanceWarningFetchScopedEmployee(mysqli $mysqli, int $employeeId, string $role, array $scopes): array
{
    $scope = employeeWarningEmployeeScopeClause($role, $scopes, 'e');
    $sql = "SELECT e.id, e.citizen_id, e.first_name_th, e.last_name_th, e.company_id, e.branch_id,
                   p.position_name_th, b.branch_name_th, c.company_name_th,
                   ws.start_time, ws.end_time, ws.late_tolerance_mins, ws.work_days
            FROM employees e
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN branches b ON e.branch_id = b.id
            LEFT JOIN companies c ON e.company_id = c.id
            LEFT JOIN work_shifts ws ON e.default_shift_id = ws.id
            WHERE e.id = ? AND e.status IN ('active', 'probation')" . $scope['sql'] . " LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    employeeWarningBindParams($stmt, 'i' . $scope['types'], array_merge([$employeeId], $scope['params']));
    $stmt->execute();
    $employee = $stmt->get_result()->fetch_assoc();
    if (!$employee) {
        throw new InvalidArgumentException('Access Denied');
    }
    return $employee;
}

function attendanceWarningFetchShiftOverrides(mysqli $mysqli, int $employeeId, string $workDate): array
{
    $stmt = $mysqli->prepare("SELECT employee_id, day_of_week, start_time, end_time, late_tolerance_mins, effective_from, effective_to
                              FROM employee_shift_overrides
                              WHERE employee_id = ? AND is_active = 1
                                AND effective_from <= ?
                                AND (effective_to IS NULL OR effective_to = '0000-00-00' OR effective_to >= ?)
                              ORDER BY effective_from DESC, id DESC");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('iss', $employeeId, $workDate, $workDate);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function attendanceWarningFetchRecord(mysqli $mysqli, int $employeeId, string $workDate): array
{
    $stmt = $mysqli->prepare("SELECT check_in, check_out FROM attendance_records WHERE employee_id = ? AND work_date = ? LIMIT 1");
    $stmt->bind_param('is', $employeeId, $workDate);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc() ?: ['check_in' => null, 'check_out' => null];

    $override = null;
    $overrideStmt = $mysqli->prepare("SELECT override_check_in, override_check_out, reason, created_at, updated_at
                                      FROM attendance_record_overrides WHERE employee_id = ? AND work_date = ? LIMIT 1");
    if ($overrideStmt) {
        $overrideStmt->bind_param('is', $employeeId, $workDate);
        $overrideStmt->execute();
        $override = $overrideStmt->get_result()->fetch_assoc() ?: null;
    }
    return attendanceApplyRecordOverride($record, $override);
}

function attendanceWarningFetchHolidayMap(mysqli $mysqli, string $workDate): array
{
    $stmt = $mysqli->prepare("SELECT holiday_name FROM company_holidays WHERE holiday_date = ? LIMIT 1");
    $stmt->bind_param('s', $workDate);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? [$workDate => $row['holiday_name']] : [];
}

function attendanceWarningFetchLeaveMap(mysqli $mysqli, int $employeeId, string $workDate): array
{
    $month = substr($workDate, 0, 7);
    $stmt = $mysqli->prepare("SELECT lr.start_date, lr.end_date, lt.type_name
                              FROM leave_requests lr
                              JOIN leave_types lt ON lr.leave_type_id = lt.id
                              WHERE lr.employee_id = ?
                                AND lr.status IN ('approved','pending_cancel_hr')
                                AND (lr.request_unit = 'day' OR (lr.request_unit = 'hour' AND lr.time_request_type IS NULL AND COALESCE(lr.total_days, 0) >= 1))
                                AND lr.start_date <= ? AND lr.end_date >= ?");
    $stmt->bind_param('iss', $employeeId, $workDate, $workDate);
    $stmt->execute();
    return attendanceBuildApprovedLeaveMap($stmt->get_result()->fetch_all(MYSQLI_ASSOC), $month);
}

function attendanceWarningFetchTrainingMap(mysqli $mysqli, int $employeeId, string $workDate): array
{
    $month = substr($workDate, 0, 7);
    $stmt = $mysqli->prepare("SELECT tr.start_date, tr.end_date, tr.course_name, at.type_name AS activity_type_name
                              FROM training_requests tr
                              LEFT JOIN activity_types at ON tr.activity_type_id = at.id
                              WHERE tr.employee_id = ? AND tr.status IN ('approved','pending_cancel_hr')
                                AND tr.start_date <= ? AND tr.end_date >= ?");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('iss', $employeeId, $workDate, $workDate);
    $stmt->execute();
    return attendanceBuildApprovedTrainingMap($stmt->get_result()->fetch_all(MYSQLI_ASSOC), $month);
}

function attendanceWarningFetchDaySwapType(mysqli $mysqli, int $employeeId, string $workDate): ?string
{
    $stmt = $mysqli->prepare("SELECT requester_employee_id, target_employee_id, requester_date, target_date
                              FROM day_swap_requests
                              WHERE status IN ('approved','pending_cancel_hr')
                                AND (requester_employee_id = ? OR target_employee_id = ?)
                                AND (? IN (requester_date, target_date))");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('iis', $employeeId, $employeeId, $workDate);
    $stmt->execute();
    $map = attendanceBuildApprovedDaySwapMap($stmt->get_result()->fetch_all(MYSQLI_ASSOC), $employeeId, substr($workDate, 0, 7));
    return $map[$workDate] ?? null;
}

function attendanceWarningFetchApprovedMinutes(mysqli $mysqli, int $employeeId, string $workDate): array
{
    $stmt = $mysqli->prepare("SELECT time_request_type,
                                     CASE WHEN COALESCE(approved_request_minutes, 0) > 0 THEN approved_request_minutes ELSE COALESCE(request_minutes, 0) END AS effective_minutes
                              FROM leave_requests
                              WHERE employee_id = ? AND start_date = ? AND status IN ('approved','pending_cancel_hr')
                                AND time_request_type IN ('late_arrival', 'early_departure')");
    $stmt->bind_param('is', $employeeId, $workDate);
    $stmt->execute();
    $minutes = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $type = (string)$row['time_request_type'];
        $minutes[$type] = ($minutes[$type] ?? 0) + max(0, (int)$row['effective_minutes']);
    }
    return $minutes;
}

function attendanceWarningLoadMonth(mysqli $mysqli, int $employeeId, string $month, string $role, array $scopes, array &$cache): array
{
    $scopeKey = json_encode([$role, $scopes, $employeeId]);
    $employee = hrRememberDataset($cache, 'employee:' . $scopeKey,
        fn() => attendanceWarningFetchScopedEmployee($mysqli, $employeeId, $role, $scopes));
    return hrRememberDataset($cache, 'attendance:' . $scopeKey . ':' . $month, function () use ($mysqli, $employeeId, $month, $employee, &$cache) {
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));
        $assignments = employeeShiftAssignmentsFetchForMonth($mysqli, $employeeId, $month);
        $shiftOverrides = hrQueryRows($mysqli, "SELECT employee_id, day_of_week, start_time, end_time, late_tolerance_mins, effective_from, effective_to
            FROM employee_shift_overrides WHERE employee_id = ? AND is_active = 1
              AND effective_from <= ? AND (effective_to IS NULL OR effective_to = '0000-00-00' OR effective_to >= ?)
            ORDER BY effective_from DESC, id DESC", 'iss', [$employeeId, $end, $start]);
        $records = array_column(hrQueryRows($mysqli, "SELECT work_date, check_in, check_out FROM attendance_records
            WHERE employee_id = ? AND work_date BETWEEN ? AND ?", 'iss', [$employeeId, $start, $end]), null, 'work_date');
        $overrides = array_column(hrQueryRows($mysqli, "SELECT work_date, override_check_in, override_check_out, reason, created_at, updated_at
            FROM attendance_record_overrides WHERE employee_id = ? AND work_date BETWEEN ? AND ?", 'iss', [$employeeId, $start, $end]), null, 'work_date');
        $holidays = hrRememberDataset($cache, 'holidays:' . $month,
            fn() => leaveFetchCompanyHolidays($mysqli, $start, $end));
        $leaves = attendanceBuildApprovedLeaveMap(hrQueryRows($mysqli, "SELECT lr.start_date, lr.end_date, lt.type_name
            FROM leave_requests lr JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.employee_id = ? AND lr.status IN ('approved','pending_cancel_hr')
              AND (lr.request_unit = 'day' OR (lr.request_unit = 'hour' AND lr.time_request_type IS NULL AND COALESCE(lr.total_days, 0) >= 1))
              AND lr.start_date <= ? AND lr.end_date >= ?", 'iss', [$employeeId, $end, $start]), $month);
        $trainings = attendanceBuildApprovedTrainingMap(hrQueryRows($mysqli, "SELECT tr.start_date, tr.end_date, tr.course_name, at.type_name AS activity_type_name
            FROM training_requests tr LEFT JOIN activity_types at ON tr.activity_type_id = at.id
            WHERE tr.employee_id = ? AND tr.status IN ('approved','pending_cancel_hr')
              AND tr.start_date <= ? AND tr.end_date >= ?", 'iss', [$employeeId, $end, $start]), $month);
        $swaps = attendanceBuildApprovedDaySwapMap(hrQueryRows($mysqli, "SELECT requester_employee_id, target_employee_id, requester_date, target_date
            FROM day_swap_requests WHERE status IN ('approved','pending_cancel_hr')
              AND (requester_employee_id = ? OR target_employee_id = ?)
              AND ((requester_date BETWEEN ? AND ?) OR (target_date BETWEEN ? AND ?))",
            'iissss', [$employeeId, $employeeId, $start, $end, $start, $end]), $employeeId, $month);
        $minutes = [];
        foreach (hrQueryRows($mysqli, "SELECT start_date, time_request_type,
            CASE WHEN COALESCE(approved_request_minutes, 0) > 0 THEN approved_request_minutes ELSE COALESCE(request_minutes, 0) END AS effective_minutes
            FROM leave_requests WHERE employee_id = ? AND start_date BETWEEN ? AND ? AND status IN ('approved','pending_cancel_hr')
              AND time_request_type IN ('late_arrival','early_departure')", 'iss', [$employeeId, $start, $end]) as $row) {
            $date = $row['start_date']; $type = $row['time_request_type'];
            $minutes[$date][$type] = ($minutes[$date][$type] ?? 0) + max(0, (int)$row['effective_minutes']);
        }
        return compact('employee','assignments','shiftOverrides','records','overrides','holidays','leaves','trainings','swaps','minutes');
    });
}

function attendanceWarningResolveContext(mysqli $mysqli, int $employeeId, string $workDate, string $role, array $scopes, ?array &$cache = null): array
{
    $cache ??= [];
    $data = attendanceWarningLoadMonth($mysqli, $employeeId, substr($workDate, 0, 7), $role, $scopes, $cache);
    $employee = $data['employee'];
    $baseShift = [
        'start_time' => $employee['start_time'] ?? null,
        'end_time' => $employee['end_time'] ?? null,
        'late_tolerance_mins' => $employee['late_tolerance_mins'] ?? 0,
        'work_days' => $employee['work_days'] ?? '',
    ];
    $shift = employeeShiftAssignmentsResolveForDate($data['assignments'], $baseShift, $workDate);
    $shift = attendanceResolveShiftForDate($shift, $data['shiftOverrides'], $workDate);
    if (isset($data['swaps'][$workDate])) $shift = attendanceApplyDayTypeOverride($shift, $workDate, $data['swaps'][$workDate]);
    return [
        'employee' => $employee,
        'record' => attendanceApplyRecordOverride($data['records'][$workDate] ?? ['check_in'=>null,'check_out'=>null], $data['overrides'][$workDate] ?? null),
        'shift' => $shift,
        'holiday_map' => isset($data['holidays'][$workDate]) ? [$workDate => $data['holidays'][$workDate]] : [],
        'leave_map' => $data['leaves'],
        'training_map' => $data['trainings'],
        'approved_minutes' => $data['minutes'][$workDate] ?? [],
    ];
}

function attendanceResolveMissingWarningSource(mysqli $mysqli, int $employeeId, string $workDate, string $role, array $scopes, ?array &$cache = null): array
{
    $context = attendanceWarningResolveContext($mysqli, $employeeId, $workDate, $role, $scopes, $cache);
    $status = attendanceEvaluateStatus(
        $workDate,
        $context['record']['check_in'],
        $context['record']['check_out'],
        $context['shift'],
        $context['holiday_map'],
        $context['leave_map'],
        $context['training_map']
    );
    if (!in_array($status['status'], attendanceMissingScanStatuses(), true)) {
        throw new InvalidArgumentException('Warning source event no longer exists');
    }
    $employee = $context['employee'];
    return [
        'employee_id' => $employeeId,
        'employee_name' => trim(($employee['first_name_th'] ?? '') . ' ' . ($employee['last_name_th'] ?? '')),
        'event_date' => $workDate,
        'event_label' => (string)$status['label'],
        'generated_detail' => sprintf(
            '%s วันที่ %s เวลาเข้า %s เวลาออก %s',
            $status['label'],
            $workDate,
            $context['record']['check_in'] ?: '-',
            $context['record']['check_out'] ?: '-'
        ),
    ];
}

function attendanceResolveLateEarlyWarningSource(mysqli $mysqli, int $employeeId, string $workDate, string $role, array $scopes, ?array &$cache = null): array
{
    $context = attendanceWarningResolveContext($mysqli, $employeeId, $workDate, $role, $scopes, $cache);
    if ($context['holiday_map'] || isset($context['leave_map'][$workDate]) || isset($context['training_map'][$workDate])) {
        throw new InvalidArgumentException('Warning source event no longer exists');
    }
    $incident = attendanceCalculateLateEarlyIncident(
        $workDate,
        $context['record']['check_in'],
        $context['record']['check_out'],
        $context['shift'],
        $context['approved_minutes']
    );
    if ($incident === null) {
        throw new InvalidArgumentException('Warning source event no longer exists');
    }
    $labels = [];
    if ($incident['late_minutes'] > 0) $labels[] = 'มาสาย ' . $incident['late_minutes'] . ' นาที';
    if ($incident['early_minutes'] > 0) $labels[] = 'ออกก่อน ' . $incident['early_minutes'] . ' นาที';
    $employee = $context['employee'];
    return [
        'employee_id' => $employeeId,
        'employee_name' => trim(($employee['first_name_th'] ?? '') . ' ' . ($employee['last_name_th'] ?? '')),
        'event_date' => $workDate,
        'event_label' => implode(' / ', $labels),
        'generated_detail' => sprintf(
            '%s วันที่ %s เวลาเข้า %s เวลาออก %s',
            implode(' / ', $labels),
            $workDate,
            $context['record']['check_in'] ?: '-',
            $context['record']['check_out'] ?: '-'
        ),
    ];
}
