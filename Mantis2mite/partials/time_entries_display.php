<?php
############	
# VARS 
#######
	
/*	
 * @global system vars
 */ 
	global $g_plugin_cache;

/*
 * @local resources/objects
 */	
	$r_result = $o_pluginController = null;
/**
 * @local array contains all configurable values
 */		
	$a_userMiteData = $a_users = array();
	
/*
 * @local strings
 */	
	$s_query = $s_output = $s_cssClass = $s_userEntries = '';
	
/*
 * @local int
 */	
	$i_currentUserId = $i_bugId = $i_projectId = $i_counter = $i_totalTimeBug = $i_totalTime = 
	$i_totalNumber = 0;
	
/*
 * @local booleans
 */
	$b_pageHasUserTimeEnries = $b_showOtherUsers = $b_showSummaryForCurrentUser = 
	$b_userIsConnected = false;
	
############	
# ACTION 
#######
	$o_pluginController = $g_plugin_cache['Mantis2mite'];
	$i_currentUserId = $o_pluginController->getCurrentUserId();
	$i_bugId = $_GET['bug_id'];
	$i_projectId = $_GET['project_id'];
	
	
	if (current_user_get_field(Mantis2mitePlugin::DB_FIELD_CONNECT_VERIFIED)) {
		
		$b_userIsConnected = true;
		
		$a_users[$i_currentUserId] = array(
			'id'	   => $i_currentUserId,
			'username' => current_user_get_field('username'),
			'realname' => current_user_get_field('realname'));
	}
	
	
# if the current user can see every entry	
	if (current_user_get_field('access_level') >= 
		plugin_config_get('mite_timetracks_visible_threshold_level')) {
		
		$b_showOtherUsers = true;
		$b_showSummaryForCurrentUser = true;
			
		$s_query = "SELECT id, username, realname FROM ".db_get_table('mantis_user_table').
				   " WHERE mite_connection_verified = 1 ORDER by realname";							 	 
		
	    $r_result = db_query_bound($s_query);	
		
			
	    if ((db_num_rows($r_result) > 0)) {
	    	
	    	while ($a_row = db_fetch_array($r_result)) {
	    		$a_users[$a_row['id']] = $a_row;
	    	}
	    }
	}
	
