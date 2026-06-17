CREATE TABLE IF NOT EXISTS attendance_record_overrides (
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
);
