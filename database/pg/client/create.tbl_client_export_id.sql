CREATE TABLE tbl_client_export_id (
	client_export_id_id		SERIAL PRIMARY KEY,
	client_id				INTEGER REFERENCES tbl_client ( client_id ),
	export_organization_code	VARCHAR(10) NOT NULL REFERENCES tbl_l_export_organization ( export_organization_code ),
	export_id				VARCHAR(20) NOT NULL,
	--system fields
	added_by			INTEGER NOT NULL REFERENCES tbl_staff (staff_id),
	added_at			TIMESTAMP(0)     NOT NULL DEFAULT CURRENT_TIMESTAMP,
	changed_by			INTEGER NOT NULL  REFERENCES tbl_staff (staff_id),
	changed_at			TIMESTAMP(0)     NOT NULL DEFAULT CURRENT_TIMESTAMP,
	is_deleted			BOOLEAN NOT NULL DEFAULT FALSE,
	deleted_at			TIMESTAMP(0),
	deleted_by			INTEGER REFERENCES tbl_staff(staff_id),
	deleted_comment		TEXT,
	sys_log			TEXT,

	UNIQUE ( client_id,export_organization_code )	,
	UNIQUE ( export_organization_code, export_id )
);

CREATE VIEW client_export_id AS SELECT * FROM tbl_client_export_id WHERE NOT is_deleted;



