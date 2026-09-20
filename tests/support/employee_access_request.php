<?php
// CLI-only, newly generated loopback fixture database. Never usable as a web router.
if (PHP_SAPI !== 'cli' || !str_starts_with((string)getenv('HR_DB_NAME'), 'hr_perf_fixture_') || !str_starts_with((string)getenv('HR_DB_HOST'), '127.0.0.1:')) exit(2);
$request = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
$allowed = ['api/employee_api.php', 'api/dashboard_api.php', 'employees.php', 'employee_add.php', 'employee_edit.php', 'employee_view.php'];
if (!in_array($request['path'], $allowed, true)) exit(2);
$_GET = $request['query'] ?? [];
$_POST = $request['data'] ?? [];
$_FILES = [];
$_SERVER['REQUEST_METHOD'] = $request['method'] ?? 'GET';
$_SERVER['PHP_SELF'] = '/' . $request['path'];
session_start();
$_SESSION = $request['session'];
$sessionFile = session_save_path() . '/sess_' . session_id();
ob_start();
register_shutdown_function(function () use ($sessionFile) {
    $body = ob_get_clean();
    if (is_file($sessionFile)) unlink($sessionFile);
    echo json_encode(['code' => http_response_code() ?: 200, 'body' => $body], JSON_UNESCAPED_UNICODE);
});
chdir(dirname(__DIR__, 2) . '/' . dirname($request['path']));
require basename($request['path']);