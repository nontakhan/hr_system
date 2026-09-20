<?php
require_once __DIR__ . '/support/performance_fixture.php';
[$db,$database,$env]=performanceFixture();
try {
    [$code,$out,$err]=performanceRun([PHP_BINARY,'scripts/prepare_schema.php'],$env);
    performanceCheck($code===0,'Prepare fixture: '.$out.$err);
    foreach (['pending','pending_manager','pending_hr','approved','pending_cancel_hr','rejected','cancelled'] as $index=>$status) {
        $db->query("INSERT INTO leave_requests (employee_id,leave_type_id,start_date,end_date,total_days,status,created_at) VALUES (1,1,'2026-09-01','2026-09-01',1,'$status','2026-09-".($index+10)." 09:00:00')");
    }
    $db->query("INSERT INTO leave_requests (employee_id,leave_type_id,start_date,end_date,total_days,status,request_unit,time_request_type,created_at) VALUES (1,1,'2026-09-20','2026-09-20',0.125,'pending_manager','hour','late_arrival','2026-09-20 09:00:00')");
    $session=['user_id'=>1,'employee_id'=>1,'role'=>'employee'];
    $r=performanceApi('dashboard_api.php',[],$session,$env);
    $data=$r['data']['personal_dashboard']??[];
    performanceCheck(($data['leave_summary']??[])===['pending'=>3,'approved'=>1,'pending_cancel_hr'=>1,'rejected'=>1,'cancelled'=>1], 'Dashboard must count each real leave state once and exclude late/early requests');
    $history=performanceApi('leave_history_api.php',[],$session,$env);
    performanceCheck(array_sum($data['leave_summary'])===count($history['data']), 'Summary matches the leave history users navigate to');
    performanceCheck(($data['recent_leaves'][0]['status']??'')==='cancelled','Recent leave list excludes newer late/early request');
    echo "PASS dashboard counts and recent list agree with real leave history\n";
} finally { $db->query('DROP DATABASE '.$database); }