
CREATE VIEW residence_own_current AS
SELECT * from residence_own
WHERE residence_date <= current_date
AND residence_date_end IS NULL
ORDER BY residence_own_id;











