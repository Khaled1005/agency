/*
Simple Staff Report
*/

INSERT INTO tbl_report (report_title, report_category_code, report_header, report_footer, report_comment, client_page, allow_output_screen, allow_output_spreadsheet, override_sql_security, rows_per_page, output, report_permission, sql, variables, added_by, added_at, changed_by, changed_at, is_deleted, deleted_at, deleted_by, deleted_comment, sys_log) VALUES ('Simple staff report', 'GENERAL', 'Simple staff report', NULL, NULL, NULL, true, true, false, NULL, NULL, NULL, 'SELECT staff_id,staff_program(staff_id),staff_project(staff_id) FROM staff', NULL, 1, current_timestamp, 1, current_timestamp, false, NULL, NULL, NULL, NULL);

INSERT INTO tbl_report (report_title, report_category_code, report_header, report_footer, report_comment, client_page, allow_output_screen, allow_output_spreadsheet, override_sql_security, rows_per_page, output, report_permission, sql, variables, added_by, changed_by, is_deleted, deleted_at, deleted_by, deleted_comment, sys_log) VALUES ('Permissions Assigned', 'GENERAL', 'AGENCY Permissions Report', NULL, 'This report lists all the permissions that have been assigned, from the permission table', NULL, true, true, false, NULL, NULL, 'admin', 'SELECT 
  permission_date,
  permission_basis,
  permission_type_code,
  CASE WHEN permission_read THEN ''R'' ELSE '' '' END 
  || CASE WHEN permission_write THEN ''W'' ELSE '' '' END 
  || CASE WHEN permission_super THEN ''S'' ELSE '' '' END AS rws, 
  comment,
  agency_program_code,
  agency_project_code,
  staff_position_code,
  staff_id
  FROM permission', NULL, sys_user(), sys_user(), false, NULL, NULL, NULL, NULL);


