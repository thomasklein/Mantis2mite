<?php		
############	
# VARS 
#######
	
/**
 * @local array contains all configurable values
 */		
	$a_configParams = array();
	
/*
 * @local strings
 */	
	$s_pluginDirPath = $s_output = $s_miteConnectionStatus = $s_bgUserAccountDataCssClass = '';
	
/*
 * @local booleans
 */
	$b_miteConnectionVerified = $b_visibleBtnUnbindConnection = $b_visibleLinkChangeApiKey = 
	$b_visibleLinkChangeAccountName = false;
	
############	
# ACTION 
#######		
	auth_reauthenticate();
	html_page_top1( lang_get( 'plugin_mite_title' ) );
	html_page_top2();

# build config arrays for convenient access to necessary elements
################################################################# 	
	$a_configParams['api_key'] = array(
		'name'				=> mitePlugin::DB_FIELD_API_KEY,
		'value' 			=> current_user_get_field(mitePlugin::DB_FIELD_API_KEY),
		'label'				=> lang_get('plugin_mite_api_key'),
		'type'   			=> 'text',
		'readonly'   		=> '',
		'cssClass'   		=> '',
		'help' 				=> '');
	
	if ($a_configParams['api_key']['value'])
		$a_configParams['api_key']['value'] = mitePlugin::decodeValue($a_configParams['api_key']['value']);
	
	$a_configParams['account_name'] = array(
		'name'				=> mitePlugin::DB_FIELD_ACCOUNT_NAME,
		'value' 			=> current_user_get_field(mitePlugin::DB_FIELD_ACCOUNT_NAME),
		'label'				=> lang_get('plugin_mite_account_name'),
		'type'   			=> 'text',
		'readonly'   		=> '',
		'cssClass'   		=> '',
		'help' 				=> '');
	
	if ($a_configParams['account_name']['value']) {
		$a_configParams['account_name']['value'] = 
			mitePlugin::decodeValue($a_configParams['account_name']['value']);
	}
	
# get the path to this plugin	
	$s_pluginDirPath = helper_mantis_url("plugins/".plugin_get_current()."/");

# get connection status 	
	$b_miteConnectionVerified = current_user_get_field(mitePlugin::DB_FIELD_CONNECT_VERIFIED);
	
