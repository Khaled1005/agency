CREATE TABLE tbl_client_note (
	client_note_id		SERIAL PRIMARY KEY,
	is_front_page		BOOLEAN NOT NULL,
	client_id			INTEGER NOT NULL REFERENCES tbl_client(client_id),
	note				TEXT NOT NULL,
	flag_entry_codes		VARCHAR[],
	--system fields
	added_by			INTEGER NOT NULL REFERENCES tbl_staff (staff_id),
	added_at			TIMESTAMP(0)     NOT NULL DEFAULT CURRENT_TIMESTAMP,
	changed_by			INTEGER NOT NULL  REFERENCES tbl_staff (staff_id),
	changed_at			TIMESTAMP(0)     NOT NULL DEFAULT CURRENT_TIMESTAMP,
	is_deleted			BOOLEAN NOT NULL DEFAULT FALSE,
	deleted_at			TIMESTAMP(0) CHECK ((NOT is_deleted AND deleted_at IS NULL) OR (is_deleted AND deleted_at IS NOT NULL)),
	deleted_by			INTEGER REFERENCES tbl_staff(staff_id)
						  CHECK ((NOT is_deleted AND deleted_by IS NULL) OR (is_deleted AND deleted_by IS NOT NULL)),
	deleted_comment		TEXT,
	sys_log			TEXT
);

CREATE OR REPLACE VIEW client_note AS
SELECT * FROM tbl_client_note WHERE NOT is_deleted;
