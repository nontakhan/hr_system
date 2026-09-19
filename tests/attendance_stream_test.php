<?php
require_once __DIR__ . '/../includes/attendance_helpers.php';
$first = ['employee_id'=>1,'work_date'=>'2026-09-01','check_in'=>'08:00:00','check_out'=>null];
$fill = array_replace($first, ['check_in'=>'09:00:00','check_out'=>'17:00:00']);
$result = attendanceMergeImportBatch([$first,$fill,$fill], []);
if ($result['inserted'] !== 1 || $result['updated'] !== 1 || $result['skipped'] !== 1) throw new RuntimeException('Duplicate counters changed');
$next = attendanceMergeImportBatch([$fill], ['1|2026-09-01'=>['check_in'=>'08:00:00','check_out'=>'17:00:00']]);
if ($next['skipped'] !== 1 || $next['rows'] !== []) throw new RuntimeException('Cross-batch duplicate overwrites scans');
$file=tempnam(sys_get_temp_dir(),'hr-csv-');
file_put_contents($file, "header
");
try {
 $iterator=attendanceIterateCsvRows($file);
 if (!($iterator instanceof Generator) || iterator_to_array($iterator)!==[]) throw new RuntimeException('CSV must stream');
} finally { unlink($file); }
echo "PASS CSV iterator and fill-missing-only batch counters
";
