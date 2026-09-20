<?php
function dashboardSummarizeLeaveStatuses(array $rows): array
{
    $summary = ['pending' => 0, 'approved' => 0, 'pending_cancel_hr' => 0, 'rejected' => 0, 'cancelled' => 0];
    foreach ($rows as $row) {
        $status = (string)($row['status'] ?? '');
        $bucket = in_array($status, ['pending_manager', 'pending_hr'], true) ? 'pending' : $status;
        if (array_key_exists($bucket, $summary)) {
            $summary[$bucket] += (int)$row['total'];
        }
    }
    return $summary;
}