# loop through all users	
	foreach ($a_users as $i_userId => $a_properties) {
		
	# get all mite projects and services of the user
	################################################
		$s_query = "SELECT type, name, mite_project_id , mite_service_id FROM ".
				    	plugin_table(Mantis2mitePlugin::DB_TABLE_PS).
				   " WHERE user_id = ".$i_userId;							 	 
		
	    $r_result = db_query_bound($s_query);
	
	    $b_userHasMiteData = (db_num_rows($r_result) > 0);
	    
	    while ($b_userHasMiteData && ($a_row = db_fetch_array($r_result))) {
			
	    	$s_type = $a_row['type'];
	    	$s_rsrcTypeFieldName = Mantis2mitePlugin::$a_fieldNamesMiteRsrcTypes[$s_type];
	    	$a_userMiteData[$s_type][$a_row[$s_rsrcTypeFieldName]] = $a_row['name'];
		}
		
	# get time entries for this bug and 
	# build a modifieable list as output 
	####################################	
		$s_query = "
			SELECT * FROM ".
				plugin_table(Mantis2mitePlugin::DB_TABLE_TE)."
			WHERE user_id = $i_userId AND bug_id = $i_bugId ORDER BY created_at DESC";							 	 
		
	    $r_result = db_query_bound($s_query);
	
	    $b_pageHasUserTimeEnries = (db_num_rows($r_result) > 0);
	    
	    $s_patternTwoDigits = '%d:%02d';
	    
		while ($b_pageHasUserTimeEnries && ($a_row = db_fetch_array($r_result))) {
			
	    	if ($i_counter == 0) {
	    		$s_cssClass = " class='firstRow'";
	    	}
	    	
	    	$i_totalTime += $a_row['mite_duration'];
	    	
	    	if ($a_row['mite_note']) {
	    		$s_noteToggler = "
	    			<a href='#' class='plugin_mite_time_entry_show_note'>".
	    				lang_get('plugin_mite_time_entry_show_note')."
	    				<span>".html_entity_decode($a_row['mite_note'],ENT_QUOTES,'UTF-8')."</span>
		    		</a>";
	    	}
	    	
	    	$s_userEntries .= "
	    		<tr>
	    			<td".$s_cssClass.">";
	    	
	    	if ($i_currentUserId == $i_userId) {
				
	    		$s_userEntries .= "
	    			<form>
	    				<button class='plugin_mite_delete_time_entry'>".
    						lang_get('plugin_mite_delete_time_entry')."</button>
	    				<input type='hidden' name = 'mite_id' value = '".$a_row['mite_time_entry_id']."' />
					</form>";
	    	}

	    	$s_userEntries .= "
					</td>
	    			<td".$s_cssClass.">".$a_row['mite_date_at']."</td>
	    			<td".$s_cssClass.">".
    		$o_pluginController->decodeValue($a_userMiteData[Mantis2mitePlugin::API_RSRC_P][$a_row['mite_project_id']])."</td>
	    			<td".$s_cssClass.">".
    		$o_pluginController->decodeValue($a_userMiteData[Mantis2mitePlugin::API_RSRC_S][$a_row['mite_service_id']])."</td>
	    			<td".$s_cssClass." style='text-align:center'>$s_noteToggler</td>
	    			<td class='column_hours".(($s_cssClass != '') ? ' firstRow' : '')."'>".
	    				sprintf($s_patternTwoDigits,
	    						((int)($a_row['mite_duration'] / 60)),($a_row['mite_duration'] % 60))."
	    			</td>
	    		</tr>";
	    						
	    	$i_counter++;
	    	$s_cssClass = $s_noteToggler = '';				
		}
		
	# if there are time entries connected to this bug	
		if ($b_pageHasUserTimeEnries) {
		
		# if the current time entry is from another user	
			if ($i_currentUserId != $i_userId) {
				
				$s_output .= "
					<h4><a class='plugin_mite_time_show_entries_other_user' href='#'>".
						$a_properties['realname']."</a> - ".
						sprintf($s_patternTwoDigits,((int)($i_totalTime / 60)),($i_totalTime % 60))."
					</h4>
					<div class='plugin_mite_time_entries_other_user'>";
			}
		# if the current time entry is from the current user
		# and he has at least a time entry 
			elseif ($b_showOtherUsers && count($a_users > 1)) {	
				$s_output .= "
					<h4>".lang_get('plugin_mite_user_time_entries')." - ".
					sprintf($s_patternTwoDigits,((int)($i_totalTime / 60)),($i_totalTime % 60))."</h4>";
			}

			$s_output .= "
				<table style='width:100%'>
				<colgroup>
				    <col width='120px'>
				    <col width='100px'>
				    <col width='*'>
				    <col width='*'>
				    <col width='25px'>
				    <col width='60px'>
				  </colgroup>
				<thead>
				<tr>
					<th></th>
					<th>".lang_get('plugin_mite_time_entry_header_date_added')."</th>
					<th>".lang_get('plugin_mite_time_entry_header_mite_project')."</th>
					<th>".lang_get('plugin_mite_time_entry_header_mite_service')."</th>
					<th>".lang_get('plugin_mite_time_entry_header_mite_note')."</th>
					<th class='column_hours'>".lang_get('plugin_mite_time_entry_header_mite_hours')."</th>
				</tr>
				</thead>
				<tbody>". 
					$s_userEntries."
				</tbody>
				<tfoot>
				<tr>
					<td></td>
					<td class='label_total_hours' colspan='4'>".
						lang_get('plugin_mite_time_entry_header_mite_total_hours')."
					</td>
					<td class='column_hours'>".
						sprintf($s_patternTwoDigits,((int)($i_totalTime / 60)),($i_totalTime % 60))."
					</td>
				</tr>
				</tfoot>
				</table>";
						
			if ($i_currentUserId != $i_userId) {
				$s_output .= "</div>";
			}			
		}
		 
		$i_totalTimeBug += $i_totalTime;
		$i_totalNumber  += $i_counter;
		$s_userEntries   = '';
		$i_totalTime 	 = $i_counter = 0;	
	}

	if (($i_totalNumber == 0) && ($b_userIsConnected))
		$s_output .= "<em>".lang_get('plugin_mite_no_user_time_entries')."</em>";
	
# display total time off all users for this bug	
	if ($b_showSummaryForCurrentUser && ($i_totalNumber > 0)) {
		
		$s_output .= "
			<div class='plugin_mite_time_entries_summary'>
				<p> 
				<em>".lang_get('plugin_mite_time_entries_number')."</em>: ".
					$i_totalNumber.", 
				<em>".lang_get('plugin_mite_time_entries_sum')."</em>: ".
					sprintf($s_patternTwoDigits,((int)($i_totalTimeBug / 60)),($i_totalTimeBug % 60))."	
				</p>
			</div>
		";
	}
	
	echo $s_output;
?>