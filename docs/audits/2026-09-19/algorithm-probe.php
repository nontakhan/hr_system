<?php
// Synthetic benchmark of the current helper. No database connection.
// Run: C:\xampp\php\php.exe docs\audits\2026-09-19\algorithm-probe.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__, 3) . '/includes/attendance_helpers.php';
foreach ([100, 500, 1000] as $employees) {
    $rows = [];
    for ($i = 0; $i < $employees * 2; $i++) {
        $rows[] = ['requester_employee_id' => ($i % $employees) + 1,
            'target_employee_id' => (($i + 1) % $employees) + 1,
            'requester_date' => '2026-07-04', 'target_date' => '2026-07-05'];
    }
    $samples = [];
    for ($trial = 0; $trial < 5; $trial++) {
        $start = hrtime(true);
        foreach (range(1, $employees) as $employeeId) {
            attendanceBuildApprovedDaySwapMap($rows, $employeeId, '2026-07');
        }
        $samples[] = (hrtime(true) - $start) / 1e6;
    }
    sort($samples);
    echo json_encode(['probe' => 'existing_day_swap_mapping', 'employees' => $employees,
        'swap_rows' => count($rows), 'row_visits' => $employees * count($rows),
        'median_ms' => round($samples[2], 3), 'trials' => 5]), PHP_EOL;
}
