ALTER TABLE employees
    ADD COLUMN IF NOT EXISTS postal_code VARCHAR(10) NULL AFTER province;
