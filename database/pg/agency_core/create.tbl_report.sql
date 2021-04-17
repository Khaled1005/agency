CREATE TABLE tbl_report (
	report_id	SERIAL PRIMARY KEY,
	report_title	TEXT NOT NULL,
	report_category_code	VARCHAR(10) REFERENCES tbl_l_report_category (report_category_code),
	report_header	TEXT,
	report_footer	TEXT,
	report_comment	TEXT,
	client_page	VARCHAR (255),
	
	allow_output_screen	BOOLEAN DEFAULT TRUE,
	allow_output_spreadsheet	BOOLEAN DEFAULT TRUE,
	override_sql_security	BOOLEAN DEFAULT FALSE,
	rows_per_page	INTEGER,
	output		TEXT,
	report_permission VARCHAR(80),
	sql		TEXT NOT NULL,
	variables	TEXT,

        --system fields
        added_by                        INTEGER NOT NULL REFERENCES tbl_staff (staff_id),
        added_at                        TIMESTAMP(0)     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        changed_by                      INTEGER NOT NULL  REFERENCES tbl_staff (staff_id),
        changed_at                      TIMESTAMP(0)     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        is_deleted                      BOOLEAN NOT NULL DEFAULT FALSE,
        deleted_at                      TIMESTAMP(0) CHECK ((NOT is_deleted AND deleted_at IS NULL) OR (is_deleted AND deleted_at IS NOT NULL))
,
        deleted_by                      INTEGER REFERENCES tbl_staff(staff_id)
                                                  CHECK ((NOT is_deleted AND deleted_by IS NULL) OR (is_deleted AND deleted_by IS NOT NULL)),
        deleted_comment         TEXT,
        sys_log                 TEXT

/*
  These should be arrays, or child tables:

	PERMISSION
	OUTPUT
	SQL
	VARIABLES
*/
);

