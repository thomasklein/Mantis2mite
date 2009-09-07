<?php
	require_once( '../../../core.php' );//reload mantis environment
	Mantis2mitePlugin::initPartial();	 	

############
# VARS
#######
/*
 * @local objects/resources
 */
	$o_xml = $r_result = null;

/*
 * @local arrays
 */	
	$a_errors = $a_tmpEntry = $a_queries = $a_errors = $a_logs =
	$a_miteProjectNames =  
	$a_mantisMiteUserData = $a_miteUserData = $a_fieldNamesMiteRsrc_id =   
	$a_rsrcEntriesToDelete = $a_newRsrcEntries = $a_rsrcEntriesPossiblyModified = $a_mantisTimeEntries =
	$a_mantisTimeEntriesNotFound = $a_mantisTimeEntriesToUpdate = $a_mantisTimeEntriesToDelete = array();
/*
 * @local strings
 */	
	$s_content = $s_query = $s_dbTable_mps = $s_dateTimeNow = $s_xmlMsg = $s_tableTimeEntries = '';
	
/*
 * @local booleans
 */
	$b_userHasTimEntries = $b_foundMantisTimeEntry = false;
	
	
############
# ACTION
#######	

# prepare to return an xml message
	header('Cache-Control: must-revalidate, pre-check=0, no-store, no-cache, max-age=0, post-check=0');
	header('Content-Type: text/xml; charset=utf-8');
	echo '<?xml version="1.0" encoding="UTF-8"?>';
	
	$s_DBTable_mps = plugin_table(Mantis2mitePlugin::DB_TABLE_PS);
	$s_tableTimeEntries = plugin_table(Mantis2mitePlugin::DB_TABLE_TE);
	$i_userId = auth_get_current_user_id();
	$a_fieldNamesMiteRsrc_id = array(Mantis2mitePlugin::API_RSRC_P => 'mite_project_id',
									 Mantis2mitePlugin::API_RSRC_S => 'mite_service_id');
	
# PROJECTS AND SERVICES synchronisation
#######################################
	foreach (Mantis2mitePlugin::$a_miteResources as $s_type => $s_rsrcPattern) {
		
	# time entries are handled later on	
		if(($s_type == Mantis2mitePlugin::API_RSRC_TEP) || ($s_type == Mantis2mitePlugin::API_RSRC_TE)) continue;
		
		$s_apiURL = sprintf($s_rsrcPattern,
							urlencode($_POST[Mantis2mitePlugin::DB_FIELD_ACCOUNT_NAME]),
							urlencode($_POST[Mantis2mitePlugin::DB_FIELD_API_KEY]));
		
	# get MITE data of the user from the api	
		if (TRUE == ($s_content = @file_get_contents($s_apiURL))) {
			
			$o_xml = simplexml_load_string($s_content);
			
			foreach ($o_xml->children() as $o_child) {
				
			# get projects with the same name to append the customer name later on
			# to better distinguish them when showing up in a selection box
				if ($s_type == Mantis2mitePlugin::API_RSRC_P) {
					$a_miteProjectNames[(string)$o_child->name][((int)$o_child->id)] = 
						(string)$o_child->{'customer-name'};
				}
				
				$a_miteUserData[$s_type][(int)$o_child->id] = array( 
					'name' 			  => (string)$o_child->name,
					'mite_updated_at' => Mantis2mitePlugin::mysqlDate((string)$o_child->{'updated-at'}));
			}
		}
	# !!! STOP THE EXECUTION OF THE FOREACH LOOP !!! and check the other urls  	
	# since we couldn't connect to the users services/projects it means 
	# the connection is broken or the provided MITE account data is wrong
	#############################################################################
		else {
			$a_errors[$s_type][] = "Could not retrieve data from <em>".$s_apiURL."</em>";
			continue;
		}
		
	# get in MANTIS saved MITE projects/services
		$s_query = "SELECT id,name,".$a_fieldNamesMiteRsrc_id[$s_type].", mite_updated_at 
					FROM ".$s_DBTable_mps.
				   " WHERE user_id = ".$i_userId." AND type = '".$s_type."'";
		
		$r_result = db_query_bound($s_query);
		
		$a_mantisMiteUserData[$s_type] = array();
		
		if (db_num_rows($r_result) > 0) {
			while ($a_row = db_fetch_array($r_result)) {
				$a_mantisMiteUserData[$s_type][$a_row[$a_fieldNamesMiteRsrc_id[$s_type]]] = $a_row;
			}
		}
		
	# get new entries (projects and services)
		$a_newRsrcEntries[$s_type] = 
			array_diff(array_keys($a_miteUserData[$s_type]),
					   array_keys($a_mantisMiteUserData[$s_type]));
		
	# get deleted entries (projects and services)
		$a_rsrcEntriesToDelete[$s_type] = 
			array_diff(array_keys($a_mantisMiteUserData[$s_type]),
					   array_keys($a_miteUserData[$s_type]));
		
	# get possibly updated entries (projects and services)				   
		$a_rsrcEntriesPossiblyModified[$s_type] = 
			array_intersect(array_keys($a_mantisMiteUserData[$s_type]),
						  	array_keys($a_miteUserData[$s_type]));
						  	
	}//end of foreach loop	
	
	
