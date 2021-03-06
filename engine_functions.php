<?php

/*
<LICENSE>

This file is part of AGENCY.

AGENCY is Copyright (c) 2003-2009 by Ken Tanzer and Downtown Emergency
Service Center (DESC).

All rights reserved.

For more information about AGENCY, see http://agency.sourceforge.net/
For more information about DESC, see http://www.desc.org/.

AGENCY is free software: you can redistribute it and/or modify
it under the terms of version 3 of the GNU General Public License
as published by the Free Software Foundation.

AGENCY is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with CHASERS.  If not, see <http://www.gnu.org/licenses/>.

For additional information, see the README.copyright file that
should be included in this distribution.

</LICENSE>
*/

// ENGINE FUNCTIONS
function elink($object,$id,$label,$options=null)
{
	/*
	 * A quick-link engine function, for default view action
	 */

	return link_engine(array('object'=>$object,'id'=>$id),$label,'',$options);
}

function qelink($rec,$def,$label,$options=null)
{
	return elink($def['object'],$rec[$def['id_field']],$label,$options);
}

function link_engine($control_array,$label='',$control_array_variable='',$link_options=null)
{
	$control_array_variable = orr($control_array_variable,'control');
      $page=orr($control_array['page'],'display.php');  //figure out destination page
      $extras=explode('?',$page);
      $page=orr($extras[0],$page);
      $extras=$extras[1];
      $anchor=$control_array['anchor'];

      $main_controls=array('object','action','id','rec_init','list','format','sql');

      if (is_array($control_array)) {
	    $control_array=array_change_key_case($control_array,CASE_LOWER); //WORK IN LOWER CASE
	    $control_array['action']=orr($control_array['action'],'view'); //DEFAULT IS VIEW

	    //CHECK PERMISSIONS TO DETERMINE WHETHER A LINK IS GENERATED
	    $perm = engine_perm($control_array);
	    foreach($control_array as $key=>$value) {
		  //initiallize things like object, action, rec_init and id
		  if (in_array($key,$main_controls)) {
			$$key=$value;
			unset($control_array[$key]);
		  }
	    }

	    $def = get_def($object);
	    $allow = $def['allow_'.$action];

      } else {
	    return dead_link('LINK_ENGINE() REQUIRES AN ARRAY');
      }

      $page = ($action=='list') ? $page : 'display.php'; //only list may be displayed elsewhere (for now)

      if (!$object) {
		return dead_link(alt($label,'LINK_ENGINE() CANNOT SEND A NULL OBJECT TO ENGINE'));
      }

      if ($rec_init and (in_array($action,array('add','widget')))) {
	    foreach($rec_init as $key=>$value) {
		    if (!get_magic_quotes_gpc()) {
			    $value = addslashes($value);
		    }
		    $init_str .= '&'.$control_array_variable."[step]=new&{$control_array_variable}[rec_init][$key]=" . $value;
	    }
      }

	if ($sql) {
		if (!is_array($sql)) {
			$sql = array($sql);
		}
		foreach ($sql as $key => $link_sql) {
			if (!get_magic_quotes_gpc()) {
				$link_sql = addslashes($link_sql);
			}
			$init_str .='&'.$control_array_variable.'[sql]['.$key.']='.$link_sql;
		}
	}
      //pass the remaining elements in control_array to engine
      if (is_array($control_array)) {
		foreach ($control_array as $key=>$value) {
			if (!get_magic_quotes_gpc()) {
				$value = addslashes($value);
			}
			$control_str .= '&'.$control_array_variable."[$key]=" . $value;
		}
      }
      if ($list) {
	    $control_str .= '&'.$control_array_variable.'[list]='.urlencode(percent_encode(serialize($list)));
      }
      return ( ($perm && $allow)||engine_perm('super_user') )//super user
		? hlink($page.'?'
			  . ($extras ? $extras.'&' : '')
			  . $control_array_variable."[action]=$action&"
			  . $control_array_variable."[object]=$object&"
			  . $control_array_variable.'[format]='.$format.'&'
			  . $control_array_variable."[id]={$id}{$init_str}{$control_str}"
			  . ($anchor ? '#'.$anchor : '') 
			  ,orr($label,$action),'',$link_options)
		: dead_link(orr($label,$action),$link_options);
}

function call_engine($control,$control_array_variable='control',$NO_TITLE=false,$NO_MESSAGES=false,&$TOTAL_RECORDS,&$PERM)
{
      //USE IN PLACE OF display.php
      //RETURNS FORMATTED OUTPUT FOR PLACEMENT ON PAGE
	$control = unserialize_control($control);
      if (!engine_perm($control)) {
		$PERM = false;
		$action = $control['action'];
		$def = get_def($control['object']);
		$singular = $def['singular'];
		return oline("You don't have permission to $action $singular records.");
      } else { 
		$PERM=true; 
	}
      $ENGINE   = engine($control,$control_array_variable);
      $title    = $ENGINE['title'];
      $output   = $ENGINE['output'];
      $message  = $ENGINE['message'];
      $commands = $ENGINE['commands'];   //Commands aren't used
	$TOTAL_RECORDS = $ENGINE['total_records'];

      $out = $NO_TITLE ? '' : $title;
      $out .= $NO_MESSAGES ? '' : ($message ? oline() . oline(box(bigger(red($message))),2) : '');
      $out .= $output;

      return $out;
}

function unserialize_control($control)
{
	//FIXME: this function is getting called too many times, and removing too many slashes

      // HAS THE ABILITY TO UNSERIALIZE EITHER A COMPLETELY SERIALIZED ARRAY,
      // OR AN ARRAY WITH SERIALIZED SUB-ARRAYS. AS OF NOW, IT WON'T WORK IF 
      // THERE ARE SERIALIZED ELEMENTS IN FURTHER SUB-ARRAYS (OF SUB-ARRAYS).
      if ($a=unserialize(percent_decode(urldecode(stripslashes($control))))) {
		return $a;
      }
      if (is_array($control)) {
		foreach ($control as $key=>$value) {
			$control[$key] = unserialize_control($value);
		}
		return orr($control,array());
      }
	return $control;
}

function engine_control_array_security($name = 'control')
{
	$security = get_engine_def('control_array_security');

	foreach ($security as $el) {
		unset($_REQUEST[$name][$el]);
		unset($_GET[$name][$el]);
		unset($_POST[$name][$el]);
	}
	
}

function engine_perm($control,$access_type='')
{
      $PERM = false;

	//hard coding a preliminary super-user check here
	if ( has_perm('super_user','S') ) {
		return true;
	}

	if (!$control || $control=='super_user') {
		return false;
	}

	$action = $control['action'];
      $object = $control['object'];
	$id     = $control['id'];

	// This is for the special case of a pending attachment:
	if (($object == 'association_attachment') && preg_match('/^pending_upload[0-9]*$/', $id)) {
		return true;
	}

      $def = get_def($object);
      $perm=$def["perm_$action"];
      $access_type = orr($access_type,
				 ($action=='view' || $action=='list') ? 'R' : 'W');

	//check for read access to verything
	if ( $access_type == 'R' && has_perm('read_all') ) {
		return true;
	}

	// read-only mode?
	if (db_read_only_mode() && $access_type == 'W') {
		return false;
	}

	//determine protected status
	if (isset($id) and $id != 'list' and ($access_type=='W') && is_protected_generic($object,$id)) {
		return false;
	}

      if (!is_array($perm)) {
		$perm=split(',',$perm);
      }
      foreach($perm as $permission) {
		if ($permission=='self') {
			global $UID;
			//must fetch record to determine if self
			if ( (sql_num_rows(get_generic(build_self_filter($control),'','',$def)) > 0) 
			     || ($action=='list' && in_array($UID,$control['list']['filter'])) ) {
				$tPERM = true;
				//$tPERM = is_self($control['id']); //is this person editing or accessing their own record?
			} else {
				$tPERM=false;
			}
		} elseif(preg_match('/^my_/',$permission)) {
			// check to see if client_id from record,filter or rec-init is related to user 
			$tPERM = staff_client_relation_generic($control,$permission);

		} elseif(false) {
			// OTHER FUNCTIONALITY CAN BE BUILT HERE.
		} elseif (($permission=='any') || !$permission ) {
			//default to granting access if perm_type hasn't been specified
			//this only generates a link, it doesn't by-pass engine's permission checks.
			/*
			 * This condition is what returns true when building the link
			 * for fields of type attachment (assuming we don't set perm_download.)
			 */
			$tPERM=true;
		} else {
			// STANDARD PERMISSION PROCESSING
			$tPERM=has_perm($permission,$access_type);
		}
		$PERM = $tPERM ? $tPERM : $PERM;
      }
	//per-record perm checking - after general check
	if ($action=='add') {
		$rec = $control['rec_init'];
	} elseif ($def && ($action !== 'list') && ($object !== 'generic_sql_query') ) {

		if (!is_numeric($id) || $id < AG_POSTGRESQL_MAX_INT) {
			$res = get_generic(array($def['id_field']=>$id),'','',$def);
			if (sql_num_rows($res) > 0) {
				$rec = sql_fetch_assoc($res);
			}
		}
	}

	$fn = $def['fn']['engine_record_perm'];
	// This was getting called with blank $fn.
	// Not sure why, so I added a check to skip that
	// Worst-case should be that some access is not allowed that
	// should be, which is beter than the reverse!
	// if ($rec && ($fn !== 'engine_record_perm_generic') ) {
	if ($rec && $fn && ($fn !== 'engine_record_perm_generic') ) {
		$PERM = $fn($control,$rec,$def);
	}

      return $PERM;
}

function build_self_filter($control)
{
	global $UID;
	$def = get_def($control['object']);
	$id = $control['id'];
	$fields = $def['fields'];
	$staff_filt = array();
	foreach ($fields as $key=>$conf) {
		if ($conf['data_type'] == 'staff') {
			$staff_filt[$key]=$UID;
		}
	}
 	return ($id) ? array($def['id_field']=>$id,$staff_filt) : array($staff_filt);
}

function staff_client_relation_generic($control,$type)
{

	/*
	 * Determines relationships between clients/staff or staff/staff
	 */

	global $UID;
	
	$def = get_def($control['object']);
	
	$action = $control['action'];

	switch ($action) {
	case 'delete':
	case 'view':
	case 'edit':
		//grab record and check for client id
		$id  = $control['id'];
		$res = get_generic(array($def['id_field']=>$id),'','',$def);
		if (sql_num_rows($res)<1) {
			return false;
		}
		$rec = sql_fetch_assoc($res);
		$cid = $rec[AG_MAIN_OBJECT_DB.'_id'];
		$sid = $rec['staff_id'];
		break;
	case 'add':
		//check rec-init for client id
		$cid = $control['rec_init'][AG_MAIN_OBJECT_DB.'_id'];
		break;
	case 'list':
		//check filter for client id
		$cid = $control['list']['filter'][AG_MAIN_OBJECT_DB.'_id'];
		$sid = $control['list']['filter']['staff_id'];
		break;
	default:
		$cid = false;
		$sid = false;
	}


	switch ($type) {

	case 'my_client':

		if (be_null($cid)) { return false; }
		return staff_client_assigned($cid);

	case 'my_client_clinical':

		if (be_null($cid)) { return false; }
		return staff_client_assigned($cid) && has_perm('clinical');

	case 'my_client_position_project_clinical':

		//check to see if staff's position/project combination has access
		if (be_null($cid)) { return false; }
		return staff_client_position_project_clinical($cid);
		       
	case 'my_client_project':

		//check to see if client and staff project match
		if (be_null($cid)) { return false; }
		return staff_client_project($cid);

	case 'my_supervisee':

		//check to see if staff is supervised by user, directly or indirectly
		if (be_null($sid)) { return false; }
		return staff_is_supervised_by($sid);

	default:

		return false;

	}
}

function engine_record_perm_generic($control,$rec,$def) 
{ // a place holder for custum functions of the form engine_record_perm_{$object}
	return true;
}

function get_engine_config_array()
{
	$filter = $t_filter = array('val_name'=>AG_ENGINE_CONFIG_ARRAY);

	if (AG_SESSION_ENGINE_ARRAY and $e = $_SESSION['STATIC_ENGINE_ARRAY']) {

		$last_mod_time = $_SESSION['STATIC_ENGINE_ARRAY_TIME'];
		$t_filter['<=:changed_at'] = $last_mod_time;
		$mod_time_res = agency_query('SELECT changed_at FROM '.AG_ENGINE_CONFIG_TABLE,$t_filter);

		if (sql_num_rows($mod_time_res) < 1) {
			//config has been updated since last load. continue
			
		} else {
			return $e;
		}
	}

      $res=agency_query('SELECT * FROM '.AG_ENGINE_CONFIG_TABLE,$filter);
      if (sql_num_rows($res) < 1) {
	    outline('Unable to read engine configuration from '.AG_ENGINE_CONFIG_TABLE.' for '.AG_ENGINE_CONFIG_ARRAY);
	    outline('You can try updating it now');
	    out(update_engine_control('noexists'));
	    exit;
      }
      $tmp=sql_fetch_assoc($res);

	$e = unserialize($tmp['value']);
	if (AG_SESSION_ENGINE_ARRAY) {
		$_SESSION['STATIC_ENGINE_ARRAY'] = $e;
		$_SESSION['STATIC_ENGINE_ARRAY_TIME'] = $tmp['changed_at'];
	}
      return $e;
}

function get_def($object)
{
	global $engine;

	// this function should always be used to get the object definition,
	// as this will protect code against changes that may come to how
	// the engine array is stored and retrieved.

	if ($def = $engine[$object]) {
		return $def;
	}

	$survey_table = preg_match('/^survey_/',$object);
	$lookup_table = preg_match('/^l_/',$object);

	if (!isTableView($object)) {
		return false;
	} elseif (!$survey_table //surveys are okay
		    and !$lookup_table //lookups are okay too
		    and !engine_perm(null) and !has_perm('any_table','RW')) {
		return false;
	}

	$def = config_undefined_object($object);

	if ( $survey_table ) {
		$def['add_link_always'] = orrn($def['add_link_always'],false);
	}

	if (has_perm('any_table','RW')) { //set read/write permissions for table similar to super-user permissions
		$def = config_any_table_perm($def);
	}

	$engine[$object] = $def;

	return $def;
}

function get_engine_def($element)
{
	// for now, function identical to above (get_def())
	// once db storage is done, this will need to be kept elsewhere

	global $engine;

	return $engine[$element];
}

function get_singular($object)
{
	$def = get_def($object);

	return $def['singular'];
}

