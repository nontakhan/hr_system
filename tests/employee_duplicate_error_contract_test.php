<?php
$root = dirname(__DIR__);

function assertContainsText($source, $needle, $message) {
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$api = file_get_contents($root . '/api/employee_api.php');

assertContainsText($api, 'throw new Exception("Update Failed: " . $stmt->error, $stmt->errno)', 'Employee update should preserve statement error code.');
assertContainsText($api, '$e->getCode() === 1062', 'Employee API should translate duplicate key exceptions into a user-facing duplicate-data error.');