# TIME ENTRIES synchronisation
############################## 
	$s_query = 
		"SELECT id, mite_time_entry_id, mite_project_id, updated_at FROM ".
		 plugin_table(Mantis2mitePlugin::DB_TABLE_TE).
		" WHERE user_id = ".$i_userId;
	
	$r_result = db_query_bound($s_query);
		
	$b_userHasTimEntries = (db_num_rows($r_result) > 0);
	
# get all time entries grouped by MITE projects				
	while ($b_userHasTimEntries && ($a_row = db_fetch_array($r_result))) {
		$a_mantisTimeEntries[$a_row['mite_project_id']][] = $a_row;
	}
	
# load time entries from each project in MITE
# and check available entries in MANTIS for change	
	foreach ($a_mantisTimeEntries as $i_miteProjectId => $a_timeEntries) {
		
		$s_apiURL = sprintf(Mantis2mitePlugin::$a_miteResources[Mantis2mitePlugin::API_RSRC_TEP],
							urlencode($_POST[Mantis2mitePlugin::DB_FIELD_ACCOUNT_NAME]),
							intval($i_miteProjectId),
							urlencode($_POST[Mantis2mitePlugin::DB_FIELD_API_KEY]));

		if (FALSE == ($s_content = @file_get_contents($s_apiURL))) {
			$a_errors[Mantis2mitePlugin::API_RSRC_TEP][] = "Could not retrieve data from <em>".$s_apiURL."</em>";
			break;
		}
				
		$o_xml = simplexml_load_string($s_content);
		
		foreach ($a_timeEntries as $a_timeEntry) {
			
			$b_foundMantisTimeEntry = false;
			
			foreach ($o_xml->children() as $o_child) {
				
			# found the time entry	
				if ($a_timeEntry['mite_time_entry_id'] == ((int)$o_child->id)) {
					
					$b_foundMantisTimeEntry = true;
					
					$s_miteTimeEntryUpdated = 
						Mantis2mitePlugin::mysqlDate((string)$o_child->{'updated-at'});
						
					if ($a_timeEntry['updated_at'] != $s_miteTimeEntryUpdated) {
						
						$a_mantisTimeEntriesToUpdate[$a_timeEntry['id']] = array(
							"updated_at" 	  => $s_miteTimeEntryUpdated,
							"mite_date_at" 	  => (string)$o_child->{'date-at'},
							"mite_note" 	  => (string)$o_child->{'note'},
							"mite_project_id" => (int)$o_child->{'project-id'},
							"mite_service_id" => (int)$o_child->{'service-id'});
					}
				}
			}
			
			if (!$b_foundMantisTimeEntry)
				$a_mantisTimeEntriesNotFound[$a_timeEntry['mite_time_entry_id']] = $a_timeEntry['id'];
		}					
	}
	