function config_object($object)
{

	/*
	 * Configure an AGENCY object, returning a configuration array
	 */

      global $engine;

      /*
	 * set global options not set elsewhere
	 */

      $OBJECT = array('object'=>$object,
			    'table'=>orr($engine[$object]['table'],$object) //the metadata functions require a table
			    );

      $table         = ($OBJECT['table']<>$OBJECT['object']) ? $OBJECT['table'] : $OBJECT['object'];
      $sql_table     = orr($engine[$object]['table_post'],((is_view($table) && is_table('tbl_'.$table)) ? 'tbl_'.$table : $table));
      $config_object = $engine[$object];  //CONFIG FILE OPTIONS
      $sql_object    = sql_metadata_wrapper(sql_metadata($sql_table)); //SQL METADATA 
	$fields_table  = $sql_object; //this must be calculated prior to possible merge w/ view below

	if ($table !== $sql_table) {

		/*
		 * There is more metadata to be gleaned from the db.
		 * Metadata from the table overrides the view.
		 */
		$sql_object = array_merge(sql_metadata_wrapper(sql_metadata($table)),$sql_object);

	}
      $engine_default = set_engine_defaults($object,$table);

	/*
	 * Initialize fields
	 */
      $fields_view = (is_view($OBJECT['table'])) 
		? sql_metadata($OBJECT['table'])
		: array();

      $fields_config_file = is_array($config_object['fields'])
		? $config_object['fields'] : array();

      $engine_object_virtual = array_merge(engine_metadata(array_keys($fields_view),array(),$object,$sql_table),
							 engine_metadata(array_keys($fields_config_file),array(),$object,$sql_table));

      $FIELDS = array_keys(array_merge($fields_view,$fields_table,$fields_config_file));

      $engine_object = engine_metadata($FIELDS,$sql_object,$object,$sql_table); //ENGINE METADATA

      //ACTION SPECIFIC OPTIONS
      $config_object = write_action_options($config_object,$FIELDS);

      //SET GLOBAL OPTIONS
      $global_options = array_keys($engine_default['global_default']); //this contains a complete list of global options

      foreach ($global_options as $option) {

		$value=orrn($config_object[$option],    // 1
				$engine_object[$option],     // 2 
				null,                        // 3 meta_data doesn't come into play here
				$engine_default['global_default'][$option]);   // 4

	    if ($option == 'table_post') {

		  $value=(is_table($value)) ? $value : $OBJECT['table'];

	    }

	    if (!is_null($value)) { //no need to carry around a bunch of null values (that weren't set)

		  $OBJECT[$option]=$value;

	    }

      }

      //FUNCTIONS
      foreach($engine['functions'] as $fn_type => $function) {

		$config_defined_function = $config_object['fn'][$fn_type];
		$OBJECT['fn'][$fn_type]=orr($config_defined_function,$OBJECT['fn'][$fn_type],$fn_type . '_' . $object);

	    if (!function_exists($OBJECT['fn'][$fn_type])) {

		  $OBJECT['fn'][$fn_type] = $function;

	    }

      }

	  // MULTI-ADD FUNCTIONS
	  if ($OBJECT['multi_records']) {
		$m = $OBJECT['multi'];
		foreach ($m as $k=>$v) {
				$OBJECT['multi'][$k]['blank_fn']=orr($v['blank_fn'],'blank_generics_add');
				$OBJECT['multi'][$k]['add_fields_fn']=orr($v['add_fields_fn'],'add_generics_fields');
				$OBJECT['multi'][$k]['form_row_fn']=orr($v['form_row_fn'],'form_generics_row');
				$OBJECT['multi'][$k]['valid_fn']=orr($v['valid_fn'],'valid_generics');
				$OBJECT['multi'][$k]['post_fn']=orr($v['post_fn'],'post_generics');
		}
	  }	

	  // Info Additional Records
	  if ($OBJECT['include_info_additional']) {
	    info_additional_config_array($OBJECT);
	  }
      // FIELDS NOT IN POST TABLE
      $fields_db       = array_keys(array_merge($fields_view,$fields_table));
      $FIELDS_NOT_DB   = array_diff(array_keys($fields_config_file),$fields_db);
      $FIELDS_NOT_POST = array_diff(array_keys($fields_view),array_keys($fields_table));

      $FIELD_OPTIONS=array_keys($engine_default['field_default']);

      foreach ($FIELDS as $field) {

	    if (in_array($field,$FIELDS_NOT_DB)) {

		  foreach ($FIELD_OPTIONS as $option) {

			// SPECIAL TREATMENT FOR FIELDS NOT IN DB
			$value=orrn($config_object['fields'][$field][$option],    // 1
				    $engine['virtual_field_options'][$option],  // 1A
				    $engine_object_virtual['fields'][$field][$option], //2A
				    $engine_object['fields'][$field][$option],    // 2 
				    $sql_object[$field][$option],                 // 3
				    $engine_default['field_default'][$option]);   // 4

			if (!is_null($value)) {

			      $OBJECT['fields'][$field][$option]=$value;

			}

		  }

	    } elseif (in_array($field,$FIELDS_NOT_POST)) {

		  foreach ($FIELD_OPTIONS as $option) {

			// SPECIAL TREATMENT FOR FIELDS NOT IN POST TABLE
			$value=orrn($config_object['fields'][$field][$option],    // 1
				    $engine['view_only_field_options'][$option],  // 1A
				    $engine_object_virtual['fields'][$field][$option], //2A
				    $engine_object['fields'][$field][$option],    // 2 
				    $sql_object[$field][$option],                 // 3
				    $engine_default['field_default'][$option]);   // 4

			if (!is_null($value)) {

			      $OBJECT['fields'][$field][$option]=$value;

			}

		  }

	    } else { //if (!$engine_object['fields'][$field]['system_field'])

		  foreach ($FIELD_OPTIONS as $option) {

			$value=orrn($config_object['fields'][$field][$option],    // 1
				    $engine_object['fields'][$field][$option],    // 2 
				    $sql_object[$field][$option],                 // 3
				    $engine_default['field_default'][$option]);   // 4

			if (!is_null($value)) {

			      $OBJECT['fields'][$field][$option]=$value;

			}

		  }

	    }

      }

      return $OBJECT;

}

function config_undefined_object($object) {

	/*
	 * A wrapper for config_object for on-the-fly object configuration
	 */

	global $engine, $off;

	/*
	 * Check for config files
	 */
	if (is_readable($off . ENGINE_CONFIG_FILE_DIRECTORY . '/'.AG_MAIN_OBJECT_DB.'/config_'.$object.'.php')) {

		include $off . ENGINE_CONFIG_FILE_DIRECTORY . '/'.AG_MAIN_OBJECT_DB.'/config_'.$object.'.php';

	} elseif (is_readable($off . ENGINE_CONFIG_FILE_DIRECTORY . '/config_'.$object.'.php')) {

		include $off . ENGINE_CONFIG_FILE_DIRECTORY . '/config_'.$object.'.php';

	}

	$def=config_object($object);


	/*
	 * Global attributes different from defaults
	 * fixme: these different defaults could still live in 
	 *        a config file somewhere, instead of being hard-coded
	 */
	$def['cancel_add_url'] = AG_ADMIN_URL;
 	$def['perm_add']       = false;
 	$def['perm_edit']      = false;


	/*
	 * Field level attributes different from defaults
	 * fixme: see fixme for global defaults above
	 */
	$fields = $def['fields'];
	foreach ($fields as $name => $field_defs) {

		if ($field_defs['data_type']=='lookup') {

			/*
			 * disable lookup handling -- disabling the 
			 * disabling as it seems like this was an
			 * earlier fix for a problem that no longer
			 * exists. Lookups are now generated via
			 * stricter code. Who knows though, this may
			 * break something too. -sd
			 * 
			 * Indeed, it did break the adding of new
			 * codes because those fields become lookups 
			 * instead of an entry field. Re-disabling
			 * until we can find a fix. -sd
			 */
			$fields[$name]['data_type'] = 'varchar';

		}

	}

	$def['fields']=$fields;

	return $def;
}

function config_generic_sql($def,$res)
{

	/*
	 * Generate a configuration array for sql statements/queries
	 */

	global $engine;

	/*
	 * The defaults for queries are stored in config_generic_sql_query.php
	 */
	$object = $def['object'] = orr($def['object'],'generic_sql_query');
	
	$fields      = sql_field_names($res);
	$list_fields = array();
	$f_default   = $engine['field_default'];
	$sql_meta    = sql_metadata_wrapper(sql_query_metadata($res));
	$engine_meta = engine_metadata($fields,$sql_meta,$object);

	foreach($fields as $field) {


		/*
		 * Fields starting with "_" are hidden by default
		 */
		if (substr($field,0,1) !== '_') {

			array_push($list_fields,$field);

		}

		$defaults['fields'][$field] = $f_default;
		$def['fields'][$field] = array_merge($f_default,$sql_meta[$field],$engine_meta['fields'][$field],orr($def['fields'][$field],array()));

	}

	$def['list_fields'] = $list_fields;

	return $def;
}

function sql_metadata_wrapper($sql_metadata)
{
	/*
	 * A wrapper to fill in additional metadata based on naming conventions etc
	 */

      global $engine;

      $engine_types=$engine['data_types'];
      foreach($sql_metadata as $field => $metadata) {
		//data type 
		$data_type = $metadata['data_type'];

		if (preg_match('/(.*)\(([0-9]*),?([0-9]*)\)$/',$metadata['data_type'],$m)) {
			// strip off length info and set
			$data_type = $m[1];
			$sql_metadata[$field]['length'] = $m[2];
			$sql_metadata[$field]['length_decimal_places'] = $m[3];
		}

	    $found = false;

	    foreach($engine_types as $type => $sql_type) {

		  if (in_array($data_type,$sql_type)) {

			$data_type=$type;
			$found=1;

		  }

	    }

	    if (!$found) {

		  $data_type='unknown';

	    }

	    $sql_metadata[$field]['data_type'] = $data_type;

	    /*
	     * Field default values
	     */
	    $default = $metadata['default'];

	    if ($default==='POSTGRES_SEQUENCE') {

		    unset($sql_metadata[$field]['default']);

		    foreach($engine['actions'] as $action=>$type) {

			    if ($type=='write') {
				    $sql_metadata[$field]['post_'.$action]=false;
			    }

			    $sql_metadata[$field]['display_'.$action]='display';
		    }

		    $sql_metadata[$field]['null_ok']=sql_true();
		    $sql_metadata[$field]['display_add']='hide';

	    }

	    /*
	     * lookup table handling
	     */
	    if (preg_match('/_code$/i',$field) and $l_field = $metadata['lookup_column'] and $l_table = $metadata['lookup_table']
		  and is_table($l_table) and is_field($l_table,$l_field)) {

		    $l_label = is_field($l_table,'description') ? 'description' : $l_field;
		    $sql_metadata[$field]['data_type'] = 'lookup';
		    $sql_metadata[$field]['lookup'] = array('table'=>$l_table,
									  'value_field'=>$l_field,
									  'label_field'=>$l_label);

	    } elseif ($data_type=='array' and preg_match('/((.*)_code)s$/i',$field,$m) and isTableView('l_'.$m[2])) {

		    /*
		     * Varchar[] type "lookup"
		     */
		    $sql_metadata[$field]['data_type'] = 'lookup_multi';
		    $sql_metadata[$field]['lookup'] = array('table'=>'l_'.$m[2],
									  'value_field'=>$m[1],
									  'label_field'=>'description');
		    $sql_metadata[$field]['comment'] = '(ctrl-click to select multiple values)';

	    }
	    
	}
      
      return $sql_metadata;

}

function engine_metadata($fields,$meta=array(),$object='',$table_post='')
{
	/*
	 * Generate config options based on field  naming conventions, etc.
	 */

      global $engine;

	$enmeta  = array();

      $system_fields = $engine['system_fields'];

	/*
	 * some global options will be set for specific actions, and the associated
	 * system fields
	 */
      $deletes=array('is_deleted','deleted_at','deleted_by','deleted_comment');
      $adds=array('added_at','added_by');
      $changes=array('changed_at','changed_by');

      if (in_array_array($deletes,$fields)) {

	    $enmeta['stamp_deletes']=true;

      }

      if (in_array_array($adds,$fields) && is_table($table_post)) {

	    $enmeta['stamp_adds']=true;
	    $enmeta['allow_add']=true;

      }

      if (in_array_array($changes,$fields) && is_table($table_post)) {

	    $enmeta['stamp_changes']=true;
	    $enmeta['allow_edit']=true;

      }

      foreach ($fields as $field) {

	    $new=array();

	    /*
	     * set labels
	     */
	    $tmp_lab = preg_replace('/'.AG_MAIN_OBJECT_DB.'/',AG_MAIN_OBJECT,$field);
	    $tmp_lab = preg_replace('/^agency_/','',$tmp_lab); //  (remove noise from labels)
	    $tmp_lab = preg_replace('/^org_/','',$tmp_lab); 
	    $new['label'] = preg_replace('/ /','&nbsp;',ucwords(preg_replace('/_/',' ',$tmp_lab)));

	    if (array_key_exists($field,$system_fields)) {
		    //handle system fields here
		    $new = $system_fields[$field];
	    }

	    if (strtolower(substr($field,-3))=='_at') {

		  $new['data_type']='timestamp';

	    } elseif (  (strtolower(substr($field,3))=='is_') ||
			    (strtolower(substr($field,4))=='has_') ||
			    (strtolower(substr($field,4))=='was_')  ) {

		  $new['data_type']='boolean';

	    } elseif ((strtolower(substr($field,-3))=='_by')
			  || ($field == 'staff_id')) {

		    /*
		     * Staff field
		     */

		    if ($field=='staff_id') {

			    $new['label']='Staff';

		    }

		    $new['data_type']         = 'staff';
		    $new['value_format_list'] = 'smaller($x)';
		    $new['order_by_instead']  = 'staff_name('.$field.')';

	    } elseif ($field == AG_MAIN_OBJECT_DB.'_id') {

		    /*
		     * main object field (eg, client, donor, subject etc)
		     */

		  $new['data_type']=AG_MAIN_OBJECT_DB;
		  $new['label']=ucwords(AG_MAIN_OBJECT);
		  $new['order_by_instead']=AG_MAIN_OBJECT_DB.'_name('.$field.')';

	    } elseif ($field == 'dob' ) {

		    /*
		     * fixme: this really belongs in the config_client file
		     */
		    $new['data_type']='date_past';
		    $new['label']='Date of Birth';

	    } elseif ($field == 'ssn' ) {

		    /*
		     * fixme: this really belongs in the config_client file
		     */

		  $new['data_type']='ssn';
		  $new['label']='Social Security #';

	    } elseif (preg_match('/(.*)_code$/i',$field,$matches)
		    && isTableView('l_' . $matches[1])) {

		    /*
		     * sql_metadata might return this information (if the lookup table is referenced in the db), 
		     * with the exception of the description field...
		     * I don't see any 'generic' workaround for that.
		     */

		    $new['data_type'] = 'lookup';

		    //$new = set_engine_actions('show_lookup_code','BOTH',$new); //returns array with actions set
		    
		    $new['show_lookup_code_list']='DESCRIPTION';  //will display only DESCRIPTION for list action
		    $new['label'] = ucwords(substr($new['label'],0,strlen($new['label'])-10)); //10 = length(&nbsp;code)
		    $new['lookup'] = array('table' => 'l_' . $matches[1],
						   'value_field' => $matches[1].'_code',
						   'label_field' => 'description'
						   );

		    $new['lookup']['data_type'] = $meta[$field]['data_type'];
		    $new['lookup']['length']    = $meta[$field]['length'];
		    if (in_array($field,array('agency_project_code','housing_project_code'))) {

			    $new['label'] = 'Project';

		    }		  

	    } elseif (preg_match('/(.*)_code_other$/i',$field,$m) and in_array($m[1].'_code',$fields)) {

		    /*
		     * lookup_code _other functionality - if a table has this_is_a_code field,
		     *    and a this_is_a_code_other text field, this will automatically prompt
		     *    users for a description if they select "other" as the value in the
		     *    this_is_a_code field.
		     *
		     * below, 21=length(&nbsp;code&nbsp;other)
		     */
		    $new['label'] = ucwords(substr($new['label'],0,strlen($new['label'])-21)).' (please specify if Other)';
		    $new['valid'] = array('be_null($x) xor $rec['.$m[1].'_code'.']=="OTHER"'
						  =>'{$Y} must (only) be filled in if Other is selected');

	    }  elseif (preg_match('/(.*)_accuracy$/i',$field,$matches)
			  && in_array($matches[1],$fields)) {

		    /*
		     * Data accuracy functionality
		     */
		    $new['data_type'] = 'lookup';
		    $new['lookup'] = array('table'=>'l_accuracy',
						   'value_field'=>'accuracy_code',
						   'label_field'=>'description',
						   'data_type' => $meta[$field]['data_type'],
						   'length' => $meta[$field]['length']);

	    } elseif (preg_match('/(.*)_source_code$/i',$field,$matches)
			  && in_array($matches[1],$fields)) {

		    /*
		     * Data source functionality
		     */

		    $new['data_type'] = 'lookup';
		    $new['lookup'] = array('table'=>'l_data_source',
						   'value_field'=>'data_source_code',
						   'label_field'=>'description',
						   'data_type' => $meta[$field]['data_type'],
						   'length' => $meta[$field]['length']);

	    } elseif (preg_match('/(.*)_code$/i',$field,$m)) { //catch other '_code' fields

		    $new['label'] = ucwords(substr($new['label'],0,strlen($new['label'])-10)); //10 = length(&nbsp;code)

	    } else {
		  // this is all engine metadata can do
		  // so do nothing here
	    }	

	    if ($meta[$field]['data_type']=='array') {

		    /*
		     * Setting maximum number for the free-flowing (meaning no corresponding lookup table)
		     * array fields. Since this isn't currently in use anywhere, I picked 10...this could be
		     * 100 or 1000...
		     */

		    $new['array_max_elements'] = 10;

	    }

	    foreach(array_keys($engine['actions']) as $action) {

		    $label=$new['label'];
		    if ($action=='list') {

			    $tmp_obj = str_replace(' ','&nbsp;',ucwords(str_replace('_',' ',$object)));
			    $label   = str_replace($tmp_obj.'&nbsp;','',$label);
			    $label   = str_replace('&nbsp;'.$tmp_obj,'',$label);

		    }

		    $new['label_'.$action]=isset($new['label_'.$action]) ? $new['label_'.$action] : $label;

	    }

	    unset($new['label']);
	    $enmeta['fields'][$field]=$new;

      }

	/*
	 * Validity checks
	 */
	if (isset($enmeta['fields'][$object.'_date']) && isset($enmeta['fields'][$object.'_date_end'])) {

		/*
		 * If a date, and a date_end field, assume date_end must be greater than or equal to the date field
		 */
		$sdate_field = $object.'_date';
		$check = array('($x >= $rec["'.$sdate_field.'"]) || be_null($x)'=>'{$Y} must be greater than '.$sdate_field);
		$enmeta['fields'][$object.'_date_end']['valid'] = $check;

	}

      return $enmeta;

}

