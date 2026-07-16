ALTER TABLE employee_warnings
    ADD COLUMN source_type VARCHAR(50) NULL AFTER detail,
    ADD COLUMN source_key VARCHAR(100) NULL AFTER source_type,
    ADD COLUMN source_event_date DATE NULL AFTER source_key,
    ADD UNIQUE KEY uq_employee_warnings_source (source_type, source_key);
