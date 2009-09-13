<?php
	require_once( '../../../core.php' );//reload mantis environment
	Mantis2mitePlugin::initPartial();
	
############	
# VARS 
#######
	
/*
 * @local resources/objects
 */	
	$r_result = $o_responseXml = null;
/**
 * @local array contains all configurable values
 */		
	$a_validTimeUnitSeparators = $a_timeUnits = $a_data = $a_dataKeyValuePairs = $a_dataKeyValue = array();
	
/*
 * @local strings/mixed
 */	
	$m_postedTime = $s_query = $s_tableTimeEntries = $s_timeUnitSep = $s_note = '';
	
/*
 * @local int
 */	
	$i_timeInMinutes = 0;
		
############	
# ACTION 
#######

# prepare to return an xml message
	header('Cache-Control: must-revalidate, pre-check=0, no-store, no-cache, max-age=0, post-check=0');
	header('Content-Type: text/xml; charset=utf-8');
	echo '<?xml version="1.0" encoding="UTF-8"?>';
	
# !!! POSSIBLE SCRIPT EXIT !!!
# only proceed if valid params where passed
###########################################	
	if (!isset($_POST['action']) || !isset($_POST['data'])) {
		echo "<error>Missing POST value 'action' and/or 'data'</error>";
		exit;
	}
	
	$o_miteRemote = Mantis2mitePlugin::getMiteRemote();
	$s_tableTimeEntries = plugin_table(Mantis2mitePlugin::DB_TABLE_TE);
	
# build key-value array $a_data of the serialized $_POST['data'] string
	$a_dataKeyValuePairs = explode("&",$_POST['data']);
	
	foreach ($a_dataKeyValuePairs as $s_dataKeyValuePair) {
		
		$a_dataKeyValue = explode("=",$s_dataKeyValuePair);
		$a_data[urldecode($a_dataKeyValue[0])] = urldecode($a_dataKeyValue[1]);
	}
	
#########################
# ADDING a new time entry
#########################	
	if ($_POST['action'] == 'addEntry') {
		
		$s_note = 
			Mantis2mitePlugin::replacePlaceHolders($a_data['plugin_mite_note_new_time_entry'],
				   								   $a_data['plugin_mite_current_bug']);
		
		$m_postedTime = $a_data['plugin_mite_hours_new_time_entry'];
		
	# in this case a single number was given and hours is as time unit suspected
		if ((((string) $m_postedTime) === ((string)(int) $m_postedTime)) && ((int) $m_postedTime != 0)) {
			
			$i_timeInMinutes = ($m_postedTime * 60);
		}
		else {
			$a_validTimeUnitSeparators = array("colon" => ":",
									  	   	   "dot"   => ".",
										   	   "comma" => ",");
			
		# check different time formats
			foreach ($a_validTimeUnitSeparators as $s_name => $s_char) {
				
				if (strpos($m_postedTime,$s_char) !== FALSE) {
					$s_timeUnitSep = $s_name;
					break;
				}
			}

		# !!! POSSIBLE SCRIPT EXIT !!!
		# only proceed if there was a valid time unit separator
		#######################################################	
			if (!$s_timeUnitSep) {
				echo "<error>".lang_get('plugin_mite_invalid_time_format')."</error>";
				exit;
			}
			
			$a_timeUnits = explode($a_validTimeUnitSeparators[$s_timeUnitSep],$m_postedTime);
			
			if ((count($a_timeUnits) == 2) && is_numeric($a_timeUnits[0]) && is_numeric($a_timeUnits[1])) {
				
				switch ($s_timeUnitSep) {
					
					case 'colon':
						
					# more than 59min are not possible	
						if ($a_timeUnits[1] > 59)
							break;
							
						$i_timeInMinutes = ($a_timeUnits[0] * 60) + $a_timeUnits[1];
						break;
						
					case 'dot':
					case 'comma':
						$i_timeInMinutes = ($a_timeUnits[0] * 60) + 
							round(($a_timeUnits[1] / (pow(10,strlen($a_timeUnits[1])))) * 60);
						break;
				}
			}
		}
		
	# build XML request for MITE API	
		$s_postRequest = sprintf(" 
			<time-entry>
				<date-at>%s</date-at>
				<minutes>%d</minutes>
				<note>%s</note>
			  	<service-id>%d</service-id>
			  	<project-id>%d</project-id>
			</time-entry>",
		  	Mantis2mitePlugin::mysqlDate($a_data['plugin_mite_date_new_time_entry']),
		  	intval($i_timeInMinutes),
		  	$s_note,
		  	intval($a_data['plugin_mite_services_new_time_entry']),
		  	intval($a_data['plugin_mite_projects_new_time_entry']));
		
		try {
		# EXIT on request errors 
			if (!$o_miteRemote->sendRequest('post','time_entries.xml', $s_postRequest)) {
			
				echo $o_miteRemote->getErrors();
				exit;
			}
			
			$o_responseXml = $o_miteRemote->getReponseXML();
	
		# add the time entry to the database
			$s_query = sprintf("
		        INSERT INTO $s_tableTimeEntries
				 (user_id,bug_id,mite_time_entry_id,mite_project_id,mite_service_id,
				  mite_duration,mite_date_at,mite_note,created_at,updated_at)
		 		VALUES (%d, %d, %d, %d, %d, %d, '%s', '%s', '%s', '%s')",
				auth_get_current_user_id(),
				intval($a_data['plugin_mite_current_bug']),
				intval($o_responseXml->id),
				intval($a_data['plugin_mite_projects_new_time_entry']),
				intval($a_data['plugin_mite_services_new_time_entry']),
				$i_timeInMinutes,
				Mantis2mitePlugin::mysqlDate($o_responseXml->{'date-at'},true),
				htmlentities($s_note,ENT_QUOTES,'UTF-8'),
				Mantis2mitePlugin::mysqlDate($o_responseXml->{'created-at'}),
				Mantis2mitePlugin::mysqlDate($o_responseXml->{'created-at'}));
			$r_result = db_query_bound($s_query);
			
		} catch (Exception $e) {
		# EXIT on function errors
			echo "<error>".$e->getMessage()."</error>";
			exit;
		}
	}
	
#########################
# DELETING a time entry
#########################	
	elseif ($_POST['action'] == 'deleteEntry') {
		
		try {
		# EXIT on request errors 
			if (!$o_miteRemote->sendRequest('delete',
											'time_entries/'.$a_data['mite_id'].".xml",
											$s_postRequest)) {
			
				echo $o_miteRemote->getErrors();
				exit;
			}
			
		# delete entry from the Mantis database	
			$s_query = sprintf(" 
		         DELETE FROM $s_tableTimeEntries
				 WHERE mite_time_entry_id = %d AND user_id = %d",
				 $a_data['mite_id'],
				 auth_get_current_user_id());
		
			$r_result = db_query_bound($s_query);
			
		} catch (Exception $e) {
		# EXIT on function errors
			echo "<error>".$e->getMessage()."</error>";
			exit;
		}
	}
	
	echo "<messages datetimestamp='".gmdate('Y-m-d H:i:s')."'></messages>";
?>