function set_engine_actions($parameter,$value,$def) {

	/*
	 * Fill in action specific values in $def if not already set.
	 * 
	 * ie: {paramater}_add = value, {parameter}_edit = value, etc
	 */

	global $engine;

	foreach(array_keys($engine['actions']) as $action) {

		if (is_null($def[$parameter.'_'.$action])) {
			$def[$parameter.'_'.$action]=$value;
		}

	}

	return $def;

}

function set_engine_defaults($object,$table='')
{

      global $engine;

      $table = orr($table,$object);

      //set defaults that must be set at runtime
      $DEFAULTS['global_default'] = $engine['global_default'];
      $DEFAULTS['field_default'] = $engine['field_default'];
      $DEFAULTS['global_default']['table'] = $table;
      $DEFAULTS['global_default']['table_post'] = is_table('tbl_'.$object)
	    ? 'tbl_' . $object : $table;
	$primary=sql_primary_keys($table);
	$indexes=array_keys(sql_indexes($table));
	$fields=array_keys(sql_metadata($table)); 
      $DEFAULTS['global_default']['id_field'] = ( is_field($table,$table.'_id')
								  ? $table.'_id'                       //if it exists, table_id
								  : ( is_field($table,substr($table,2,strlen($table)-2))  //trim off l_
									? $table.'_code' //match on code
									: ( $primary[0] 
									    ? $primary[0]  //else go for the primary key
									    : $fields[0] )  //failing that, the first field
									)
								  );
      $singular=orr($engine[$object]['singular'],ucwords(preg_replace("/_/",' ',$object)));
	$singular=preg_replace("/".AG_MAIN_OBJECT_DB."/i",ucwords(AG_MAIN_OBJECT),$singular);
      $DEFAULTS['global_default']['singular'] = $singular;
      $DEFAULTS['global_default']['plural'] = (strtolower(substr($singular,-1))=='y') 
	    ? substr($singular,0,strlen($singular)-1) . 'ies'
	    : $singular . 's';
      $DEFAULTS['global_default']['sel_sql'] = 'SELECT * FROM ' . $table;

	/*
	 * This attempts to derive more meaningful default list fields by looking at primary keys
	 * and indexed columns.
	 *
	 * The logic is thus: 1) if object_id exists, this is the first field, otherwise primary key, otherwise first field
	 *                    2) 2nd index (1st index is primary key) or 2nd field
	 *                    3) 3rd index or 3rd field
	 *
	 * fixme: the object_id is rarely useful to display as a default list field. A far better set of defaults would
	 *        at least include, if available the object_date and object_date_end fields, and then the first non-primary-key-field
	 */
      $list_fields = array();

	/*
	 * 1st field
	 */
	array_push($list_fields,(is_field($table,$table.'_id')
					 ? $table.'_id'                       //if it exists, table_id
					 : ( is_field($table,substr($table,2,strlen($table)-2))  //trim off l_
					     ? $table.'_code' //match on code
					     : ( $primary[0] 
						   ? $primary[0]  //else go for the primary key
						   : $fields[0] )  //failing that, the first field
					     )));
	/*
	 * 2nd field
	 */
	array_push($list_fields,( (isset($indexes[1]) && !strpos($indexes[1],',')) //ignore multi-column indexes
					  ? $indexes[1] : $fields[1]
					  ));
	/*
	 * 3rd field
	 */
	array_push($list_fields, ( (isset($indexes[2]) && !strpos($indexes[2],',') ) //ignore multi-column indexes
					   ? $indexes[2] : $fields[2]
					   ));

	$DEFAULTS['global_default']['list_fields']=array_unique(array_filter($list_fields));
      return $DEFAULTS;

}

function write_action_options($config_object,$FIELDS)
{
	/*
	 * A pass-through function, wherin the action-specific options are filled in. Returns 
	 * a more complete config_object array.
	 */

      global $engine;

      if (!is_array($config_object)) {

	    return $config_object;

      }

      $fields=$config_object['fields'];

      /*
	 * Check for a 'global' to override all fields
	 */
      $config_tmp = $config_object;
      foreach (array_keys($engine['field_default']) as $option) {

		if (array_key_exists($option,$config_tmp)) {

			foreach ($FIELDS as $field) {

				$fields[$field][$option]=$config_tmp[$option];
			}
		}

      }

      foreach (array_keys($engine['action_specific_vars']['global']) as $option) {

		if (isset($config_object[$option])) {

			$value=$config_object[$option];
			unset($config_object[$option]);
			foreach (array_keys($engine['actions']) as $action) {

				if (!isset($config_object[$option.'_'.$action])) {

					$config_object[$option.'_'.$action]=$value;

				}

			}

		}

      }

      if (is_array($fields)) {

		foreach(array_keys($engine['action_specific_vars']['fields']) as $option) {
			
			foreach($fields as $field=>$val) {

				if (isset($fields[$field][$option])) {

					$value=$fields[$field][$option];
					unset($fields[$field][$option]);
					foreach (array_keys($engine['actions']) as $action) {

						if (!isset($fields[$field][$option.'_'.$action])) {

							$fields[$field][$option.'_'.$action]=$value;
						}

					}

				}

			}
		}
      }

      $config_object['fields'] = $fields;

      return $config_object;

}

function blank_generic(&$def, &$rec_init,&$control)
{
	/*
	 * Returns a blank record, filling in defaults, and values
	 * passed in rec_init()
	 */

	//-----------------------------------//
	// merging blank with clients previous record
	$id_field = AG_MAIN_OBJECT_DB.'_id';
	if ($def['rec_init_from_previous'] && !be_null($rec_init[$id_field]) ) {
		if ($old_rec = sql_fetch_assoc(get_generic(array($id_field=>$rec_init[$id_field])
									 , $def['id_field'].' DESC','1',$def)) ) {
			$old_rec_merge = array();
			foreach ($old_rec as $o_key=>$o_val) {
				if ($def['fields'][$o_key]['rec_init_from_previous_f']==true) {
					$old_rec_merge[$o_key] = $o_val;
				}
			}
			$rec_init = array_merge($old_rec_merge,$rec_init);
		}
	}
	//-------------------------------//


      $fields=$def['fields'];
      $a=array_keys($fields);
      foreach($a as $key) {

		$default=$fields[$key]['default'];    
		$type=$fields[$key]['data_type'];

		if (isset($rec_init[$key])) {

			$rec[$key]=$rec_init[$key];

		} elseif (isset($default)) {

			if ( $default === "current_$type" || $default === 'NOW' ) {
				
				/*
				 * Current date/timestamp/time 
				 */
				$current = datetimeof('NOW');

				switch ($type) {
				case 'date' :
				case 'date_past' :
				case 'date_future' :
					$rec[$key] = dateof($current);                    
					break;
				case 'time' :
				case 'time_past' :
				case 'time_future' :
					$rec[$key] = timeof($current);
					break;
				case 'timestamp' :
				case 'timestamp_past' :
				case 'timestamp_future' :
					$rec[$key] = datetimeof($current); 
					break;
				}
			} else {

				/*
				 * Evaluate defaults starting with a "$"
				 */
				if (substr($default,0,1)=='$') {

					$default=eval('return '.$default.';');

				} elseif (preg_match('/^EVAL:\s(.*)/',$default,$m)) {

					$default = eval('return '.$m[1].';');

				}

				$rec[$key]=$default;

			}

		} else {

			if ($type<>'multi_rec') {

				$rec[$key]='';

			}

		}

      }

      if ($def['multi_records']) {
		foreach ($def['multi'] as $m=>$opts) {
			$rec=$opts['blank_fn']($rec,$def,$m);
		}
      }
	// rec_init key not in REC (might?) mean create a reference...
	$other_keys=array_diff(array_keys(orr($rec_init,array())),$a);
	foreach( $other_keys as $dummy=>$o) {
		//not much to work with with just a key, so try for object_id
		if (preg_match('/^(.*)_id$/',$o,$matches)) {
			$t_def=get_def($matches[1]);
			if ($t_def['id_field']==$o) {
		        $control['object_references']['to'][]=array(
	                'object'=>$t_def['object'],
    	            'id' => $rec_init[$o],
        	        'label' => object_label($t_def['object'],$rec_init[$o]));
			}
		}
	  }	

      return $rec;
}

function value_generic($value,$def,$key,$action,$do_formatting=true)
{
	global $engine;
      $field=$def['fields'][$key];
	if (!$field) {
		return $value;
	}

      $type=$field['data_type'];
	//fixme: this should really be done at the formatting stage, since it doesn't webify anything
	// that is occasionally a link (such as DAL progress note field).
	if (!in_array($type,array('html','lookup','table_switch','lookup_multi','array','staff_list','attachment')) && !$field['is_html']) {
		$value=webify($value); 
	}
      $show_value = $field['show_lookup_code_'.$action];
      if ($type=='multi_rec') {
	    $type=$field['multi_type'];
      }
      if ($type=='staff') {
	    switch ($show_value)
	    {
	    case 'CODE':
		  break;
	    case 'DESCRIPTION':
		  $value=(!be_null($value)) ? staff_link($value) : $value;
		  break;
	    case 'BOTH':
	    default:
		  $value=(!be_null($value)) ? staff_link($value) . smaller(" ($value)",2) : $value;
	    }
	} elseif ($type == 'staff_list') {

		if (is_array($value)) {

			$n_value = array();
			foreach ($value as $sid) {
				
				switch ($show_value) {
				case 'CODE':
					break;
				case 'DESCRIPTION':
					$n_value[] = staff_link($sid);
					break;
				case 'BOTH':
				default:
					$n_value[] = staff_link($sid) . smaller(" ($sid)",2);
				}
				
			}
			$value = implode(oline(),$n_value);

		}

      } elseif ($type == AG_MAIN_OBJECT_DB) {
	    switch ($show_value)
	    {
	    case 'CODE':
		  break;
	    case 'DESCRIPTION':
		  $value=(!be_null($value)) ? client_link($value) : $value;
		  break;
	    case 'BOTH':
	    default:
		  $value=(!be_null($value)) ? client_link($value) . smaller(" ($value)",2) : $value;
	    }
      } elseif (substr($type,0,4) == 'date') {
		$value = orr(dateof($value),$value); //this is required for pre-formatted values
      } elseif (substr($type,0,9) == 'timestamp') {
		$value = datetimeof(datetotimestamp($value),'US');
	} elseif ($type == 'time') {
		$value = timeof($value,'ampm');
	} elseif ($type == 'currency') {
		$value = orr(currency_of($value),$value); //this is required for pre-formatted values
      } elseif ($type == 'lookup') {
		if (!be_null($value)) {
			// <Optimize Me!>
			$look=$field['lookup'];
			$sql = "SELECT {$look['label_field']} FROM {$look['table']}";
			$filt[$look['value_field']]=$value;
			$new_val=sql_assign($sql,$filt);
			// </Optimize Me!>
			switch ($show_value) {
			case 'CODE':
				$value = alt($value,$new_val);
				break;
			case 'DESCRIPTION':
				$value=alt(orr($new_val,"($value not found in lookup table)"),$value);
				break;
			case 'BOTH':
			default:
				$value=orr($new_val,'(not found in lookup table)') . smaller(" ($value)",2);
			}
		}
	} elseif ($type == 'lookup_multi') {
		if (is_array($value) ) { 
			
			$look=$field['lookup'];
			$sql = "SELECT {$look['label_field']} FROM {$look['table']}";
			if ($action=='list') {
				$show_value = orr($show_value,'DESCRIPTION');
			}
			foreach($value as $t_v) {
				$filt[$look['value_field']]=$t_v;
				$desc = orr(sql_assign($sql,$filt),"($t_v not found in lookup table");
				switch ($show_value) {
				case 'CODE':
					$n_value[] = alt($t_v,$desc);
					break;
				case 'DESCRIPTION':
					$n_value[] = alt($desc,$t_v);
					break;
				case 'BOTH':
				default:
					$n_value[] = $desc. smaller(" ($t_v)",2);
				}
			}
			$value = $do_formatting ? implode(','.oline(),orr($n_value,$value)) : orr($n_value,$value);
		}
      } elseif ($type == 'boolean') {
	    $value = be_null($value) ? '' : (sql_true($value) ? 'Yes' : 'No');
	} elseif ($type =='array') {
		$value = implode(', '.oline(),orr($value,array()));
      } elseif ($type == 'table_switch') {
		list($tmp_id,$tmp_obj) = get_table_switch_object_id($value,$def,$key);
		if ($tmp_id && $tmp_obj) {
			$tmp_def = get_def($tmp_obj);
			$tmp_singular = ucwords($tmp_def['singular']);
			$label = $action=='list' ? $tmp_singular .' '.$tmp_id : 'View/Edit '.$tmp_singular.' Data Record';
			$value = link_engine(array('object'=>$tmp_obj,
							   'action'=>'view',
							   'id'=>$tmp_id),$label);
		}
	} elseif ($type=='attachment') {
		$short_format = ($action == 'list');
		$value = link_attachment($value, $key, $short_format);
	}
	elseif (($type=='text') || ($type=='varchar')) {
		$value = hot_link_objects($value);
	}
      //VALUE FORMAT
	$value= be_null(trim($value)) ? '&nbsp;' : $value; // avoid blank cells w/o border
	if ($do_formatting) {
		$a=$field['value_format_'.$action];
		$x=$value;
		$value=eval("return $a;");
	}
    return $value;
}

function label_generic($key,$def,$action,$do_formatting=true)
{
      $field=$def['fields'][$key];
      $type=$field['data_type'];
	if (!$field) {
		return ucfirst($key);
	}
 
      //FORMAT LABEL
	if ( ($action=='add'||$action=='edit') and !$field['null_ok'] ) {
		$req =' '.red('*');
	}

      $label=$field['label_'.$action];
	$display = $field['display_'.$action];
	if (($type=='timestamp') and in_array($action,array('add','edit')) and !in_array($display,array('regular','display'))) {
		if (!$field['null_ok']) {
			$req = red(' *');
		}
 		$label = oline($label . ' Date'.$req)
			. right('Time');
		unset($req);
	}

	if ($do_formatting) {
		$a=$field['label_format_'.$action];
		$x=$label;
		$label = eval("return $a;");
		$label .= $req;
	}

	if ($action == 'view' && in_array($type,array('lookup','lookup_multi'))) {

		$l_table = $field['lookup']['table'];
		$label = link_engine(array('object'=>$l_table,'action'=>'list'),alt($label,'Show lookup list (Table: '.$l_table.')'),'',' class="fancyLink" target="_blank"');

	}

      return $label;
}

function data_dictionary($object=NULL,$field=NULL) {
	  if ($object) {
		$filter['name_table']=$object;  //FIXME: object v. table
	  } else {
		$filter['NULL:name_table']='dummy';
	  }
	  if ($field) {
	    $filter['name_field']=$field;
	  } else {
		$filter['NULL:name_field']='dummy';
	  }	  
	  //$GLOBALS['query_display']='Y';
      $dd_records = get_generic($filter,'','','data_dictionary');
	  $GLOBALS['query_display']=false;
	  while ($data_dict=sql_fetch_assoc($dd_records)) {
		$out .= $data_dict['comment_general'];
	  }
	  return $out;
}	
		
function data_dictionary_hide_show( $object=NULL,$field=NULL,$hide=true,$element_id=NULL ) {
	$element_id = orr($element_id,'dd.O'.$object.'.F'.$field);
	$data_dict = data_dictionary($object,$field);
	return !$data_dict ? '' :
				Java_Engine::hide_show_button($element_id) 
				. Java_Engine::hide_show_content(oline() . $data_dict,$element_id,$hide);
}

