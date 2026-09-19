<?php
// Explicit CLI upgrade. Back up the database and rehearse before deployment.
// Does not create the base HR schema: import the existing database schema first.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('HR_SCHEMA_MIGRATION', true);
require_once __DIR__ . '/../includes/db_connect.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
foreach (['leave','day_swap','training_request','hr_scope','employee_shift_assignment','employee_warning','employee_training'] as $helper) {
    require_once __DIR__ . '/../includes/' . $helper . '_helpers.php';
}
$locked = false;
try {
    $lock = $mysqli->query("SELECT GET_LOCK(CONCAT(DATABASE(), ':hr_schema_upgrade'), 30) AS acquired")->fetch_assoc();
    if ((int)($lock['acquired'] ?? 0) !== 1) throw new RuntimeException('Migration lock unavailable');
    $locked = true;
    $mysqli->query("CREATE TABLE IF NOT EXISTS hr_schema_versions (
        migration_key VARCHAR(80) PRIMARY KEY,
        version INT NOT NULL,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $row = $mysqli->query("SELECT version FROM hr_schema_versions WHERE migration_key = 'runtime_schema'")->fetch_assoc();
    if ((int)($row['version'] ?? 0) >= HR_SCHEMA_VERSION) {
        echo "SCHEMA_ALREADY_READY
";
    } else {
        hrScopeEnsureTable($mysqli);
        hrSchemaEnsureEmployeePostalCode($mysqli);
        leaveEnsureSettingsTable($mysqli);
        leaveEnsurePoliciesTable($mysqli);
        leaveEnsureLeaveTypeCalculationColumns($mysqli);
        leaveEnsureHourlyRequestTypes($mysqli);
        leaveEnsureTwoStepApprovalColumns($mysqli);
        daySwapEnsureTable($mysqli);
        ensureEmployeeTrainingRecordsTable($mysqli);
        trainingRequestEnsureTable($mysqli);
        employeeWarningEnsureTables($mysqli);
        employeeShiftAssignmentsEnsureTable($mysqli);
        hrSchemaEnsureAttendanceOverrides($mysqli);
        $version = HR_SCHEMA_VERSION;
        $stmt = $mysqli->prepare("INSERT INTO hr_schema_versions (migration_key, version) VALUES ('runtime_schema', ?)
            ON DUPLICATE KEY UPDATE version = VALUES(version), applied_at = CURRENT_TIMESTAMP");
        $stmt->bind_param('i', $version);
        $stmt->execute();
        echo "SCHEMA_READY
";
    }
} catch (Throwable $e) {
    // No credentials, SQL values or employee records in console output.
    fwrite(STDERR, "Schema preparation failed (" . get_class($e) . "). Version was not advanced; inspect the schema and rerun after correction.
");
    exit(1);
} finally {
    if ($locked) $mysqli->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(), ':hr_schema_upgrade'))");
}
