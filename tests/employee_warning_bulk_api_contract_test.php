<?php

$root = dirname(__DIR__);
$api = file_get_contents($root . '/api/employee_warning_api.php');
$bulk = file_get_contents($root . '/includes/employee_warning_bulk_helpers.php');

function requireBulkText(string $source, string $needle, string $message): void
{
    if (strpos($source, $needle) === false) {
        throw new RuntimeException($message . ': ' . $needle);
    }
}

foreach (['bulk_create'] as $action) {
    requireBulkText($api, $action, 'Warning API must expose bulk action');
}
requireBulkText($api, 'employeeWarningRequireHr($role)', 'Bulk actions must retain the role gate');
requireBulkText($bulk, 'employeeWarningEmployeeScopeClause', 'Bulk source resolution must reapply HR scope through the shared scope helper');
requireBulkText($bulk, "date('Y-m-d')", 'Bulk warnings must use the server date');
requireBulkText($bulk, 'begin_transaction', 'Bulk creation must start a transaction');
requireBulkText($bulk, 'commit()', 'Bulk creation must commit successful work');
requireBulkText($bulk, 'rollback()', 'Bulk creation must roll back invalid work');
requireBulkText($bulk, 'uq_employee_warnings_source', 'Duplicate-key handling must recognize the source uniqueness rule');
requireBulkText($bulk, 'INSERT INTO employee_warnings', 'Bulk creation must insert warning records');
requireBulkText($bulk, 'source_type, source_key, source_event_date', 'Bulk insert must persist trusted source metadata');
requireBulkText($bulk, 'attendanceResolveMissingWarningSource', 'Bulk creation must use the non-controller missing resolver');
requireBulkText($bulk, 'attendanceResolveLateEarlyWarningSource', 'Bulk creation must use the non-controller late/early resolver');
requireBulkText($bulk, 'shared_note', 'Bulk creation must accept one shared note');
requireBulkText($bulk, 'employeeWarningAppendSharedNote', 'Bulk creation must append the shared note to trusted details');

echo "employee warning bulk API contract ok\n";
