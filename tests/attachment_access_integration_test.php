<?php
require_once __DIR__ . '/support/performance_fixture.php';
require_once __DIR__ . '/../includes/attachment_helpers.php';
[$db,$database,$env] = performanceFixture();
$filename='ux_test_'.bin2hex(random_bytes(6)).'.pdf';
$path=__DIR__.'/../assets/uploads/leaves/'.$filename;
$trainingPath=__DIR__.'/../assets/uploads/employee_training/'.$filename;
try {
    [$code,$out,$err]=performanceRun([PHP_BINARY,'scripts/prepare_schema.php'],$env);
    performanceCheck($code===0,'Prepare fixture schema');
    file_put_contents($path,'%PDF-1.4 synthetic test document');
    file_put_contents($trainingPath,'%PDF-1.4 synthetic activity document');
    $db->query("INSERT INTO users VALUES (2,2,'outside','employee'),(3,1,'owner','employee'),(4,7,'manager','manager'),(10,NULL,'hr','hr'),(11,NULL,'emptyhr','hr')");
    $db->query("INSERT INTO user_hr_scopes (user_id,scope_type,scope_id) VALUES (10,'company',10)");
    $db->query("INSERT INTO leave_requests (id,employee_id,leave_type_id,start_date,end_date,total_days,reason,status) VALUES (1,1,1,'2026-09-20','2026-09-20',1,'fixture','pending_hr')");
    $stored='assets/uploads/leaves/'.$filename;
    $stmt=$db->prepare('INSERT INTO leave_attachments (leave_request_id,file_path,file_name) VALUES (1,?,?)');$stmt->bind_param('ss',$stored,$filename);$stmt->execute();
    $db->query("INSERT INTO leave_attachments (leave_request_id,file_path,file_name) SELECT leave_request_id,file_path,'second.pdf' FROM leave_attachments WHERE id=1");
    performanceCheck(attachmentResolveForUser($db,3,'leave',1,2)['mime']==='application/pdf','Selected legacy attachment remains readable');
    try { attachmentResolveForUser($db,3,'leave',1,999); throw new RuntimeException('Unrelated attachment was allowed'); } catch(AttachmentAccessException $e) { performanceCheck($e->getCode()===404,'Attachment must belong to the selected request'); }
    $page=performanceApi('leave_approval_api.php',['type'=>'pending','request_unit'=>'day','draw'=>1,'start'=>0,'length'=>10],['user_id'=>1,'employee_id'=>1,'role'=>'admin'],$env);
    performanceCheck(count($page['data'])===1 && count($page['data'][0]['attachments'])===2,'One approval row per request retains every legacy attachment');
    $storedTraining='assets/uploads/employee_training/'.$filename;
    $stmt=$db->prepare("INSERT INTO training_requests (id,employee_id,course_name,start_date,end_date,location,objective,attachment_path,status) VALUES (1,1,'fixture','2026-09-20','2026-09-20','fixture','fixture',?,'approved')");$stmt->bind_param('s',$storedTraining);$stmt->execute();
    $stmt=$db->prepare("INSERT INTO employee_training_records (id,employee_id,training_date,course_name,attachment_path) VALUES (1,1,'2026-09-20','fixture',?)");$stmt->bind_param('s',$storedTraining);$stmt->execute();
    $denied=function($uid,$kind,$id,$code=403)use($db){try{attachmentResolveForUser($db,$uid,$kind,$id);throw new RuntimeException('Expected denial');}catch(AttachmentAccessException $e){performanceCheck($e->getCode()===$code,'Expected safe denial');}};
    foreach(['leave','training_request'] as $kind) {
        foreach([1,3,4,10] as $uid) performanceCheck(attachmentResolveForUser($db,$uid,$kind,1)['mime']==='application/pdf','Authorized role reads '.$kind);
        foreach([0,2,11] as $uid) $denied($uid,$kind,1);
    }
    foreach([1,10] as $uid) performanceCheck(attachmentResolveForUser($db,$uid,'training_record',1)['mime']==='application/pdf','Personnel management can read record');
    foreach([2,3,4,11] as $uid) $denied($uid,'training_record',1);
    $db->query("DELETE FROM user_hr_scopes WHERE user_id=10");$denied(10,'leave',1);
    $db->query("INSERT INTO user_hr_scopes (user_id,scope_type,scope_id) VALUES (10,'branch',11)");performanceCheck(attachmentResolveForUser($db,10,'leave',1)['mime']==='application/pdf','Branch-only HR scope works');
    $db->query("UPDATE users SET role='employee',employee_id=2 WHERE id=1");$denied(1,'leave',1);
    $denied(3,'../leave',1,404);$denied(3,'leave',999,404);
    $db->query("UPDATE leave_attachments SET file_path='assets/uploads/leaves/../../../includes/db_config.php'");$denied(3,'leave',1,404);
    $db->query("UPDATE leave_attachments SET file_path='assets/uploads/leaves/%2e%2e%2fsecret.pdf'");$denied(3,'leave',1,404);
    echo "PASS attachment owners, supervisors, HR union scope, revocation and path boundaries\n";
} finally {
    foreach([$path,$trainingPath] as $file)if(is_file($file))unlink($file);
    $db->query('DROP DATABASE '.$database);
}