function view_generic($rec,$def,$action,$control='',$control_array_variable='control')
{
      global $colors,$Java_Engine;
      $control=unserialize_control($control);
      if (isset($control['list']['fields'])) {
	    $list_links=right(bold(view_generic_links($control,$def,$control_array_variable)));
      }
      $fields=$def['fields'];
	  $object=$def['object'];
      $out = tablestart('','class="engineForm"');
      $out .= ($list_links) 
	    ? row(cell($list_links,'colspan="2"'),'class="listHeader"')
	    : '';
	  $element_id = "dd.$object";
	  //$data_dict = Java_Engine::hide_show_button($element_id) . Java_Engine::hide_show_content(data_dictionary($object,NULL),$element_id);
	  $data_dict = data_dictionary_hide_show($object,NULL);
	  $out .= $data_dict ? row(cell("Data dictionary for $object")  . cell($data_dict)) : '';

      foreach ($rec as $key=>$value) {
	    $disp = $fields[$key]["display_{$action}"];
	    if ($disp=='multi_disp') { // THIS STUFF ALL NEEDS TO GO SOON!!
		  $sub_value = $value[$fields[$key]['multi_field']];
		  //$multi_out .= view_generic_row($key,$sub_value,$def,$action); //SAVE FOR THE END
		  //this stuff is so far removed from engine now, it is becoming a pain to keep it in sync w/ functionality
		  //thus I am de-genericizing it:
		  preg_match('/^multi_(.*)_multi_(.*)$/',$key,$matches);
          $multi_out[$matches[1]] .= be_null($sub_value) ? '' : rowrlcell($fields[$key]['label_add'],$sub_value);
	    } else {
		  // EVALUATE $value
		  $x=$value;
		  $value = $fields[$key] 
			  ? eval('return '. $fields[$key]['value_'.$action].';')
			  : $value;
		  if ($disp=='hide') {
			// nothing...want to capture special fields that don't have $disp
			// set, so this test goes above the one below...!$disp
		  } elseif ($disp=='regular' || $disp=='display' ) {
			  if ($key=='sys_log') {
				  //special treatment for syslog for now
				  if (has_perm('system_log_field')) {

					  if ($action == 'edit') {

						  $old_value = div(smaller(value_generic($control['rec_last'][$key],$def,$key,$action)),'','class="append_only"');

					  }

					  $button = be_null($value)
						  ? smaller(gray('+/-'))
						  : Java_Engine::toggle_id_display(smaller('+/-'),'hiddenSystemLog'.$i,'block');
					  $system .= smaller(label_generic($key,$def,$action).' ('.$button.'):')
						  . div($old_value . smaller(value_generic($value,$def,$key,$action)),'hiddenSystemLog',' style="display: none;"');
				  }
			  } elseif ($fields[$key]['system_field']) {
				  $system .= oline(smaller(label_generic($key,$def,$action).': '.value_generic($value,$def,$key,$action)));
			  } else {
				  $out .= $def['fn']['view_row']($key,$value,$def,$action,$rec);
			  }
		  } else {
				  $out .= $def['fn']['view_row']($key,$value,$def,$action,$rec);
		  }
	    }
      }
      
      // need to handle calculated fields that aren't in the table's structure
      if ($fields) {
	    unset($x);
	    foreach($fields as $key => $field) {
		  // CAPTURE VIRTUAL FIELDS
		  if (!array_key_exists($key,$rec)) {
			$disp = $fields[$key]['display_'.$action];
			if ($disp=='hide') {
			      //nothing
			} else {
			      $value =  eval('return '.$fields[$key]['value_'.$action].';');
				$out .= $def['fn']['view_row']($key,$value,$def,$action,$rec);
			}
		  }
	    }
      }
      
      $out .= row(cell($system,'class="systemField" colspan="2"'));
      $out .= tableend();
		// TACK ON MULTI RECORDS
		if (is_array($multi_out)) {   
			foreach($multi_out as $k=>$v) {
	    		$out .= oline() . bigger(bold(oline(orr($def['multi'][$k]['sub_title'],''))))
						. $def['multi'][$k]['sub_sub_title']
						. tablestart('','class="engineForm"')
					   . $v
					   . tableend();
			}
		}
      return $out;
}

function view_generic_row($key,$value,$def,$action,$rec)
{
	global $colors;
      $value=value_generic($value,$def,$key,$action);
      $label=label_generic($key,$def,$action);

	//ideally, comment handling would be in the label_generic function, but for now, not breaking things is good
	if (($tmp = $def['fields'][$key]['comment']) && $def['fields'][$key]['comment_show_'.$action]) {
		$comment = div(webify($tmp),'',' class="generalComment"');
	}
	$data_dict = data_dictionary_hide_show($def['object'],$key);
	if ($data_dict) {
		$comment .= $data_dict;
	}

	$l_opts = 'class="engineLabel"';
	$v_opts = 'class="engineValue"';

	$pr = $def['fields'][$key];

	switch ($pr['cell_align_label']) {
	case 'center':
		$label_cell = centercell($label.$comment,$l_opts);
		break;
	case 'left':
		$label_cell = leftcell($label.$comment,$l_opts);
		break;
	case 'right':
		$label_cell = rightcell($label.$comment,$l_opts);
		break;
	default:
		$label_cell = cell($label.$comment,$l_opts);
	}

	switch ($pr['cell_align_value']) {
	case 'center':
		$value_cell = centercell($value,$v_opts);
		break;
	case 'right':
		$value_cell = rightcell($value,$v_opts);
		break;
	case 'left':
		$value_cell = leftcell($value,$v_opts);
		break;
	default:
		$value_cell = cell($value,$v_opts);
	}

	return row($label_cell.$value_cell);

}

function view_generic_links($control,$def,$control_array_variable='control')
{
      $control=unserialize_control($control);

      //MIMIC THE LIST QUERY
      $object=$control['object'];
      $table=$def['table'];
      $position=$control['list']['position'];
      $filter=$control['list']['filter'];
      $order=$control['list']['order'];
      if (!$order) {
	    $order=array_flip($fields);
	    foreach($order as $field=>$value)
	    {
		  $order[$field]=false;
	    }
      }
      $result=list_query($def,$filter,$order,$control);
      $total=sql_num_rows($result);

      $rec_no=$position+1;
      $place = "Record $rec_no of $total ";

      if ( $total > $position+1 ) {
	    $next_rec=sql_fetch_assoc($result,$position+1);
	    $next_control=$control;
	    $next_control['list']['position']=$position+1;
	    unset($next_control['page']);
	    $next_link=list_view_link($next_rec,$next_control,$def,'','Next');
      }
      if ( $position-1 >= 0 ) {
	    $prev_rec=sql_fetch_assoc($result,$position-1);
	    $prev_control=$control;
	    $prev_control['list']['position']=$position-1;
	    unset($prev_control['page']);
	    $prev_link=list_view_link($prev_rec,$prev_control,$def,'','Previous');
      }

      // MIMIC THE LIST CONTROL ARRAY
      $list_control=$control;
      $max=$list_control['list']['max'];
      $list_control['list']['position']=floor($position/$max)*$max;
      $list_control['action']='list';
      $list_link = link_engine($list_control,'List',$control_array_variable);

      return $place . $prev_link.' '.$next_link.' '.$list_link;
}

function form_field_generic($key,$value,&$def,$control,&$Java_Engine,$formvar='rec')
{
	$action = $control['action'];
      $pr     = $def['fields'][$key];
      $label  = $pr['label_'.$action];
      $type   = $pr['data_type'];

	//JavaScript handling
	if (is_object($Java_Engine)) {
		$element_options=$Java_Engine->form_element_java($key);
	}

	if ($type=='currency') {
		$type='float';
	} elseif ($type=='phone') {
		$type='varchar';
	}
      $len = $pr['length'];
	switch ($type) {
	case 'staff':
	    $subset = $pr['staff_subset'];
	    $active_only = $action=='add' && !$pr['staff_inactive_add'] ? 'HUMAN' : false;
	    $field = pick_staff_to($formvar.'['.$key.']',$active_only,$value,$subset,$element_options);
	    break;
	case 'staff_list':
		$field = make_staff_list_form($value,$key,$def,$control,$formvar);
		break;
      case AG_MAIN_OBJECT_DB:
		$allowed=$pr[$action.'_main_objects'] || be_null($value); //edit & add
		$field = $value 
			? (client_link($value) . hiddenvar($formvar.'['.$key.']',$value))
			: '';
		if ($allowed) {
			$hide = !be_null($value);
			$hide_button = Java_Engine::hide_show_button($key.'ClientSelector',$hide);
			$little_box = Java_Engine::hide_show_content(client_selector(false),$key.'ClientSelector',$hide);
			$wipeout = be_null($value) ? '' : formvar_wipeout($formvar.'['.$key.']');
			$field .= $hide_button . $little_box . $wipeout
				. hiddenvar('engineQuickSearchField',$key);
		}
		break;
      case 'timestamp':
	case 'timestamp_past':
	case 'timestamp_future':
		switch ($pr['timestamp_format']) {
		case 'drop_list' :
			$time_field = Calendar::generate_time_list($formvar.'['.$key.'_time_]',timeof($value,'SQL'));
			break;
		default :
            $time_field = formtime($formvar.'['.$key.'_time_]',timeof($value),$element_options) ;
		}
		
		$field = hiddenvar($formvar.'['.$key.']',$value)
			. oline(formdate($formvar.'['.$key.'_date_]',dateof(trim($value)),'Calendar',$element_options)) 
			. $time_field;
		$label = oline($label . ' date')
			. right('time');
		break;
      case 'lookup':
		if ($Java_Engine->FLAG[$key]['blank_selects']) {
			$field = selectto($formvar.'['.$key.']').selectend();
			//the lookup fields will be populated dynamically
		} else {
			$query = build_lookup_query($pr,$action);
			switch ($pr['lookup_format']) {
			case 'radio_v':
			case 'radio':
				$tmp_spacer = $pr['lookup_format'] == 'radio_v' ? oline() : '';
				$tmp_field = do_radio_sql($query,$formvar.'['.$key.']',$addnull=true,$value,$tmp_spacer);
				break;
			case 'droplist':
			case 'select':
			default:
				// $select=do_pick_sql(make_agency_query($query,$filt),$value,($pr["null_ok"] || $def["null_ok"]));
				// null option should always be present, even if null is not ok
				// that way, users are forced to choose a value rather than defaulting to first on list
				$select=do_pick_sql($query,$value,true);
				$tmp_field = $select
					? selectto($formvar.'['.$key.']',$element_options) . $select . selectend()
					: false;
			}
			$field = orr($tmp_field,formvartext($formvar.'['.$key.']',$value, $element_options));
		}
		break;
	case 'lookup_multi':
		$query = build_lookup_query($pr,$action);
		switch ($pr['lookup_format']) {
		case 'checkbox_v':
		case 'checkbox':
			if ($pr['comment'] == '(ctrl-click to select multiple values)') {
				$def['fields'][$key]['comment'] = '(select all that apply)';
			}
			$tmp_spacer = $pr['lookup_format'] == 'checkbox_v' ? oline() : '';
			$tmp_field = do_checkbox_sql($query,$formvar.'['.$key.']',$value,$tmp_spacer);
			break;
		case 'droplist':
		case 'select':
		default:
			$select = do_pick_sql($query,$value,true);
			$tmp_field = $select
				? selectto_multiple($formvar.'['.$key.'][]','',$element_options) . $select . selectend()
				: false;
		}
		$field = $tmp_field ? $tmp_field : formvartext($formvar.'['.$key.']',$value, $element_options);
		break;
	case 'time':
		if ($pr['time_drop_list']) {
			$field = Calendar::generate_time_list($formvar.'['.$key.']',$value);
			break;
		}
	case 'float':
		$len++;
      case 'varchar':
	case 'character':
	    $size = $len < 70 ? $len : '70'; // 92 matches default textarea width
	    $options = ($len ? "maxlength=\"$len\" size=\"$size\"" : '').$element_options;
	    $field = form_field($type,$formvar.'['.$key.']',$value,$options);        
	    break;
	case 'text':

		$options = ($len ? "maxlength=\"$len\"" : '').$element_options;
		$textarea_x = orr($pr['textarea_width'],60);
		$textarea_y = orr($pr['textarea_height'],10);

		if ($pr['append_only']) {

			$existing = div(value_generic($control['rec_last'][$key],$def,$key,$action),'','class="append_only"');

		}

		$field = $existing . formtextarea($formvar.'['.$key.']',$value,$options,$textarea_x,$textarea_y);
		break;
	case 'boolean':
		switch ($pr['boolean_form_type']){
		case 'checkbox':
			$field = form_field('boolcheck',$formvar.'['.$key.']',sql_true($value),$element_options);
			break;
		case 'allow_null':
			$wipeout = formradio_wipeout($formvar.'['.$key.']');
		default:
			$field = form_field($type,$formvar.'['.$key.']',$value,$element_options).$wipeout;
		}
		break;
	case 'array':
		if (!be_null($value) and is_array($value)) {
			$field = array();
			foreach ($value as $t_v) {
				$field[] = formvartext($formvar.'['.$key.'][]',$t_v, $element_options);
			}
			$field = implode(oline(),$field);
		} else {
			$field = formvartext($formvar.'['.$key.'][]',$t_v, $element_options);
		}
		$f_id = $formvar . $key . 'arrayGroup';
		$field = div($field,$f_id);
		$field .= js_add_content_link(oline().formvartext($formvar.'['.$key.'][]','', $element_options),
							$f_id,smaller('Add more...',2),' class="fancyLink"');
		break;

	case 'attachment':
		/*
		 * If there is a pending or attached file, we want to display a remove button 
		 * and the existing value. 
		 * 
		 * Otherwise, set $value to be null, as it would only contain error text
		 */
		
		if ((!be_null($value)) && (is_numeric($value) || (is_array($value) && ($value['session_key'] && is_readable($_SESSION[$value['session_key']]['tmp_file']))))) { 
			
			$control_object = $_REQUEST['control']['object'];
			$control_id = $_REQUEST['control']['id'];
			$control_action = $_REQUEST['control']['action'];
			$control_step = $_REQUEST['control']['step'];
			
			for ($i = 0;  $_REQUEST['remove_attachment'.$i]; $i++) {
				$to_remove .= '&remove_attachment'.$i . '='.$_REQUEST['remove_attachment'.$i];
			}
			
			$to_remove .= '&remove_attachment'.$i .'=' . $key;
			$remove_button =  smaller(hlink($_SERVER['PHP_SELF']
								  .'?control[object]='.$control_object
								  .'&control[id]='.$control_id
								  .'&control[action]='.$control_action
								  .'&control[step]='.$control_step
								  . $to_remove
								  ,'Remove',''
								  ,' class="removeButton" onclick="'.call_java_confirm('Are you sure you want to remove the attachment?').'"'));	
	
			$value = $remove_button . value_generic($value,$def,$key,$action);
		} else {
			/*
			 * $value contains error text, so set to null.
			 */
			$value = null;
		}

		$field =  form_field($type,$formvar.'['.$key.']',$value,$element_options,$pr['max_file_size'] );
		break;
		
	default:
	    $field = form_field($type,$formvar.'['.$key.']',$value,$element_options);
	}
	return $field;
}

function form_generic_row($key,$value,&$def,$control,&$Java_Engine,$rec,$formvar='rec')
{

	$field = form_field_generic($key,$value,&$def,$control,&$Java_Engine,$formvar);

      //FORMAT LABEL
	$action = $control['action'];
      $pr=$def['fields'][$key];
      $not_valid_flag=$pr['not_valid_flag'];
      $type=$pr['data_type'];
      $label=label_generic($key,$def,$action);
      $label = $not_valid_flag ? span($label,'class=engineFormError') : $label; //display in red invalid fields

	$label .= ($pr['comment'] && $pr['comment_show_'.$action])
		? div(webify($pr['comment']),'',' class="generalComment"')
		: ''; // comments look better on next line

	//append only comment appended
	if ($pr['append_only'] && $action == 'edit') {

		$label .= oline() . smaller('Append to this field');

	}

	$l_opts = 'class="engineLabel"';

	switch ($pr['cell_align_label']) {
	case 'center':
		$label_cell = centercell($label,$l_opts);
		break;
	case 'left':
		$label_cell = leftcell($label,$l_opts);
		break;
	case 'right':
		$label_cell = rightcell($label,$l_opts);
		break;
	default:
		$label_cell = cell($label,$l_opts);
	}

	$v_opts = 'class="engineValue"';

	switch ($pr['cell_align_value']) {
	case 'center':
		$value_cell = centercell($field,$v_opts);
		break;
	case 'right':
		$value_cell = rightcell($field,$v_opts);
		break;
	case 'left':
		$value_cell = leftcell($field,$v_opts);
		break;
	default:
		$value_cell = cell($field,$v_opts);
	}
	$out = row($label_cell.$value_cell);
      return $out;
}