# check each time entry not found separately to find out if it was deletet or just moved to another project	
	foreach ($a_mantisTimeEntriesNotFound as $i_miteTimeEntryId => $i_mantisTimeEntryId) {
		
		$s_apiURL = sprintf(Mantis2mitePlugin::$a_miteResources[Mantis2mitePlugin::API_RSRC_TE],
							$_POST[Mantis2mitePlugin::DB_FIELD_ACCOUNT_NAME],
							$i_miteTimeEntryId,
							$_POST[Mantis2mitePlugin::DB_FIELD_API_KEY]);

	# if the entry does not exist anymore, delete it from the MANTIS database 						
		if (FALSE == ($s_content = @file_get_contents($s_apiURL))) {
			
			$a_logs[Mantis2mitePlugin::API_RSRC_TE][] = "Deleted time entry $i_mantisTimeEntryId";
			$a_queries[] = "DELETE FROM $s_tableTimeEntries WHERE id = $i_mantisTimeEntryId";
		}
	# if it does exist, but was moved to another project, prepare params to update the entry
		else {
			
			$o_xml = simplexml_load_string($s_content);
			
			$s_miteTimeEntryUpdated = 
						Mantis2mitePlugin::mysqlDate((string)$o_xml->{'updated-at'});
						
			$a_mantisTimeEntriesToUpdate[$i_mantisTimeEntryId] = array(
				"updated_at" 	  => $s_miteTimeEntryUpdated,
				"mite_date_at" 	  => (string)$o_xml->{'date-at'},
				"mite_note" 	  => (string)$o_xml->{'note'},
				"mite_project_id" => (int)$o_xml->{'project-id'},
				"mite_service_id" => (int)$o_xml->{'service-id'});
		}
	}
	
# !!! POSSIBLE SCRIPT EXIT !!!
# don't continue if something was wrong with the urls
# return error messages as xml
#####################################################
	if (!empty($a_errors)) {
		
		user_set_field($i_userId,Mantis2mitePlugin::DB_FIELD_CONNECT_VERIFIED, 0);
		
		foreach ($a_errors as $s_type => $a_messages) {
			
			foreach ($a_messages as $s_message)
				$s_xmlMsg .= "<error data='".$s_type."'>".$s_message."</error>";
			
		}
		echo "<errors>" . $s_xmlMsg . "</errors>";
		exit;
	}

# update time entries	
	foreach ($a_mantisTimeEntriesToUpdate as $i_mantisTimeEntryId => $a_updatedValues) {
		
		$a_logs[Mantis2mitePlugin::API_RSRC_TE][] = " 
			Updated time entry '$i_mantisTimeEntryId': 
				mite_project_id=".$a_updatedValues['mite_project_id'].", 
		 		mite_service_id=".$a_updatedValues['mite_service_id'].",
		 		mite_date_at='".$a_updatedValues['mite_date_at']."',
		 		mite_note='".$a_updatedValues['mite_note']."',
		 		updated_at=".$a_updatedValues['updated_at'];
				
		$a_queries[] = sprintf("
			UPDATE $s_tableTimeEntries
			SET mite_project_id = %d, mite_service_id = %d, mite_date_at = '%s',
				mite_note = '%s',updated_at	= '%s'
			WHERE id = %d",
			$a_updatedValues['mite_project_id'],
			$a_updatedValues['mite_service_id'],
			$a_updatedValues['mite_date_at'],
			$a_updatedValues['mite_note'],
			$a_updatedValues['updated_at'],
			$i_mantisTimeEntryId);
	}
	
	
# insert new project/service entries into database	
	foreach ($a_newRsrcEntries as $s_type => $a_idsNewEntries) {
		
		foreach ($a_idsNewEntries as $i_idNewEntry) {
			$a_tmpEntry = $a_miteUserData[$s_type][$i_idNewEntry];
			
			$s_entryName = $a_tmpEntry['name'];
			
		# append the customer name to a project name that belongs to more than one customers 
			if (($s_type == Mantis2mitePlugin::API_RSRC_P) && 
				(count($a_miteProjectNames[$s_entryName]) > 1)) {
				
				$s_entryName .= " (".$a_miteProjectNames[$s_entryName][$i_idNewEntry].")";
			}
			
			$a_logs[$s_type][] = 
				"Inserted entry '".$s_entryName."' with '".
				$a_fieldNamesMiteRsrc_id[$s_type]."=".$i_idNewEntry."'";
				
			$a_queries[] = sprintf( 
				"INSERT INTO ".$s_DBTable_mps.
				" (user_id,name,type,".$a_fieldNamesMiteRsrc_id[$s_type].",mite_updated_at)".
		 		" VALUES (%d, '%s', '%s', %d ,'%s')",
				$i_userId,
				Mantis2mitePlugin::encodeValue($s_entryName),
				$s_type,
				$i_idNewEntry,
				$a_tmpEntry['mite_updated_at']);
		}
	}
	
