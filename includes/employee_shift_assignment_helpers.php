<?php

function employeeShiftAssignmentsEnsureTable(mysqli $mysqli): void
{
    $sql = "CREATE TABLE IF NOT EXISTS employee_shift_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        shift_id INT NOT NULL,
        effective_from DATE NOT NULL,
        effective_to DATE NULL,
        reason TEXT NULL,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_employee_shift_assignments_employee_dates (employee_id, effective_from, effective_to),
        KEY idx_employee_shift_assignments_shift (shift_id)
    )";
    if (!$mysqli->query($sql)) {
        throw new Exception('Create employee shift assignment table failed: ' . $mysqli->error);
    }
    employeeShiftAssignmentsBackfillCurrentDefaults($mysqli);
}

function employeeShiftAssignmentsBackfillCurrentDefaults(mysqli $mysqli): void
{
    $sql = "INSERT INTO employee_shift_assignments
        (employee_id, shift_id, effective_from, effective_to, reason, created_by)
        SELECT e.id,
               e.default_shift_id,
               COALESCE(NULLIF(e.start_date, '0000-00-00'), CURDATE()),
               NULL,
               'Initial shift assignment backfill',
               NULL
        FROM employees e
        WHERE e.default_shift_id IS NOT NULL
          AND e.default_shift_id > 0
          AND NOT EXISTS (
              SELECT 1
              FROM employee_shift_assignments existing
              WHERE existing.employee_id = e.id
              LIMIT 1
          )";
    if (!$mysqli->query($sql)) {
        throw new Exception('Backfill employee shift assignments failed: ' . $mysqli->error);
    }
}

function employeeShiftAssignmentsNormalizeDate($value, string $fieldName): string
{
    $date = trim((string)$value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException("Invalid {$fieldName}");
    }
    return $date;
}

function employeeShiftAssignmentsPreviousDate(string $date): string
{
    return (new DateTimeImmutable($date))->modify('-1 day')->format('Y-m-d');
}

