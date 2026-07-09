-- Normalize accidentally stored Buddhist Era dates back to Gregorian dates.
-- Safe to run more than once: it only updates date values with years greater than 2400.

UPDATE leave_requests
SET
    start_date = CASE
        WHEN start_date IS NOT NULL AND start_date >= '2400-01-01' THEN DATE_SUB(start_date, INTERVAL 543 YEAR)
        ELSE start_date
    END,
    end_date = CASE
        WHEN end_date IS NOT NULL AND end_date >= '2400-01-01' THEN DATE_SUB(end_date, INTERVAL 543 YEAR)
        ELSE end_date
    END
WHERE start_date >= '2400-01-01'
   OR end_date >= '2400-01-01';

UPDATE training_requests
SET
    start_date = CASE
        WHEN start_date IS NOT NULL AND start_date >= '2400-01-01' THEN DATE_SUB(start_date, INTERVAL 543 YEAR)
        ELSE start_date
    END,
    end_date = CASE
        WHEN end_date IS NOT NULL AND end_date >= '2400-01-01' THEN DATE_SUB(end_date, INTERVAL 543 YEAR)
        ELSE end_date
    END
WHERE start_date >= '2400-01-01'
   OR end_date >= '2400-01-01';

UPDATE employees
SET
    start_date = CASE
        WHEN start_date IS NOT NULL AND start_date >= '2400-01-01' THEN DATE_SUB(start_date, INTERVAL 543 YEAR)
        ELSE start_date
    END,
    birth_date = CASE
        WHEN birth_date IS NOT NULL AND birth_date >= '2400-01-01' THEN DATE_SUB(birth_date, INTERVAL 543 YEAR)
        ELSE birth_date
    END
WHERE start_date >= '2400-01-01'
   OR birth_date >= '2400-01-01';
