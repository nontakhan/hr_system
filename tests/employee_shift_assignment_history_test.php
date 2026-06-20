<?php
$root = dirname(__DIR__);

function assertContainsText($source, $needle, $message) {
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function assertFileExistsForTest($path, $message) {
    if (!file_exists($path)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing file: ' . $path . PHP_EOL);
        exit(1);
    }
}

$helperPath = $root . '/includes/employee_shift_assignment_helpers.php';
assertFileExistsForTest($helperPath, 'Shift assignment helper should exist.');
$helper = file_get_contents($helperPath);

assertContainsText($helper, 'employee_shift_assignments', 'Helper should own the employee shift assignment table.');
assertContainsText($helper, 'employeeShiftAssignmentsEnsureTable', 'Helper should expose a table ensure function.');
assertContainsText($helper, 'employeeShiftAssignmentsBackfillCurrentDefaults', 'Helper should backfill current shifts for existing employees.');
assertContainsText($helper, 'employeeShiftAssignmentsSyncCurrent', 'Helper should sync current shift assignments.');
assertContainsText($helper, 'employeeShiftAssignmentsFetchForMonth', 'Helper should fetch monthly shift assignments.');
assertContainsText($helper, 'employeeShiftAssignmentsResolveForDate', 'Helper should resolve shift by work date.');
assertContainsText($helper, 'effective_from', 'Assignments should have effective_from dates.');
assertContainsText($helper, 'effective_to', 'Assignments should have effective_to dates.');

$attendanceApi = file_get_contents($root . '/api/attendance_api.php');
assertContainsText($attendanceApi, 'employee_shift_assignment_helpers.php', 'Attendance API should load shift assignment helpers.');
assertContainsText($attendanceApi, 'employeeShiftAssignmentsFetchForMonth', 'Attendance reports should fetch monthly shift assignments.');
assertContainsText($attendanceApi, 'employeeShiftAssignmentsResolveForDate', 'Attendance rows should resolve shift by work date.');

$employeeApi = file_get_contents($root . '/api/employee_api.php');
assertContainsText($employeeApi, 'employee_shift_assignment_helpers.php', 'Employee API should load shift assignment helpers.');
assertContainsText($employeeApi, 'employeeShiftAssignmentsSyncCurrent', 'Employee API should sync shift assignment history on create/update.');
assertContainsText($employeeApi, 'shift_effective_from', 'Employee API should accept HR selected shift effective date.');
assertContainsText($employeeApi, 'shift_assignment_reason', 'Employee API should accept shift assignment reason.');

$employeeAdd = file_get_contents($root . '/employee_add.php');
assertContainsText($employeeAdd, 'shift_effective_from', 'Employee add form should post the initial shift effective date.');
assertContainsText($employeeAdd, 'data-shift-effective-from', 'Employee add form should link shift effective date to start date.');

$employeeEdit = file_get_contents($root . '/employee_edit.php');
assertContainsText($employeeEdit, 'shift_effective_from', 'Employee edit form should let HR choose when a shift change starts.');
assertContainsText($employeeEdit, 'shift_assignment_reason', 'Employee edit form should collect a shift change reason.');
assertContainsText($employeeEdit, 'employeeShiftAssignmentsFetchHistory', 'Employee edit form should show shift assignment history.');

echo "Employee shift assignment history source checks passed." . PHP_EOL;