function form_generic($rec,$def,$control)
{
	$Java_Engine = new Java_Engine($def,$rec);
	$action      = $control['action'];
      $fields      = $def['fields'];

      $out = tablestart('','class="engineForm"');
      foreach ($rec as $key=>$value) {

		if ($fields[$key]['never_to_form']) { continue; }
		$disp = $fields[$key]["display_$action"];
		if ($disp=='edit') { unset($disp); }

		if ($key=='sys_log' && has_perm('system_log_field','W')) {

			unset($disp);

		}

		if ($disp=='hide') {
			$out .= hiddenvar("rec[$key]",$value);	
		} elseif ($disp=='display') {
			$out .= hiddenvar("rec[$key]",$value); //moving hidden variable above $value change - JH 2/7/05
			// EVALUATE $value
			$x=$value;
			$value = eval('return '. $fields[$key]['value_'.$action].';');
			$out .= $def['fn']['view_row']($key,$value,$def,$action,$rec);
		} elseif ($disp=='multi_disp') {
			preg_match('/^multi_(.*?)_multi_(.*)$/',$key,$matches);
			$multi_out[$matches[1]] .= $def['multi'][$matches[1]]['form_row_fn']($key,$value,$def,$matches[1]);
		} else {
			$out .= $def['fn']['form_row']($key,$value,$def,$control,$Java_Engine,$rec);
		}
      }
	  $out .= tableend();
		if ($multi_out) {
			foreach($multi_out as $key=>$multi) {
				$out .= oline() . bigger(bold(oline(orr($def['multi'][$key]['sub_title'],'')))) . $def['multi'][$key]['sub_sub_title']
		     . tablestart('','class="engineForm"')
		     . $multi
		     . tableend();
			}
		}
		$GLOBALS['AG_HEAD_TAG'].=$Java_Engine->get_javascript();
		return $out;
}

function get_generic($filter,$order='',$limit='',$def,$table_post=false,$group='')
{
	if ($group) {
		$group_fields = $group['fields'];
		$order=implode(',',orr($group['order'],$group_fields)); // if no explicit order, use group fields
		$values = orr($group['values'],array('count(*)'));
		$sql = "SELECT " . implode(',',$group_fields) . "," . implode(',',$values) . " FROM " 
				. orr($table_post,is_array($def) ? $def['table'] : $def);
	} elseif ($table_post) {
		$sql = "SELECT * FROM ".$def['table_post'];
	} elseif (is_array($def)) {
		$sql = $def['sel_sql'];
	} else {
		$sql = "SELECT * FROM $def";
	}
      // $def should be an def array, but you can stuff a table name in here too.
	  return agency_query($sql,$filter,$order,$limit,'',$group);
}

function get_active_generic(&$filter,$rec,$def,$order='')
{
	// FIXME:  This function fails if record exists w/ no end date,
	// and new record is added w/ earlier start date and no end date

	$fields = array_keys($def['fields']);
	$object = $def['object'];

	//check for start/end dates
	$start = orr($def['active_date_field'],$object.'_date');
	$end   = orr($def['active_date_end_field'],$object.'_date_end');
	if ($new_filter = $def['active_record_filter']) {
		//nothing
	} elseif (in_array($start,$fields) and in_array($end,$fields)) {
		$new_filter = array();

		$start_date_compare = ($e_comp = orr($rec[$end],$rec[$start]))   ? enquote1(sql_escape_string($e_comp))   : 'CURRENT_DATE';
		$end_date_compare   = $rec[$start] ? enquote1(sql_escape_string($rec[$start])) : 'CURRENT_DATE';

		$new_filter['FIELD<=:'.$start] = $start_date_compare;
		$new_filter[]                  = array('FIELD>=:'.$end=>$end_date_compare,
								   'NULL:'.$end=>'');

		$order = orr($order,$start.' DESC');

	} else {
		$order = orr($order,$def['id_field'].' DESC'); //default to pull most recent record
	}
	$filter = array_merge($new_filter,$filter);
	return $def['fn']['get']($filter,$order,'1',$def);
}

function valid_generic($rec,&$def,&$mesg,$action,$rec_last=array())
{
      global $engine_ignore_fields;
      $VALID=true;
      $fields=$def['fields'];
	//manual record-wide validity check
	if ($val = $def['valid_record']) {
		// field can have multiple tests and multiple messages to display
		foreach ($val as $test => $msg)  
		{
			if (!eval( "return $test;" ))
			{
			      $mesg .= empty($msg) 
					? oline("Record has invalid data")
					: oline($msg);
			      $VALID=false;
			}
		}
	}
	if ($def['multi_records']) { //horrid 'generic' multi-record hack
		foreach ($def['multi'] as $c_obj=>$opts) {
			$opts['valid_fn']($action,$rec,&$def,&$mesg,&$VALID,$c_obj);  
		}
	}
	foreach ($rec as $key=>$value) {
	    $type=$fields[$key]['data_type'];
	    $label=$fields[$key]['label_'.$action];
	    $pr=$fields[$key];
	    $valid=true;
	    if ($type=='multi_rec') { //do nothing, but capture
		    continue;
	    } elseif ( (be_null(trim($value))) && (!$fields[$key]['null_ok']) ) {	
		    $mesg .= oline("Field $label cannot be blank.");
		    $valid=false;
	    } elseif ( ($type == 'attachment') && is_array($value)) {
		    if ($value['uploaded'] == false)  { 
			    // User tried to upload file, but it failed (and field could not be null.)
			    $mesg .= oline($value['error']);
			    $valid = false;	
		    } elseif ( $value['session_key'] && !(is_readable(($q=$_SESSION[$value['session_key']]['tmp_file'])))) {
			    // pending upload is not readable.
			    $mesg .= oline("Attachment pending in field $label is not readable from $q.");
			    $valid = false;
		    }
	    } elseif ( ($type=='ssn') && (!ssn_of($value))  && (!be_null($value)) ) {
		  $mesg .= oline("Field $label has an invalid SSN.");
		  $valid=false;
	    } elseif ( ($type=='time' || substr($type,0,5)=='time_') && (!timeof($value)) && (!be_null($value)) ) {
		  $mesg .= oline("Field $label has an invalid time.");
		  $valid=false;
	    } elseif ( ($type=='time_past') && (timeof($value,'SQL') > timeof('now','SQL'))  && (!be_null($value)) ) {
		  $mesg .= oline("Field $label cannot be in the future.");
		  $valid=false;
	    } elseif ( ($type=='time_future') && (timeof($value,'SQL') < timeof('now','SQL'))  && (!be_null($value)) ) {
		  $mesg .= oline("Field $label cannot be in the past.");
		  $valid=false;
	    } elseif ((substr($type,0,4)=='date') && (!dateof($value))  && (!be_null($value))) {
		  $mesg .= oline("Field $label has an invalid date.");
		  $valid=false;
	    } elseif ( ($type=='date_past') && (dateof($value,'SQL') > dateof('now','SQL'))  && (!be_null($value)) ) {
		  $mesg .= oline("Field $label cannot be in the future.");
		  $valid=false;
	    } elseif ( ($type=='date_future') && (dateof($value,'SQL') < dateof('now','SQL'))  && (!be_null($value)) ) {
		  $mesg .= oline("Field $label cannot be in the past.");
		  $valid=false;
	    } elseif ( (substr($type,0,9)=='timestamp') && (!is_timestamp($value,'SQL')) 
			   && !($pr['timestamp_allow_date'] && dateof($value)) //if defined, date-only values are valid
			   && (!be_null($value)) ) {
		    $mesg .= oline("Field $label has an invalid timestamp.");
		    $valid=false;
	    } elseif ( ($type=='timestamp_past') && (datetimeof(datetotimestamp($value),'SQL') > datetimeof('now','SQL'))  && (!be_null($value)) ) {
		  $mesg .= oline("Field $label cannot be in the future.");
		  $valid=false;
	    } elseif ( ($type=='timestamp_future') && (datetimeof(datetotimestamp($value),'SQL') < datetimeof('now','SQL'))  && (!be_null($value)) ) {
		  $mesg .= oline("Field $label cannot be in the past.");
		  $valid=false;
	    }	elseif ( ($type=='currency') && (!currency_of($value)) && (!be_null($value)) ) {
		  $mesg .= oline("Field $label must be a dollar amount.");
		  $valid=false;
	    } elseif ( ($type=='phone') && (!phone_of($value)) && (!be_null($value)) ) {
		    $mesg .= oline("Field $label contains an invalid phone number.")
			    . oline(indent(smaller('A valid phone number is of the form: ('.AG_DEFAULT_PHONE_AREA_CODE.') 123-4567')));
		    $valid=false;
	    } elseif ( ($type=='float') && (!is_numeric($value)) && (!be_null($value)) ) {
		  $mesg .= oline("Field $label must be a number.");
		  $valid=false;
	    } elseif ( ($type=='integer')
			   and (!is_integer_all($value))
			   and (!be_null($value))) {
		  $mesg .= ($value > AG_POSTGRESQL_MAX_INT)
			  ? oline("Field $label is out of range for integers (max: ".AG_POSTGRESQL_MAX_INT.")")
			  : oline("Field $label must be a whole number.");
		  $valid=false;
	    } elseif ( ($type=='interval')
			   and !is_interval($value) and !be_null($value)) {
		    $mesg .= oline("Field $label must be of the form x [years,months,weeks,days,hours,minutes, or seconds]");
		    $valid=false;
	    } elseif ( $type == 'array'
			   and count($value) > $pr['array_max_elements'] ) {

		    $mesg .= oline("Field $label has ".count($value)." elements while the maximum allowable values is ".$pr['array_max_elements'].".");
		    $valid = false;

	    } elseif ( $type == 'lookup_multi'
			   and !sql_lookup_value_exists($value,$pr['lookup']['table'],$pr['lookup']['value_field']) and !be_null($value)) {
		    $mesg .= oline("Field $label has an unknown value");
		    $valid = false;
	    } 

	    //maximum length
	    if (!is_array($type) and strlen($value) > AG_MAXIMUM_STRING_LENGTH) {
		    $valid = false;
		    $mesg .= oline('Field '.$label.' has exceeded the maximum length ('.AG_MAXIMUM_STRING_LENGTH
					 .'). Contact your system administrator if this is valid data.');
	    }

		//require comments on specific values
		if (in_array($value,$pr['require_comment_codes']) and be_null($rec['comment'])) {
			$def['fields']['comment']['not_valid_flag']=true;
			$VALID=false;
			//$valid=false;
			$mesg .= oline("Comment required for $label of " . value_generic($value,$def,$key,'view'));
		}

	    //manual validity definitions
	    if ($val = $fields[$key]['valid']) {
		    $x=$value;  
		    $ox = $rec_last[$key];
		    // field can have multiple tests and multiple messages to display
		    foreach ($val as $test => $msg) {
			    if (!eval( "return $test;" )) {
				    $mesg .= empty($msg) 
					    ? oline("Field $label has an invalid value.")
					    : oline(str_replace('{$Y}',$label,$msg));
				    $valid=false;
			    }
		    }
	    }
	    $def['fields'][$key]['not_valid_flag']= orr($def['fields'][$key]['not_valid_flag'],$valid ? false : true);
	    $VALID = $valid ? $VALID : $valid;
      }
      return $VALID;
}

function confirm_generic($rec,$def,&$mesg,$action,$rec_last)
{
      $confirmed = true;
      $fields=$def['fields'];
	if ($conf = $def['confirm_record']) {
		foreach ($conf as $test => $msg) {
			if (!eval( "return $test;" )) {
			      $mesg .= empty($msg) 
					? oline("Record requires confirmation?")
					: oline($msg);
			      $confirmed = false;
			}
		}
	}
      foreach ($rec as $key=>$value) {
		if($confirm = $fields[$key]['confirm']) {  // just like valid
			$label = $fields[$key]['label_'.$action];
			$x = $value;
			$ox = $rec_last[$key];
			foreach($confirm as $test => $msg) {
				if(!eval("return $test;")) {
					$confirmed = false;
					$mesg .= empty($msg) 
						? oline("Please review field $label.")
						: oline(str_replace('{$Y}',$label,$msg));                
				}
			}
		}
      }
      return $confirmed;
}

function rec_changed_generic($rec,$rec_last,$def)
{
	// first, check for append only fields
	foreach ($rec as $key => $value) {

		if ($def['fields'][$key]['append_only'] && !be_null($rec[$key])) {

			return true;

		}

	}

	$rec = unset_system_fields($rec);
	$rec_last = unset_system_fields($rec_last);
      return !($rec==$rec_last);
}

function unset_system_fields($rec)
{

      unset($rec['changed_at'],$rec['changed_by'],$rec['added_at'],$rec['sys_log']);

	return $rec;

}

function unset_system_fields_all($rec)
{
	global $engine;

	foreach (array_keys($engine['system_fields']) as $key) {

		unset($rec[$key]);

	}

	return $rec;
}

function rec_collision_generic($rec,$rec_last,$def,$action,&$mesg)
{

	/*
	 * $rec --> the current 'live' edited version of the record
	 * $rec_last --> the initial record prior to edits (stored as session variable)
	 * $old_rec --> if no collision, this should be the same as $rec_last
	 */

	if ($action=='add') { return false; } //can't have collisions on adds

	$rec = unset_system_fields($rec);
	$rec_last = unset_system_fields($rec_last);

	$rec = php_to_sql_generic($rec,$def);
	$rec_last = php_to_sql_generic($rec_last,$def);

	$filter = array($def['id_field']=>$rec[$def['id_field']]);

	/*
	 * Using get_generic() since custom get functions tend to modify they record,
	 * which doesn't work for determining a record collision
	 */
	$old_rec = sql_fetch_assoc(get_generic($filter,'','',$def,$def['use_table_post_'.$action]));
	$old_rec = unset_system_fields($old_rec);

	if ($rec_last == $old_rec) {

		/*
		 * No collision
		 */

		return false;

	}

	/*
	 * Record collision
	 */

	$changed_by = $old_rec['changed_by'] ? ' ('.staff_link($old_rec['changed_by']).') ' : '';

	if ($rec == $old_rec) { //user's changes took - user probably hit reload

		$mesg .= oline('Your changes have already been submitted. Re-posting of changes blocked.');

	} else {

		$rev_hist = Revision_History::has_history($def['table_post']) 
			? 'See '.Revision_History::link_history($def['object'],$rec[$def['id_field']],'View Revision History').' for details.'
			: '';

		$mesg .= oline(bigger('WARNING: This record has been changed by another user '.$changed_by.'since your edit started. EDIT FAILED.'))
			. $rev_hist;

	}
	
	return $old_rec;
}

