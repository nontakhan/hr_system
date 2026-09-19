<?php
// Runtime schema is prepared by scripts/prepare_schema.php before deployment.
const HR_SCHEMA_VERSION = 1;

function hrSchemaMigrationEnabled(): bool
{
    return PHP_SAPI === 'cli' && defined('HR_SCHEMA_MIGRATION') && HR_SCHEMA_MIGRATION === true;
}

function hrSchemaMigrationStep(mysqli $mysqli, string $step): bool
{
    if (!hrSchemaMigrationEnabled()) return false;
    static $completed;
    $completed ??= new WeakMap();
    $steps = $completed[$mysqli] ?? [];
    if (isset($steps[$step])) return false;
    $steps[$step] = true;
    $completed[$mysqli] = $steps;
    return true;
}

function hrSchemaAssertReady(mysqli $mysqli): void
{
    if (hrSchemaMigrationEnabled()) return;
    static $ready;
    $ready ??= new WeakMap();
    if (isset($ready[$mysqli])) return;
    $result = $mysqli->query("SELECT version FROM hr_schema_versions WHERE migration_key = 'runtime_schema'");
    $row = $result ? $result->fetch_assoc() : null;
    if ((int)($row['version'] ?? 0) < HR_SCHEMA_VERSION) {
        throw new RuntimeException('Run scripts/prepare_schema.php before serving this release.');
    }
    $ready[$mysqli] = true;
}

function hrSchemaEnsureAttendanceOverrides(mysqli $mysqli) {
    if (!hrSchemaMigrationStep($mysqli, __FUNCTION__)) return;
    $sql = "CREATE TABLE IF NOT EXISTS attendance_record_overrides (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        work_date DATE NOT NULL,
        override_check_in TIME NULL,
        override_check_out TIME NULL,
        reason TEXT NOT NULL,
        created_by INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_by INT NULL,
        updated_at DATETIME NULL,
        UNIQUE KEY uniq_attendance_override_employee_date (employee_id, work_date),
        KEY idx_attendance_override_work_date (work_date),
        KEY idx_attendance_override_created_by (created_by)
    )";
    if (!$mysqli->query($sql)) {
        throw new Exception('Create attendance override table failed: ' . $mysqli->error);
    }
}

function hrSchemaEnsureEmployeePostalCode(mysqli $mysqli): void
{
    if (!hrSchemaMigrationStep($mysqli, __FUNCTION__)) return;
    $result = $mysqli->query("SHOW COLUMNS FROM employees LIKE 'postal_code'");
    if ($result && $result->num_rows > 0) {
        return;
    }

    if (!$mysqli->query("ALTER TABLE employees ADD COLUMN postal_code VARCHAR(10) NULL AFTER province")) {
        throw new Exception('Ensure employees.postal_code failed: ' . $mysqli->error);
    }
}
