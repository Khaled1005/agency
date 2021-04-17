CREATE TABLE tbl_alert_notify (
	alert_notify_id			SERIAL PRIMARY KEY,
	staff_id				INTEGER NOT NULL REFERENCES tbl_staff ( staff_id ),
	alert_object			NAME NOT NULL,
	alert_notify_action_code	VARCHAR(10) NOT NULL REFERENCES tbl_l_alert_notify_action ( alert_notify_action_code ),
	alert_notify_date			DATE NOT NULL DEFAULT CURRENT_DATE,	
	alert_notify_date_end		DATE,
	alert_notify_field		NAME,
	alert_notify_value		TEXT,
	alert_notify_reason		TEXT,
	comments				TEXT,
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
);

CREATE VIEW alert_notify AS SELECT * FROM tbl_alert_notify WHERE NOT is_deleted;

CREATE VIEW alert_notify_current AS SELECT * FROM alert_notify 
WHERE alert_notify_date <= CURRENT_DATE AND (alert_notify_date_end IS NULL OR alert_notify_date_end >= CURRENT_DATE);

CREATE INDEX index_tbl_alert_notify_dates_and_such ON tbl_alert_notify (alert_notify_date,alert_notify_date_end,alert_notify_action_code);
CREATE INDEX index_tbl_alert_notify_action_code ON tbl_alert_notify (alert_notify_action_code);
CREATE INDEX index_tbl_alert_notify_staff_id ON tbl_alert_notify (staff_id);

CREATE TRIGGER alert_notify_insert /* verify valid object [and column] names */
	BEFORE INSERT ON tbl_alert_notify FOR EACH ROW EXECUTE PROCEDURE verify_alert_notify();

