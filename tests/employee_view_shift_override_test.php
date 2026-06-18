<?php
$source = file_get_contents(__DIR__ . '/../employee_view.php');

function assertContainsText($source, $needle, $message) {
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

assertContainsText($source, 'employee_shift_overrides', 'Employee view should load weekly shift overrides.');
assertContainsText($source, '$shiftOverrideDaysMap', 'Employee view should translate override weekdays for display.');
assertContainsText($source, '$shiftOverrides', 'Employee view should render the loaded weekly shift override rows.');
assertContainsText($source, 'shiftOverrideDayLabels', 'Employee view should build readable weekday labels for each override.');
