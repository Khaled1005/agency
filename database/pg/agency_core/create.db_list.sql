CREATE TABLE db_list (
	db_name		NAME PRIMARY KEY,
	description		TEXT NOT NULL UNIQUE,
	is_test_db		BOOLEAN NOT NULL,
	primary_url		VARCHAR(150)
);
