/*
 *
 * Creates a 'system user' and a 'super user'
 *
 * Edit passwords and names accordingly
 *
 */

INSERT INTO tbl_staff(
    username,
    name_last,
    name_first,
    is_active,
    gender_code,
    login_allowed,
    added_by,
    changed_by)

/* This user is meant for automated tasks (it shouldn't need to be changed), and won't be allowed to log in via the web interface */
SELECT 'sys_user', --username
	'USER', --last name
	'SYSTEM', -- first name
	true, --is_active
	'8', --gender_code (leave as 8)
	false, --login_allowed
	1,    --added_by
	1    --changed_by

UNION ALL

/* This is the system administator account, and should be edited to reflect real values */

SELECT 
	'super_user', --change to desired username
	'USER',       --change to sys admin's last name
	'SUPER',      --change to sys admin's first name
	true,   
	'8',          --change to sys admin's gender (complete list in create.l_gender.sql, or 1 for Female, 2 for Male
	true,
	1,
	1
;

-- Uncomment one of these lines, depending on which type of password you want
-- (See http://www.desc.org/chasers_wiki/index.php/Chasers_config#passwords for more details)

--INSERT INTO tbl_staff_password (staff_id,staff_password,added_by,changed_by) VALUES (2,flipbits('PASSWORD'),1,1);
INSERT INTO tbl_staff_password (staff_id,staff_password_md5,added_by,changed_by) 
	VALUES (2,md5('PASSWORD' /*CHANGE THIS TO THE DESIRED PASSWORD */),1,1);


-- This file shouldn't need to be edited below here. --

/* system user function to return sys_user's id */
CREATE OR REPLACE FUNCTION sys_user() RETURNS INTEGER AS '
BEGIN
	RETURN 1;
END;' LANGUAGE 'plpgsql';
