<?php
require_once __DIR__ . '/query_helpers.php';

function hrListIsPaged(array $request): bool { return isset($request['draw']); }

// Column expressions are code-owned allowlists, never request input.
function hrListQueryPlan(string $sql, string $types, array $params, array $searchColumns, array $orderColumns, array $request): array
{
    $base = preg_replace('/\s+ORDER\s+BY\s+[^;]+$/is', '', trim($sql));
    $base = preg_replace('/\s+LIMIT\s+\d+\s*$/i', '', $base);
    // Several legacy projections repeat fields already present in alias.*.
    if (preg_match('/SELECT\s+(\w+)\.\*/i', $base, $star)) {
        $base = preg_replace('/,\s*' . preg_quote($star[1], '/') . '\.(?:created_via|created_by_role|proxy_note)\b(?!\s+AS)/i', '', $base);
    }
    $sql = 'SELECT * FROM (' . $base . ') hr_list WHERE 1=1';
    $search = trim((string)($request['search']['value'] ?? ''));
    if ($search !== '' && $searchColumns) {
        $displayColumns = $searchColumns;
        foreach ($searchColumns as $column) {
            if (in_array($column, ['start_date','end_date','created_at','approval_date','cancelled_at','requester_date','target_date'], true)) {
                $displayColumns[] = "CONCAT(LPAD(DAY($column),2,'0'),'/',LPAD(MONTH($column),2,'0'),'/',YEAR($column)+543)";
            }
        }
        // DataTables smart search: each word/quoted phrase may match any displayed column.
        preg_match_all('/"([^"]+)"|(\S+)/u', $search, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $token = $match[1] !== '' ? $match[1] : ($match[2] ?? '');
            if ($token === '') continue;
            $term = '%' . str_replace(['=', '%', '_'], ['==', '=%', '=_'], $token) . '%';
            $sql .= ' AND (' . implode(' OR ', array_map(fn($column) => $column . " LIKE ? ESCAPE '='", $displayColumns)) . ')';
            $types .= str_repeat('s', count($displayColumns));
            foreach ($displayColumns as $_) $params[] = $term;
        }
    }
    $order = [];
    foreach (array_slice((array)($request['order'] ?? []), 0, 5) as $item) {
        $index = filter_var($item['column'] ?? null, FILTER_VALIDATE_INT);
        if ($index === false || !isset($orderColumns[$index]) || $orderColumns[$index] === '') continue;
        $direction = ($item['dir'] ?? '') === 'desc' ? 'DESC' : 'ASC';
        $order[] = $orderColumns[$index] . ' ' . $direction;
    }
    $order[] = 'id DESC';
    return [
        'base' => $base, 'sql' => $sql, 'types' => $types, 'params' => $params,
        'order' => implode(', ', $order),
        'draw' => max(0, (int)($request['draw'] ?? 0)),
        'start' => max(0, (int)($request['start'] ?? 0)),
        'length' => max(1, min(100, (int)($request['length'] ?? 10))),
    ];
}

function hrFetchList(mysqli $mysqli, string $sql, string $types, array $params, array $searchColumns, array $orderColumns, ?array $request = null): array
{
    $request ??= $_GET;
    if (!hrListIsPaged($request)) return ['status'=>'success', 'data'=>hrQueryRows($mysqli, $sql, $types, $params)];
    $plan = hrListQueryPlan($sql, $types, $params, $searchColumns, $orderColumns, $request);
    $total = hrQueryRows($mysqli, 'SELECT COUNT(*) AS total FROM (' . $plan['base'] . ') hr_total', $types, $params)[0]['total'] ?? 0;
    $filtered = hrQueryRows($mysqli, 'SELECT COUNT(*) AS total FROM (' . $plan['sql'] . ') hr_filtered', $plan['types'], $plan['params'])[0]['total'] ?? 0;
    $rows = hrQueryRows($mysqli, $plan['sql'] . ' ORDER BY ' . $plan['order'] . ' LIMIT ? OFFSET ?',
        $plan['types'] . 'ii', array_merge($plan['params'], [$plan['length'], $plan['start']]));
    return ['status'=>'success', 'draw'=>$plan['draw'], 'recordsTotal'=>(int)$total, 'recordsFiltered'=>(int)$filtered, 'data'=>$rows];
}

function hrRequestStatusSearchExpression(): string {
    return "CASE status WHEN 'pending' THEN 'รอหัวหน้างานอนุมัติ' WHEN 'pending_manager' THEN 'รอหัวหน้างานอนุมัติ'
        WHEN 'pending_hr' THEN 'รอ HR อนุมัติ' WHEN 'pending_cancel_hr' THEN 'รอ HR/Admin อนุมัติยกเลิก'
        WHEN 'approved' THEN 'อนุมัติแล้ว' WHEN 'rejected' THEN 'ไม่อนุมัติ' WHEN 'cancelled' THEN 'ยกเลิกแล้ว' ELSE status END";
}
