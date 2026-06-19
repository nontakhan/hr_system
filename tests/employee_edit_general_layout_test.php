<?php
$source = file_get_contents(__DIR__ . '/../employee_edit.php');

function assertContainsText($source, $needle, $message) {
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

assertContainsText($source, 'employee-general-card', 'Employee edit personal info should use the refined card class.');
assertContainsText($source, 'employee-general-grid', 'Employee edit personal info should use the refined grid layout.');
assertContainsText($source, 'field-span-3', 'Employee edit personal info should support intentional wide fields.');
assertContainsText($source, 'employee-section-note', 'Employee edit personal info should explain the grouped section briefly.');
