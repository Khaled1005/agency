CREATE TABLE tbl_staff_password (
	staff_password_id		SERIAL PRIMARY KEY,
	staff_id			INTEGER NOT NULL REFERENCES tbl_staff (staff_id),
	staff_password		VARCHAR(40),
	staff_password_md5	VARCHAR(32),
	--system fields
	added_by			INTEGER NOT NULL REFERENCES tbl_staff (staff_id),
	added_at			TIMESTAMP(0)     NOT NULL DEFAULT CURRENT_TIMESTAMP,
	changed_by			INTEGER NOT NULL  REFERENCES tbl_staff (staff_id),
	changed_at			TIMESTAMP(0)     NOT NULL DEFAULT CURRENT_TIMESTAMP,
	is_deleted			BOOLEAN NOT NULL DEFAULT FALSE,
	deleted_at			TIMESTAMP(0),
	deleted_by			INTEGER REFERENCES tbl_staff(staff_id),
	deleted_comment		TEXT,
	sys_log			TEXT

--	CONSTRAINT password_security CHECK (staff_password IS NULL OR length(staff_password>5))
);

CREATE VIEW staff_password AS SELECT * FROM tbl_staff_password WHERE NOT is_deleted;

CREATE INDEX index_tbl_staff_password_staff_id ON tbl_staff_password ( staff_id );
CREATE INDEX index_tbl_staff_password_staff_password ON tbl_staff_password ( staff_password );
CREATE INDEX index_tbl_staff_password_staff_password_md5 ON tbl_staff_password ( staff_password_md5 );



