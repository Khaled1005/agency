/*
Simple Client Report
*/

INSERT INTO tbl_report (report_title, report_category_code, report_header, report_footer, report_comment, client_page, allow_output_screen, allow_output_spreadsheet, override_sql_security, rows_per_page, output, report_permission, sql, variables, added_by, added_at, changed_by, changed_at, is_deleted, deleted_at, deleted_by, deleted_comment, sys_log) VALUES ('Simple client report', 'GENERAL', 'Simple Client Report
Sorted by $order_label
', NULL, 'This is a a very simple client report', NULL, true, true, false, NULL, NULL, NULL, 'SELECT client_id,dob,ssn FROM client ORDER BY $order', 'PICK order "Sort results by"
name_last,name_first "Client Name"
dob "Date of Birth"
ssn "Social Security Number"
ENDPICK
', 1, current_timestamp, 1, current_timestamp, false, NULL, NULL, NULL, NULL);

