

CREATE VIEW  bar_current AS
SELECT * from bar
WHERE bar_date <= current_date
AND COALESCE(bar_date_end,current_date)>= current_date;