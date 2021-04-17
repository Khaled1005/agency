CREATE OR REPLACE FUNCTION alert_notify() RETURNS trigger AS $$
DECLARE
    textMessage text;
    textSubject text;
    textSender  text;
    textRecipient text;
    emailAlert boolean;
    alert_url  text;
    url_base text;
    change_opt_url text;
    change_opt_blurb text;

BEGIN
     SELECT INTO emailAlert opt_alerts_email FROM user_option WHERE staff_id=NEW.staff_id;
     IF emailAlert THEN
          SELECT INTO url_base agency_base_url();
          SELECT INTO textRecipient staff_email FROM staff WHERE staff_id=NEW.staff_id;
          change_opt_url := url_base || E'display.php?control\%5baction\%5d=view&control\%5bobject\%5d=user_option&control\%5bformat\%5d=&control\%5bid\%5d=' || NEW.staff_id;
          change_opt_blurb := E'You have received this email because your AGENCY user options are set to send an email whenever you receive a AGENCY alert.  If you prefer not to receive emails about your alerts, use the link below to change your settings:\n\n\"' || change_opt_url || '"';
          textSender='AGENCY SYSTEM <No_Email@xxx.org>';
          IF (UPPER(NEW.ref_table)='LOG') THEN
               alert_url := url_base || 'log_browse.php?action=show&id=' || NEW.ref_id;
               textSubject := get_db_name()||' Alert: ' || INITCAP(NEW.ref_table) || ' ' || NEW.ref_id;
          ELSE 
               alert_url := url_base || E'display.php?control\%5baction\%5d=view&control\%5bobject\%5d=alert&control%5bformat%5d=&control\%5bid\%5d=' 
                    || NEW.alert_id;
               textSubject := get_db_name()||' Alert: ' || INITCAP(NEW.ref_table) || ' Record ' || NEW.ref_id;
          END IF;
          textMessage := E'You have received an AGENCY alert\n\n'
                    || alert_url || E'\n\n'
                    || change_opt_blurb;
          IF (is_test_db() IS FALSE) THEN
               perform pgmail(textSender,textRecipient,textSubject, textMessage);
          END IF;
    end if;
    return NEW;
END;$$ language 'plpgsql';

CREATE OR REPLACE FUNCTION verify_alert_notify() RETURNS trigger AS $$
#check for existence of table, and, if applicable, column

     spi_exec "SELECT oid FROM pg_class WHERE relname ~ '^$NEW(alert_object)$'"

     if {![info exists oid]} {
          elog ERROR "Must use valid objects in tbl_alert_notify. $NEW(alert_object) does not exist."
     }

     if {[info exists NEW(alert_notify_field)]} {
          spi_exec "SELECT a.attrelid AS col FROM pg_catalog.pg_attribute a
               WHERE a.attrelid = $oid AND a.attname ~ '^$NEW(alert_notify_field)$' AND NOT a.attisdropped"
          if {![info exists col]} {
               elog ERROR "$NEW(alert_notify_field) does not exist in $NEW(alert_object)"
          }
     }

     return [array get NEW]
$$ LANGUAGE pltcl;

CREATE OR REPLACE FUNCTION alert_notify_enable(varchar,varchar) RETURNS boolean AS $$
		if {[info exists 1]} {
			set TABLE $1
		} else {
			elog ERROR "no table passed to alert_notify()"
			return false
		}
		if {[info exists 2]} {
			set CUSTOM_COLUMN  $2
		} else {
			set CUSTOM_COLUMN ""
		}
        set cre_exec  "CREATE TRIGGER ${TABLE}_alert_notify
        AFTER INSERT OR UPDATE OR DELETE ON ${TABLE}
        FOR EACH ROW EXECUTE PROCEDURE table_alert_notify(${CUSTOM_COLUMN})"
        spi_exec $cre_exec
        return true
$$ LANGUAGE pltcl;