# delete old project/service from database
	foreach ($a_rsrcEntriesToDelete as $s_type => $a_idsEntriesToDelete) {
		
		foreach ($a_idsEntriesToDelete as $i_idEntryToDelete) {
			
			$a_logs[$s_type][] = 
				"Deleted entry '".$a_mantisMiteUserData[$s_type][$i_idEntryToDelete]['name']."' with '".
				$a_fieldNamesMiteRsrc_id[$s_type]."=".$i_idEntryToDelete."'";
			
			$a_queries[] = sprintf("
				DELETE FROM ".$s_DBTable_mps." 
				WHERE user_id = %d AND type = '%s' AND %s = %d",
				$i_userId,
				$s_type,
				$a_fieldNamesMiteRsrc_id[$s_type],
				$i_idEntryToDelete);
		}
	}
	
# update modified project/service in database
	foreach ($a_rsrcEntriesPossiblyModified as $s_type => $a_idsEntriesPossiblyModified) {
		
		foreach ($a_idsEntriesPossiblyModified as $i_idEntryPossiblyModified) {
			
			$a_tmpEntryMite = $a_miteUserData[$s_type][$i_idEntryPossiblyModified];
			$a_tmpEntryMantis = $a_mantisMiteUserData[$s_type][$i_idEntryPossiblyModified];
			
			if ($a_tmpEntryMite['mite_updated_at'] != $a_tmpEntryMantis['mite_updated_at']) {
				
				$s_entryName = $a_tmpEntryMite['name'];
				
			# append the customer name to a project name that belongs to more than one customers 
				if (($s_type == Mantis2mitePlugin::API_RSRC_P) && 
					(count($a_miteProjectNames[$s_entryName]) > 1)) {
					
					$s_entryName .= " (".$a_miteProjectNames[$s_entryName][$i_idEntryPossiblyModified].")";
				}
				
				$a_logs[$s_type][] = 
	"Updated entry from name='".$a_tmpEntryMantis['name']."' to name='".$s_entryName."' ".
	"on '".$a_fieldNamesMiteRsrc_id[$s_type]."=".$i_idEntryPossiblyModified."'";
				
				$a_queries[] = sprintf(" 
					UPDATE ".$s_DBTable_mps." 
					SET name = '%s', mite_updated_at = '%s'
					WHERE id = %d",
					Mantis2mitePlugin::encodeValue($s_entryName),
					$a_tmpEntryMite['mite_updated_at'],
					$a_tmpEntryMantis['id']);
			}
		}
	}
	
	
# execute the database queries	
	for ($i = 0; $i < count($a_queries); $i++) {
		$r_result = db_query_bound($a_queries[$i]);
	}
	
# set connection verified flag in the database	
	user_set_field($i_userId,Mantis2mitePlugin::DB_FIELD_CONNECT_VERIFIED, 1);

# update last update value	
	user_set_field($i_userId,Mantis2mitePlugin::DB_FIELD_CONNECT_LAST_UPDATED, Mantis2mitePlugin::mysqlDate());
	
# save the account name	
	user_set_field($i_userId,
				   Mantis2mitePlugin::DB_FIELD_ACCOUNT_NAME,
				   Mantis2mitePlugin::encodeValue($_POST[Mantis2mitePlugin::DB_FIELD_ACCOUNT_NAME]));
# save the API key				   
	user_set_field($i_userId,
				   Mantis2mitePlugin::DB_FIELD_API_KEY,
				   Mantis2mitePlugin::encodeValue($_POST[Mantis2mitePlugin::DB_FIELD_API_KEY]));
	
# build xml log messages	
	foreach ($a_logs as $s_type => $a_messages) {

		foreach ($a_messages as $s_message)
			$s_xmlMsg .= "<message data='".$s_type."'>".$s_message."</message>";
			
	}
	
# force re-initialization of session stored user values	
	Mantis2mitePlugin::initSessionVars();
	session_set('plugin_mite_status_session_vars','isCurrent');

# return xml log messages
	echo "<messages datetimestamp='".date('Y-m-d H:i:s')."'>" . $s_xmlMsg . "</messages>";
	
?>