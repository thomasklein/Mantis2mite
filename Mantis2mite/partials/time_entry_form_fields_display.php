<?php
	require_once( '../../../core.php' );//reload mantis environment
	mitePlugin::initPartial();

############	
# VARS 
#######
	
/*
 * @local resources/objects
 */	
	$r_result = null;
/**
 * @local array contains all configurable values
 */		
	$a_userBindings = $a_userMiteData =  
	$a_restOfMiteServiceIds = $a_selectBoxesNewTimeEntry = array();
	
/*
 * @local strings
 */	
	$s_query = $s_output = $s_unbindedRsrces = '';
	
/*
 * @local int
 */	
	$i_userId = $i_bugId = $i_projectId = 0;
	
/*
 * @local booleans
 */
	$b_pageHasUserTimeEnries = $b_userHasBindings = false;
	
############	
# ACTION 
#######
	$i_userId = auth_get_current_user_id();
	$i_bugId = $_GET['bug_id'];
	$i_projectId = $_GET['project_id'];
	
	$a_userMiteData[mitePlugin::API_RSRC_P] = 
		mitePlugin::decodeAndOrderByValue(session_get('plugin_mite_user_projects'),'name');
	$a_userMiteData[mitePlugin::API_RSRC_S] =
		mitePlugin::decodeAndOrderByValue(session_get('plugin_mite_user_services'),'name');
		
# get user bindings for this project
# split of the rest of the possible MITE projects/services to append it
#######################################################################
	$s_query = "SELECT type, mite_project_id,mite_service_id FROM ".
			    	plugin_table(mitePlugin::DB_TABLE_PSMP).
			   " WHERE user_id = ".$i_userId." AND mantis_project_id = ".$i_projectId;							 	 
	
    $r_result = db_query_bound($s_query);

    $b_userHasBindings = (db_num_rows($r_result) > 0);
    
    $a_userBindings[mitePlugin::API_RSRC_S] = $a_userBindings[mitePlugin::API_RSRC_P] = array();
    
# get users MITE projects and services binded to this MANTIS project 
#################################################################### 
    while ($b_userHasBindings && ($a_row = db_fetch_array($r_result))) {
		$s_type = $a_row['type'];
    	$s_rsrcTypeFieldName = mitePlugin::$a_fieldNamesMiteRsrcTypes[$s_type];
    	$i_idRsrcType = $a_row[$s_rsrcTypeFieldName];
    	$s_nameRsrcType = $a_userMiteData[$s_type][$a_row[$s_rsrcTypeFieldName]]['name'];
    	$a_userBindings[$s_type][$i_idRsrcType] = $a_userMiteData[$s_type][$i_idRsrcType]['name'];
    		
    	$a_selectBoxesNewTimeEntry[$s_type] .= 
			"<option value='".$i_idRsrcType."'>".$s_nameRsrcType."</option>";
    }
    
# add unbinded resources to the resoruce select boxes 
# but separate them with an optgroup
#####################################################	
    foreach (mitePlugin::$a_rsrcTypes as $s_type) {
    	
    	$a_unbindedRsrces = array_diff(array_keys($a_userMiteData[$s_type]),
    								   array_keys($a_userBindings[$s_type]));
    	
    	if (!empty($a_unbindedRsrces)) {
			
			$s_unbindedRsrces = '';
    	
			foreach($a_unbindedRsrces as $i_idUnbindedRsrc) {
				
				$s_unbindedRsrces .= 
					"<option value='$i_idUnbindedRsrc'>".
						$a_userMiteData[$s_type][$i_idUnbindedRsrc]['name'].
					"</option>";
			}
	
			if (!empty($a_userBindings[$s_type])) {
				$s_unbindedRsrces = 
					"<optgroup label='".lang_get('plugin_mite_other_'.$s_type)."'>".
						$s_unbindedRsrces."</optgroup>";	
			}
			
			$a_selectBoxesNewTimeEntry[$s_type] .= $s_unbindedRsrces;
    	}
    	
    	$a_selectBoxesNewTimeEntry[$s_type] = "
			<select name='plugin_mite_".$s_type."_new_time_entry' 
					id='plugin_mite_".$s_type."_new_time_entry'>".
				$a_selectBoxesNewTimeEntry[$s_type]."</select>";
    }
    
	if (count($a_userBindings[mitePlugin::API_RSRC_P]) == 1) {
    	
		$a_miteProjectId = array_keys($a_userBindings[mitePlugin::API_RSRC_P]);
		
		$a_selectBoxesNewTimeEntry[mitePlugin::API_RSRC_P] =  
			$a_userBindings[mitePlugin::API_RSRC_P][$a_miteProjectId[0]] ."
			<input type='hidden' name='plugin_mite_projects_new_time_entry' 
				    value='".$a_miteProjectId[0]."' id='plugin_mite_projects_new_time_entry' />";
    }
    
	
