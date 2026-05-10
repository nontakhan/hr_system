<?php
/*
 * MySQL connection bootstrap.
 *
 * Put local credentials in .env, environment variables, or create
 * includes/db_config.php from includes/db_config.example.php.
 */

$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");

        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

$localConfig = __DIR__ . '/db_config.php';
if (is_file($localConfig)) {
    require $localConfig;
}

define('DB_HOST', getenv('HR_DB_HOST') ?: ($db_config['host'] ?? 'localhost'));
define('DB_USER', getenv('HR_DB_USER') ?: ($db_config['user'] ?? ''));
define('DB_PASS', getenv('HR_DB_PASS') ?: ($db_config['pass'] ?? ''));
define('DB_NAME', getenv('HR_DB_NAME') ?: ($db_config['name'] ?? 'hr_system'));

mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_errno) {
    error_log('MySQL connection failed: ' . $mysqli->connect_error);
    http_response_code(500);
    echo 'Database connection failed';
    exit();
}

if (!$mysqli->set_charset('utf8mb4')) {
    error_log('Error loading charset utf8mb4: ' . $mysqli->error);
    http_response_code(500);
    echo 'Database initialization failed';
    exit();
}
?>
