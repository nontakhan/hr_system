<?php

const EMPLOYEE_WARNING_SOURCE_ATTENDANCE_MISSING = 'attendance_missing';
const EMPLOYEE_WARNING_SOURCE_ATTENDANCE_LATE_EARLY = 'attendance_late_early';
const EMPLOYEE_WARNING_SOURCE_APPROVED_LEAVE = 'approved_leave';
const EMPLOYEE_WARNING_BULK_MAX_ITEMS = 500;
const EMPLOYEE_WARNING_DETAIL_MAX_LENGTH = 2000;

function employeeWarningNormalizeSharedNote($value): string
{
    if ($value !== null && !is_string($value)) {
        throw new InvalidArgumentException('หมายเหตุใบเตือนไม่ถูกต้อง');
    }
    $note = trim((string)$value);
    if (mb_strlen($note, 'UTF-8') > EMPLOYEE_WARNING_DETAIL_MAX_LENGTH) {
        throw new InvalidArgumentException('หมายเหตุใบเตือนยาวเกินกำหนด');
    }
    return $note;
}

function employeeWarningAppendSharedNote(string $generatedDetail, string $sharedNote): string
{
    $generatedDetail = trim($generatedDetail);
    $sharedNote = employeeWarningNormalizeSharedNote($sharedNote);
    if ($sharedNote === '') {
        return $generatedDetail;
    }
    return $generatedDetail . "\nหมายเหตุ: " . $sharedNote;
}

function employeeWarningSupportedSourceTypes(): array
{
    return [
        EMPLOYEE_WARNING_SOURCE_ATTENDANCE_MISSING,
        EMPLOYEE_WARNING_SOURCE_ATTENDANCE_LATE_EARLY,
        EMPLOYEE_WARNING_SOURCE_APPROVED_LEAVE,
    ];
}

function employeeWarningNormalizeSourceDate($value): string
{
    $date = trim((string)$value);
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $errors = DateTimeImmutable::getLastErrors();
    $valid = $parsed instanceof DateTimeImmutable
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $parsed->format('Y-m-d') === $date;
    $year = $valid ? (int)$parsed->format('Y') : 0;
    if (!$valid || $year < 1900 || $year > 2100) {
        throw new InvalidArgumentException('Invalid warning source date');
    }
    return $date;
}

function employeeWarningBuildSourceKey(string $sourceType, array $event): string
{
    if (in_array($sourceType, [
        EMPLOYEE_WARNING_SOURCE_ATTENDANCE_MISSING,
        EMPLOYEE_WARNING_SOURCE_ATTENDANCE_LATE_EARLY,
    ], true)) {
        $employeeId = (int)($event['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            throw new InvalidArgumentException('Invalid warning source employee');
        }
        $workDate = employeeWarningNormalizeSourceDate($event['work_date'] ?? '');
        return "employee:{$employeeId}|date:{$workDate}";
    }

    if ($sourceType === EMPLOYEE_WARNING_SOURCE_APPROVED_LEAVE) {
        $requestId = (int)($event['id'] ?? $event['request_id'] ?? 0);
        if ($requestId <= 0) {
            throw new InvalidArgumentException('Invalid warning source request');
        }
        $leaveDate = employeeWarningNormalizeSourceDate($event['leave_date'] ?? '');
        return "request:{$requestId}|date:{$leaveDate}";
    }

    throw new InvalidArgumentException('Unsupported warning source type');
}

function employeeWarningParseSourceKey(string $sourceType, string $sourceKey): array
{
    if (!in_array($sourceType, employeeWarningSupportedSourceTypes(), true)) {
        throw new InvalidArgumentException('Unsupported warning source type');
    }

    if (in_array($sourceType, [
        EMPLOYEE_WARNING_SOURCE_ATTENDANCE_MISSING,
        EMPLOYEE_WARNING_SOURCE_ATTENDANCE_LATE_EARLY,
    ], true)) {
        if (!preg_match('/^employee:([1-9]\d*)\|date:(\d{4}-\d{2}-\d{2})$/', $sourceKey, $matches)) {
            throw new InvalidArgumentException('Invalid attendance warning source key');
        }
        return [
            'employee_id' => (int)$matches[1],
            'work_date' => employeeWarningNormalizeSourceDate($matches[2]),
        ];
    }

    if (!preg_match('/^request:([1-9]\d*)\|date:(\d{4}-\d{2}-\d{2})$/', $sourceKey, $matches)) {
        throw new InvalidArgumentException('Invalid leave warning source key');
    }
    return [
        'request_id' => (int)$matches[1],
        'leave_date' => employeeWarningNormalizeSourceDate($matches[2]),
    ];
}

function employeeWarningFetchExistingSourceKeys(mysqli $mysqli, string $sourceType, array $sourceKeys): array
{
    if (!in_array($sourceType, employeeWarningSupportedSourceTypes(), true)) {
        throw new InvalidArgumentException('Unsupported warning source type');
    }
    $sourceKeys = array_values(array_unique(array_filter(array_map('strval', $sourceKeys))));
    if (!$sourceKeys) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($sourceKeys), '?'));
    $stmt = $mysqli->prepare("SELECT source_key FROM employee_warnings WHERE source_type = ? AND source_key IN ({$placeholders})");
    $types = 's' . str_repeat('s', count($sourceKeys));
    $params = array_merge([$sourceType], $sourceKeys);
    $refs = [];
    foreach ($params as $index => $_) {
        $refs[$index] = &$params[$index];
    }
    $stmt->bind_param($types, ...$refs);
    $stmt->execute();

    $existing = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $existing[(string)$row['source_key']] = true;
    }
    return $existing;
}

