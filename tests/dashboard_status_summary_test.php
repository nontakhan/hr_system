<?php
require_once __DIR__ . '/../includes/dashboard_helpers.php';
$summary = dashboardSummarizeLeaveStatuses([
    ['status' => 'pending', 'total' => 1],
    ['status' => 'pending_manager', 'total' => 2],
    ['status' => 'pending_hr', 'total' => 3],
    ['status' => 'approved', 'total' => 4],
    ['status' => 'pending_cancel_hr', 'total' => 5],
    ['status' => 'rejected', 'total' => 6],
    ['status' => 'cancelled', 'total' => 7],
]);
if ($summary !== ['pending' => 6, 'approved' => 4, 'pending_cancel_hr' => 5, 'rejected' => 6, 'cancelled' => 7]) {
    throw new RuntimeException('Every approval stage must count once; cancellation review is separate.');
}
if (array_sum(dashboardSummarizeLeaveStatuses([])) !== 0) throw new RuntimeException('Empty history must show zero counts.');
echo "PASS dashboard status summary\n";