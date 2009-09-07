<?php
	require_once( '../../../core.php' );//reload mantis environment
	Mantis2mitePlugin::initPartial();
	
############	
# VARS 
#######
/*
 * @local objects/resources
 */
	$r_result = null;

/*
 * @local arrays
 */
	$a_fieldNamesMiteRsrc_id = $a_miteUserData = $a_userBindings = $s_quickLinksList = array();
/*
 * @local int
 */	
	$i_userId = $i_dataId = 0;
/*
 * @local strings
 */
	$s_query = $s_type = $s_DBTable_mpsmp = $s_output = $s_projectsBindingOptions = '';

/*
 * @local booleans
 */	
	$b_hasMiteUserData = false;
	
############	
# ACTION 
#######	
	$i_userId = auth_get_current_user_id();
	
# !!! POSSIBLE SCRIPT EXIT !!!
# only proceed if there are any projects assigned to the user
###############################################	
	$s_query = "SELECT project_id FROM ".db_get_table('mantis_project_user_list_table').
			   " WHERE user_id=".$i_userId;
	
	if (db_num_rows(db_query_bound($s_query)) == 0) {
		echo lang_get('plugin_mite_no_projets_assigned');
		exit;
	}
		
	$a_userMiteData[Mantis2mitePlugin::API_RSRC_P] = 
		Mantis2mitePlugin::decodeAndOrderByValue(session_get('plugin_mite_user_projects'),'name');
	$a_userMiteData[Mantis2mitePlugin::API_RSRC_S] =
		Mantis2mitePlugin::decodeAndOrderByValue(session_get('plugin_mite_user_services'),'name');
		
# select MITE - MANTIS bindings of the user
###########################################
	$s_query = "SELECT type, mite_project_id, mite_service_id, mantis_project_id FROM ".
					plugin_table(Mantis2mitePlugin::DB_TABLE_PSMP).
			   " WHERE user_id=".$i_userId;
		
	$r_result = db_query_bound($s_query);
	
	if (db_num_rows($r_result) > 0) {
		
		while ($a_row = db_fetch_array($r_result)) {
			
			$s_type = $a_row['type'];
			$i_dataId = $a_row[Mantis2mitePlugin::$a_fieldNamesMiteRsrcTypes[$s_type]];
			$a_userBindings[$s_type][$i_dataId][] = $a_row['mantis_project_id'];
		}
	}				
			
# build form with configured values
###################################			
	$s_output .= "
		<hr size='1' />
		<form id='frm_mite_mantis_bindings'>
		<h2>".lang_get( 'plugin_mite_header_preferences' )."</h2>";

	$s_quickLinksList = "<ul>";
	
	$s_query = "SELECT a.id,a.name, a.description FROM ".db_get_table('mantis_project_table')." a ".
			   "JOIN ".db_get_table('mantis_project_user_list_table')." b ".
			   "ON b.user_id=".$i_userId." AND a.id = b.project_id";
	
	$r_result = db_query_bound($s_query);
	
	$b_userHasMantisProjects = (db_num_rows($r_result) > 0);
	
	while ($b_userHasMantisProjects && ($a_mantisProject = db_fetch_array($r_result))) {
		
	# create select boxes for all MITE resources of the user
	#######################################################		
		foreach (Mantis2mitePlugin::$a_rsrcTypes as $s_type) {
			
			$a_selectBoxesRsrc[$s_type] = '';
			$s_selectBoxRsrc = '';
			$i_sizeSelectBox = 0;
			
			if ($s_type == Mantis2mitePlugin::API_RSRC_P) {
				$s_selectBoxRsrc .= "<option value=''>".lang_get('plugin_mite_please_select')."</option>";
			}
			
			foreach ($a_userMiteData[$s_type] as $i_miteRsrc_id => $a_rsrc) {
				
				$s_selectBoxRsrc .= "<option value='$i_miteRsrc_id'";
				
			# mark as selected if it is binded	
				if (isset($a_userBindings[$s_type][$i_miteRsrc_id]) &&
					in_array($a_mantisProject['id'],$a_userBindings[$s_type][$i_miteRsrc_id])) {
					
					$s_selectBoxRsrc .= " selected='selected'";
				}	
				$s_selectBoxRsrc .= ">".$a_rsrc['name']."</option>";
			}
			$i_sizeSelectBox = count($a_userMiteData[$s_type]);
		
			$a_selectBoxesRsrc[$s_type] = " 
				<select name='sb_plugin_mite_".$s_type."_mantis_project_".$a_mantisProject['id']."[]' 
					class='sb_plugin_mite_".$s_type."'";

		# only allow selecting multiple entries for services	
			if ($s_type == Mantis2mitePlugin::API_RSRC_S)
				$a_selectBoxesRsrc[$s_type] .= " multiple='multiple'";		
			else
				$i_sizeSelectBox = 1;
				
			$a_selectBoxesRsrc[$s_type] .= "size='$i_sizeSelectBox'>$s_selectBoxRsrc</select>";
		}
		
		$s_quickLinksList .= "<li><a href='#project_".$a_mantisProject['id']."'>".
							 $a_mantisProject['name']."</li>";
		
		$s_projectsBindingOptions .= "  
			<a name='project_".$a_mantisProject['id']."'></a>
			<fieldset><legend>".$a_mantisProject['name']."</legend>
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
			<input type='text' class='note_pattern' name='".Mantis2mitePlugin::DB_FIELD_NOTE_PATTERN."' value='".
			stripslashes(current_user_get_field(Mantis2mitePlugin::DB_FIELD_NOTE_PATTERN))."' autocomplete='off' />
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