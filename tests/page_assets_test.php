<?php
require_once __DIR__ . '/../includes/page_assets.php';
$leave = hrPageAssets('leave_request.php');
if ($leave['scripts'] !== ['utils','request_display','leave_request']) throw new RuntimeException('Leave dependencies incorrect');
if ($leave['datatables'] || $leave['chart']) throw new RuntimeException('Unused vendors loaded');
if (!hrPageAssets('employee_request_attendance_report.php')['datatables']) throw new RuntimeException('Employee timeline report lost DataTables');
$report = hrPageAssets('attendance_missing_report.php');
if (!in_array('bulk_employee_warnings', $report['scripts'], true) || !$report['datatables']) throw new RuntimeException('Missing report dependency');
foreach (glob(__DIR__ . '/../*.php') as $page) {
 if (!str_contains(file_get_contents($page), 'footer.php')) continue;
 foreach (hrPageAssets(basename($page))['scripts'] as $script) {
  if (!is_file(__DIR__ . '/../assets/js/' . $script . '.js')) throw new RuntimeException('Unknown script: ' . $script);
 }
}
echo "PASS page asset contracts
";
