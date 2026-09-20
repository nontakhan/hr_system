<?php
require_once __DIR__ . '/support/performance_fixture.php';
[$db, $database, $env] = performanceFixture();
try {
    [$code,$out,$err] = performanceRun([PHP_BINARY,'scripts/prepare_schema.php'], $env);
    performanceCheck($code === 0, 'Schema preparation: ' . $out . $err);
    $db->query("ALTER TABLE employees MODIFY id INT AUTO_INCREMENT, ADD prefix_th VARCHAR(30), ADD prefix_en VARCHAR(30), ADD first_name_en VARCHAR(100), ADD last_name_en VARCHAR(100), ADD birth_date DATE, ADD gender VARCHAR(20), ADD religion VARCHAR(50), ADD blood_group VARCHAR(10), ADD marital_status VARCHAR(30), ADD phone_number VARCHAR(30), ADD current_address TEXT, ADD district VARCHAR(100), ADD education_level VARCHAR(100), ADD emergency_contact_name VARCHAR(100), ADD emergency_contact_phone VARCHAR(30), ADD employment_type_id INT");
    $db->query("ALTER TABLE users MODIFY id INT AUTO_INCREMENT, ADD password VARCHAR(255) DEFAULT 'fixture-password-hash'");
    $db->query("CREATE TABLE employment_types (id INT PRIMARY KEY, type_name VARCHAR(100))");
    $db->query("INSERT INTO employment_types VALUES (1,'Fixture')");
    $db->query("CREATE TABLE employee_transfer_log (id INT AUTO_INCREMENT PRIMARY KEY, employee_id INT, effective_date DATE, from_company_id INT, to_company_id INT, from_branch_id INT, to_branch_id INT, from_department_id INT, to_department_id INT, from_position_id INT, to_position_id INT, notes TEXT)");
    $db->query("INSERT INTO branches VALUES (12,10,'Branch A2'), (22,20,'Branch B2')");
    $db->query("UPDATE employees SET branch_id=22 WHERE id=2");
    $db->query("INSERT INTO employees (id,citizen_id,first_name_th,last_name_th,company_id,branch_id,department_id,position_id,default_shift_id,start_date,status,employment_type_id) VALUES (3,'fixture-3','Scoped','Employee',10,12,1,1,1,'2025-01-01','active',1),(4,'fixture-4','Scoped','Manager',20,21,1,1,1,'2025-01-01','active',1)");
    $db->query("INSERT INTO users (id,employee_id,username,role) VALUES (2,2,'outside','employee'),(3,3,'scoped','employee'),(4,4,'manager','manager'),(10,NULL,'hr-fixture','hr'),(11,NULL,'hr-empty','hr')");
    $db->query("INSERT INTO user_hr_scopes (user_id,scope_type,scope_id) VALUES (10,'company',10),(10,'branch',21)");
    $session = fn($id,$role,$employee=0) => ['user_id'=>$id,'employee_id'=>$employee,'role'=>$role,'hr_company_ids'=>[10,20],'hr_branch_ids'=>[22]];
    $admin=$session(1,'admin',1); $hr=$session(10,'hr'); $emptyHr=$session(11,'hr'); $employee=$session(3,'employee',3); $manager=$session(4,'manager',4);
    $failures=[];
    $check=function($value,$message) use (&$failures) { if (!$value) $failures[]=$message; };
    $request=function($actor,$method='GET',$data=[],$query=[],$path='api/employee_api.php') use ($env) {
        [$code,$out,$err]=performanceRun([PHP_BINARY,'tests/support/employee_access_request.php',json_encode(['session'=>$actor,'method'=>$method,'data'=>$data,'query'=>$query,'path'=>$path])],$env);
        $response=json_decode($out,true);
        performanceCheck($code===0 && is_array($response),'Invalid fixture response: '.$path.' '.$out.$err);
        $response['json']=json_decode($response['body'],true);
        return $response;
    };
    foreach ([$employee,$manager] as $actor) {
        foreach ([[],['action'=>'get_history','employee_id'=>1],['action'=>'training_history','employee_id'=>1]] as $query) {
            $r=$request($actor,'GET',[],$query); $check($r['code']===403 && ($r['json']['status']??'')==='error','Management read denied for '.$actor['role'].' '.json_encode($query));
        }
        foreach (['employees.php','employee_add.php','employee_edit.php','employee_view.php'] as $page) {
            $r=$request($actor,'GET',[],['id'=>1],$page);
            $check($r['code']===403 && !str_contains($r['body'],'fixture-1') && str_contains($r['body'],'dashboard.php'),'Page denied with safe return link: '.$actor['role'].' '.$page);
        }
    }
    foreach ([$hr,$emptyHr] as $actor) {
        foreach (['get_history','training_history'] as $action) $check($request($actor,'GET',[],['action'=>$action,'employee_id'=>2])['code']===403,'HR cannot read outside '.$action);
        foreach (['employee_edit.php','employee_view.php'] as $page) $check($request($actor,'GET',[],['id'=>2],$page)['code']===403,'HR page cannot read outside '.$page);
    }
    $r=$request($hr); $ids=array_map('intval',array_column($r['json']['data']??[],'id')); sort($ids);
    $check($ids===[1,3,4],'HR list honors DB company/branch union, not forged cached scopes');
    $r=$request($emptyHr); $check(($r['json']['data']??null)===[],'Empty-scope HR list is empty');
    $check($request($emptyHr,'GET',[],[],'employee_add.php')['code']===403,'HR without assignment scope sees a clear denied page instead of an unusable form');
    $r=$request($admin); $check(count($r['json']['data']??[])===4,'Admin still sees all employees');
    $payload=['first_name_th'=>'Updated','last_name_th'=>'Fixture','citizen_id'=>'fixture-3','company_id'=>10,'branch_id'=>12,'department_id'=>1,'position_id'=>1,'employment_type_id'=>1,'default_shift_id'=>1,'start_date'=>'2025-01-01','birth_date'=>'1990-01-01','status'=>'active'];
    $baseline=$db->query('SELECT * FROM employees ORDER BY id')->fetch_all(MYSQLI_ASSOC);
    foreach (['update_employee','update_profile_image','transfer_employee','update_transfer_history','save_training','delete_training'] as $action) {
        $data=['action'=>$action,'id'=>2,'employee_id'=>2,'training_id'=>1,'transfer_log_id'=>1,'new_company_id'=>10,'new_branch_id'=>12]+$payload;
        $r=$request($hr,'POST',$data); $check($r['code']===403,'Denied before mutation/upload: '.$action);
        $check($db->query('SELECT * FROM employees ORDER BY id')->fetch_all(MYSQLI_ASSOC)===$baseline,'Outside rows unchanged: '.$action);
    }
    foreach ([['company_id'=>20,'branch_id'=>22],['company_id'=>10,'branch_id'=>22]] as $destination) {
        foreach (['create_employee','update_employee','transfer_employee','update_transfer_history'] as $action) {
            $r=$request($hr,'POST',['action'=>$action,'id'=>3,'employee_id'=>3,'new_company_id'=>$destination['company_id'],'new_branch_id'=>$destination['branch_id']]+$destination+$payload);
            $check($r['code']===403,'Out-of-scope/incoherent destination rejected: '.$action);
        }
    }
    $r=$request($hr,'DELETE'); $check($r['code']===403,'HR cannot delete employee');
    foreach (['role'=>'admin','password'=>'synthetic-new-password'] as $key=>$value) {
        $r=$request($hr,'POST',['action'=>'update_employee','id'=>1,$key=>$value]+$payload);
        $check($r['code']===403,'HR cannot change protected account '.$key);
    }
    $r=$request($hr,'POST',['action'=>'create_employee','username'=>'forged-admin','password'=>'synthetic','role'=>'admin']+$payload);
    $check($r['code']===403,'HR cannot create privileged account');
    $r=$request($hr,'POST',['action'=>'update_employee','id'=>3,'hr_company_ids'=>[20]]+$payload);
    $check($r['code']===403,'HR cannot grant scopes');
    // A normal personnel save must not overwrite an existing privileged account.
    $accountBefore=$db->query('SELECT * FROM users WHERE id=1')->fetch_assoc();
    $r=$request($hr,'POST',['action'=>'update_employee','id'=>1,'citizen_id'=>'fixture-1','branch_id'=>11]+$payload);
    $check(($r['json']['status']??'')==='success','HR can still edit in-scope personnel records');
    $check($db->query('SELECT * FROM users WHERE id=1')->fetch_assoc()===$accountBefore,'Personnel-only save preserves privileged account');
    // Self-service still uses the authenticated employee and ignores forged target/role fields.
    $r=$request($employee,'POST',['action'=>'update_my_profile','id'=>2,'employee_id'=>2,'phone_number'=>'fixture-phone','role'=>'admin']);
    $check(($r['json']['status']??'')==='success','Self profile remains available');
    $check($db->query('SELECT phone_number FROM employees WHERE id=3')->fetch_row()[0]==='fixture-phone','Self profile uses session employee');
    $check($db->query('SELECT phone_number FROM employees WHERE id=2')->fetch_row()[0]===null,'Forged self target ignored');
    $db->query("DELETE FROM employee_transfer_log");
    $db->query("UPDATE employees SET company_id=10, branch_id=12 WHERE id=3");
    // Editing dates must not make an out-of-scope historical destination current.
    $db->query("INSERT INTO employee_transfer_log (id,employee_id,effective_date,to_company_id,to_branch_id,to_department_id,to_position_id) VALUES (1,3,'2025-01-01',20,22,1,1),(2,3,'2026-01-01',10,12,1,1)");
    $r=$request($hr,'POST',['action'=>'update_transfer_history','employee_id'=>3,'transfer_log_id'=>2,'effective_date'=>'2024-01-01','new_company_id'=>10,'new_branch_id'=>12,'new_department_id'=>1,'new_position_id'=>1]);
    $check(($r['json']['status']??'')==='error','History reordering cannot escape scope');
    $check($db->query('SELECT branch_id FROM employees WHERE id=3')->fetch_row()[0]==12,'History failure preserves current organization');
    $check($db->query('SELECT effective_date FROM employee_transfer_log WHERE id=2')->fetch_row()[0]==='2026-01-01','History failure rolls back edited date');
    // Positive account workflows use the same HTTP dispatcher as denied requests.
    $r=$request($hr,'POST',['action'=>'update_employee','id'=>3,'username'=>'scoped-updated','password'=>'synthetic-reset','role'=>'employee']+$payload);
    $check(($r['json']['status']??'')==='success','HR can reset in-scope employee account');
    $account=$db->query('SELECT username,password,role FROM users WHERE id=3')->fetch_assoc();
    $check($account['username']==='scoped-updated' && $account['role']==='employee' && password_verify('synthetic-reset',$account['password']),'Employee credentials persist through HR save');
    $r=$request($admin,'POST',['action'=>'update_employee','id'=>3,'username'=>'admin-edited','role'=>'manager']+$payload);
    $check(($r['json']['status']??'')==='success','Admin can change username and role without password');
    $check($db->query('SELECT password FROM users WHERE id=3')->fetch_row()[0]===$account['password'],'Blank password preserves existing hash');
    $r=$request($admin,'POST',['action'=>'update_employee','id'=>3,'username'=>'admin-edited','password'=>'synthetic-admin-reset','role'=>'employee']+$payload);
    $check(($r['json']['status']??'')==='success' && password_verify('synthetic-admin-reset',$db->query('SELECT password FROM users WHERE id=3')->fetch_row()[0]),'Admin can reset password');
    $r=$request($admin,'POST',['action'=>'update_employee','id'=>3,'username'=>'outside','role'=>'employee']+$payload);
    $check(($r['json']['status']??'')==='error' && $db->query('SELECT username FROM users WHERE id=3')->fetch_row()[0]==='admin-edited','Duplicate username rejects and rolls back');
    $r=$request($hr,'POST',['action'=>'create_employee','citizen_id'=>'fixture-new','username'=>'new-scoped','password'=>'synthetic-new','role'=>'employee']+$payload);
    $check(($r['json']['status']??'')==='success','HR can create personnel and employee account');
    $r=$request($hr,'POST',['action'=>'save_training','employee_id'=>3,'course_name'=>'Fixture training','training_date'=>'2026-09-01']);
    $check(($r['json']['status']??'')==='success','In-scope training save works');
    $history=$request($hr,'GET',[],['action'=>'training_history','employee_id'=>3]);
    $trainingId=(int)($history['json']['data'][0]['id']??0);
    $check($trainingId>0,'In-scope training history reads saved record');
    $r=$request($hr,'POST',['action'=>'delete_training','employee_id'=>3,'training_id'=>$trainingId]);
    $check(($r['json']['status']??'')==='success','HR can remove in-scope training record');
    $r=$request($hr,'POST',['action'=>'transfer_employee','employee_id'=>3,'new_company_id'=>20,'new_branch_id'=>21,'effective_date'=>'2026-09-20']);
    $check(($r['json']['status']??'')==='success' && $db->query('SELECT branch_id FROM employees WHERE id=3')->fetch_row()[0]==21,'Transfer to branch-only scope works');
    foreach (['employee_add.php','employee_edit.php','employee_view.php','employees.php'] as $page) {
        $r=$request($hr,'GET',[],['id'=>1],$page);
        $check($r['code']===200 && !str_contains($r['body'],'Fatal error'),'HR page renders '.$page);
        if ($page==='employee_add.php') {
            $check(str_contains($r['body'],'Branch B1') || str_contains($r['body'],'Branch B'),'Branch-only assignment offered');
            $check(!str_contains($r['body'],'Branch B2'),'Out-of-scope branch not offered');
            preg_match('/<select name="role"[^>]*>(.*?)<\/select>/s',$r['body'],$roleSelect);
            $check(str_contains($roleSelect[1]??'', 'value="employee"') && !str_contains($roleSelect[1]??'', 'value="admin"'),'HR role selector is employee-only');
        }
        if ($page==='employee_edit.php') $check(!str_contains($r['body'],'name="password"'),'HR cannot edit protected account via form');
        if ($page==='employees.php') $check(str_contains($r['body'],'data-can-delete="false"'),'HR list has no deletion capability');
    }
    $r=$request($admin,'GET',[],['id'=>1],'employee_edit.php');
    $check($r['code']===200 && str_contains($r['body'],'name="password"') && str_contains($r['body'],"value='admin'"),'Admin form retains account controls');
    // Ambiguous legacy account sets cannot make a privileged account writable by HR.
    $db->query("INSERT INTO users (id,employee_id,username,role) VALUES (30,3,'second-privileged','admin')");
    $accountsBefore=$db->query('SELECT * FROM users WHERE employee_id=3 ORDER BY id')->fetch_all(MYSQLI_ASSOC);
    $r=$request($hr,'POST',['action'=>'update_employee','id'=>3,'username'=>'forged-multiple','password'=>'synthetic-multiple','role'=>'employee','company_id'=>20,'branch_id'=>21]+$payload);
    $check($r['code']===403,'Mixed employee/privileged account set rejects HR credential edits');
    $check($db->query('SELECT * FROM users WHERE employee_id=3 ORDER BY id')->fetch_all(MYSQLI_ASSOC)===$accountsBefore,'All linked accounts preserved on denied HR edit');
    $r=$request($hr,'POST',['action'=>'update_employee','id'=>3,'company_id'=>20,'branch_id'=>21]+$payload);
    $check(($r['json']['status']??'')==='success','HR can still save personnel with multiple linked accounts');
    $check($db->query('SELECT * FROM users WHERE employee_id=3 ORDER BY id')->fetch_all(MYSQLI_ASSOC)===$accountsBefore,'Personnel save leaves every linked account untouched');
    // Fresh database authority wins over stale or forged session role/scope values.
    $r=$request(array_merge($hr,['role'=>'admin']),'GET',[],['action'=>'get_history','employee_id'=>2]);
    $check($r['code']===403,'Forged cached admin role does not bypass HR scope');
    $db->query('DELETE FROM user_hr_scopes WHERE user_id=10');
    $check(($request($hr)['json']['data']??null)===[],'Revoked HR scopes take effect on the next API request');
    $db->query("UPDATE users SET role='employee' WHERE id=10");
    $check($request($hr)['code']===403,'Revoked management role takes effect immediately');

    foreach ($failures as $failure) echo 'FAIL '.$failure.PHP_EOL;
    performanceCheck(!$failures,count($failures).' employee access checks failed');
    echo "PASS employee API/page role/scope boundaries, account protection, self profile and history rollback\n";
} finally { $db->query('DROP DATABASE '.$database); }