function employeeWarningAnnotateReportRows(mysqli $mysqli, array $rows, string $sourceType, callable $eventMapper): array
{
    $keys = [];
    foreach ($rows as $index => $row) {
        $keys[$index] = employeeWarningBuildSourceKey($sourceType, $eventMapper($row));
    }
    $existing = employeeWarningFetchExistingSourceKeys($mysqli, $sourceType, array_values($keys));

    foreach ($rows as $index => &$row) {
        $sourceKey = $keys[$index];
        $parsed = employeeWarningParseSourceKey($sourceType, $sourceKey);
        $row['warning_source_type'] = $sourceType;
        $row['warning_source_key'] = $sourceKey;
        $row['warning_event_date'] = $parsed['work_date'] ?? $parsed['leave_date'];
        $row['already_warned'] = isset($existing[$sourceKey]);
    }
    unset($row);
    return $rows;
}

function employeeWarningEnsureSourceColumns(mysqli $mysqli): void
{
    $columnResult = $mysqli->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employee_warnings' AND COLUMN_NAME IN ('source_type', 'source_key', 'source_event_date')");
    $columns = [];
    while ($columnResult && ($row = $columnResult->fetch_assoc())) {
        $columns[$row['COLUMN_NAME']] = true;
    }
    if (!isset($columns['source_type'])) {
        $mysqli->query("ALTER TABLE employee_warnings ADD COLUMN source_type VARCHAR(50) NULL AFTER detail");
    }
    if (!isset($columns['source_key'])) {
        $mysqli->query("ALTER TABLE employee_warnings ADD COLUMN source_key VARCHAR(100) NULL AFTER source_type");
    }
    if (!isset($columns['source_event_date'])) {
        $mysqli->query("ALTER TABLE employee_warnings ADD COLUMN source_event_date DATE NULL AFTER source_key");
    }

    $indexResult = $mysqli->query("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employee_warnings' AND INDEX_NAME = 'uq_employee_warnings_source' LIMIT 1");
    if (!$indexResult || $indexResult->num_rows === 0) {
        $mysqli->query("ALTER TABLE employee_warnings ADD UNIQUE KEY uq_employee_warnings_source (source_type, source_key)");
    }
}

