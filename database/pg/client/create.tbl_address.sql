CREATE TABLE tbl_address (
	address_id		SERIAL PRIMARY KEY,
	client_id 		INTEGER REFERENCES tbl_client (client_id),
	staff_id 		INTEGER REFERENCES tbl_staff (staff_id),
	address_date	DATE NOT NULL,
	address_date_end	DATE,
	address1		VARCHAR(80),
	address2		VARCHAR(80),
	city			VARCHAR(80),
	state_code		VARCHAR(3),
	zipcode			VARCHAR(10),
	address_comment TEXT,
	added_by     INTEGER NOT NULL REFERENCES tbl_staff (staff_id),
	added_at     TIMESTAMP(0)     NOT NULL DEFAULT CURRENT_TIMESTAMP,
	changed_by     INTEGER NOT NULL  REFERENCES tbl_staff (staff_id),
	changed_at     TIMESTAMP(0)     NOT NULL DEFAULT CURRENT_TIMESTAMP,
	is_deleted    BOOLEAN NOT NULL DEFAULT FALSE,
	deleted_at   TIMESTAMP(0),
	deleted_by   INTEGER REFERENCES tbl_staff(staff_id),
	deleted_comment TEXT,
	sys_log TEXT

	CONSTRAINT address_has_exactly_one_id CHECK (COALESCE(staff_id,client_id) IS NOT NULL AND NOT (staff_id is NOT NULL AND client_id IS NOT NULL))
);

CREATE INDEX index_tbl_address_client_id ON tbl_address ( client_id );
CREATE INDEX index_tbl_address_staff_id ON tbl_address ( staff_id );

CREATE VIEW address AS (SELECT * FROM tbl_address WHERE NOT is_deleted);
CREATE VIEW address_current AS (SELECT * FROM address WHERE COALESCE(address_date_end,current_date) >= current_date);

CREATE VIEW address_client AS (
	SELECT address_id,
			client_id,
			address_date,
			address_date_end,
			address1,
			address2,
			city,
			state_code,
			zipcode,
			added_by,
			added_at,
			changed_by,
			changed_at,
			is_deleted,
			deleted_at,
			deleted_by,
			deleted_comment,
			sys_log
	FROM address
	WHERE client_id IS NOT NULL
UNION
	SELECT NULL AS address_id,
		client_id,
		residence_date AS address_date,
		residence_date_end AS address_date_end,
		address1,
		address2,
		city,
		state_code,
		zipcode,
		ro.added_by,
		ro.added_at,
		ro.changed_by,
		ro.changed_at,
		ro.is_deleted,
		ro.deleted_at,
		ro.deleted_by,
		ro.deleted_comment,
		ro.sys_log
	FROM residence_own ro
	LEFT JOIN l_housing_project USING (housing_project_code)
	WHERE COALESCE(residence_date_end,current_date) >= current_date
);

CREATE VIEW address_client_current AS (SELECT * FROM address_client WHERE COALESCE(address_date_end,current_date) >= current_date);

CREATE VIEW address_staff AS (
	SELECT address_id,
			staff_id,
			address_date,
			address_date_end,
			address1,
			address2,
			city,
			state_code,
			zipcode,
			added_by,
			added_at,
			changed_by,
			changed_at,
			is_deleted,
			deleted_at,
			deleted_by,
			deleted_comment,
			sys_log
	FROM address
	WHERE staff_id IS NOT NULL);

CREATE VIEW address_staff_current AS (SELECT * FROM address_staff WHERE COALESCE(address_date_end,current_date) >= current_date);

