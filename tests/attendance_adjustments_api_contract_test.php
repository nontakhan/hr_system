<?php
$source = file_get_contents(__DIR__ . '/../api/attendance_api.php');

function assertContainsText($source, $needle, $message) {
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL . 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

assertContainsText($source, "attendance_record_overrides", "API should read/write attendance override rows.");
assertContainsText($source, "function attendanceEnsureOverrideTable", "API should lazily ensure override table exists.");
assertContainsText($source, "function fetchAttendanceOverridesForMonth", "Reports should load override rows by month.");
assertContainsText($source, "attendanceApplyRecordOverride", "Reports should merge override values before evaluating status.");
assertContainsText($source, "\$action === 'adjustment_employees'", "API should expose adjustment employee lookup.");
assertContainsText($source, "\$action === 'adjustment_filter_options'", "API should expose company, branch, and position filter options.");
assertContainsText($source, "'company_id' => (int)\$row['company_id']", "Branch filter options should carry company_id for dependent company/branch dropdowns.");
assertContainsText($source, "\$action === 'save_adjustment'", "API should expose single adjustment save.");
assertContainsText($source, "\$action === 'save_bulk_adjustments'", "API should expose bulk adjustment save.");
assertContainsText($source, "hrScopeBuildEmployeeWhereClause", "API should reuse HR scope helper for adjustment authorization.");
assertContainsText($source, "begin_transaction", "Bulk adjustment saves should use a transaction.");
assertContainsText($source, "commit()", "Bulk adjustment saves should commit only after all rows are valid.");
assertContainsText($source, "rollback()", "Bulk adjustment saves should rollback on failure.");
assertContainsText($source, "override_reason", "Report rows should include override metadata.");

echo "attendance_adjustments_api_contract_test passed" . PHP_EOL;