function employeeWarningNormalizeBulkItems(array $input): array
{
    $items = $input['items'] ?? null;
    if (!is_array($items) || !$items || count($items) > EMPLOYEE_WARNING_BULK_MAX_ITEMS) {
        throw new InvalidArgumentException('จำนวนรายการใบเตือนไม่ถูกต้อง');
    }
    $normalized = [];
    $seen = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException('ข้อมูลเหตุการณ์ใบเตือนไม่ถูกต้อง');
        }
        $sourceType = trim((string)($item['source_type'] ?? ''));
        $sourceKey = trim((string)($item['source_key'] ?? ''));
        employeeWarningParseSourceKey($sourceType, $sourceKey);
        $compound = $sourceType . '|' . $sourceKey;
        if (isset($seen[$compound])) {
            throw new InvalidArgumentException('มีเหตุการณ์ซ้ำในรายการที่เลือก');
        }
        $seen[$compound] = true;
        $normalized[] = [
            'source_type' => $sourceType,
            'source_key' => $sourceKey,
        ];
    }
    return $normalized;
}

function employeeWarningRequireValidType(mysqli $mysqli, int $warningTypeId): int
{
    if ($warningTypeId <= 0) {
        throw new InvalidArgumentException('กรุณาเลือกรายการใบเตือน');
    }
    $stmt = $mysqli->prepare('SELECT id FROM warning_types WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $warningTypeId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        throw new InvalidArgumentException('ไม่พบรายการใบเตือน');
    }
    return $warningTypeId;
}

function employeeWarningResolveApprovedLeaveSource(mysqli $mysqli, int $requestId, string $leaveDate, string $role, array $scopes): array
{
    $scope = employeeWarningEmployeeScopeClause($role, $scopes, 'e');
    $sql = "SELECT lr.id, lr.employee_id, lr.start_date, lr.end_date, lr.start_day_part, lr.end_day_part, lr.reason,
                   e.first_name_th, e.last_name_th, lt.type_name AS leave_type_name
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.id
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.id = ? AND lr.status = 'approved' AND lt.is_actual_leave = 1
              AND (lr.request_unit = 'day' OR (lr.request_unit = 'hour' AND lr.time_request_type IS NULL))" . $scope['sql'] . " LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    employeeWarningBindParams($stmt, 'i' . $scope['types'], array_merge([$requestId], $scope['params']));
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    if (!$request) {
        throw new InvalidArgumentException('Access Denied');
    }

    $month = substr($leaveDate, 0, 7);
    $monthStart = $month . '-01';
    $monthEnd = (new DateTimeImmutable($monthStart))->modify('last day of this month')->format('Y-m-d');
    $workDays = leaveFetchEmployeeWorkDays($mysqli, (int)$request['employee_id']);
    $holidays = leaveFetchCompanyHolidays($mysqli, $monthStart, $monthEnd);
    $expanded = leaveExpandApprovedRequestForMonth($request, $month, $workDays, $holidays);
    foreach ($expanded as $row) {
        if (($row['leave_date'] ?? '') !== $leaveDate) {
            continue;
        }
        $days = (float)($row['leave_days'] ?? 0);
        $partLabel = trim((string)($row['day_part_label'] ?? ''));
        $amount = rtrim(rtrim(number_format($days, 2, '.', ''), '0'), '.');
        return [
            'employee_id' => (int)$request['employee_id'],
            'employee_name' => trim(($request['first_name_th'] ?? '') . ' ' . ($request['last_name_th'] ?? '')),
            'event_date' => $leaveDate,
            'event_label' => (string)$request['leave_type_name'],
            'generated_detail' => sprintf(
                'ลา%s วันที่ %s จำนวน %s วัน%s เหตุผล: %s',
                $request['leave_type_name'],
                $leaveDate,
                $amount,
                $partLabel !== '' ? ' (' . $partLabel . ')' : '',
                trim((string)($request['reason'] ?? '')) ?: '-'
            ),
        ];
    }
    throw new InvalidArgumentException('Warning source event no longer exists');
}

function employeeWarningResolveSourceEvent(mysqli $mysqli, string $sourceType, string $sourceKey, string $role, array $scopes): array
{
    require_once __DIR__ . '/attendance_warning_source_helpers.php';
    $parsed = employeeWarningParseSourceKey($sourceType, $sourceKey);
    if ($sourceType === EMPLOYEE_WARNING_SOURCE_ATTENDANCE_MISSING) {
        $resolved = attendanceResolveMissingWarningSource($mysqli, $parsed['employee_id'], $parsed['work_date'], $role, $scopes);
    } elseif ($sourceType === EMPLOYEE_WARNING_SOURCE_ATTENDANCE_LATE_EARLY) {
        $resolved = attendanceResolveLateEarlyWarningSource($mysqli, $parsed['employee_id'], $parsed['work_date'], $role, $scopes);
    } else {
        $resolved = employeeWarningResolveApprovedLeaveSource($mysqli, $parsed['request_id'], $parsed['leave_date'], $role, $scopes);
    }
    return $resolved + [
        'source_type' => $sourceType,
        'source_key' => $sourceKey,
    ];
}

