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
	$o_xml = $r_result = $o_pluginController = null;

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
	$s_DBTable_mps = plugin_table(Mantis2mitePlugin::DB_TABLE_PS);
	$s_tableTimeEntries = plugin_table(Mantis2mitePlugin::DB_TABLE_TE);
	
	$o_pluginController = $g_plugin_cache['Mantis2mite'];
	$i_userId = $o_pluginController->getCurrentUserId();
	
	$a_fieldNamesMiteRsrc_id = array(Mantis2mitePlugin::API_RSRC_P => 'mite_project_id',
									 Mantis2mitePlugin::API_RSRC_S => 'mite_service_id');
									 
	$o_miteRemote = $o_pluginController->getMiteRemote();
	$o_miteRemote->init($_POST[Mantis2mitePlugin::DB_FIELD_API_KEY],
						$_POST[Mantis2mitePlugin::DB_FIELD_ACCOUNT_NAME],
						"Mantis2mite/" . Mantis2mitePlugin::MANTIS2MITE_VERSION);
	
	
# PROJECTS AND SERVICES synchronisation
#######################################
	foreach (Mantis2mitePlugin::$a_miteResources as $s_rsrcType => $s_rsrcName) {
		
	# time entries are handled later on	
		if(($s_rsrcType == Mantis2mitePlugin::API_RSRC_TEP) || 
		   ($s_rsrcType == Mantis2mitePlugin::API_RSRC_TE)) {
			
			continue;
		}
		
		try {
		 	$o_xml = $o_miteRemote->sendRequest('get',$s_rsrcName);
			
			foreach ($o_xml->children() as $o_child) {
				
			# get projects with the same name to append the customer name later on
			# to better distinguish them when showing up in a selection box
				if ($s_rsrcType == Mantis2mitePlugin::API_RSRC_P) {
					$a_miteProjectNames[(string)$o_child->name][((int)$o_child->id)] = 
						(string)$o_child->{'customer-name'};
				}
				
				$a_miteUserData[$s_rsrcType][(int)$o_child->id] = array( 
					'name' 			  => (string)$o_child->name,
					'mite_updated_at' => Mantis2mitePlugin::mysqlDate((string)$o_child->{'updated-at'}));
			}
			
		# get in MANTIS saved MITE projects/services
			$s_query = "SELECT id,name,".$a_fieldNamesMiteRsrc_id[$s_rsrcType].", mite_updated_at 
						FROM ".$s_DBTable_mps.
					   " WHERE user_id = ".$i_userId." AND type = '".$s_rsrcType."'";
			
			$r_result = db_query_bound($s_query);
			
			$a_mantisMiteUserData[$s_rsrcType] = array();
			
			if (db_num_rows($r_result) > 0) {
				while ($a_row = db_fetch_array($r_result)) {
					$a_mantisMiteUserData[$s_rsrcType][$a_row[$a_fieldNamesMiteRsrc_id[$s_rsrcType]]] = $a_row;
				}
			}
			
		# get new entries (projects and services)
			$a_newRsrcEntries[$s_rsrcType] = 
				array_diff(array_keys($a_miteUserData[$s_rsrcType]),
						   array_keys($a_mantisMiteUserData[$s_rsrcType]));
			
		# get deleted entries (projects and services)
			$a_rsrcEntriesToDelete[$s_rsrcType] = 
				array_diff(array_keys($a_mantisMiteUserData[$s_rsrcType]),
						   array_keys($a_miteUserData[$s_rsrcType]));
			
		# get possibly updated entries (projects and services)				   
			$a_rsrcEntriesPossiblyModified[$s_rsrcType] = 
				array_intersect(array_keys($a_mantisMiteUserData[$s_rsrcType]),
							  	array_keys($a_miteUserData[$s_rsrcType]));	
			
		} catch (Exception $e) {
			$a_errors[$s_rsrcType][] = $e->getMessage();
		}				  	
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
		
		try {
			$o_xml = $o_miteRemote->sendRequest('get','/time_entries.xml?project-id='.intval($i_miteProjectId));
											
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
			
		} catch (Exception $e) {
			$a_errors[$s_type][] = $e->getMessage();
		}		
	}
	
# check each time entry not found separately to find out 
# if it was deletet or just moved to another project	
	foreach ($a_mantisTimeEntriesNotFound as $i_miteTimeEntryId => $i_mantisTimeEntryId) {
		
		try {
			$o_xml = $o_miteRemote->sendRequest('get','/time_entries/'.$i_miteTimeEntryId);
			
		# if it does exist, but was moved to another project, prepare params to update the entry	
			$s_miteTimeEntryUpdated = 
						Mantis2mitePlugin::mysqlDate((string)$o_xml->{'updated-at'});
						
			$a_mantisTimeEntriesToUpdate[$i_mantisTimeEntryId] = array(
				"updated_at" 	  => $s_miteTimeEntryUpdated,
				"mite_date_at" 	  => (string)$o_xml->{'date-at'},
				"mite_note" 	  => (string)$o_xml->{'note'},
				"mite_project_id" => (int)$o_xml->{'project-id'},
				"mite_service_id" => (int)$o_xml->{'service-id'});
			
				
			
		} catch (Exception $e) {
			
			switch ($e->getCode()) {
				
			# if the entry does not exist anymore, delete it from the MANTIS database	
				case mite::EXCEPTION_RSRC_NOT_FOUND: 
					
					$a_logs[Mantis2mitePlugin::API_RSRC_TE][] = "Deleted time entry $i_mantisTimeEntryId";
					$a_queries[] = "DELETE FROM $s_tableTimeEntries WHERE id = $i_mantisTimeEntryId";
					break;
				
			# note error if anything went wrong with the request 		
				default:
					$a_errors[Mantis2mitePlugin::API_RSRC_TE][] = $e->getMessage();
					break;
			}
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
	session_set('plugin_mite_status_session_vars','reinit');

# return xml log messages
	echo "<messages datetimestamp='".date('Y-m-d H:i:s')."'>" . $s_xmlMsg . "</messages>";
	
?>