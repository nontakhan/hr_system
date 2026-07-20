ALTER TABLE leave_requests
    ADD COLUMN IF NOT EXISTS cancelled_by_user_id INT NULL AFTER cancellation_reason,
    ADD COLUMN IF NOT EXISTS cancelled_by_employee_id INT NULL AFTER cancelled_by_user_id,
    ADD COLUMN IF NOT EXISTS cancelled_by_role VARCHAR(30) NULL AFTER cancelled_by_employee_id,
    ADD COLUMN IF NOT EXISTS cancelled_at DATETIME NULL AFTER cancelled_by_role;

ALTER TABLE day_swap_requests
    ADD COLUMN IF NOT EXISTS cancelled_by_user_id INT NULL AFTER cancellation_reason,
    ADD COLUMN IF NOT EXISTS cancelled_by_employee_id INT NULL AFTER cancelled_by_user_id,
    ADD COLUMN IF NOT EXISTS cancelled_by_role VARCHAR(30) NULL AFTER cancelled_by_employee_id,
    ADD COLUMN IF NOT EXISTS cancelled_at DATETIME NULL AFTER cancelled_by_role;

ALTER TABLE training_requests
    ADD COLUMN IF NOT EXISTS cancelled_by_user_id INT NULL AFTER cancellation_reason,
    ADD COLUMN IF NOT EXISTS cancelled_by_employee_id INT NULL AFTER cancelled_by_user_id,
    ADD COLUMN IF NOT EXISTS cancelled_by_role VARCHAR(30) NULL AFTER cancelled_by_employee_id,
    ADD COLUMN IF NOT EXISTS cancelled_at DATETIME NULL AFTER cancelled_by_role;
