CREATE TABLE tbl_phone (
	phone_id 		SERIAL PRIMARY KEY,
	client_id		INTEGER NOT NULL REFERENCES tbl_client(client_id),
	phone_date 		DATE NOT NULL,
	phone_date_end 	DATE,
	phone_type_code	VARCHAR(10) NOT NULL DEFAULT 'HOME' REFERENCES tbl_l_phone_type(phone_type_code),
	number		VARCHAR(14) NOT NULL CHECK (number ~ '\\([0-9]{3}\\) [0-9]{3}-[0-9]{4}$'),
	extension		VARCHAR(5),
	comment		TEXT,
	--system fields
	added_by				INTEGER NOT NULL REFERENCES tbl_staff (staff_id),
	added_at				TIMESTAMP(0)     NOT NULL DEFAULT CURRENT_TIMESTAMP,
	changed_by				INTEGER NOT NULL REFERENCES tbl_staff (staff_id),
	changed_at				TIMESTAMP(0)     NOT NULL DEFAULT CURRENT_TIMESTAMP,
	is_deleted				BOOLEAN NOT NULL DEFAULT FALSE,
	deleted_at				TIMESTAMP(0),
	deleted_by				INTEGER REFERENCES tbl_staff (staff_id),
	deleted_comment			TEXT,
	sys_log 				TEXT
);

CREATE OR REPLACE VIEW phone AS
SELECT * FROM tbl_phone WHERE NOT is_deleted;

CREATE INDEX index_tbl_phone_client_id_phone_date ON tbl_phone ( client_id,phone_date );
CREATE INDEX index_tbl_phone_phone_date ON tbl_phone ( phone_date );