function post_generic($rec,$def,&$mesg,$filter='',$control=array())
{
      // if a filter is passed, it means we want to update an existing record
      // no filter means add a new record
      $fields=$def['fields'];
      $table=$def['table_post'];
	$def_id_field = $def['id_field'];
      $action = $filter ? 'edit' : 'add' ;

	//postgres array handling
	$rec = php_to_sql_generic($rec,$def);

      foreach ($rec as $key=>$value) {
	    //detach multiple records into a separate array for easier manipulation
	    $type=$fields[$key]['data_type'];
	    if ($type=='multi_rec') {
			if (!strstr($key,'no_multi_records')) {
			  $multi_records[$key]=$value; 
			}
			unset($rec[$key]);
			continue;
	    }
	    if ($type=='attachment') {
		    $posted_new_attachment = false;
		    /* 
		     * $value can be integer, indicating key for attachment table, or array,
		     * indicating temp file waiting to be posted
		     */
		    if (is_array($value) && $value['uploaded']) {
			    $post_attachment_res = post_attachment($value['session_key'], $def['object'], $key);
			    
			    if (is_numeric($post_attachment_res)) {
				    $posted_new_attachment = true;
				    $rec[$key] = $value = $post_attachment_res;
			    } elseif (is_string($post_attachment_res)) {
				    $mesg .= oline($post_attachment_res);
				    log_error($post_attachment_res);
				    return false;
			    } else {
				    $mesg .= oline('Failed to post attachment. ');
				    log_error('Unexpected value when trying to post attachment. Value is :' . $new_attachment_id);
				    return false;
			    }	
		    } elseif ($value && !is_numeric($value)) {
			    //null $value is okay 	    
	 		    $mesg .= oline('Unexpected value in '. $key. ' field.  Yikes! IT IS ' . $value);
			    log_error('Unexpected value in attachment field.  Yikes! IT IS ' . $value);
			    return false;
		    }

		    /*
		     * If editing record and have uploaded a new file, then we need to update attachment table: 
		     * get value for this field in record we are editing, and get id_field.
		     * update attachment record (where attachment_id equals returned value) to set obsolete at/by
		     * and set parent_id to be returned id_field
		     */
		    if ($action =='edit' && $posted_new_attachment) {

			    $old_rec = sql_fetch_assoc(get_generic($filter, '','',$def));

			    if (is_numeric($old_rec[$key])) {
				    $assoc_attachment_def = get_def('attachment_link');
				    $assoc_attachment_table = $assoc_attachment_def['table_post'];
				    $assoc_attachment_id_field = $assoc_attachment_def['id_field'];		
				    $assoc_attachment_filter = array($assoc_attachment_id_field => $old_rec[$key]);
		
				    $assoc_attachment_rec = array(
								    'parent_id_obsolete' => $old_rec[$def_id_field],
								    'obsolete_at' =>  'now()',
								    'obsolete_by' => $GLOBALS['UID'],
								    'changed_at' =>  'now()',
								    'changed_by' => $GLOBALS['UID']
								    );
				    
				    agency_query(sql_update($assoc_attachment_table,$assoc_attachment_rec,$assoc_attachment_filter));
			    }
		    }
	    }

	    if ($action=='add') {

		  $ADD_FILTER[$key]=$value;	    //contsruct an add filter to verify that this isn't a duplicate

		  if ($key=='added_at' || $key=='changed_at' || $key=='deleted_at' || $key=='deleted_by') {

			unset($ADD_FILTER[$key]);
			if (in_array($key,array('added_at','changed_at'))) {
			      $ADD_FILTER['>=:'.$key]=minusHour('',$def['duplicate_posting_window']);
			}
		  } elseif ($fields[$key]['view_field_only'] || $fields[$key]['virtual_field'] ) {
			unset($ADD_FILTER[$key]);
		  } elseif (be_null($value)) {
			$ADD_FILTER["FIELD:BE_NULL($key)"]='true';
			unset($ADD_FILTER[$key]);
		  }

		  if (($condition = $fields[$key]['add_query_modify_condition']) && is_array($condition)) { //for tables with value-changing triggers
			  foreach ($condition as $e_code => $new_val) {
				  $x = $value;
				  $change_val = eval('return '.$e_code.';');
				  if ($change_val && $new_val=='ENGINE_UNSET_FIELD') {
					  unset($ADD_FILTER["FIELD:BE_NULL($key)"]); //in case of null conditions
					  unset($ADD_FILTER[$key]);
				  } else {
					  $ADD_FILTER[$key] = $change_val ? $new_val : $value;
				  }
			  }
		  }
	    }
	    if (be_null($value) && !is_null($fields[$key]['null_post_value'])) {
		    $rec[$key] = $fields[$key]['null_post_value'];
	    }
  	    if ($key=='changed_at') {
		  unset($rec[$key]);
		  $rec['FIELD:'.$key]='CURRENT_TIMESTAMP';
	    }
	    if ($key=='changed_by') {
		    $rec[$key] = $GLOBALS['UID'];
	    }
	    if (!$fields[$key]['post_'.$action]) {
		  unset($rec[$key]);
	    }
	    if ($fields[$key]['append_only'] && !be_null($control['rec_last'][$key])) {

		    $rec[$key] = $control['rec_last'][$key] . "\n" . $rec[$key];

	    }

	    
      }

      // FOR ADD, CHECK TO SEE IF DUPLICATE
      if ($action=='add') {
	    $duplicate = agency_query("SELECT * FROM $table",$ADD_FILTER);
	    if (sql_num_rows($duplicate) > 0 ) {
		  //DUPLICATE RECORD EXISTS
		  $singular=$def['singular'];
		  $mesg .= oline(bigger('RECORD NOT POSTED')) 
			. oline("This record has already been entered in the $singular table.");
		  return sql_to_php_generic(sql_fetch_assoc($duplicate),$def);
	    }
      }
      
      $result = agency_query( $filter
				    ? sql_update($table,$rec,$filter,sql_supports_returning() ? '*' : false)
				    : sql_insert($table,$rec,sql_supports_returning() ? '*' : false)
				    );

      if (!$result) {

		  $mesg .= oline("Your attempt to {$action} a record failed.");
		  log_error("Error in {$action}ing, using post_generic(). Here was the record: " . dump_array($rec));
		  return false;

	} elseif (sql_supports_returning()) {

		// fetch back the returned record
		$tmp_new_rec = sql_fetch_assoc($result);

		// just to be safe, the record is re-queried from the table using the primary key
		if ($id = $tmp_new_rec[$def['id_field']]) {

			$NEW_REC = sql_to_php_generic(sql_fetch_assoc(get_generic(array($def['id_field'] => $id),'','',$def)),$def);

		} else {

			// if for whatever reason this isn't available, we go with the returned record.
			$NEW_REC = $tmp_new_rec;

		}

	} else {

		// if insert/update returning is not supported, must attempt at a filter to retrieve the record
      
		// we're having a problem w/ nulls, so as a temporary
		// hack we'll set null values to be NULL or '' for query
		// This is a temporary hack and needs fixing!!

		$test_rec=array();

		foreach ($rec as $key=>$value) {

			if (be_null($value)) {

				$test_rec["FIELD:BE_NULL($key)"]='true';

			} elseif(substr($key,0,6) == 'FIELD:') {

				continue;// skip fields like added_at, changed_at

			} else {

				$test_rec[$key]=$value;

			}

			if (($condition = $fields[$key][$action.'_query_modify_condition']) && is_array($condition)) {

				foreach ($condition as $e_code => $new_val) {

					$x = $value;
					$change_val = eval('return '.$e_code.';');

					if ($change_val && $new_val=='ENGINE_UNSET_FIELD') {

						unset($test_rec[$key]);
						unset($test_rec["FIELD:BE_NULL($key)"]); //in case of null conditions

					} else {

						$test_rec[$key] = $change_val ? $new_val : $value;

					}

				}

			}

		}
	
		$new_rec = get_generic($test_rec,'','',$def,$def['use_table_post_'.$action]);
		$NEW_REC = sql_to_php_generic(sql_fetch_assoc($new_rec),$def);

	}
      if (isset($multi_records)) {
	    foreach ($def['multi'] as $m=>$opts) {
			if (!$opts['post_fn']($multi_records,$NEW_REC,$def,$mesg_minor,$m)) {
				$mesg .= oline("You attempted to {$action} multiple $m records failed.")
						. message_result_detail($mesg_minor);
				return false;
	    	}
		}
      }

      if (!$NEW_REC) {

	    $mesg .= oline("The database accepted your $action, but I was unable to find the record. The $action will be aborted.") . message_result_detail($mesg_minor);
	    log_error("The database accepted your $action, but I was unable to find the record.");
	    return false;

      }

	$singular = $def['singular'];
      $mesg .= oline("Your {$singular} was successfully {$action}ed.");
	  $mesg .= message_result_detail($mesg_minor);
      return $NEW_REC;
}

function delete_generic($filter,$def,$delete_comment='')
{
	if ($def['stamp_deletes']) {
		$del_rec=$filter;
		$del_rec['is_deleted']=sql_true();
		$del_rec['deleted_by']=$GLOBALS['UID'];
		$del_rec['FIELD:deleted_at']='CURRENT_TIMESTAMP';
		$del_rec['deleted_comment']=$delete_comment;
		$res = agency_query(sql_update(orr($def['table_post'],$def['table']),$del_rec,$filter));
	} else {
		$res = agency_query(sql_delete(orr($def['table_post'],$def['table']),$filter));
	}
	return $res;
}

function engine_delete_filter($rec,$def)
{
	/*
	 * Generates a filter for list to use after a record is deleted
	 */

	foreach ($rec as $key => $val) {

		$pr = $def['fields'][$key];
		$type = $pr['data_type'];

		if (in_array($type,array(AG_MAIN_OBJECT_DB,'staff'))
		    && !$pr['virtual_field'] && !$pr['view_field_only']) {
			return array($key => $val);
		}
	}
	return false;
}

function object_merge_generic($def,$control)
{
	//this is a place-holder function for custom object mergining -- see path_tracking

	return $def;
}

function process_generic(&$sess,&$form,$def)
{
      if (!isset($form)) {
	    return false;
      }
      //The following initial loop was added to maintain key consistency between $form
      //and $sess variables. This was inspired by the fact that html forms don't pass
      //variables that aren't checked, or true. This was done quickly out of necessity, and there is
      //certainly a better way to obtain the desired result.
      if (isset($sess)) {
		foreach ($sess as $sess_key=>$sess_value) {
			$type=$def['fields'][$sess_key]['data_type'];
			if ($type=='multi_rec') {
				foreach ($sess_value as $sub_key=>$sub_value) {
					if(!array_key_exists($sub_key,$form[$sess_key]))
					{
						$form[$sess_key][$sub_key]='';
					}
				}
			} else {
				if (!array_key_exists($sess_key,$form)) {
					$form[$sess_key]='';
				}
			}
		}
      }
      foreach ($form as $form_key=>$form_value) {
		$fields = $def['fields'][$form_key];
		$type = $def['fields'][$form_key]['data_type'];
		
		if (!$def['fields'][$form_key]['allow_smart_chars']) { //no smart quotes
			$form_value=smart_char_destroy($form_value);
		}

		//FORCE_CASE IF SPECIFIED IN CONFIG FILE
		if ($fcase = $def['fields'][$form_key]['force_case']) {
			$form_value = force_case($form_value,$fcase);
		}
		// CAPTURE STAFF FIELDS...-1 IS REALLY NULL
		if ( $type=='staff' && $form_value=='-1' ) {

			// -1 = no staff on form, set to null for data record
			$form_value = ($form_value=='-1') ? '' : $form_value;
			$sess[$form_key] = get_magic_quotes_gpc() ? stripslashes($form_value) : $form_value;

		} elseif ( substr($type,0,9)=='timestamp' ) {

			if (isset($form[$form_key.'_date_']) && isset($form[$form_key.'_time_'])) {
				$tmp_date=dateof($form[$form_key.'_date_'],'SQL');
				$tmp_time=timeof($form[$form_key.'_time_'],'SQL');
				$sess[$form_key] = trim($tmp_date.' '.$tmp_time);
				unset($form[$form_key.'_date_']);
				unset($form[$form_key.'_time_']);
			} else {
				$sess[$form_key] = $form_value;
			}
		} elseif ( $type=='ssn' ) {
			if (!($sess[$form_key]=ssn_of($form_value))) {
				$sess[$form_key] = $form_value; //assign invalid ssn anyway, for validity check
			}
		} elseif ( $type=='currency' ) {
			$sess[$form_key] = currency_of($form_value,'sql'); //format for db
		} elseif ( $type == 'attachment' ) {
			if ($_REQUEST['control']['step'] == 'submit') {
				$sess[$form_key] = process_attachment($form_key, $sess[$form_key],$fields['null_ok'] );
			}
		} elseif ( $type == 'float' && (!be_null($form_value) && $round = $def['fields'][$form_key]['length_decimal_places'])) {
			$sess[$form_key] = round($form_value,$round);
		} elseif ($type=='phone' ) {
			if (!($sess[$form_key]=phone_of($form_value))) {
				$sess[$form_key] = $form_value;
			}
		} elseif ( substr($type,0,4)=='date' ) {
			if (!($sess[$form_key]=dateof($form_value,'SQL'))) {
				$sess[$form_key] = $form_value; //assign invalid date anyway, for validity check to catch
			}
		} elseif ( ($type=='boolean') ) {  //added for checkbox booleans
			if (sql_true($form_value)) { $form_value=sql_true(); }
			if (sql_false($form_value)) { $form_value=sql_false(); }
			$sess[$form_key] = $form_value;
		} elseif ($type=='multi_rec') { //cycle through and assign sub-records
			foreach ($form_value as $sub_key=>$sub_value) {
				if ( ($fields['multi_type']=='boolean') 
				     && ($sub_key==$fields['multi_field'])) {
					if ($fields['multi_format']=='radio') {
						$sub_value=sql_true($sub_value) ? 'Y' : (sql_false($sub_value) ? 'N' : NULL );
					} elseif (sql_true($sub_value)) {
						$sub_value=sql_true();
					}
					$sess[$form_key][$sub_key]=$sub_value;
				} else {
					$sess[$form_key][$sub_key] = get_magic_quotes_gpc() ? stripslashes($sub_value) : $sub_value;
				}
			}
		} elseif (in_array($type,array('lookup_multi','array','staff_list'))) {
			if (in_array($fields['lookup_format'],array('checkbox','checkbox_v')) and !be_null($form_value)) {
				$form_value = $form[$form_key] = array_keys($form_value);
			}
			if ($type == 'staff_list') {
				$form_value = array_unique($form_value);
				$form_value = array_diff($form_value,array(-1)); //strip -1
			}
			$sess[$form_key] = dewebify_array(array_filter(orr($form_value,array())));
		} else {

			$sess[$form_key] = get_magic_quotes_gpc() ? stripslashes($form_value) : $form_value;

		}
      }
}

function title_generic($action,$rec,$def)
{
      $a=orr($def['title_' . $action],$def['title']);
      $object=$def['object'];
      $b=$def['title_format'];
      $x = $a
	    // first figure out title text
	    ? eval("return $a;")
	    : ucwords($action). 'ing ' . $def['singular'] 
	    . ($rec[$def['id_field']] ? " #{$rec[$def['id_field']]}": '');
	if (array_key_exists(AG_MAIN_OBJECT_DB.'_id',$rec) && !strstr($x,' for ')
	    && !stristr($x,'Adding a new '.AG_MAIN_OBJECT.' record.')) {
		$x .= ' for '.client_link($rec[AG_MAIN_OBJECT_DB.'_id']);
	}

      // then determine format
      return oline($b
			 ? eval("return $b;")
			 : bigger(bold($x)))
		. $required_note
		. (sql_true($rec['is_deleted'])
			? $GLOBALS['NL'] . italic(bold(red('(this record was deleted by '
				. staff_link($rec['deleted_by']) . ' at ' . datetimeof($rec['deleted_at'],'US') 
				. $GLOBALS['NL'] . ' with this comment: ' . underline($rec['deleted_comment']) . ')')))
		   : '');
}

function engine_process_quicksearch(&$step,&$rec,$control)
{
	$_SESSION['engineQuickSearchField']=orr($_REQUEST['engineQuickSearchField'],
									  $_SESSION['engineQuickSearchField']);
	if ($client = $_REQUEST['client_select']) {
		$field=$_SESSION['engineQuickSearchField'];
		$rec[$field]=$client;
		$step = 'continued';
		$_SESSION['engineQuickSearchField']=null;
	}

	global $AG_QUICK_SEARCH_CONTROL_ARRAY;     //this is a kludge to pass the control array in the old qs form code
	$AG_QUICK_SEARCH_CONTROL_ARRAY = $control; //once list is used for qs, this will be cleaner...

	return process_quick_search(true,false,true,true); //exit the script, don't auto-forward, use old form for now, and don't directly output
}

function add_another_set_rec_init($rec,$def,$rec_init,$session_identifier)
{
	$init=array();
	if ($_REQUEST['engineAddAndRemember']) {
		foreach ($rec as $key=>$val) {
			if ($def['fields'][$key]['add_another_remember']) {
			   $init[$key]=$val;
			}
		}
	} else {
	  $key=AG_MAIN_OBJECT_DB.'_id';
	  if (array_key_exists($key,$rec)) {
	     $init[$key]=$rec[$key];
	  }
	}
	$_SESSION['CONTROL'.$session_identifier]['rec_init'] = $init;
}