CREATE OR REPLACE FUNCTION alert_notify_enable(varchar) RETURNS BOOLEAN AS $$
DECLARE table ALIAS FOR $1;
BEGIN
	RETURN alert_notify_enable($1,'');
END;
$$ LANGUAGE plpgsql;


CREATE OR REPLACE FUNCTION table_alert_notify() RETURNS trigger AS $$

     spi_exec "SELECT relname AS table FROM pg_class WHERE oid=$TG_relid"

     regsub "^tbl_" $table "" object
     set action $TG_op

     if {[info exists 1]} { 

          #odd-ball primary key fields can be passed during trigger creation
          set object_id_column $1

     } else {

          set id "_id"
          set object_id_column "$object$id"

     }

     if {[array exists NEW] } {

          # $NEW and $OLD are always passed, sometimes empty (NEW is empty
          # for deletes, while OLD is empty for INSERTS

          array set record [array get NEW]

     } else {

          array set record [array get OLD]

     }

     set object_id $record($object_id_column)

     #get staff and insert alert
     set staff [list]
     spi_exec -array notify_recs "SELECT staff_id, alert_notify_field, alert_notify_value FROM alert_notify_current 
		LEFT JOIN staff USING (staff_id)
		WHERE alert_object='$object' 
 			AND alert_notify_action_code IN ('$action','ANY') 
			AND is_active" {

          if {[info exists notify_recs(alert_notify_field)]} {

               #
               # field is set, determine if value matches
               #

               if {[info exists record($notify_recs(alert_notify_field))] 
                    && [info exists notify_recs(alert_notify_value)]} { #value match

                    if { $record($notify_recs(alert_notify_field)) == $notify_recs(alert_notify_value) } {

                         lappend staff $notify_recs(staff_id)

                    }

               } elseif {![info exists record($notify_recs(alert_notify_field))] 
                    && ![info exists notify_recs(alert_notify_value)]} { #null value match

                    lappend staff $notify_recs(staff_id)

               }

          } else {

               lappend staff $notify_recs(staff_id)

          }
     }

     if {$action=="INSERT"} {

          set human_action "Added"

     } elseif {$action=="UPDATE"} {

          set human_action "Edited"

     } else {

          set human_action "Deleted"

     }

     set alert_subject "$object $object_id has been $human_action"
     set alert_text "$alert_subject\n\n\nThis alert was auto-generated by an alert_notify record.\nThese can be modified from your staff page"

     foreach x [lsort -unique $staff] { #only need unique staff

          spi_exec "INSERT INTO tbl_alert (
                staff_id,
                ref_table,
                ref_id,
                alert_subject,
                alert_text,
                added_by,
                changed_by
          ) VALUES ($x,'$object',$object_id,'$alert_subject','$alert_text',sys_user(),sys_user())"

     }

    return [array get record]

$$ LANGUAGE pltcl;

CREATE OR REPLACE FUNCTION get_alert_text(INTEGER,TEXT,INTEGER) RETURNS text AS $$
DECLARE
     sid    ALIAS FOR $1;
     rtab   ALIAS FOR $2;
     rid    ALIAS FOR $3;
     t_text TEXT;
     tt_text TEXT;
     t_sub  TEXT;
     alrt   RECORD;
     first  BOOLEAN;
BEGIN

     first := true;
     FOR alrt IN SELECT * FROM alert WHERE staff_id=sid AND ref_table=rtab AND ref_id=rid
               ORDER BY alert_id DESC
     LOOP
          tt_text := alrt.alert_text;
          IF (NOT first) THEN
                tt_text := '====='||COALESCE(alrt.alert_subject,'')||E'=====\n'
                    ||'From: '||staff_name(alrt.added_by)||', at: '||to_char(alrt.added_at,'MM/DD/YYYY HH:MI a.m.')||E'\n'||tt_text;
          ELSE
               first := false;
          END IF;
          t_text := COALESCE(t_text||E'\n\n\n','') || tt_text;
     END LOOP;

     RETURN t_text;
END;$$ LANGUAGE plpgsql STABLE;
