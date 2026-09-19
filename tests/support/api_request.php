<?php
if (PHP_SAPI !== 'cli' || !str_starts_with((string)getenv('HR_DB_NAME'), 'hr_perf_fixture_')) exit(2);
$api = basename($argv[1]);
$_GET = json_decode($argv[2], true);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/api/' . $api;
session_start();
$_SESSION = json_decode($argv[3], true);
$sessionFile = session_save_path() . '/sess_' . session_id();
register_shutdown_function(function () use ($sessionFile) { if (is_file($sessionFile)) unlink($sessionFile); });
chdir(__DIR__ . '/../../api');
require $api;
