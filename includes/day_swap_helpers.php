<?php

require_once __DIR__ . '/proxy_request_helpers.php';

function daySwapEnsureTable($mysqli) {
    $mysqli->query("CREATE TABLE IF NOT EXISTS day_swap_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        requester_employee_id INT NOT NULL,
        target_employee_id INT NOT NULL,
        requester_date DATE NOT NULL,
        target_date DATE NOT NULL,
        reason TEXT NOT NULL,
        status ENUM('pending','pending_manager','pending_hr','approved','pending_cancel_hr','rejected','cancelled') NOT NULL DEFAULT 'pending_manager',
        approver_id INT NULL,
        approval_date DATETIME NULL,
        rejection_reason TEXT NULL,
        cancellation_reason TEXT NULL,
        cancelled_by_user_id INT NULL,
        cancelled_by_employee_id INT NULL,
        cancelled_by_role VARCHAR(30) NULL,
        cancelled_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_day_swap_requester (requester_employee_id, status),
        INDEX idx_day_swap_target (target_employee_id, status),
        INDEX idx_day_swap_dates (requester_date, target_date),
        CONSTRAINT fk_day_swap_requester FOREIGN KEY (requester_employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        CONSTRAINT fk_day_swap_target FOREIGN KEY (target_employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        CONSTRAINT fk_day_swap_approver FOREIGN KEY (approver_id) REFERENCES employees(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $columns = [];
    $result = $mysqli->query("SHOW COLUMNS FROM day_swap_requests");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = $row;
        }
    }
    proxyRequestEnsureAuditColumns($mysqli, 'day_swap_requests');

    if (isset($columns['status']) && strpos($columns['status']['Type'], 'pending_cancel_hr') === false) {
        $mysqli->query("ALTER TABLE day_swap_requests MODIFY status ENUM('pending','pending_manager','pending_hr','approved','pending_cancel_hr','rejected','cancelled') NOT NULL DEFAULT 'pending_manager'");
    }
    if (!isset($columns['manager_approver_id'])) {
        $mysqli->query("ALTER TABLE day_swap_requests ADD COLUMN manager_approver_id INT NULL AFTER approver_id");
    }
    if (!isset($columns['manager_approval_date'])) {
        $mysqli->query("ALTER TABLE day_swap_requests ADD COLUMN manager_approval_date DATETIME NULL AFTER manager_approver_id");
    }
    if (!isset($columns['hr_approver_id'])) {
        $mysqli->query("ALTER TABLE day_swap_requests ADD COLUMN hr_approver_id INT NULL AFTER manager_approval_date");
    }
    if (!isset($columns['hr_approval_date'])) {
        $mysqli->query("ALTER TABLE day_swap_requests ADD COLUMN hr_approval_date DATETIME NULL AFTER hr_approver_id");
    }
    if (!isset($columns['cancellation_reason'])) {
        $mysqli->query("ALTER TABLE day_swap_requests ADD COLUMN cancellation_reason TEXT NULL AFTER rejection_reason");
    }
    if (!isset($columns['cancelled_by_user_id'])) {
        $mysqli->query("ALTER TABLE day_swap_requests ADD COLUMN cancelled_by_user_id INT NULL AFTER cancellation_reason");
    }
    if (!isset($columns['cancelled_by_employee_id'])) {
        $mysqli->query("ALTER TABLE day_swap_requests ADD COLUMN cancelled_by_employee_id INT NULL AFTER cancelled_by_user_id");
    }
    if (!isset($columns['cancelled_by_role'])) {
        $mysqli->query("ALTER TABLE day_swap_requests ADD COLUMN cancelled_by_role VARCHAR(30) NULL AFTER cancelled_by_employee_id");
    }
    if (!isset($columns['cancelled_at'])) {
        $mysqli->query("ALTER TABLE day_swap_requests ADD COLUMN cancelled_at DATETIME NULL AFTER cancelled_by_role");
    }
}

function daySwapFetchEmployee($mysqli, $employeeId) {
    $stmt = $mysqli->prepare("SELECT e.id, e.first_name_th, e.last_name_th, e.citizen_id, e.company_id, e.supervisor_id,
                                     ws.start_time, ws.end_time, ws.late_tolerance_mins, ws.work_days
                              FROM employees e
                              LEFT JOIN work_shifts ws ON e.default_shift_id = ws.id
                              WHERE e.id = ? AND e.status IN ('active', 'probation')");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function daySwapFetchShiftOverridesForMonth($mysqli, $employeeId, $month) {
    $start = $month . '-01';
    $end = (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');
    $stmt = $mysqli->prepare("SELECT day_of_week, start_time, end_time, late_tolerance_mins, effective_from, effective_to
                              FROM employee_shift_overrides
                              WHERE employee_id = ?
                                AND is_active = 1
                                AND effective_from <= ?
                                AND (effective_to IS NULL OR effective_to = '0000-00-00' OR effective_to >= ?)
                              ORDER BY effective_from DESC, id DESC");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('iss', $employeeId, $end, $start);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function daySwapFetchCompanyHolidaysForMonth($mysqli, $month) {
    $start = $month . '-01';
    $end = (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');
    $stmt = $mysqli->prepare("SELECT holiday_date, holiday_name FROM company_holidays WHERE holiday_date BETWEEN ? AND ?");
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $holidays = [];
    foreach ($rows as $row) {
        $holidays[$row['holiday_date']] = $row['holiday_name'];
    }
    return $holidays;
}

function daySwapFetchApprovedRowsForMonth($mysqli, $employeeId, $month) {
    daySwapEnsureTable($mysqli);
    $start = $month . '-01';
    $end = (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');
    $stmt = $mysqli->prepare("SELECT requester_employee_id, target_employee_id, requester_date, target_date
                              FROM day_swap_requests
                              WHERE status IN ('approved','pending_cancel_hr')
                                AND (requester_employee_id = ? OR target_employee_id = ?)
                                AND ((requester_date BETWEEN ? AND ?) OR (target_date BETWEEN ? AND ?))");
    $stmt->bind_param('iissss', $employeeId, $employeeId, $start, $end, $start, $end);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function daySwapBuildHolidayOptions($mysqli, $employeeId, $month) {
    $employee = daySwapFetchEmployee($mysqli, $employeeId);
    if (!$employee) {
        return [];
    }

    $shift = [
        'start_time' => $employee['start_time'],
        'end_time' => $employee['end_time'],
        'late_tolerance_mins' => $employee['late_tolerance_mins'],
        'work_days' => $employee['work_days'],
    ];
    $shiftOverrides = daySwapFetchShiftOverridesForMonth($mysqli, $employeeId, $month);
    $approvedSwaps = attendanceBuildApprovedDaySwapMap(daySwapFetchApprovedRowsForMonth($mysqli, $employeeId, $month), $employeeId, $month);
    $companyHolidays = daySwapFetchCompanyHolidaysForMonth($mysqli, $month);

    $start = new DateTimeImmutable($month . '-01');
    $end = $start->modify('last day of this month');
    $days = [];

    for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
        $workDate = $date->format('Y-m-d');
        if (isset($companyHolidays[$workDate])) {
            continue;
        }

        $effectiveShift = attendanceResolveShiftForDate($shift, $shiftOverrides, $workDate);
        if (isset($approvedSwaps[$workDate])) {
            $effectiveShift = attendanceApplyDayTypeOverride($effectiveShift, $workDate, $approvedSwaps[$workDate]);
        }

        $status = attendanceEvaluateStatus($workDate, null, null, $effectiveShift, [], []);
        if ($status['status'] === 'holiday') {
            $days[] = [
                'date' => $workDate,
                'day_name' => $date->format('D'),
                'label' => $date->format('d/m/Y'),
            ];
        }
    }

    return $days;
}
