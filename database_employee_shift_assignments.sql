CREATE TABLE IF NOT EXISTS employee_shift_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    shift_id INT NOT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    reason TEXT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_employee_shift_assignments_employee_dates (employee_id, effective_from, effective_to),
    KEY idx_employee_shift_assignments_shift (shift_id),
    CONSTRAINT fk_employee_shift_assignments_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_employee_shift_assignments_shift FOREIGN KEY (shift_id) REFERENCES work_shifts(id)
);
