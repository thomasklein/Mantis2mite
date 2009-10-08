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
	$r_result = $o_pluginController = null;

/*
 * @local arrays
 */	
	$a_queries = array();
/*
 * @local strings
 */	
	$s_query = '';
	
############
# ACTION
#######	
	$o_pluginController = $g_plugin_cache['Mantis2mite'];
	$i_userId = $o_pluginController->getCurrentUserId();
	
	$a_queries[] =  
		"DELETE FROM ".plugin_table(Mantis2mitePlugin::DB_TABLE_PS)." WHERE user_id = ".$i_userId;
	$a_queries[] =  
		"DELETE FROM ".plugin_table(Mantis2mitePlugin::DB_TABLE_PSMP)." WHERE user_id = ".$i_userId;
	$a_queries[] =  
		"DELETE FROM ".plugin_table(Mantis2mitePlugin::DB_TABLE_TE)." WHERE user_id = ".$i_userId;
	
# empty all database fields in the user table which are connected to the plugin	
	user_set_field($i_userId,Mantis2mitePlugin::DB_FIELD_CONNECT_VERIFIED, 0);
	user_set_field($i_userId,Mantis2mitePlugin::DB_FIELD_API_KEY, '');
	user_set_field($i_userId,Mantis2mitePlugin::DB_FIELD_ACCOUNT_NAME, '');
	user_set_field($i_userId,Mantis2mitePlugin::DB_FIELD_NOTE_PATTERN, '');
	user_set_field($i_userId,Mantis2mitePlugin::DB_FIELD_CONNECT_LAST_UPDATED, 0);
	
# reset session status for plugin vars	
	session_set('plugin_mite_status_session_vars','init');
	
# execute the database queries	
	for ($i = 0; $i < count($a_queries); $i++) {
		$r_result = db_query_bound($a_queries[$i]);
	}
	echo "<messages datetimestamp='".gmdate('Y-m-d H:i:s')."'></messages>";
?>