function update_engine_array($use_auth=true,$engine_array=null,$tables=null) {

	global $AG_ENGINE_TABLES,$engine,$off;

	$engine_array = orr($engine_array,AG_ENGINE_CONFIG_ARRAY);
	$engine = null; //objects w/o config files were not configuring properly w/o this

	$auth = $use_auth ? has_perm('admin','RW') : true;
	if (!$auth) {
		outline(red('You don\'t have permission to update the engine array.'));
		exit;
	}

	outline(bold('Updating and storing in Engine Array: '.$engine_array),2);
	//GENERATE ENGINE ARRAY FROM CONFIG FILES
	require ENGINE_CONFIG_FILE_DIRECTORY.'/config_engine.php';
	if ($tables)
	{
		$engine=get_engine_config_array();
	}
	else
	{
		$tables=$AG_ENGINE_TABLES;
	}	
	/*
     * Look first in a subdirectory matching the main object name for the configfile.
     * If not found, look in the base config directory.
     * e.g., in donor version:
	 * look for config/donor/config_address.php
	 * if not found, look for config/config_address.php.
	 */
	foreach ($tables as $tmp)	{
		if (is_readable( ($x = $off . ENGINE_CONFIG_FILE_DIRECTORY . '/'.AG_MAIN_OBJECT_DB.'/config_'.$tmp.'.php'))) {

			include $x;

		} elseif (is_readable( ($x = $off . ENGINE_CONFIG_FILE_DIRECTORY.'/config_'.$tmp.'.php'))) {

			include $x;

		} else {

			outline('No config file found for '.bold($tmp).'. Will configure '.$tmp.' using engine defaults.');

		}
	}
	
	foreach ($tables as $tmp)	{
		$engine[$tmp]=config_object($tmp);
		outline ('Configuring '.bold($tmp));
	}

	$serialized_engine=serialize($engine);
	
	outline();
	outline(bold('UPDATING CONFIGURATION ARRAY IN DB'));
	$exists=agency_query('SELECT * FROM '.AG_ENGINE_CONFIG_TABLE,array('val_name'=>$engine_array));
	if (sql_num_rows($exists)<1) {
		$res=agency_query(sql_insert(AG_ENGINE_CONFIG_TABLE,array('val_name'=>$engine_array,
											  'FIELD:changed_at'=>'CURRENT_TIMESTAMP',
											  'value'=>$serialized_engine)));
	} else {
		$res=agency_query(sql_update(AG_ENGINE_CONFIG_TABLE,array('value'=>$serialized_engine,
											  'FIELD:changed_at'=>'CURRENT_TIMESTAMP'),
						   array('val_name'=>$engine_array)));
	}
	if (AG_SESSION_ENGINE_ARRAY) {
		$_SESSION['STATIC_ENGINE_ARRAY']=null; //array will be pulled on fresh on next page load.
	}

	return $res;
}

function config_any_table_perm($def)
{
	//modify a config array to mimic super user on given table
	global $engine;
	foreach (array_keys($engine['actions']) as $action) {
		$def['allow_'.$action] = true;
		$def['perm_'.$action] = 'any_table';
	}

	$def['add_link_always'] = orrn($def['add_link_always'],true);

	return $def;
}

function is_protected_generic($object,$id=null)
{ //determine whether a record is protected
	global $engine;
	$def = $engine[$object];
	if (isset($def['fields']['is_protected_id']) && !be_null($id)) {
		//this object type has records of protected nature
		//the record must now be fetched to determine status
		$filter = array($def['id_field']=>$id);
		$rec = sql_fetch_assoc(get_generic($filter,'','',$def));
		if (sql_true($rec['is_protected_id'])) {
			//record is protected
			return true;
		}
	}
	//record/object is not protected
	return false;
}

