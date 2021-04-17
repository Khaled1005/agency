/*
 * Create report & report usage tables & views
 *
 * The report view needs to be created
 * after report_usage, since
 * it references it.
 */

\i create.l_report_category.sql
\i create.tbl_report.sql
\i create.tbl_report_usage.sql
\i create.view.report.sql

/* Add core/sample reports */
\i add.report.agency_core.sql

/*
 * This code could be used to delete the same items
 */

/*
DROP VIEW report;
DROP VIEW report_usage;
DROP TABLE tbl_report_usage;
DROP TABLE  tbl_report;
DROP TABLE l_report_category;
*/
