CREATE TABLE IF NOT EXISTS employee_shift_overrides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    day_of_week VARCHAR(32) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    late_tolerance_mins INT NOT NULL DEFAULT 0,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_employee_shift_override_employee (employee_id),
    INDEX idx_employee_shift_override_active_dates (employee_id, is_active, effective_from, effective_to),
    CONSTRAINT fk_employee_shift_override_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
