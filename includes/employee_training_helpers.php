<?php

function ensureEmployeeTrainingRecordsTable(mysqli $mysqli): void
{
    $mysqli->query("CREATE TABLE IF NOT EXISTS employee_training_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        training_date DATE NOT NULL,
        course_name VARCHAR(255) NOT NULL,
        result_status VARCHAR(100) NULL,
        certificate_expiry_date DATE NULL,
        attachment_path VARCHAR(255) NULL,
        notes TEXT NULL,
        created_by INT NULL,
        updated_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_employee_training_records_employee (employee_id),
        INDEX idx_employee_training_records_date (training_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
?>
