<?php
require_once __DIR__ . '/../includes/session_helpers.php';
require_once __DIR__ . '/../includes/proxy_request_helpers.php';
$dir = sys_get_temp_dir() . '/hr-session-test-' . bin2hex(random_bytes(6));
mkdir($dir);
ini_set('session.save_path', $dir);
session_start();
$_SESSION = ['user_id'=>41, 'role'=>'admin', 'employee_id'=>7];
$id = session_id();
hrSessionRelease();
if (session_status() === PHP_SESSION_ACTIVE || $_SESSION['user_id'] !== 41) throw new RuntimeException('Session was not released or context lost');
proxyRequestRequireAccess();
if (session_status() === PHP_SESSION_ACTIVE) throw new RuntimeException('Authorization reopened session lock');
session_id($id);
session_start();
if ($_SESSION['user_id'] !== 41) throw new RuntimeException('Session update not persisted');
session_destroy();
rmdir($dir);
echo "PASS session persisted, released and authorization does not reopen it
";