function employeeShiftAssignmentsFetchHistory(mysqli $mysqli, int $employeeId): array
{
    employeeShiftAssignmentsEnsureTable($mysqli);
    $stmt = $mysqli->prepare("SELECT esa.id, esa.employee_id, esa.shift_id, esa.effective_from, esa.effective_to,
                                     esa.reason, esa.created_at,
                                     ws.shift_name, ws.start_time, ws.end_time, ws.late_tolerance_mins, ws.work_days
                              FROM employee_shift_assignments esa
                              JOIN work_shifts ws ON esa.shift_id = ws.id
                              WHERE esa.employee_id = ?
                              ORDER BY esa.effective_from DESC, esa.id DESC");
    if (!$stmt) {
        throw new Exception('Prepare shift assignment history failed: ' . $mysqli->error);
    }
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function employeeShiftAssignmentsFetchForMonth(mysqli $mysqli, int $employeeId, string $month): array
{
    employeeShiftAssignmentsEnsureTable($mysqli);
    $start = $month . '-01';
    $end = (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');
    $stmt = $mysqli->prepare("SELECT esa.shift_id, esa.effective_from, esa.effective_to,
                                     ws.start_time, ws.end_time, ws.late_tolerance_mins, ws.work_days
                              FROM employee_shift_assignments esa
                              JOIN work_shifts ws ON esa.shift_id = ws.id
                              WHERE esa.employee_id = ?
                                AND esa.effective_from <= ?
                                AND (esa.effective_to IS NULL OR esa.effective_to = '0000-00-00' OR esa.effective_to >= ?)
                              ORDER BY esa.effective_from DESC, esa.id DESC");
    if (!$stmt) {
        throw new Exception('Prepare monthly shift assignments failed: ' . $mysqli->error);
    }
    $stmt->bind_param('iss', $employeeId, $end, $start);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function employeeShiftAssignmentsResolveForDate(array $assignments, array $fallbackShift, string $workDate): array
{
    foreach ($assignments as $assignment) {
        $effectiveFrom = (string)($assignment['effective_from'] ?? '');
        $effectiveTo = (string)($assignment['effective_to'] ?? '');
        if ($effectiveFrom !== '' && $workDate < $effectiveFrom) {
            continue;
        }
        if ($effectiveTo !== '' && $effectiveTo !== '0000-00-00' && $workDate > $effectiveTo) {
            continue;
        }
        return [
            'start_time' => $assignment['start_time'] ?? $fallbackShift['start_time'] ?? null,
            'end_time' => $assignment['end_time'] ?? $fallbackShift['end_time'] ?? null,
            'late_tolerance_mins' => $assignment['late_tolerance_mins'] ?? $fallbackShift['late_tolerance_mins'] ?? 0,
            'work_days' => $assignment['work_days'] ?? $fallbackShift['work_days'] ?? '',
        ];
    }

    return $fallbackShift;
}

function employeeShiftAssignmentsBootstrapIfEmpty(
    mysqli $mysqli,
    int $employeeId,
    int $legacyShiftId,
    string $legacyStartDate,
    string $newEffectiveFrom,
    ?int $createdBy
): void {
    $stmt = $mysqli->prepare("SELECT id FROM employee_shift_assignments WHERE employee_id = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception('Prepare shift assignment bootstrap check failed: ' . $mysqli->error);
    }
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0 || $legacyShiftId <= 0) {
        return;
    }

    $effectiveTo = $legacyStartDate < $newEffectiveFrom ? employeeShiftAssignmentsPreviousDate($newEffectiveFrom) : null;
    $reason = 'Legacy shift assignment';
    $insert = $mysqli->prepare("INSERT INTO employee_shift_assignments
        (employee_id, shift_id, effective_from, effective_to, reason, created_by)
        VALUES (?, ?, ?, ?, ?, ?)");
    if (!$insert) {
        throw new Exception('Prepare legacy shift assignment insert failed: ' . $mysqli->error);
    }
    $insert->bind_param('iisssi', $employeeId, $legacyShiftId, $legacyStartDate, $effectiveTo, $reason, $createdBy);
    if (!$insert->execute()) {
        throw new Exception('Insert legacy shift assignment failed: ' . $insert->error);
    }
}

function employeeShiftAssignmentsSyncCurrent(
    mysqli $mysqli,
    int $employeeId,
    int $shiftId,
    string $effectiveFrom,
    string $reason = '',
    ?int $createdBy = null,
    ?int $legacyShiftId = null,
    ?string $legacyStartDate = null
): void {
    employeeShiftAssignmentsEnsureTable($mysqli);
    if ($employeeId <= 0 || $shiftId <= 0) {
        throw new InvalidArgumentException('Invalid employee shift assignment');
    }
    $effectiveFrom = employeeShiftAssignmentsNormalizeDate($effectiveFrom, 'shift effective date');
    $legacyStartDate = employeeShiftAssignmentsNormalizeDate($legacyStartDate ?: $effectiveFrom, 'legacy shift start date');
    $legacyShiftId = $legacyShiftId !== null ? (int)$legacyShiftId : $shiftId;
    $reason = trim($reason) !== '' ? trim($reason) : 'Shift assignment updated';

    employeeShiftAssignmentsBootstrapIfEmpty($mysqli, $employeeId, $legacyShiftId, $legacyStartDate, $effectiveFrom, $createdBy);

    $same = $mysqli->prepare("SELECT id FROM employee_shift_assignments
                              WHERE employee_id = ?
                                AND shift_id = ?
                                AND effective_from = ?
                                AND (effective_to IS NULL OR effective_to = '0000-00-00')
                              LIMIT 1");
    if (!$same) {
        throw new Exception('Prepare duplicate shift assignment check failed: ' . $mysqli->error);
    }
    $same->bind_param('iis', $employeeId, $shiftId, $effectiveFrom);
    $same->execute();
    if ($same->get_result()->num_rows > 0) {
        return;
    }

    $previousDate = employeeShiftAssignmentsPreviousDate($effectiveFrom);
    $close = $mysqli->prepare("UPDATE employee_shift_assignments
                               SET effective_to = ?
                               WHERE employee_id = ?
                                 AND effective_from < ?
                                 AND (effective_to IS NULL OR effective_to = '0000-00-00' OR effective_to >= ?)");
    if (!$close) {
        throw new Exception('Prepare close shift assignments failed: ' . $mysqli->error);
    }
    $close->bind_param('siss', $previousDate, $employeeId, $effectiveFrom, $effectiveFrom);
    if (!$close->execute()) {
        throw new Exception('Close shift assignments failed: ' . $close->error);
    }

    $deleteFuture = $mysqli->prepare("DELETE FROM employee_shift_assignments WHERE employee_id = ? AND effective_from >= ?");
    if (!$deleteFuture) {
        throw new Exception('Prepare future shift assignment cleanup failed: ' . $mysqli->error);
    }
    $deleteFuture->bind_param('is', $employeeId, $effectiveFrom);
    if (!$deleteFuture->execute()) {
        throw new Exception('Delete future shift assignments failed: ' . $deleteFuture->error);
    }

    $insert = $mysqli->prepare("INSERT INTO employee_shift_assignments
        (employee_id, shift_id, effective_from, effective_to, reason, created_by)
        VALUES (?, ?, ?, NULL, ?, ?)");
    if (!$insert) {
        throw new Exception('Prepare shift assignment insert failed: ' . $mysqli->error);
    }
    $insert->bind_param('iissi', $employeeId, $shiftId, $effectiveFrom, $reason, $createdBy);
    if (!$insert->execute()) {
        throw new Exception('Insert shift assignment failed: ' . $insert->error);
    }
}
