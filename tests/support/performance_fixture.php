<?php
// Isolated fixture only: explicit loopback port and a newly generated database.
function performanceFixture(): array {
    $port = (int)getenv('HR_TEST_DB_PORT');
    if (!$port) { echo "SKIP MariaDB fixture (set HR_TEST_DB_PORT to isolated loopback instance)
"; exit(0); }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli('127.0.0.1', 'root', 'hr-fixture-only', '', $port);
    $name = 'hr_perf_fixture_' . bin2hex(random_bytes(6));
    $db->query("CREATE DATABASE " . $name . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $db->select_db($name);
    $db->set_charset('utf8mb4');
    $sql = [
        "CREATE TABLE companies (id INT PRIMARY KEY, company_name_th VARCHAR(100)) ENGINE=InnoDB",
        "CREATE TABLE branches (id INT PRIMARY KEY, company_id INT, branch_name_th VARCHAR(100)) ENGINE=InnoDB",
        "CREATE TABLE positions (id INT PRIMARY KEY, position_name_th VARCHAR(100)) ENGINE=InnoDB",
        "CREATE TABLE departments (id INT PRIMARY KEY, dept_name_th VARCHAR(100)) ENGINE=InnoDB",
        "CREATE TABLE work_shifts (id INT PRIMARY KEY, shift_name VARCHAR(100), start_time TIME, end_time TIME, late_tolerance_mins INT, work_days VARCHAR(100)) ENGINE=InnoDB",
        "CREATE TABLE employees (id INT PRIMARY KEY, citizen_id VARCHAR(20), first_name_th VARCHAR(100), last_name_th VARCHAR(100), nickname VARCHAR(100),
            company_id INT, branch_id INT, position_id INT, department_id INT, supervisor_id INT, status VARCHAR(20), default_shift_id INT,
            start_date DATE, province VARCHAR(100), profile_img_url VARCHAR(255)) ENGINE=InnoDB",
        "CREATE TABLE users (id INT PRIMARY KEY, employee_id INT, username VARCHAR(100), role VARCHAR(30)) ENGINE=InnoDB",
        "CREATE TABLE leave_types (id INT AUTO_INCREMENT PRIMARY KEY, type_name VARCHAR(100), days_per_year INT, description TEXT, requires_file TINYINT) ENGINE=InnoDB",
        "CREATE TABLE leave_requests (id INT AUTO_INCREMENT PRIMARY KEY, employee_id INT, leave_type_id INT, start_date DATE, end_date DATE,
            total_days DECIMAL(6,2), reason TEXT, status ENUM('pending','approved','rejected') DEFAULT 'pending',
            approver_id INT, approval_date DATETIME, rejection_reason TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
        "CREATE TABLE leave_attachments (id INT AUTO_INCREMENT PRIMARY KEY, leave_request_id INT, file_path VARCHAR(255), file_name VARCHAR(255)) ENGINE=InnoDB",
    ];
    foreach ($sql as $query) $db->query($query);
    foreach (['database_attendance.sql','database_employee_shift_overrides.sql','database_company_holidays.sql'] as $file) $db->query(file_get_contents(__DIR__ . '/../../' . $file));
    $db->query("INSERT INTO companies VALUES (10,'Fixture A'),(20,'Fixture B')");
    $db->query("INSERT INTO branches VALUES (11,10,'Branch A'),(21,20,'Branch B')");
    $db->query("INSERT INTO positions VALUES (1,'Officer')");
    $db->query("INSERT INTO departments VALUES (1,'Department')");
    $db->query("INSERT INTO work_shifts VALUES (1,'Day','08:00:00','17:00:00',5,'Mon,Tue,Wed,Thu,Fri'),(2,'Night','22:00:00','06:00:00',10,'Mon,Tue,Wed,Thu,Fri,Sat,Sun')");
    $db->query("INSERT INTO employees (id,citizen_id,first_name_th,last_name_th,nickname,company_id,branch_id,position_id,department_id,supervisor_id,status,default_shift_id,start_date)
        VALUES (1,'fixture-1','สมชาย','ทดสอบ','หนึ่ง',10,11,1,1,7,'active',1,'2025-01-01'),
               (2,'fixture-2','สมหญิง','ทดสอบ','สอง',20,21,1,1,8,'active',2,'2025-01-01')");
    $db->query("INSERT INTO users VALUES (1,1,'fixture','admin')");
    $db->query("INSERT INTO leave_types VALUES (1,'ลาทดสอบ',30,'Fixture',0)");
    $env = array_merge(getenv(), ['HR_DB_HOST'=>'127.0.0.1:' . $port,'HR_DB_USER'=>'root','HR_DB_PASS'=>'hr-fixture-only','HR_DB_NAME'=>$name]);
    return [$db, $name, $env];
}
function performanceRun(array $command, array $env): array {
    $pipes = [];
    $process = proc_open($command, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, dirname(__DIR__, 2), $env);
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $code = proc_close($process);
    return [$code, $out, $err];
}
function performanceCheck(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function performanceApi(string $file, array $query, array $session, array $env): array {
    [$code,$out,$err] = performanceRun([PHP_BINARY,__DIR__ . '/api_request.php',$file,json_encode($query),json_encode($session)], $env);
    $data = json_decode($out, true);
    performanceCheck($code === 0 && is_array($data), 'API did not return JSON: ' . $file . ' ' . $out . $err);
    return $data;
}
