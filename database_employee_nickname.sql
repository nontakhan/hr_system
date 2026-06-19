ALTER TABLE employees
    ADD COLUMN IF NOT EXISTS nickname VARCHAR(100) NULL AFTER last_name_th;
