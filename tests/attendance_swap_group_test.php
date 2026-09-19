<?php
require_once __DIR__ . '/../includes/attendance_helpers.php';
$rows = [
 ['requester_employee_id'=>1,'target_employee_id'=>2,'requester_date'=>'2026-09-01','target_date'=>'2026-09-02'],
 ['requester_employee_id'=>2,'target_employee_id'=>1,'requester_date'=>'2026-09-01','target_date'=>'2026-10-02'],
 ['requester_employee_id'=>3,'target_employee_id'=>3,'requester_date'=>'2026-09-03','target_date'=>'2026-09-04'],
];
$actual = attendanceBuildApprovedDaySwapMaps($rows, [1,2,3,4], '2026-09');
$expected = [
 1=>['2026-09-01'=>'holiday','2026-09-02'=>'holiday'],
 2=>['2026-09-01'=>'workday','2026-09-02'=>'workday'],
 3=>['2026-09-03'=>'holiday','2026-09-04'=>'workday'],
 4=>[],
];
if ($actual !== $expected) throw new RuntimeException('Swap precedence or self-swap changed');
echo "PASS grouped swap maps
";
