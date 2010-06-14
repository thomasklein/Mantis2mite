<?php
############	
# VARS 
#######
	global $g_plugin_cache;	

/*
 * @local resources/objects
 */	
	$r_result = $o_userMiteData = null;
/**
 * @local array contains all configurable values
 */		
	$a_projectBindedRsrces = $a_projectUnbindedRsrces = $a_selectBoxesNewTimeEntry = array();
	
/*
 * @local strings
 */	
	$s_query = $s_output = $s_unbindedRsrces = '';
	
/*
 * @local int
 */	
	$i_userId = $i_bugId = $i_projectId = 0;
	
############	
# ACTION 
#######
	$o_pluginController = $g_plugin_cache['Mantis2mite'];
	$i_userId = $o_pluginController->getCurrentUserId();
	$i_bugId = $_GET['bug_id'];
	$i_mantisProjectId = $_GET['project_id'];
	
	$o_userMiteData = $o_pluginController->getMiteUserData();
	
	$a_projectBindedRsrces = $o_userMiteData->getBindedRsrcesForMantisProject($i_mantisProjectId);
	$a_projectUnbindedRsrces = $o_userMiteData->getUnbindedRsrcesForMantisProject($i_mantisProjectId);
	
# build select box entries from binded resources    
    foreach ($a_projectBindedRsrces as $s_type => $a_miteRsrces) {
		
    	foreach ($a_miteRsrces as $i_rsrc_id => $a_rsrc) {
    		$a_selectBoxesNewTimeEntry[$s_type] .= 
				"<option value='".$i_rsrc_id."'>".$a_rsrc['name']."</option>";
    	}
	}
	
# add unbinded resources as select box entries if any	
	foreach ($a_projectUnbindedRsrces as $s_type => $a_miteRsrces) {
		
		$s_unbindedRsrces = '';
		
		foreach ($a_miteRsrces as $i_miteRsrc_id => $a_rsrc) {
			
			$s_unbindedRsrces .= "<option value='$i_miteRsrc_id'>".$a_rsrc['name']."</option>";
		}
		
		if (!empty($a_projectBindedRsrces[$s_type])) {
			$a_selectBoxesNewTimeEntry[$s_type] .= 
				"<optgroup label='".lang_get('plugin_mite_other_'.$s_type)."'>".
					$s_unbindedRsrces.
				"</optgroup>";
		}
		else {
			$a_selectBoxesNewTimeEntry[$s_type] .= $s_unbindedRsrces;
		}
	}
	
# wrap the available entries with the HTML select tag	
	$a_selectBoxesNewTimeEntry[Mantis2mitePlugin::API_RSRC_P] = "
			<select name='plugin_mite_".Mantis2mitePlugin::API_RSRC_P."_new_time_entry' 
					id='plugin_mite_".Mantis2mitePlugin::API_RSRC_P."_new_time_entry'>".
				$a_selectBoxesNewTimeEntry[Mantis2mitePlugin::API_RSRC_P].
			"</select>";
				
# wrap the available entries with the HTML select tag	
	$a_selectBoxesNewTimeEntry[Mantis2mitePlugin::API_RSRC_S] = "
			<select name='plugin_mite_".Mantis2mitePlugin::API_RSRC_S."_new_time_entry' 
					id='plugin_mite_".Mantis2mitePlugin::API_RSRC_S."_new_time_entry'>".
				$a_selectBoxesNewTimeEntry[Mantis2mitePlugin::API_RSRC_S].
			"</select>";				

    if (count($a_projectBindedRsrces[Mantis2mitePlugin::API_RSRC_P]) == 1) {
    	
	# dirty...	
    	$i_bindedMiteProject_id = 
			current($a_projectBindedRsrces[Mantis2mitePlugin::API_RSRC_P]);
		$i_bindedMiteProject_id = $i_bindedMiteProject_id['mite_project_id'];	
			
		$a_selectBoxesNewTimeEntry[Mantis2mitePlugin::API_RSRC_P] =  
			
			$a_projectBindedRsrces[Mantis2mitePlugin::API_RSRC_P][$i_bindedMiteProject_id]['name'] ."
			<input type='hidden' name='plugin_mite_projects_new_time_entry' 
				    value='".$i_bindedMiteProject_id."' id='plugin_mite_projects_new_time_entry' />";
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
				$a_selectBoxesNewTimeEntry[Mantis2mitePlugin::API_RSRC_P] ."
			</div>
			<div class='time_entry_param'>
				<label for='plugin_mite_services_new_time_entry'>".
					lang_get('plugin_mite_header_services_new_time_entry')."
				</label>".
				$a_selectBoxesNewTimeEntry[Mantis2mitePlugin::API_RSRC_S] . "
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
	stripslashes(Mantis2mitePlugin::replacePlaceHolders(current_user_get_field(Mantis2mitePlugin::DB_FIELD_NOTE_PATTERN),
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