# add the services select list to the output
###########################################			
	$s_output .= " 
		<fieldset><legend>".lang_get('plugin_mite_header_new_time_entry')."</legend>
			<div class='time_entry_param'>
				<label for='plugin_mite_date_new_time_entry'>".
					lang_get('plugin_mite_header_date_new_time_entry')."
				</label>
				<input type='text' name='plugin_mite_date_new_time_entry'
					   id='plugin_mite_date_new_time_entry' value='".date('Y-m-d')."' />
				<span class='plugin_mite_user_input_helper'>
					<a tabIndex='-1' href='#'>?</a></span>
				<span class='plugin_mite_user_input_helper_text' style='display:none'>".
					lang_get('plugin_mite_date_help_text')."</span>	   
			</div>
			<div class='time_entry_param'>
				<label for='plugin_mite_projects_new_time_entry'>".
					lang_get('plugin_mite_header_projects_new_time_entry')."
				</label>".
				$a_selectBoxesNewTimeEntry[mitePlugin::API_RSRC_P] ."
			</div>
			<div class='time_entry_param'>
				<label for='plugin_mite_services_new_time_entry'>".
					lang_get('plugin_mite_header_services_new_time_entry')."
				</label>".
				$a_selectBoxesNewTimeEntry[mitePlugin::API_RSRC_S] . "
			</div>
			
			<div class='time_entry_param'>
				<label for='plugin_mite_hours_new_time_entry'>".
					lang_get('plugin_mite_header_hours_new_time_entry')."
				</label>
				<input type='text' name='plugin_mite_hours_new_time_entry' 
					   id='plugin_mite_hours_new_time_entry' value='0:00'/>
				<span class='plugin_mite_user_input_helper'>
					<a tabIndex='-1' href='#'>?</a></span>
				<span class='plugin_mite_user_input_helper_text' style='display:none'>".
					lang_get('plugin_mite_hours_help_text')."</span>
			</div>
			<div class='time_entry_param'>
				<label for='plugin_mite_note_new_time_entry'>".
					lang_get('plugin_mite_header_note_new_time_entry')."
				</label>
				<span class='plugin_mite_user_input_helper'>
					<a tabIndex='-1' href='#'>?</a></span>
				<span class='plugin_mite_user_input_helper_text' style='display:none'>".
					lang_get('plugin_mite_help_note_pattern')."</span> 
				<input type='text' name='plugin_mite_note_new_time_entry' 
					   id='plugin_mite_note_new_time_entry' autocomplete='off' value='".
	stripslashes(mitePlugin::replacePlaceHolders(current_user_get_field(mitePlugin::DB_FIELD_NOTE_PATTERN),
									$i_bugId))."' />
			</div>
			<div class='formularButtons'>
				<div class='buttonsRight'>
					<button type='submit' id='plugin_mite_add_new_time_entry'>".
						lang_get('plugin_mite_add_new_time_entry') ."
					</button>
				</div>
				<div class='buttonsLeft'>
					<a href='#' id='plugin_mite_cancel_adding_time_entry'>".
						lang_get('plugin_mite_cancel_adding_time_entry') ."
					</a>
				</div>
			</div>
		</fieldset>";
	
	echo $s_output;
?>