function employeeWarningResolveBulkEvents(mysqli $mysqli, array $items, string $role, array $scopes, string $sharedNote = ''): array
{
    $resolved = [];
    foreach ($items as $item) {
        $event = employeeWarningResolveSourceEvent($mysqli, $item['source_type'], $item['source_key'], $role, $scopes);
        $event['detail'] = employeeWarningAppendSharedNote($event['generated_detail'], $sharedNote);
        $resolved[] = $event;
    }
    return $resolved;
}

function employeeWarningFetchExistingSourcesByType(mysqli $mysqli, array $resolved): array
{
    $grouped = [];
    foreach ($resolved as $event) {
        $grouped[$event['source_type']][] = $event['source_key'];
    }
    $existing = [];
    foreach ($grouped as $sourceType => $keys) {
        foreach (employeeWarningFetchExistingSourceKeys($mysqli, $sourceType, $keys) as $key => $_) {
            $existing[$sourceType . '|' . $key] = true;
        }
    }
    return $existing;
}

function employeeWarningInsertResolvedBulk(
    mysqli $mysqli,
    array $resolved,
    array $existing,
    int $warningTypeId,
    string $processingDate,
    int $userId
): array {
    $stmt = $mysqli->prepare("INSERT INTO employee_warnings
        (employee_id, warning_type_id, warning_date, detail, source_type, source_key, source_event_date, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $createdKeys = [];
    $duplicateKeys = [];
    foreach ($resolved as $event) {
        $compound = $event['source_type'] . '|' . $event['source_key'];
        if (isset($existing[$compound])) {
            $duplicateKeys[] = $event['source_key'];
            continue;
        }
        $employeeId = (int)$event['employee_id'];
        $detail = (string)$event['detail'];
        $sourceType = (string)$event['source_type'];
        $sourceKey = (string)$event['source_key'];
        $eventDate = (string)$event['event_date'];
        $stmt->bind_param('iisssssi', $employeeId, $warningTypeId, $processingDate, $detail, $sourceType, $sourceKey, $eventDate, $userId);
        if (!$stmt->execute()) {
            if ($stmt->errno === 1062 && (stripos($stmt->error, 'uq_employee_warnings_source') !== false || stripos($stmt->error, 'Duplicate') !== false)) {
                $duplicateKeys[] = $sourceKey;
                continue;
            }
            throw new RuntimeException($stmt->error ?: 'Cannot create bulk employee warnings');
        }
        $createdKeys[] = $sourceKey;
    }
    return [
        'created_count' => count($createdKeys),
        'duplicate_count' => count($duplicateKeys),
        'skipped_count' => 0,
        'created_keys' => $createdKeys,
        'duplicate_keys' => $duplicateKeys,
        'skipped' => [],
    ];
}

function employeeWarningCreateBulk(mysqli $mysqli, array $input, int $userId, string $role, array $scopes): array
{
    $items = employeeWarningNormalizeBulkItems($input);
    $sharedNote = employeeWarningNormalizeSharedNote($input['shared_note'] ?? '');
    $warningTypeId = employeeWarningRequireValidType($mysqli, (int)($input['warning_type_id'] ?? 0));
    $resolved = employeeWarningResolveBulkEvents($mysqli, $items, $role, $scopes, $sharedNote);
    $existing = employeeWarningFetchExistingSourcesByType($mysqli, $resolved);
    $processingDate = date('Y-m-d');
    $mysqli->begin_transaction();
    try {
        $result = employeeWarningInsertResolvedBulk($mysqli, $resolved, $existing, $warningTypeId, $processingDate, $userId);
        $mysqli->commit();
        return ['processing_date' => $processingDate] + $result;
    } catch (Throwable $e) {
        $mysqli->rollback();
        throw $e;
    }
}
