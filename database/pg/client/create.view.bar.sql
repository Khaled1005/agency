CREATE OR REPLACE VIEW bar AS
SELECT
	bar_id,
	client_id,
	non_client_name_last || ', ' || non_client_name_first AS non_client_name_full,
	non_client_name_last,
	non_client_name_first,
	non_client_description,
	bar_date,
	bar_date_end,
	(bar_date_end - bar_date) + 1 as days_barred,
	barred_by,
	bar_incident_location_code,
	bar_resolution_location_code,
	REPLACE(REPLACE(TRIM(
			/* all locations */
			CASE WHEN barred_from_admin
				AND barred_from_clinical
				AND barred_from_dropin
				AND barred_from_housinga 
				AND barred_from_housingb
				AND barred_from_shelter
			THEN 'ALL'
			ELSE 
					CASE WHEN barred_from_admin THEN 'Admin ' ELSE '' END ||
					CASE WHEN barred_from_clinical THEN 'Clinical ' ELSE '' END ||
					CASE WHEN barred_from_dropin THEN 'Dropin ' ELSE '' END ||
					CASE WHEN barred_from_housinga THEN 'Housing A ' ELSE '' END ||
					CASE WHEN barred_from_housingb THEN 'Housing B ' ELSE '' END ||
					CASE WHEN barred_from_shelter THEN 'Shelter ' ELSE '' END
			END
			),
		' ',','),'_',' ') AS barred_from_summary,
	barred_from_admin,
	barred_from_clinical,
	barred_from_dropin,
	barred_from_housinga, 
	barred_from_housingb,
	barred_from_shelter,
	nc,
	va,
	sib,
	rl,
	wn,
	rd,
	po,
	overdose,
	ts,
	tc,
	ct,
	ie,
	police,
	th,
	pd,
	assault,
	ac,
	ms,
	mc,
	dv,
	ft,
	money_ex,
	therapeutic,
	cdmhp,
	description,
	staff_involved,
	gate_mail_date,
	brc_elig_date,
	brc_client_attended_date,
	brc_resolution_code,
	appeal_elig_date,	
	reinstate_condition,
	brc_recommendation,
	comments,
	police_incident_number,
	added_by,
	added_at,
	changed_by,
	changed_at,
	is_deleted,
	deleted_by,
	deleted_at,
	deleted_comment,
	sys_log,
			CASE WHEN nc THEN ' NC' ELSE '' END 
			||	CASE WHEN va THEN ' VA' ELSE '' END 
			||	CASE WHEN sib THEN ' SIB' ELSE '' END 
			||	CASE WHEN rl THEN ' RL' ELSE '' END 
			||	CASE WHEN wn THEN ' WN' ELSE '' END 
			||	CASE WHEN rd THEN ' RD' ELSE '' END 
			||	CASE WHEN po THEN ' PO' ELSE '' END 
			||	CASE WHEN ts THEN ' TS' ELSE '' END 
			||	CASE WHEN tc THEN ' TC' ELSE '' END 
			||	CASE WHEN ct THEN ' CT' ELSE '' END 
			||	CASE WHEN ie THEN ' IE' ELSE '' END 
			||	CASE WHEN police THEN ' POLICE' ELSE '' END 
			||	CASE WHEN th THEN ' TH' ELSE '' END 
			||	CASE WHEN pd THEN ' PD' ELSE '' END 
			||	CASE WHEN assault THEN ' AS' ELSE '' END 
			||	CASE WHEN ac THEN ' AC' ELSE '' END 
			||	CASE WHEN ms THEN ' MS' ELSE '' END 
			||	CASE WHEN mc THEN ' MC' ELSE '' END 
			||	CASE WHEN dv THEN ' DV' ELSE '' END 
			||	CASE WHEN ft THEN ' FT' ELSE '' END 
			||	CASE WHEN money_ex THEN ' EM' ELSE '' END 
			||	CASE WHEN therapeutic THEN ' TP' ELSE '' END 
			||	CASE WHEN overdose THEN ' CO' ELSE '' END 
			||	CASE WHEN cdmhp THEN ' CD' ELSE '' END 
	AS flags,
	CASE WHEN bar_date_end IS NOT NULL AND brc_client_attended_date IS NULL
		THEN bar_date_end-bar_date + 1 || (CASE 
											WHEN bar_date_end-bar_date > 0 THEN ' days'
											ELSE ' day'
											END)
		ELSE 'BRC'
	END AS bar_type
FROM tbl_bar
WHERE NOT is_deleted;
