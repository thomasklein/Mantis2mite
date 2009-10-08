<?php
############	
# VARS 
#######

/*	
 * @global system vars
 */ 
	global $g_plugin_cache;

/*
 * @local objects/resources
 */
	$r_result = $o_userMiteData = null;

/*
 * @local arrays
 */
	$a_userMiteRsrces = $a_userMiteBindings = $s_quickLinksList = $a_userProject_ids = array();
/*
 * @local int
 */	
	$i_userId = 0;
/*
 * @local strings
 */
	$s_query = $s_type = $s_DBTable_mpsmp = $s_output = $s_projectsBindingOptions = $s_projectName = '';
	
############	
# ACTION 
#######	
	$o_pluginController = $g_plugin_cache['Mantis2mite'];
	$i_userId = $o_pluginController->getCurrentUserId();
	$a_userProject_ids = user_get_all_accessible_projects($i_userId,ALL_PROJECTS);
	
# !!! POSSIBLE SCRIPT EXIT !!!
# only proceed if the user has access to any of the projects
###############################################	
	if (empty($a_userProject_ids)) {
		echo lang_get('plugin_mite_no_projets_assigned');
		exit;
	} 

	$o_userMiteData = $o_pluginController->getMiteUserData();
	
	$a_userMiteRsrces[Mantis2mitePlugin::API_RSRC_P] = $o_userMiteData->getProjects();
	$a_userMiteRsrces[Mantis2mitePlugin::API_RSRC_S] = $o_userMiteData->getServices();
	$a_userMiteBindings = $o_userMiteData->getBindings();
			
# build form with configured values
###################################			
	$s_output .= "
		<hr size='1' />
		<form id='frm_mite_mantis_bindings'>
		<h2>".lang_get( 'plugin_mite_header_preferences' )."</h2>";

	$s_quickLinksList = "<ul>";
	
# gather Mantis project names accessible for the user
	foreach ($a_userProject_ids as $i_project_id){
		
		$s_projectName = project_get_name($i_project_id);
		
	# create select boxes for all MITE resources of the user
	#######################################################		
		foreach (Mantis2mitePlugin::$a_rsrcTypes as $s_type) {
			
			$a_selectBoxesRsrc[$s_type] = '';
			$s_selectBoxRsrc = '';
			$i_sizeSelectBox = 0;
			
			if ($s_type == Mantis2mitePlugin::API_RSRC_P) {
				$s_selectBoxRsrc .= "<option value=''>".lang_get('plugin_mite_please_select')."</option>";
			}
			
			foreach ($a_userMiteRsrces[$s_type] as $i_miteRsrc_id => $a_rsrc) {
				
				$s_selectBoxRsrc .= "<option value='$i_miteRsrc_id'";
				
			# mark as selected if it is binded	
				if (isset($a_userMiteBindings[$s_type][$i_miteRsrc_id]) &&
					in_array($i_project_id,$a_userMiteBindings[$s_type][$i_miteRsrc_id])) {
					
					$s_selectBoxRsrc .= " selected='selected'";
				}	
				$s_selectBoxRsrc .= ">".$a_rsrc['name']."</option>";
			}
			$i_sizeSelectBox = count($a_userMiteRsrces[$s_type]);
		
			$a_selectBoxesRsrc[$s_type] = " 
				<select name='sb_plugin_mite_".$s_type."_mantis_project_".$i_project_id."[]' 
					class='sb_plugin_mite_".$s_type."'";

		# only allow selecting multiple entries for services	
			if ($s_type == Mantis2mitePlugin::API_RSRC_S)
				$a_selectBoxesRsrc[$s_type] .= " multiple='multiple'";		
			else
				$i_sizeSelectBox = 1;
				
			$a_selectBoxesRsrc[$s_type] .= "size='$i_sizeSelectBox'>$s_selectBoxRsrc</select>";
		}
		
		$s_quickLinksList .= "<li><a href='#project_$i_project_id'>$s_projectName</li>";
		
		$s_projectsBindingOptions .= "  
			<a name='project_".$i_project_id."'></a>
			<fieldset><legend>".$s_projectName."</legend>
				<label>".lang_get('plugin_mite_assignment_mite_project')."</label>".
				$a_selectBoxesRsrc[Mantis2mitePlugin::API_RSRC_P]."
				<label>".lang_get('plugin_mite_assignment_mite_service')."</label>".
					$a_selectBoxesRsrc[Mantis2mitePlugin::API_RSRC_S]."
			</fieldset>";
	}	
	
	$s_quickLinksList .= "</ul>";
	
	$s_output .= " 
		<label>".lang_get('plugin_mite_header_note_pattern')."</label>
		<p class='bindings_help'>".lang_get('plugin_mite_help_note_pattern')."</p>	
			<input type='text' class='note_pattern' name='".Mantis2mitePlugin::DB_FIELD_NOTE_PATTERN."' 
				   value='".
			stripslashes(current_user_get_field(Mantis2mitePlugin::DB_FIELD_NOTE_PATTERN))."' />
		<label>".lang_get('plugin_mite_header_interconnections')."</label>
		<p class='bindings_help'>".lang_get( 'plugin_mite_help_interconnections' )."</p>
			$s_quickLinksList
			$s_projectsBindingOptions
		<div class='formularButtons'>
			<div class='buttonsRight'>
				<button id='plugin_mite_save_bindings' type='submit'>".
					lang_get('plugin_mite_save_bindings')."
				</button>
				<input type='hidden' value='".lang_get('plugin_mite_save_bindings_active')."' 
									 id='plugin_mite_save_bindings_active' />
			</div>
			<div class='buttonsLeft'>
				<input type='reset' value='".lang_get('plugin_mite_reset_form')."' 
					   id='plugin_mite_reset_bindings' />
			</div>
			<div class='clearBoth'></div>
		</div>			
		</table>
		</form>";
	
	echo $s_output;
?>