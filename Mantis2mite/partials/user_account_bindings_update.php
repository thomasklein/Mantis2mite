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
	$a_userMantisProjects = $a_userBindings = $a_userSelectedBindings = $a_modifiedUserBindings = 
	$a_queries = array();
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
	
# prepare to return an xml message
	header('Cache-Control: must-revalidate, pre-check=0, no-store, no-cache, max-age=0, post-check=0');
	header('Content-Type: text/xml; charset=utf-8');
	echo '<?xml version="1.0" encoding="UTF-8"?>';	
	
	$i_userId = auth_get_current_user_id();
	$a_fieldNamesMiteRsrc_id = array(Mantis2mitePlugin::API_RSRC_P => 'mite_project_id',
									 Mantis2mitePlugin::API_RSRC_S => 'mite_service_id');
	$s_DBTable_mpsmp = plugin_table(Mantis2mitePlugin::DB_TABLE_PSMP);
	
# select MANTIS projects of the user
####################################
	$s_query = "SELECT a.id FROM ".db_get_table('mantis_project_table')." a ".
			   "JOIN ".db_get_table('mantis_project_user_list_table')." b ".
			   "ON b.user_id=".$i_userId." AND a.id = b.project_id";
		
	$r_result = db_query_bound($s_query);
	
	if (db_num_rows($r_result) > 0) {
		while ($a_row = db_fetch_array($r_result)) {
			$a_userMantisProjects[] = $a_row['id'];
		}
	}
	
# select MITE - MANTIS bindings of the user
###########################################
	$s_query = "SELECT type, mite_project_id, mite_service_id, mantis_project_id FROM ".
					plugin_table(Mantis2mitePlugin::DB_TABLE_PSMP).
			   " WHERE user_id=".$i_userId;
		
	$r_result = db_query_bound($s_query);
	
	if (db_num_rows($r_result) > 0) {
		
		while ($a_row = db_fetch_array($r_result)) {
			
			$s_type = $a_row['type'];
			$i_dataId = $a_row[$a_fieldNamesMiteRsrc_id[$s_type]];
			$a_userBindings[$a_row['mantis_project_id']][$s_type][] = $i_dataId;
		}
	}
	
# get all bindings selected on the form
#######################################
	foreach ($a_userMantisProjects as $i_projectId) {
		
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
	$a_newProjectsWithBindings = array_diff(array_keys($a_userSelectedBindings),
			       					     	array_keys($a_userBindings));
	
	foreach ($a_newProjectsWithBindings as $i_projectId) {
		
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
	$a_updatedProjectBindings = array_intersect(array_keys($a_userBindings),
										   		array_keys($a_userSelectedBindings));

	foreach ($a_updatedProjectBindings as $i_updatedProject) {
		
		$a_updatedPB = $a_userSelectedBindings[$i_updatedProject]; //updated project bindings
		$a_oldPB = $a_userBindings[$i_updatedProject];// old project bindings
		
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
	$a_deletedProjectBindings = array_diff(array_keys($a_userBindings),
										   array_keys($a_userSelectedBindings));
	
	foreach ($a_deletedProjectBindings as $i_removedProjectId) {
		
		$a_project = $a_userBindings[$i_removedProjectId];
		
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
	
	# save the API key				   
	user_set_field($i_userId,
				   Mantis2mitePlugin::DB_FIELD_NOTE_PATTERN,
				   $_POST[Mantis2mitePlugin::DB_FIELD_NOTE_PATTERN]);
	
		
	echo "<messages datetimestamp='".gmdate('Y-m-d H:i:s')."'>" . $s_xmlMsg . "</messages>";	
?>