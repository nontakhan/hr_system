<?php
require_once __DIR__ . '/support/performance_fixture.php';
[$db,$database,$env] = performanceFixture();
try {
    $command = [PHP_BINARY, __DIR__ . '/../scripts/prepare_schema.php'];
    [$code,$out,$err] = performanceRun($command, $env);
    performanceCheck($code === 0 && str_contains($out,'SCHEMA_READY'), 'Migration failed: ' . $out . $err);
    performanceCheck((int)$db->query("SELECT COUNT(*) AS n FROM employee_shift_assignments")->fetch_assoc()['n'] === 2, 'Initial shift backfill missing');
    [$code,$out,$err] = performanceRun($command, $env);
    performanceCheck($code === 0 && str_contains($out,'SCHEMA_ALREADY_READY'), 'Migration not idempotent');
    performanceCheck((int)$db->query("SELECT COUNT(*) AS n FROM employee_shift_assignments")->fetch_assoc()['n'] === 2, 'Repeated backfill duplicated rows');

    foreach (['attendance','leave','day_swap','hr_scope','employee_shift_assignment','training_request','employee_warning','approval_badge','employee_request_attendance_report','query','list'] as $helper) require_once __DIR__ . '/../includes/' . $helper . '_helpers.php';
    $apiSource = file_get_contents(__DIR__ . '/../api/attendance_api.php');
    eval(substr($apiSource, strpos($apiSource, 'function resolveAttendanceEmployeeId(')));
    $db->query("INSERT INTO attendance_records (employee_id,citizen_id,work_date,check_in,check_out,import_month) VALUES
        (1,'fixture-1','2026-01-05','08:12:00','17:00:00','2026-01'),(1,'fixture-1','2026-02-02','08:00:00',NULL,'2026-02'),
        (2,'fixture-2','2026-01-05','22:15:00','06:00:00','2026-01')");
    $db->query("INSERT INTO company_holidays (holiday_date,holiday_name) VALUES ('2026-01-01','Holiday')");
    $db->query("INSERT INTO leave_requests (employee_id,leave_type_id,start_date,end_date,start_day_part,end_day_part,total_days,status,reason)
        VALUES (1,1,'2026-01-06','2026-01-07','afternoon','full',1.5,'approved','fixture'),
               (1,1,'2026-02-03','2026-02-03','full','full',1,'pending_cancel_hr','fixture')");
    $db->query("INSERT INTO leave_requests (employee_id,leave_type_id,start_date,end_date,total_days,status,reason,request_unit,time_request_type,request_minutes)
        VALUES (1,2,'2026-01-05','2026-01-05',0,'approved','fixture','hour','late_arrival',10)");
    $db->query("INSERT INTO day_swap_requests (requester_employee_id,target_employee_id,requester_date,target_date,reason,status)
        VALUES (1,2,'2026-01-10','2026-02-04','fixture','approved')");
    $db->query("INSERT INTO training_requests (employee_id,activity_type_id,course_name,start_date,end_date,objective,status)
        VALUES (1,1,'Fixture activity','2026-01-08','2026-01-09','fixture','pending_cancel_hr')");
    $db->query("INSERT INTO employee_shift_overrides (employee_id,day_of_week,start_time,end_time,late_tolerance_mins,effective_from,effective_to)
        VALUES (1,'Mon','09:00:00','18:00:00',10,'2026-02-01','2026-02-28')");
    $employee = fetchAttendanceEmployee($db, 1);
    $months = array_merge(buildMonthlyAttendanceReport($db,$employee,'2026-01'),buildMonthlyAttendanceReport($db,$employee,'2026-02'));
    $range = buildAttendanceReportRange($db,$employee,'2026-01','2026-02');
    performanceCheck($months === $range && count($range) === 59,'Range/month output mismatch');
    $leaveEvents = fetchEmployeeRequestReportLeaveEvents($db,1,'2026-01');
    $reusedLeaveEvents = fetchEmployeeRequestReportLeaveEvents($db,1,'2026-01',(string)$employee['work_days'],fetchCompanyHolidaysForMonth($db,'2026-01'));
    performanceCheck($leaveEvents === $reusedLeaveEvents,'Timeline context reuse changed events');
    performanceCheck(fetchEmployeeRequestReportLeaveEvents($db,1,'2026-02') === [],'Timeline includes pending-cancellation leave');
    $dates = array_column($range,null,'work_date');
    performanceCheck($dates['2026-01-01']['status'] === 'holiday','Holiday changed');
    performanceCheck(count($dates['2026-01-06']['partial_leave_details']) === 1,'Partial leave lost');
    performanceCheck($dates['2026-02-03']['status'] === 'leave','Pending-cancellation leave coverage changed');
    performanceCheck($dates['2026-01-08']['status'] === 'present' && $dates['2026-01-08']['training_name'] !== null,'Pending-cancellation activity coverage changed');

    // Range loading must retain historical assignment and day-specific override precedence.
    $nightEmployee = fetchAttendanceEmployee($db, 2);
    $night = array_column(buildAttendanceReportRange($db,$nightEmployee,'2026-01','2026-02'),null,'work_date');
    performanceCheck($night['2026-01-05']['status'] === 'late' && $night['2026-01-05']['check_out'] === '06:00:00','Overnight shift changed');
    $db->query("UPDATE employee_shift_assignments SET effective_to='2026-01-31' WHERE employee_id=1");
    $db->query("INSERT INTO employee_shift_assignments (employee_id,shift_id,effective_from) VALUES (1,2,'2026-02-01')");
    $db->query("UPDATE employees SET default_shift_id=2 WHERE id=1");
    $db->query("INSERT INTO attendance_records (employee_id,citizen_id,work_date,check_in,check_out,import_month) VALUES
        (1,'fixture-1','2026-02-09','09:05:00','18:00:00','2026-02'),
        (1,'fixture-1','2026-02-10','22:05:00','06:00:00','2026-02')");
    $historicalEmployee = fetchAttendanceEmployee($db, 1);
    $history = array_column(buildAttendanceReportRange($db,$historicalEmployee,'2026-01','2026-02'),null,'work_date');
    performanceCheck($history['2026-01-05']['status'] === 'late','Past day shift replaced by current default');
    performanceCheck($history['2026-02-09']['status'] === 'present','Dated Monday override did not take precedence');
    performanceCheck($history['2026-02-10']['status'] === 'present','Historical night assignment did not apply');

    $_SESSION = ['role'=>'admin','user_id'=>1,'employee_id'=>1];
    $db->query("CREATE TRIGGER fixture_override_failure BEFORE INSERT ON attendance_record_overrides FOR EACH ROW BEGIN IF NEW.employee_id = 2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture failure'; END IF; END");
    try { saveBulkAttendanceAdjustments($db,'admin',['employee_ids'=>[1,2],'work_date'=>'2026-01-20','override_check_in'=>'08:00','reason'=>'fixture']); throw new LogicException('Expected failed write'); }
    catch (mysqli_sql_exception $expected) {}
    performanceCheck((int)$db->query("SELECT COUNT(*) AS n FROM attendance_record_overrides")->fetch_assoc()['n'] === 0,'Bulk failure did not roll back first employee');
    $db->query("DROP TRIGGER fixture_override_failure");


    $csv = tempnam(sys_get_temp_dir(), 'hr-stream-');
    $writeCsv = function (string $path, bool $failure = false): void {
        $handle = fopen($path, 'w');
        fputcsv($handle, ['header']);
        for ($i = 0; $i < 250; $i++) {
            $row = array_fill(0, 20, '');
            $row[0] = 'fixture-1';
            $row[3] = $i === 0 ? '01/03/2569' : (new DateTimeImmutable('2027-01-01'))->modify('+' . $i . ' days')->format('d/m/Y');
            $row[10] = '08:00';
            fputcsv($handle, $row);
        }
        $row = array_fill(0, 20, '');
        $row[0] = $failure ? 'fixture-2' : 'fixture-1';
        $row[3] = '01/03/2569';
        $row[10] = '09:00'; $row[18] = '17:00';
        fputcsv($handle, $row);
        if (!$failure) fputcsv($handle, $row);
        fclose($handle);
    };
    try {
        $writeCsv($csv);
        $import = importAttendanceCsv($db, $csv, 'fixture.csv');
        performanceCheck($import['inserted'] === 250 && $import['updated'] === 1 && $import['skipped'] === 1, 'Cross-batch import counters changed');
        $saved = $db->query("SELECT check_in, check_out FROM attendance_records WHERE employee_id=1 AND work_date='2026-03-01'")->fetch_assoc();
        performanceCheck($saved === ['check_in'=>'08:00:00','check_out'=>'17:00:00'], 'Import overwrote a pre-existing scan');
        $repeat = importAttendanceCsv($db, $csv, 'fixture.csv');
        performanceCheck($repeat['inserted'] === 0 && $repeat['updated'] === 0 && $repeat['skipped'] === 252, 'Repeat import changed rows');
        $db->query("DELETE FROM attendance_records WHERE work_date >= '2026-03-01'");
        $db->query("CREATE TRIGGER fixture_import_failure BEFORE INSERT ON attendance_records FOR EACH ROW BEGIN IF NEW.employee_id = 2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture failure'; END IF; END");
        $writeCsv($csv, true);
        try { importAttendanceCsv($db, $csv, 'fixture.csv'); throw new LogicException('Expected import failure'); }
        catch (mysqli_sql_exception $expected) {}
        performanceCheck((int)$db->query("SELECT COUNT(*) AS n FROM attendance_records WHERE work_date >= '2026-03-01'")->fetch_assoc()['n'] === 0, 'CSV later-batch failure did not roll back first 250 rows');
        $db->query("DROP TRIGGER fixture_import_failure");
    } finally { unlink($csv); }

    $admin = ['user_id'=>1,'employee_id'=>1,'role'=>'admin','company_id'=>10];
    $hr = array_merge($admin,['role'=>'hr','hr_company_ids'=>[10],'hr_branch_ids'=>[]]);
    $employeeSession = array_merge($admin,['role'=>'employee']);
    foreach (['employee_api.php'=>[], 'leave_approval_api.php'=>['type'=>'history'], 'leave_history_api.php'=>[],
              'late_early_request_api.php'=>['action'=>'history'], 'day_swap_api.php'=>['action'=>'my_requests'],
              'training_request_api.php'=>['action'=>'my_requests']] as $file=>$query) {
        $legacy = performanceApi($file,$query,$admin,$env);
        $page = performanceApi($file,$query+['draw'=>1,'start'=>0,'length'=>1],$admin,$env);
        performanceCheck(($legacy['status'] ?? '') === 'success' && ($page['status'] ?? '') === 'success','List failed: ' . $file . json_encode($page));
        performanceCheck(count($page['data']) <= 1 && $page['recordsTotal'] === count($legacy['data']),'List totals/page size mismatch: ' . $file);
    }
    $page = performanceApi('employee_api.php',['draw'=>1,'length'=>10,'search'=>['value'=>'ทดสอบ']],$hr,$env);
    performanceCheck($page['recordsTotal'] === 1 && $page['recordsFiltered'] === 1 && (int)$page['data'][0]['id'] === 1,'HR scope leaked through paging');
    foreach (['employees','holidays','my_requests','pending','history'] as $action) {
        $result = performanceApi('day_swap_api.php',['action'=>$action,'month'=>'2026-01','draw'=>1,'length'=>1],$admin,$env);
        performanceCheck(($result['status'] ?? '') === 'success','Day swap route failed: ' . $action . json_encode($result));
    }
    $denied = performanceApi('day_swap_api.php',['action'=>'pending','draw'=>1],$employeeSession,$env);
    performanceCheck(($denied['status'] ?? '') === 'error','Employee can access approval list');

    $reviewFailures = [];
    $reviewCheck = function ($condition, $message) use (&$reviewFailures) { if (!$condition) $reviewFailures[] = $message; };
    $smart = performanceApi('employee_api.php',['draw'=>1,'search'=>['value'=>'สมชาย Officer']],$hr,$env);
    $reviewCheck($smart['recordsFiltered'] === 1, 'Smart search across displayed columns changed');
    $thaiDate = performanceApi('leave_history_api.php',['draw'=>1,'search'=>['value'=>'06/01/2569']],$admin,$env);
    $reviewCheck($thaiDate['recordsFiltered'] === 1, 'Displayed Buddhist date is not searchable');
    $db->query("INSERT INTO training_requests (employee_id,activity_type_id,course_name,start_date,end_date,objective,status)
        VALUES (1,1,'Sort fixture','2026-02-01','2026-02-01','aaaa','approved')");
    $sorted = performanceApi('training_request_api.php',['action'=>'my_requests','draw'=>1,'order'=>[['column'=>4,'dir'=>'asc']]],$admin,$env);
    $reviewCheck($sorted['data'][0]['status'] === 'pending_cancel_hr', 'Status column sorted by hidden objective');
    performanceCheck(!$reviewFailures, implode('; ', $reviewFailures));
    echo "PASS MariaDB migration twice, range/partial/cancellation contracts, atomic bulk/CSV rollback, cross-chunk duplicate counters, scoped and legacy paginated APIs
";
} finally {
    $db->query('DROP DATABASE ' . $database);
    $db->close();
}
