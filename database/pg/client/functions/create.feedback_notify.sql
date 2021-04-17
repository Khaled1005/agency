-------------------------------------
--
--        Feedback alert (copied from Jail/Hospital stuff)
--
-------------------------------------

CREATE OR REPLACE FUNCTION feedback_insert() RETURNS trigger AS '
DECLARE
  notify integer[2] := ARRAY[83,923,543];
  ref_tab TEXT := ''feedback'';
  alert_sub TEXT;
  alert_txt TEXT;
  feed_id integer;
  s integer;

BEGIN
  alert_sub  := staff_name(NEW.added_by) || '' has submitted feedback. '';
  alert_txt  := ''The Record ID # is '' || NEW.feedback_id;
	FOR s IN 1..3 LOOP
		INSERT INTO tbl_alert (
			staff_id,
			ref_table,
			ref_id,
			alert_subject,
			alert_text,
			added_by,
			changed_by
		)
		VALUES (notify[s],ref_tab,NEW.feedback_id,alert_sub,alert_txt,sys_user(),sys_user());
	END LOOP;
        RETURN NEW;
END;' language 'plpgsql';

CREATE TRIGGER feedback_insert
    AFTER INSERT ON tbl_feedback FOR EACH ROW
    EXECUTE PROCEDURE feedback_insert();

