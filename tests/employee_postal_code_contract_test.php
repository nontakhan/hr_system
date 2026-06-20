<?php
$root = dirname(__DIR__);

function assertContainsText($source, $needle, $message) {
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$schemaPath = $root . '/database_employee_postal_code.sql';
$schema = file_exists($schemaPath) ? file_get_contents($schemaPath) : '';
$addForm = file_get_contents($root . '/employee_add.php');
$editForm = file_get_contents($root . '/employee_edit.php');
$viewPage = file_get_contents($root . '/employee_view.php');
$api = file_get_contents($root . '/api/employee_api.php');

assertContainsText($schema, 'ADD COLUMN IF NOT EXISTS postal_code', 'Postal code schema should add employees.postal_code.');
assertContainsText($addForm, 'name="postal_code"', 'Employee add form should submit postal code.');
assertContainsText($editForm, 'name="postal_code"', 'Employee edit form should submit postal code.');
assertContainsText($editForm, '$emp[\'postal_code\']', 'Employee edit form should preload postal code.');
assertContainsText($api, 'getVal($data, \'postal_code\')', 'Employee API should read posted postal code.');
assertContainsText($api, 'postal_code', 'Employee API should insert and update postal code.');
assertContainsText($api, 'function ensureEmployeePostalCodeColumn', 'Employee API should define a lazy postal code schema guard.');
assertContainsText($api, 'ensureEmployeePostalCodeColumn($mysqli);', 'Employee API should ensure employees.postal_code exists before save queries.');
assertContainsText($viewPage, '$emp[\'postal_code\']', 'Employee view should display postal code.');
