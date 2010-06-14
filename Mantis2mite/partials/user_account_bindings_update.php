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
	$r_result = null;

/*
 * @local arrays
 */
	$a_userProject_ids = $a_userMiteBindings = $a_userSelectedBindings = $a_modifiedUserBindings = 
	$a_queries = $a_newProjectsBindings = array();
/*
 * @local int
 */	
	$i_userId = $i_dataId = 0;
/*
 * @local strings
 */
	$s_query = $s_type = $s_DBTable_mpsmp = $s_xmlMsg = '';

	
############	
# ACTION 
#######	
	$o_pluginController = $g_plugin_cache['Mantis2mite'];
	$i_userId = $o_pluginController->getCurrentUserId();
	$a_fieldNamesMiteRsrc_id = array(Mantis2mitePlugin::API_RSRC_P => 'mite_project_id',
									 Mantis2mitePlugin::API_RSRC_S => 'mite_service_id');
	$s_DBTable_mpsmp = plugin_table(Mantis2mitePlugin::DB_TABLE_PSMP);
	
	$o_userMiteData = $o_pluginController->getMiteUserData();
	$a_userMiteBindings = $o_userMiteData->getBindingsByMantisProject();
	
	$a_userProject_ids = user_get_all_accessible_projects($i_userId,ALL_PROJECTS);
	
# get all bindings selected on the form
#######################################
	foreach ($a_userProject_ids as $i_projectId) {
		
	# get MITE project to MANTIS project bindings	
		if (isset($_POST['sb_plugin_mite_projects_mantis_project_' . $i_projectId])) {
			
			foreach ($_POST['sb_plugin_mite_projects_mantis_project_' . $i_projectId] as $i_rsrcId) {
				if (is_numeric($i_rsrcId)) {
					$a_userSelectedBindings[$i_projectId][Mantis2mitePlugin::API_RSRC_P][] = $i_rsrcId;
				}
			}
		}

	# get MITE services to MANTIS service bindings	
		if (isset($_POST['sb_plugin_mite_services_mantis_project_' . $i_projectId])) {
			
			foreach ($_POST['sb_plugin_mite_services_mantis_project_' . $i_projectId] as $i_rsrcId) {
				if (is_numeric($i_rsrcId)) {
					$a_userSelectedBindings[$i_projectId][Mantis2mitePlugin::API_RSRC_S][] = $i_rsrcId;
				}
			}
		}
	}
	
# get new MITE project/services to MANTIS project bindings 	
# and build queries to INSERT them
##########################################################
	$a_newProjectsBindings = array_diff(array_keys($a_userSelectedBindings),
			       					    array_keys($a_userMiteBindings));
	
	foreach ($a_newProjectsBindings as $i_projectId) {
		
		$a_project = $a_userSelectedBindings[$i_projectId];	   
		
		foreach ($a_project as $s_type => $a_entries) {
			
			foreach ($a_entries as $i_miteIdEntry) {
				
				$a_queries[] = 
					"INSERT INTO ".$s_DBTable_mpsmp.
					" (user_id,mantis_project_id,type, ".$a_fieldNamesMiteRsrc_id[$s_type].")".
			 		" VALUES ($i_userId,$i_projectId,'$s_type', $i_miteIdEntry)";
			}		
		}
	}

# get updated MITE project/services to MANTIS project bindings 	
# and build queries to INSERT new MITE project/services and DELETE removed bindings
###################################################################################	
	$a_updatedProjectBindings = array_intersect(array_keys($a_userMiteBindings),
										   		array_keys($a_userSelectedBindings));

	foreach ($a_updatedProjectBindings as $i_updatedProject) {
		
		$a_updatedPB = $a_userSelectedBindings[$i_updatedProject]; //updated project bindings
		$a_oldPB = $a_userMiteBindings[$i_updatedProject];// old project bindings
		
		foreach (Mantis2mitePlugin::$a_rsrcTypes as $s_type) {
		
		# prepare arrays for comparison	
			if (!isset($a_updatedPB[$s_type])) $a_updatedPB[$s_type] = array();
			if (!isset($a_oldPB[$s_type])) $a_oldPB[$s_type] = array();
			
		# get new MITE resources binding to the current MANTIS projects	($i_updatedProject)
			$a_newRsrcBinding = array_diff($a_updatedPB[$s_type],$a_oldPB[$s_type]);
			
		# build queries to insert the new entries if any
			foreach ($a_newRsrcBinding as $i_miteIdEntry) {
				       					       
	        	$a_queries[] = 
	        		"INSERT INTO ".$s_DBTable_mpsmp.
					" (user_id,mantis_project_id,type,".$a_fieldNamesMiteRsrc_id[$s_type].")".
			 		" VALUES ($i_userId,$i_updatedProject,'".$s_type."', $i_miteIdEntry)";
	        }
	        
		# get removed resource bindings
			$a_removedRsrcBindings = array_diff($a_oldPB[$s_type],$a_updatedPB[$s_type]);
	
			foreach ($a_removedRsrcBindings as $i_miteIdEntry) {
				$a_queries[] = 
	        		"DELETE FROM ".$s_DBTable_mpsmp.
					" WHERE user_id = $i_userId AND type = '".$s_type."' AND " .
	        		$a_fieldNamesMiteRsrc_id[$s_type]." = ".$i_miteIdEntry.
	        		" AND mantis_project_id = ".$i_updatedProject;
			}
		}	   
	}
	
# get removed MITE project/services to MANTIS project bindings 	
# and build queries to DELETE the removed bindings
###################################################################################
	$a_deletedProjectBindings = array_diff(array_keys($a_userMiteBindings),
										   array_keys($a_userSelectedBindings));
	
	foreach ($a_deletedProjectBindings as $i_removedProjectId) {
		
		$a_project = $a_userMiteBindings[$i_removedProjectId];
		
		foreach (Mantis2mitePlugin::$a_rsrcTypes as $s_type) {
			
		# prepare array for foreach loop	
			if (!isset($a_project[$s_type])) $a_project[$s_type] = array();
			
			foreach ($a_project[$s_type] as $i_miteIdEntry) {
				$a_queries[] = 
		        	"DELETE FROM ".$s_DBTable_mpsmp.
					" WHERE user_id = $i_userId AND type = '$s_type' AND " .
		        	$a_fieldNamesMiteRsrc_id[$s_type]." = ".$i_miteIdEntry.
		        	" AND mantis_project_id = ".$i_removedProjectId;	
			}
		}
	}
	
# execute the database queries	
	for ($i = 0; $i < count($a_queries); $i++) {
		$r_result = db_query_bound($a_queries[$i]);
	}
	
	# save the field for the notes pattern				   
	user_set_field($i_userId,
				   Mantis2mitePlugin::DB_FIELD_NOTE_PATTERN,
				   $_POST[Mantis2mitePlugin::DB_FIELD_NOTE_PATTERN]);
	
# force re-initialization of session stored user values	
	session_set('plugin_mite_status_session_vars','reinit');
	$o_pluginController->initMiteObjects();
		
	echo "<messages datetimestamp='".gmdate('Y-m-d H:i:s')."'>" . $s_xmlMsg . "</messages>";	
?>