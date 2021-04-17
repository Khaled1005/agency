CREATE VIEW report AS (
    SELECT tbl_report.*,
            generated_by AS last_generated_by,
            generated_at AS last_generated_at
    FROM tbl_report
        LEFT JOIN (SELECT DISTINCT ON (report_id) report_id,generated_at,generated_by FROM report_usage) foo USING (report_id)
    WHERE NOT is_deleted
);