# add options if the connection was verified
############################################	
	if ($b_miteConnectionVerified) {
		$s_miteConnectionStatus = sprintf(lang_get('plugin_mite_connection_verified'),
						    			  current_user_get_field(mitePlugin::DB_FIELD_CONNECT_LAST_UPDATED));
		$s_connectionStatusCssClass = 'plugin_mite_positive_connection_status';
		
		$a_configParams['account_name']['readonly'] = $a_configParams['api_key']['readonly'] = 
			" readonly='readonly'";
		
		$a_configParams['account_name']['cssClass'] = $a_configParams['api_key']['cssClass'] = 
			" class='readonly'";
		
		$a_configParams['api_key']['type'] = "password";
		
		$b_visibleBtnUnbindConnection = $b_visibleLinkChangeApiKey = 
		$b_visibleLinkChangeAccountName = true;
		
		$s_bgUserAccountDataCssClass = 'mite_user_account_active';
		
	}
	else {
		$s_miteConnectionStatus = lang_get('plugin_mite_connection_unverified');
		$s_connectionStatusCssClass = 'plugin_mite_negative_connection_status';
		$s_bgUserAccountDataCssClass = 'mite_user_account_inactive';
	}
	
	$s_output = "
		<div id='plugin_mite_messages'>
			<div>
				<a class='closeBtn' href='#'>".lang_get('plugin_mite_msg_close_message')."</a>
				<p></p>
			</div>
			<input type='hidden' value='".lang_get('plugin_mite_msg_missing_account_data')."' 
				   id='plugin_mite_msg_missing_account_data' />
			<input type='hidden' value='".lang_get('plugin_mite_msg_success_saving_bindings')."' 
				   id='plugin_mite_msg_success_saving_bindings' />
			<input type='hidden' value='".lang_get('plugin_mite_msg_error_saving_bindings')."' 
				   id='plugin_mite_msg_error_saving_bindings' />	   
			<input type='hidden' value='".lang_get('plugin_mite_msg_success_verification')."' 
				   id='plugin_mite_msg_success_verification' />
			<input type='hidden' value='".lang_get('plugin_mite_msg_success_updating_account_data')."' 
				   id='plugin_mite_msg_success_updating_account_data' />	   
			<input type='hidden' value='".lang_get('plugin_mite_msg_error_verification')."' 
				   id='plugin_mite_msg_error_verification' />
			<input type='hidden' value='".lang_get('plugin_mite_txt_error_updating_account_data')."' 
				   id='plugin_mite_txt_error_updating_account_data' />
			<input type='hidden' value='".lang_get('plugin_mite_msg_confirm_disconnecting_account')."' 
				   id='plugin_mite_msg_confirm_disconnecting_account' />
			<input type='hidden' value='".lang_get('plugin_mite_msg_success_disconnecting_account')."' 
				   id='plugin_mite_msg_success_disconnecting_account' />	   
			<input type='hidden' value='".lang_get('plugin_mite_msg_error_disconnecting_account')."' 
				   id='plugin_mite_msg_error_disconnecting_account' />
			<input type='hidden' value='".lang_get('plugin_mite_msg_error_loading_binding_area')."' 
				   id='plugin_mite_msg_error_loading_binding_area' />
			<input type='hidden' value='".lang_get('plugin_mite_connection_verified')."' 
							 	 id='plugin_mite_txt_connection_verified' />
			<input type='hidden' value='".lang_get('plugin_mite_connection_unverified')."' 
								 id='plugin_mite_txt_connection_unverified' />
			<input type='hidden' value='".$s_pluginDirPath."' id='plugin_mite_path' />
			<input type='hidden' value='".lang_get('plugin_mite_loading_user_bindings')."' 
								 id='plugin_mite_txt_loading_user_bindings' />
			<input type='hidden' value='".lang_get('plugin_mite_msg_database_error')."' 
								 id='plugin_mite_msg_database_error' />					 
			
		</div><!-- plugin_mite_messages -->
		<div id='plugin_mite_config'>
		<form id='frm_mite_account_data'>
		
		<h2>".lang_get( 'plugin_mite_user_config_header' )."</h2>
	
	<!-- connection status -->	 
		<div id='plugin_mite_connection_status' class='".$s_connectionStatusCssClass."'>".
			$s_miteConnectionStatus."
		</div>
		
	<!-- account name -->
		<div class='config_fields $s_bgUserAccountDataCssClass'>
			<label>".$a_configParams['account_name']['label']."</label>
			http://
			<input type='".$a_configParams['account_name']['type']."' name='".$a_configParams['account_name']['name']."' 
				   value='".$a_configParams['account_name']['value']."'".
				   $a_configParams['account_name']['cssClass'].
				   $a_configParams['account_name']['readonly']." 
				   id='plugin_mite_account_name' />.mite.yo.lk
			<span class='linkChangeValue' style='display:".(($b_visibleLinkChangeAccountName) ? 'block' : 'none')."'>
				<a href='#' id='plugin_mite_change_account_name'>".
				   lang_get('plugin_mite_change_account_name')."</a>
			</span>		
						  
	<!-- API key -->
			<label>".$a_configParams['api_key']['label']."</label>
			<input type='".$a_configParams['api_key']['type']."' name='".$a_configParams['api_key']['name']."' 
				   value='".$a_configParams['api_key']['value']."'".
				   $a_configParams['api_key']['cssClass'].
				   $a_configParams['api_key']['readonly']." 
				   id='plugin_mite_account_api_key' />
			<span class='linkChangeValue' style='display:".(($b_visibleLinkChangeApiKey) ? 'block' : 'none')."'>
				<a href='#' id='plugin_mite_change_api_key'>".
				   lang_get('plugin_mite_change_api_key')."</a>
			</span>
		</div>
		
	<!-- button area -->
		<div class='formularButtons'>
			<div class='buttonsRight'>
				<button id='plugin_mite_check_account_data' type='submit'>".
					lang_get('plugin_mite_check_account_data' )."
				</button>
				<input type='hidden' value='".lang_get('plugin_mite_check_account_data_active')."' 
							 id='plugin_mite_txt_check_account_data_active' />
			</div>
			<div class='buttonsLeft'>
				<button id='plugin_mite_disconnect_account_data' 
					style='display:".(($b_visibleBtnUnbindConnection) ? 'block' : 'none')."'>".
					lang_get('plugin_mite_disconnect_account_data' )."
				</button>
				<input type='hidden' value='".lang_get('plugin_mite_disconnecting_account_data_active')."' 
							 id='plugin_mite_disconnecting_account_data_active' />
			</div>
			<div class='clearBoth'></div>
		</div>
	
		</form>
		<div id='plugin_mite_user_bindings'></div>
		</div><!-- plugin_mite_config -->";
					
	echo $s_output;
	
	html_page_bottom1( __FILE__ );
?>