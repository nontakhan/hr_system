<?php
$root = dirname(__DIR__);

function assertContainsText($source, $needle, $message) {
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$schema = file_get_contents($root . '/database_employee_nickname.sql');
$addForm = file_get_contents($root . '/employee_add.php');
$editForm = file_get_contents($root . '/employee_edit.php');
$api = file_get_contents($root . '/api/employee_api.php');

assertContainsText($schema, 'ADD COLUMN IF NOT EXISTS nickname', 'Nickname schema should add employees.nickname.');
assertContainsText($addForm, 'name="nickname"', 'Employee add form should submit nickname.');
assertContainsText($editForm, 'name="nickname"', 'Employee edit form should submit nickname.');
assertContainsText($editForm, '$emp[\'nickname\']', 'Employee edit form should preload nickname.');
assertContainsText($api, 'getVal($data, \'nickname\')', 'Employee API should read posted nickname.');
assertContainsText($api, 'nickname', 'Employee API should insert and update nickname.');