function add_staff_alert_form_generic($def,$rec,$control)
{
	if ($control['action'] == 'add') {
		$i = 0;
		foreach ($control['staff_alerts'] as $id) {
			$js_id = 'addStaffAlert'.$i;
			$remove_link = hlink($_SERVER['PHP_SELF'].'?staff_alert_remove[]='.$id,smaller('remove',2)
						   ,'',' onclick="javascript:document.getElementById(\''.$js_id.'\').innerHTML=\'\';'
 						   . ' document.getElementById(\'addStaffAlertVar'.$i.'\').name=\'staff_alert_remove[]\'; return false;"');
			$alerts .= hiddenvar('staff_alerts[]',$id,' id="addStaffAlertVar'.$i.'"') . span(oline($remove_link.' '.staff_link($id)),' id="'.$js_id.'"');
			$i++;
		}
		global $AG_HEAD_TAG;
		$AG_HEAD_TAG .= Java_Engine::get_js('     var currentStaffAlertID = \''.$i.'\';

     function addStaffAlert() {
          currentStaffAlertID ++;
          var cont = document.getElementById(\'addStaffAlertContainer\');
          var staffList = document.getElementById(\'addStaffAlertList\');
          var staffID = staffList.options[staffList.selectedIndex].value;

          var staffName = staffList.options[staffList.selectedIndex].innerHTML;
          var staffLink = \'<a href="' . AG_STAFF_PAGE . '?id=\'+staffID+\'"  class="staffLink">\'+staffName+\'</a>\';

          var removeLink = \'<a href="javascript:void(0);" onclick="javascript:document.getElementById(\\\'addStaffAlert\'+currentStaffAlertID+\'\\\').innerHTML=\\\'\\\'; document.getElementById(\\\'addStaffAlertVar\'+currentStaffAlertID+\'\\\').name=\\\'staff_alert_remove[]\\\'"><font size="-2">remove</font></a> \';
          var staffNameFormatted = \'<span id="addStaffAlert\'+currentStaffAlertID+\'">\'+removeLink+\' \'+staffLink+\'<br /></span>\';


          cont.innerHTML+=\'<input type="hidden" name="staff_alerts[]" value="\'+staffID+\'"\'+ \' id="addStaffAlertVar\'+currentStaffAlertID + \'"/>\'+staffNameFormatted+\'\'; 

          return true;
     }
');

		$out = div($alerts,'addStaffAlertContainer')
			. pick_staff_to('staff_alerts[]', $active_only="Yes", $default=-1 ,$subset=false,$options=' id="addStaffAlertList"')
			. button('Add','','','','javascript:addStaffAlert(); return false;');
		return div(oline(bold('Staff Alerts')).$out,'staffAlertContainer','class="staff" style="border: solid 1px black; padding: 5px; margin: 10px; width: 22em;"');
	} elseif ($control['action'] != 'view') { return false; }

	$req = ' '.red('*');
	$alert = $_REQUEST['engineStaffAlert'];
	$hide = !be_null($alert) ? 'block' : 'none';
	$close = 'document.getElementById(\'engineStaffAlertForm\').style.display="none";blur();return false;';

	global $engine, $AG_AUTH;
	$adef = $engine['alert'];
// 	$adef['fields']['alert_subject']['null_ok'] = 
// 		$adef['fields']['alert_text']['null_ok'] = false;

	$alert['staff_id'] = $alert['staff_id']=='-1' ? '' : $alert['staff_id'];
	
	if (!be_null(array_filter($alert)) and !$adef['fn']['valid']($alert,$adef,$mesg,'add',$rec_last=array())) {
		$error = red($mesg);
	}
 	$multi = oline() . js_link(smaller('(multiple)'),'document.getElementById(\'engineStaffAlertFormStaff\').multiple="multiple";document.getElementById(\'engineStaffAlertFormStaff\').size=8;document.getElementById(\'engineStaffAlertFormStaff\').name=document.getElementById(\'engineStaffAlertFormStaff\').name+"[]"');
	$form = html_heading_3('Add a Staff Alert')
		. div(/*span(js_link('X',$close,'title="Close"')) 
			 . */ $error
				. formto('','',$AG_AUTH->get_onsubmit(''))
				. hiddenvar('engineStaffAlert[ref_id]',$rec[$def['id_field']])
				. hiddenvar('engineStaffAlert[ref_table]',$def['object'])
				. hiddenvar('control[action]',$control['action'])
				. hiddenvar('control[id]',$control['id'])
				. hiddenvar('control[object]',$control['object'])
				. table(rowrlcell('Staff' . $req . $multi,pick_staff_to('engineStaffAlert[staff_id]','HUMAN',$alert['staff_id'],false,' id="engineStaffAlertFormStaff"'))
				/*
				 * Uncomment the two lines below to allow users to enter subject and text for alerts.
				 * Handled in process_staff_alert_generic().
				 */
// 			  . rowrlcell('Subject' . $req,formvartext('engineStaffAlert[alert_subject]',$alert['alert_subject']))
// 			  . rowrlcell('Text' . $req,formtextarea('engineStaffAlert[alert_text]',$alert['alert_text']))
					  . rowrlcell('Your Password' . $req,$AG_AUTH->get_password_field($auto_focus=false))
					  ,'','class=""')
			. button('Add Alert','','','','','class="engineButton"')
			. js_link('Cancel',$close,'class="linkButton"')
			. formend());
	return js_link('Add staff alert(s)',
			   'document.getElementById(\'engineStaffAlertForm\').style.display="block";blur();return false;')
		. div($form,'engineStaffAlertForm',' style="display: '.$hide.';"');
}

function process_staff_alert_generic($def,$rec,&$control)
{
	if ($control['action'] == 'add') {
		$alerts = array_unique(array_merge(orr($_REQUEST['staff_alerts'],array()),orr($control['staff_alerts'],array())));
		$remove = orr($_REQUEST['staff_alert_remove'],array());
		foreach ($alerts as $key => $staff) {
			if ($staff < 1) {
				unset($alerts[$key]);
			} elseif (in_array($staff,$remove)) {
				unset($alerts[$key]);
			}
		}
		if ($control['step'] == 'new') {
			$alerts = array();
		}

		$control['staff_alerts'] = $alerts;
		return false;
	}

	if (!$alert = $_REQUEST['engineStaffAlert']) {
		return false;
	}
	global $UID, $AG_AUTH;
	$adef = get_def('alert');
	//check password
	if (!$AG_AUTH->reconfirm_password()) {
		return 'Incorrect password for '.staff_link($UID);
	}

	// require text and subject
//  	$adef['fields']['alert_subject']['null_ok'] = 
//  		$adef['fields']['alert_text']['null_ok'] = false;
	$alerts = $alert['staff_id'];
	if (! (be_null($alerts) || is_array($alerts)))
	{
		// Force even single ID into array
		$alerts=array($alerts);
	}
	$alert['changed_by'] = $alert['added_by'] = $UID;
	$alert['sys_log'] = 'Alert added through AGENCY interface.';

	/*
	 * If no subject and text provided (e.g., user-entered subjects and texts are not enabled),
	 *  these lines revert to generic subject and title.
     */
	$alert['alert_subject'] = orr($alert['alert_subject'],substr($def['singular'] .' (id '.$control['id'].') has been flagged for your attention',0,90));
	$alert['alert_text'] = orr($alert['alert_text'],staff_name($UID) . ' has flagged '.aan($def['singular']).' '.$def['singular'].' (id '.$control['id'] . ') for your attention');

	if ($alert['ref_id'] != $rec[$def['id_field']] 
	    or $alert['ref_table'] != $def['object']) {
		return 'Error: ID or OBJECT mismatch. Couldn\'t add alert';
	}
	if (!$adef['fn']['valid']($alert,&$adef,&$mesg,'add',$rec_last=array())) {
		return 'Couldn\'t add alert. Validity Problems: ' . oline() . $mesg;
	}
	foreach( $alerts as $alert_staff )
	{
		if ($alert_staff == '-1' ) {
			// Skip the -1 user, which is really null
			continue;
		}
		$alert['staff_id']=$alert_staff;
	//verify alert doesn't exist
	$res = get_generic($alert,'','','alert');
	if (sql_num_rows($res) > 0) {
		$_REQUEST['engineStaffAlert'] = null;
//			return 'Error: Alert already exists for this staff and id';
			$mesg .= oline('Alert already exists for ' . staff_link($alert['staff_id']))
					 . oline('(skipping)');
	}
		else
		{
	//post alert
	$n_alert = post_generic($alert,$adef,&$mesg,$filter='');
	if (!$n_alert) {
		return 'Failed to post alert: '.$mesg;
	}
			else
			{
				$mesg .= oline('(Posted alert for ' . staff_link($n_alert['staff_id']).')');
			}
		}
	}
	$_REQUEST['engineStaffAlert'] = null;
	$query_string = 'control[object]='.$control['object']
		. '&control[action]='.$control['action']
		. '&control[id]='.$control['id']
		. '&control[message]='.urlencode($mesg);
	header('Location: display.php?'.$query_string);
	exit;
}

function get_table_switch_object_id($id,$def,$key='')
{
	/*
	 * returns an array(id,object)
	 */

	$key = orr($key,$def['id_field']);
	$identifier = $def['fields'][$key]['table_switch']['identifier'];

	return explode($identifier,$id);
}

function get_client_refs_generic($rec,$action,$def)
{
	switch ($action) {
	case 'view':
		$id    = $rec[$def['id_field']];
		$table = $def['table'];
		
		//------ extract table and id from joint views -------//
		if ($def['fields'][$def['id_field']]['data_type']=='table_switch' ) {
			list($id,$table) = get_table_switch_object_id($id,$def);
		}
		if (!is_numeric($id)) { return false; }

		$filter = array('ref_table' => strtoupper($table), //fixme: testing
				    'ref_id'    => $id);
		$res = get_generic($filter,'added_at DESC','','client_ref');
		if (sql_num_rows($res) < 1) { return false; }

		$clients = array();
		while ($a = sql_fetch_assoc($res)) {
			$client = $a[AG_MAIN_OBJECT_DB.'_id'];
			$clients[] = div(client_photo($client,0.35) . smaller(client_link($client),2));
		}
		return div(implode("\n",$clients),'',' style="white-space: nowrap;" class="'.AG_MAIN_OBJECT_DB.'"');
	}

	return false;
}

function get_staff_alerts_generic($rec,$action,$def,$control)
{
	switch ($action) {
	case 'add':
		if (!be_null($control['staff_alerts'])) {
			global $UID;
			$alerts = array();
			foreach ($control['staff_alerts'] as $sid) {
				$link = staff_link($sid);
				if ($UID === $sid) {
					$link = bigger(bold('<<'.$link.'>>'));
				}
				$alerts[] = $link;
			}
			return implode(oline(),$alerts);
		}
		return false;
	case 'edit':
		//here the results will be prepended with the queued staff alerts once supported
	case 'view':
		global $engine;
		$id = $rec[$def['id_field']];

		$table = $def['object']; //for now, must use object since for jail, table<>jail, but alerts use jail in ref_table

		//------ extract table and id from joint views -------//
		if ($def['fields'][$def['id_field']]['data_type']=='table_switch' ) {
			list($id,$table) = get_table_switch_object_id($id,$def);
		}

		if (!is_numeric($id)) { return false; }

		$filter = array('ref_table'=>$table,
				    'ref_id'=>$id);
		$res = get_generic($filter,'added_at DESC','','alert_consolidated');
		if (sql_num_rows($res) < 1) {
			return false;
		}

		global $colors,$AG_HEAD_TAG;
		$AG_HEAD_TAG .= style('.hiddenAlertData { padding: 2px 0px 2px 35px; }');
		$alt = 'The following staff have received alerts on '.$def['id_field'].' '.$id;
		$formatted_refs = row(centercell(smaller(bold(alt('Staff Alerts',$alt))))
					    ,' class="staff"');

		static $i = 0;
		while ($a = sql_fetch_assoc($res)) {
			//3rd argument should be 'table-row', but blank works just as well for compliant browsers, 
			//and it also works for IE (while 'table-row' does not)
			$button = Java_Engine::toggle_id_display(smaller('+/- ',2),'hiddenAlertRow'.$i,'');

			$alert_text = webify($a['alert_text']);
			if (preg_match_all('/=====(.*)=====/',$alert_text,$matches)) {
				foreach ($matches[0] as $key => $m) {
					$alert_text = str_replace($m,bold($matches[1][$key]),$alert_text);
				}
			}
			$hidden_content = table(row(leftcell(smaller(bold('time: ').datetimeof($a['added_at'],'US'))))
							. row(leftcell(smaller(bold('by: ').smaller(staff_link($a['added_by'])))))
							. row(cell(smaller(bold(webify($a['alert_subject'])))))
							. row(cell(smaller($alert_text)))
							,'','cellspacing="0" cellpadding="0" class=""');
							    
			$hidden_row = row(cell($hidden_content,' class="hiddenAlertData"'),' id="hiddenAlertRow'.$i.'" style="display: none;"');
			$c = $c==2 ? 1 : 2; //color flipping
			$formatted_refs .= row(cell($button . smaller(staff_link($a['staff_id']))),' class="staffData'.$c.'"')
				. $hidden_row;
			$i++;
		}
		return table($formatted_refs,'',
				 ' class="" cellspacing="0" style="background-color: #efefef; border: 1px solid black;"');
	}
}

function is_safe_sql($sql_all,&$errors,$non_select = false)
{
	global $generic_sql_security_checks;

	/*
	 * Check Security First
	 */

	if (!is_array($sql_all)) {
		$sql_all = array($sql_all);
	}

	$security_passed = true;
	foreach ($sql_all as $sql) {
		if (preg_match('/^SELECT/i',$sql) or $non_select) {
		} else {
			$security_passed = false;
			$errors .= oline(alert_mark('Query must start with SELECT'));
		}
		
		$sql = sql_strip_comments($sql); // comments can't be used to trick the checks
		foreach ($generic_sql_security_checks as $s_check=>$message) {
			if (preg_match($s_check,$sql,$m)) {
				$errors .= oline(alert_mark($message));
				$security_passed = false;
			}
		}

		/*
		 * Check Permissions based on FROM and LEFT JOIN objects
		 */

		preg_match_all('/from\s+([a-z0-9_]+)\s+/i',$sql,$froms);
		preg_match_all('/join\s+([a-z0-9_]+)\s+/i',$sql,$ljs);
		if ($froms or $ljs) {

			$objects = array_unique(array_merge($froms[1],$ljs[1]));

			if (has_perm('open_query,any_table')) {
				//default to above security passed
			} else {
				foreach ($objects as $object) {
					$o_def = get_def($object);
					$view_perm = $o_def['perm_view'];
					$list_perm = $o_def['perm_list'];
					if (preg_match('/^(l_|tmp_)/',$object) 
					    // fixme: define lookup and tmp_ table permissions elsewhere (this grants all)					    
					    or (has_perm($view_perm) and has_perm($list_perm))) {
						//default to above security passed
					} else {
						$security_passed = false;
						$singular = orr($o_def['singular'],$object);
						$errors .= oline(alert_mark('You don\'t have permission for '.$singular.' records'));
					}
				}
			}
		} else {
			$errors .= oline(alert_mark('Couldn\'t determine FROM or JOIN table'));
			$security_passed = false;
		}
	}

	if ($errors) {
		log_error($errors,$silent=true);
	}

	return $security_passed;
}

function cancel_url_generic($rec,$def,$action,$control_array_variable)
{
	if ($cancel_url = $def['cancel_'.$action.'_url']) {
		return $cancel_url;
	} elseif ($action=='add') {

		$idnum = orr($rec[AG_MAIN_OBJECT_DB.'_id'],$rec['staff_id']);
		$cancel_url = (($idnum==$rec['staff_id']) ? AG_STAFF_PAGE : $client_page) . "?id=$idnum";
	} else {
		$cancel_url = $_SERVER['PHP_SELF'].'?'.$control_array_variable.'[action]=view'
			.'&'.$control_array_variable.'[object]='.$def['object']
			.'&'.$control_array_variable.'[id]='.$rec[$def['id_field']];
	}
	return $cancel_url;
}

function build_lookup_query($field_def,$action)
{
	$look=$field_def['lookup'];
	$look_table=$look['table'];
	//action-based table
	if ($action and isTableView($look_table.'_'.$action)) {
		$look_table = $look_table.'_'.$action;
	}
	$look_code=$look['value_field'];
	$show_value = $field_def['show_lookup_code_'.$action];
	$look_label=$look['label_field'];
	$tmp_order = orr($field_def['lookup_order'],'LABEL');
	switch (strtoupper($tmp_order)) {
	case 'TABLE_ORDER':
		$look_order = null;
		break;
	case 'CODE':
		$look_order = $look_code;
		break;
	case 'LABEL':
		$look_order = $look_label;
		break;
	default:
		$look_order = $tmp_order;
	}
	switch ($show_value) {
	case 'CODE':
		$look_label = $look_code;
		break;
	case 'BOTH':
		$look_label = $look_label.'||\' (\'||'.$look_code.'||\')\'';
		break;
	case 'PREPEND_CODE' :
		$look_label = $look_code.'||\' - \'||'.$look_label;
		break;
	case 'DESCRIPTION':
	default:
	}
	$filt = $look['filter'];
	return make_agency_query("SELECT $look_code AS value, $look_label AS label FROM $look_table",$filt,$look_order);
}

function engine_password_cycle($def,$action,$step)
{
	/*
	 * Returns true if password required this time around.
	 */

	$object = $def['object'];

	$re_enter_pwd = true;

	switch ($action) {
	case 'add':
		$check = ( ($step=='submit') or ($step=='confirm_pass'));
		break;
	case 'delete':
		$check = true;
		break;
	case 'view':
	case 'list':
		$re_enter_pwd = false; //passwords currently aren't used for these actions
	default:
	}

	if ( $check ) {
		if ( $def[$action.'_another'] ) {
			if (!isset($_SESSION['ENGINE_PASSWORD_CYCLER'.$object]) ) {
				$pwd_cycle = $_SESSION['ENGINE_PASSWORD_CYCLER'.$object] = 0;
			} else {
				$pwd_cycle = ($step=='confirm_pass') 
					? $_SESSION['ENGINE_PASSWORD_CYCLER'.$object]++
					: $_SESSION['ENGINE_PASSWORD_CYCLER'.$object];
				if ($pwd_cycle >= $def[$action.'_another_password_cycle']) {
					$pwd_cycle=$_SESSION['ENGINE_PASSWORD_CYCLER'.$object]=0;
				}
			}
			$re_enter_pwd = ($pwd_cycle==0) ? true : false;
		}
	}
	return $re_enter_pwd;
}

function engine_set_control_id($control)
{
	if ($id = $control['id']) { //id exists
		return $id;
	}

	$action = $control['action'];
	if ($action == 'list') { //fixme, better handling needed?
		return $action;
	}

	if (in_array($action,array('add','widget'))) {
		//generate unique id for this session
		$_SESSION['ENGINE_UNIQUE_ADD_ID']++;
		return $_SESSION['ENGINE_UNIQUE_ADD_ID'];
	}
}

function generate_session_identifier($object,$id)
{
	return '_'.strtoupper($object).'_'.strtoupper($id);
}

function auto_close_generic($def,$action,$id,$date)
{
	if (!$id) {
		return false;
	}

	$object = $def['object'];
	$fields = array_keys($def['fields']);

	if ($_SESSION['approved_auto_close_'.md5($object . $id)] !== true) {
		return oline('This record ('.$object.' '.$id.') hasn\'t been authorized for auto_close_generic()');
	}

	// check for end date
	$end   = orr($def['active_date_end_field'],$object.'_date_end');
	if (!in_array($end,$fields)) {
		return oline($object.': auto_close_generic() needs an end-date field. Contact your system administrator.');
	}

	if (!($close_date = dateof($date,'SQL'))) {
		return oline('Invalid closing date: '.$date);
	}

	// get existing record
	$filter = array($def['id_field']=>$id);
	$res = $def['fn']['get']($filter,'','',$def);
	if (!$res) {
		return oline('Couldn\'t find '.$object.' record '.$id);
	}
	$rec_last = $rec = sql_fetch_assoc($res);

	//set end date
	$rec[$end] = $close_date;

	//verify record
	if (!$def['fn']['valid']($rec,&$def,&$message,$action,$rec_last)) {
		return bold('This record couldn\'t be closed due to the following errors (it might need to be edited manually):')
			. div($message,'','class="indent"');
	} 

	$a      = $def['fn']['post']($rec,$def,$message,$filter);

	$_SESSION['approved_auto_close_'.md5($object . $id)] = null; //unset authorization

	return $a ? oline($def['singular'].' record '.$id.' successfully edited.') : $message;
}

function link_engine_list_filter($object,$filter,$label,$options='')
{
	$control = array('object'=>$object,
			     'action'=>'list',
			     'list'=>array(
						 'filter'=>$filter)
			     );
	return link_engine($control,$label,'',$options);
}

function sql_to_php_generic($rec,$def)
{
	if (is_array($rec)) {
		foreach ($rec as $key=>$value) {
			if (in_array($def['fields'][$key]['data_type'],array('lookup_multi','array','staff_list')) and !be_null($value)) {
				$rec[$key] = sql_to_php_array($value);
			}
		}
	}
	return $rec;
}

function php_to_sql_generic($rec,$def)
{
	foreach ($rec as $key=>$value) {
		if (in_array($def['fields'][$key]['data_type'],array('lookup_multi','array','staff_list')) and !be_null($value)) {
			$rec[$key] = php_to_sql_array($value);
		}
	}
	return $rec;
}

function service_note_object_by_id($id)
{
	/*
	 * returns view in which service note is contained
	 */

	$res = get_generic(array('service_id'=>$id),'','','tbl_service');

	if (sql_num_rows($res)==1) {
		$a = sql_fetch_assoc($res);
		switch ($a['service_project_code']) {
		default :
			return 'service_'.strtolower($a['service_project_code']);
		}
	}

	return false;
}

function hot_link_objects( $text, $type="" )
{
/*
	MAKE SURE that changes to get_log_references or
	hot_link_objects are made in both functions, so that
	whatever logs are hot_linked are also reflected
	as references.

*/
	$type = orr($type,array('bar','note','log','bug','dal','news'));
	if (is_array($type)) {

		if (is_table('tbl_service')) {
			$type[] = 'service';
		}

		foreach($type as $ty) {

			$text = hot_link_objects( $text, $ty );

		}
		return $text;
	}

	$engine_types = array('bar','note','client_note','dal','service','news');
	global $show_bugzilla_url;
	if (in_array($type,$engine_types)) {

		$noun="($type)";

	} elseif ( ($type=='bug')) {

		if ($show_bugzilla_url) {
			$noun = "bug(zilla)?";
		} else {
			return $text;
		}
	} else {
		$noun = "(log)";
	}

	while (1) {

		// 1--is it a continuation? (i.e., after "log 5, " comes "7"
		if ($continued && preg_match('/^(,?\s*([ #,+-]|or|and|&amp;|\\n|<br>|<br \/>)*[ ]*)?([0-9]{1,7})(([^0-9]|$).*)/is',$text,$matches)) {

			switch ($type) {
			case 'log':
				$t_link = log_link($matches[3],$matches[3]);
				break;
			case 'bug':
				$t_link = bug_link($matches[3],$matches[3]);
				break;
			case 'note':
				$type = 'client_note';
			default:
				// $type can be set to false when searching for service note type, thus
				// the original is preserved, and reset below
				$original_type = $type;
				if (preg_match('/^service_?/',$type)) {
					$type = service_note_object_by_id($matches[3]);
				}
				$t_link = $type 
					? link_engine(array('object'=>$type,'id'=>$matches[3]),$matches[3])
					: dead_link(alt($matches[3],'No '.$original_type.' record found for id '.$matches[3]));
				$type = $original_type;
			}
			$new_text .= $matches[1] . $t_link;
			$text = $matches[4];
			continue;

		} elseif (preg_match('/(.*?)(('.$noun.'s?)(\s|<br>|<br \/>)*(entry|no|number|#)?(\s|<br>|<br \/>)*([0-9]+))(.*)/is',$text,$matches)) {

			if ($type == 'note') {
				// notes are really client notes, so this does a 'redirect'
				$type = 'client_note';
			}
			if (preg_match('/^service_?/',$type)) {
				$type = 'service'; //this gets changed back again
			}

			if (in_array($type,$engine_types)) {

				$original_type = $type;
				if ($type == 'service') {
					$type = service_note_object_by_id($matches[8]);
				}

			      $new_text .= $matches[1] 
					. ($type ? link_engine(array("object"=>$type,"id"=>$matches[8]),$matches[2])
					   : dead_link(alt($matches[2],'No '.$original_type.' record found for id '.$matches[8])));

				$type = $original_type;

			} elseif ($type=="bug") {

				$new_text .= $matches[1] . bug_link($matches[8],$matches[2]);

			} else {

				$new_text .= $matches[1] . log_link($matches[8],$matches[2]);

			}

			$text = $matches[9];
			$continued = true;
			continue;
		} else {

			$new_text .= $text;
			return $new_text;

		}
	}
}

function engine_translate_singular($string)
{
	static $engine_objects;
	if (!$engine_objects) {
		global $engine;
		$engine_objects = array();
		foreach ($engine as $def) {
			if ($def['object']) {
				$engine_objects['/'.preg_quote($def['object']).'/i'] = $def['singular'];
			}
		}
		uksort($engine_objects,'strlen_cmp_reverse');
	}
	//str_ireplace would be a better choice, but didn't come around until php 5
	return preg_replace(array_keys($engine_objects),array_values($engine_objects),$string);
}

function jump_to_object_link($object,$parent=AG_MAIN_OBJECT_DB,$label='')
{
	$def = get_def($object);
	$pdef = get_def($parent);
	$label = orr($label,'Jump to '.$def['plural']);

	//find parent grouping
	if ($grouping = orr($pdef['child_records'][$object])) {
		$expand_group = 'showElement(\''.$grouping.'ChildList\');';
	}

	return seclink($object,$label,'onclick="javascript:'.$expand_group.'showElement(\''.$object.'ChildList\');"');
}

function make_staff_list_form($value,$key,$def,$control,$formvar)
{
	/*
	 * returns a form field that can be appened for multiple staff
	 */

	$i = 0;
	$value = orr($value,array());
	foreach ($value as $id) {
		$js_id = $key.'Staff'.$i;
		$remove_link = js_link(smaller('remove',2),
					     'document.getElementById(\''.$js_id.'\').innerHTML=\'\';'
					     . ' document.getElementById(\'add'.$key.'Var'.$i.'\').value=\'\'; return false;');
		$hidden .= hiddenvar($formvar.'['.$key.'][]',$id,' id="add'.$key.'Var'.$i.'"') . span(oline($remove_link.' '.staff_link($id)),' id="'.$js_id.'"');
		$i++;
	}

	global $AG_HEAD_TAG;
	$AG_HEAD_TAG .= Java_Engine::get_js('     var current'.$key.'id = \''.$i.'\';

     function addStaff'.$key.'() {
          current'.$key.'id ++;
          var cont = document.getElementById(\'add'.$key.'Container\');
          var staffList = document.getElementById(\'addStaff'.$key.'List\');
          var staffID = staffList.options[staffList.selectedIndex].value;

          var staffName = staffList.options[staffList.selectedIndex].innerHTML;
          var staffLink = \'<a href="' . AG_STAFF_PAGE . '?id=\'+staffID+\'"  class="staffLink">\'+staffName+\'</a>\';

          var removeLink = \'<a href="javascript:void(0);" onclick="javascript:document.getElementById(\\\'add'.$key.'\'+current'.$key.'id+\'\\\').innerHTML=\\\'\\\'; document.getElementById(\\\'add'.$key.'Var\'+current'.$key.'id+\'\\\').value=\\\'\\\'"><font size="-2">remove</font></a> \';
          var staffNameFormatted = \'<span id="add'.$key.'\'+current'.$key.'id+\'">\'+removeLink+\' \'+staffLink+\'<br /></span>\';


          cont.innerHTML+=\'<input type="hidden" name="'.$formvar.'['.$key.'][]" value="\'+staffID+\'"\'+ \' id="add'.$key.'Var\'+current'.$key.'id + \'"/>\'+staffNameFormatted+\'\'; 

          return true;
     }
');

		$out = div($hidden,'add'.$key.'Container')
			. pick_staff_to($formvar.'['.$key.'][]', $active_only='HUMAN', $default=-1 ,$subset=false,$options=' id="addStaff'.$key.'List"')
			. button('Add','','','','javascript:addStaff'.$key.'(); return false;');
		return div($out,'staffAlertContainer','class="staff" style="border: solid 1px black; padding: 5px; margin: 10px; width: 22em;"');

	return $key;
}

function grab_append_only_fields($rec,$def)
{

	/*
	 * Blanks out the working version for fields defined as append only (ie, sys_log)
	 */

	foreach ($rec as $key => $value) {

		if ($def['fields'][$key]['append_only']) {

			$rec[$key] = '';

		}

	}

	return $rec;
}

function update_engine_control($noexists=false)
{
	/*
	 * Control for updating engine items
 	*/

	// can't check perms without engine array, hence $noexists

	// copy, so sort() doesn't change global.
	$tables=$GLOBALS['AG_ENGINE_TABLES'];
	sort($tables);

	$update_list = selectto('UPDATE_ENGINE_OBJECT')
			. selectitem('UPDATE_ALL','Full Engine Array');
	foreach ($tables as $table) {
		$update_list .= selectitem($table,$table,orr($_REQUEST['UPDATE_ENGINE_OBJECT'],
						$_SESSION['UPDATE_ENGINE_OBJECT'])==$table);
	}
	$update_list .= selectend() . oline() . button('update!') . formend();

	return ( ($noexists or has_perm('update_engine'))
	?  smaller( formto('update_engine_config.php','',' target="_blank"')
	. oline('Update Engine Object') . $update_list)
	: '');
}

function message_result_detail($mesg) {
	// hacky way to avoid nested divs, should work if mesg is just text.
	return $mesg ? div(strip_tags($mesg,'<br><p>'),'','class="engineMessageResultDetail"') : '';
}

?>
