<?php
/* CLASS Mantis2mite
 * 
 * Mantis2mite plugin for MantisBT
 * 
 * mite is an sleek time tracking tool for team and freelancers: http://mite.yo.lk
 * 
 * @package Mantis2mite
 * @author Thomas Klein (thomas.klein83@gmail.com)
 * @licence MIT license
 * @description Connects your Mantis account with your mite.account. 
 * Track your time easily on issues within Mantis and get them automatically send to mite.
 *
 */ 

# MantisBT - a php based bugtracking system

# MantisBT is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# MantisBT is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with MantisBT.  If not, see <http://www.gnu.org/licenses/>.

require_once(config_get('class_path').'MantisPlugin.class.php');
require_once('classes/miteUserData.class.php');
require_once('classes/miteRemote.class.php');

class Mantis2mitePlugin extends MantisPlugin {
	
############	
# PROPERTIES/CONSTANTS
#######	
	
	const TIMEZONE_MITE_SERVER = 'Europe/Berlin';
	
	const DB_FIELD_API_KEY 				= "mite_api_key";
	const DB_FIELD_ACCOUNT_NAME			= "mite_account_name";
	const DB_FIELD_NOTE_PATTERN			= "mite_note_pattern";
	const DB_FIELD_CONNECT_VERIFIED		= "mite_connection_verified";
	const DB_FIELD_CONNECT_LAST_UPDATED = "mite_connection_updated_at";

/**
 * New columns for 'mantis_user_table'
 *
 * @pulic static array
 */	
	public static $a_userTable_newColumns = array(
		self::DB_FIELD_API_KEY 				=> "C(350) NOTNULL DEFAULT \" '' \"",
		self::DB_FIELD_ACCOUNT_NAME 		=> "C(350) NOTNULL DEFAULT \" '' \"",
		self::DB_FIELD_NOTE_PATTERN 		=> "X NOTNULL DEFAULT \" '' \"",
		self::DB_FIELD_CONNECT_VERIFIED 	=> "L NOTNULL DEFAULT \" '0' \"",
		self::DB_FIELD_CONNECT_LAST_UPDATED => "T NOTNULL DEFTIMESTAMP");
			
/**
 * Standard collation for this plugins new tables
 *
 * @public static array
 */
	public static $a_newDBTables_collation = 
		array('mysql' => 'ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_general_ci',
			  'pgsql' => 'WITHOUT OIDS');
	
	const DB_TABLE_TE = "timeEntries";
	const DB_TABLE_PS = "projects_services";
	const DB_TABLE_PSMP = "psmp";//projects_services_mantis_project
/**
 * New database tables for this plugin
 *
 * @private array
 */	
	public static $a_newDBTables =  array(
		self::DB_TABLE_TE => "
			id					I	UNSIGNED NOTNULL PRIMARY AUTOINCREMENT,
			user_id				I	UNSIGNED NOTNULL,
			bug_id				I	UNSIGNED NOTNULL,
			bugnote_id			I	UNSIGNED NOTNULL DEFAULT '0',
			mite_time_entry_id	I	UNSIGNED NOTNULL DEFAULT '0',
			mite_project_id		I	UNSIGNED NOTNULL DEFAULT '0',
			mite_service_id		I	UNSIGNED NOTNULL DEFAULT '0',
			mite_duration		I	UNSIGNED NOTNULL,
			mite_date_at		D	NOTNULL DEFAULT 0,
			mite_note			X 	NOTNULL DEFAULT \" '' \",
			updated_at			T	NOTNULL DEFTIMESTAMP,
			created_at			T	NOTNULL DEFAULT 0",
		
		self::DB_TABLE_PS => "
			id							I		UNSIGNED NOTNULL PRIMARY AUTOINCREMENT,
			user_id						I		UNSIGNED NOTNULL,
			type						C(10)	NOTNULL DEFAULT \" '' \",
			name						C(350)	NOTNULL DEFAULT \" '' \",
			mite_project_id				I		UNSIGNED NOTNULL DEFAULT '0',
			mite_service_id				I		UNSIGNED NOTNULL DEFAULT '0',
			mite_updated_at				T		NOTNULL DEFAULT '0'",
	
		self::DB_TABLE_PSMP => "
			user_id						I		UNSIGNED NOTNULL,
			type						C(10)	NOTNULL DEFAULT \" '' \",
			mite_project_id				I		UNSIGNED NOTNULL DEFAULT '0',
			mite_service_id				I		UNSIGNED NOTNULL DEFAULT '0',
			mantis_project_id			I		UNSIGNED NOTNULL DEFAULT '0'
		");		
/**
 * Files to include for specific pages
 *
 * @private array
 */	
	private $a_pageResources = array(
		'Mantis2mite/user_account' => array('js' => array('mite_user_account.js')),
		'view.php' 		    => array('js' => array('mite_time_entries.js')));
	
/**
 * Files to include generally if some plugin specific action happens on the current page
 *
 * @private array
 */	
	private $a_generalResources = array('css' => array('mite.css'),
										'js'  => array('jquery-1.3.2.min.js','mite.js'));
	
	const API_RSRC_P   = 'projects';
	const API_RSRC_S   = 'services';
	const API_RSRC_TEP = 'time_entries_project';
	const API_RSRC_TE  = 'time_entry';
	
	public static $a_rsrcTypes = array(Mantis2mitePlugin::API_RSRC_P,Mantis2mitePlugin::API_RSRC_S);
	
	public static $a_fieldNamesMiteRsrcTypes = 
		array(Mantis2mitePlugin::API_RSRC_P => 'mite_project_id',
			  Mantis2mitePlugin::API_RSRC_S => 'mite_service_id');
	
/*
 * @public array containing patterns for api data structures to obtain from mite
 */
	public static $a_miteResources = 
		array(self::API_RSRC_P   => 'projects.xml',
			  self::API_RSRC_S   => 'services.xml',
			  self::API_RSRC_TEP => 'time_entries.xml?project-id=%s',
			  self::API_RSRC_TE  => 'time_entries/%s.xml'
			  );
	
/**
 * Contains the current users MITE data: projects, services, bindings to Mantis projects
 *
 * @public static object
 */
  	private static $o_miteUserData;
  	
/**
 * Provides methods to communicate with the MITE API
 *
 * @public static object
 */
  	private static $o_miteRemote;
  	
			  
############	
# METHODS
#######	
	
# METHODS CALLED BY THE MANTIS PLUGIN SYSTEM 			  
############################################

/*****************************************************
 *  Callback to register the plugin with the Mantis plugin manager
 */	
	function register() {
		$this->name        = 'Mantis2<em>mite</em>';
		$this->description = lang_get('plugin_mite_description');
		$this->page = 'config';
		$this->version     = '1.1';
		$this->requires    = array('MantisCore'=> '1.2.0');
		$this->author      = 'Thomas Klein';
		$this->contact     = 'thomas.klein83@gmail.com';
		$this->url         = 'http://mite.yo.lk';
	}//register
 
	
/*****************************************************
 *  Callback to set config vars for the plugin behaviour  
 */		
	function config() {
		return array(
			//other possible values are: NOBODY,ADMINISTRATOR,DEVELOPER,UPDATER,REPORTER,VIEWER,ANYBODY
			'mite_timetracks_visible_threshold_level' => MANAGER
		);
	}//config
	
	
/*****************************************************
 * Callback raised in the installation process (adding tables and columns to the mantis database)
 * and everytime the user is on the 'Manage Plugins' site
 */
	function schema() {
		
	############	
	# VARS 
	#######
	/*
	 * @local array
	 */
		$a_schema = array();
	/*
	 * @local string
	 */	
		$s_userTable = '';
		
	############	
	# ACTION 
	#######		
	
	# add the new tables to the mantis database
	###########################################	
		foreach (self::$a_newDBTables as $s_tableName => $s_tableDecl) {
			$a_schema[] = array('CreateTableSQL',array(plugin_table($s_tableName),
													   $s_tableDecl,
													   self::$a_newDBTables_collation));
		}
	
	# add fields to the mantis user table
	#####################################
		$s_userTable = db_get_table('mantis_user_table');
		
		foreach(self::$a_userTable_newColumns as $s_columnName => $s_definition) {
			$a_schema[] = array('AddColumnSQL',array($s_userTable, $s_columnName." ".$s_definition));
		}
		
		return $a_schema;
	}//schema	
	

/****************************************************
 * @CALLBACK MANTIS EVENT
 * Must return true, otherwise the installation will fail
 */	
	function install() {
		
	/*
	 * @local string
	 */	
		echo  "
			<div style='width:450px;color:green; margin: 1em auto'> 
				<p><strong>Installation successful!</strong><br /><br />
				Added columns <em>mantis_user_table</em>:<br /><code>".
					implode(", ",array_keys(self::$a_userTable_newColumns))."</code><br /><br />
				Added database tables (<em>mantis_plugin_mite_NAME_table</em>):<br /><em>".
					implode(", ",array_keys(self::$a_newDBTables))."</em>
			</div>";
			
		return true;
	}//install
  	
	
/*****************************************************
 * @CALLBACK MANTIS EVENT
 * Callback raised after the user clicked the uninstall button (!)
 * Removes all database columns that were added via the plugin  
 */	
	function uninstall() {
		
	############	
	# VARS 
	#######		
		global $g_db;
	/*
	 * @local object
	 */	
		$t_dict = NewDataDictionary( $g_db );
	/*
	 * @local arrays
	 */	
		$a_sql = $a_dbErrors = array();	
	/*
	 * @local int
	 */	
		$i_status = -1;
	/*
	 * @local strings
	 */	
		$s_mantisUserTable = $s_errorMsg = $s_pluginTableName = '';

	############	
	# ACTION 
	#######	
		
	# delete added columns to the mantis_user_table
	###############################################
		$s_mantisUserTable = db_get_table('mantis_user_table');
		
		foreach(self::$a_userTable_newColumns as $s_columnName => $s_definition) {
			$a_sql = call_user_func_array(array($t_dict,'DropColumnSQL'),
										  array($s_mantisUserTable,$s_columnName));
			$i_status = $t_dict->ExecuteSQLArray($a_sql);
			
			if ($i_status != 2) {
				$a_dbErrors[$s_mantisUserTable][] = "column <code>".$s_miteColumn."</code> did not exist";
			}
		}
		
	# delete plugin database tables
	###############################
		foreach (self::$a_newDBTables as $s_tableName => $s_tableDecl) {
			
			$s_pluginTableName = plugin_table($s_tableName);
			
			$a_sql = call_user_func_array( Array( $t_dict, 'DropTableSQL' ), array($s_pluginTableName));
			$i_status = $t_dict->ExecuteSQLArray($a_sql);
		
			if ($i_status != 2)
				$a_dbErrors[$s_pluginTableName][] = 'could not delete the table';
		}
		
	# delete plugin schema entry form the mantis config table
	# otherwise a reinstallation might fail
	#########################################################
		$s_configTable = db_get_table('mantis_config_table');
		$a_sql = call_user_func_array( Array( $t_dict, 'DropColumnSQL' ), array($s_userTable,$s_miteColumn));
		
		$s_query = "DELETE FROM ".$s_configTable." WHERE config_id = 'plugin_".$this->basename."_schema'";
		
		if (!db_query_bound( $s_query)) 
			$a_dbErrors[$s_configTable][] = 'could not delete the field <code>config_id</code>';
		
	# show errors in case of
	########################
		if (!empty($a_dbErrors)) {
			
			$s_errorMsg = "
				<div style='width:450px;color:red; margin: 1em auto'>
				<p><strong>Errors where raised for database queries in the following tables:</strong>
				<ul>";   
				
			foreach ($a_dbErrors as $s_table => $a_errors) {

				$s_errorMsg .= "
					<li><em>".$s_table."</em></li>
					<ul>";
				
				foreach ($a_errors as $s_error)
					$s_errorMsg .= "<li>".$s_error."</li>";
				
				$s_errorMsg .= "</ul>";
			}
			
			echo $s_errorMsg."</ul></p></div>";
		}
		else {
			echo "
				<div style='width:450px;color:green; margin: 1em auto'> 
					<p><strong>Uninstallation successful!</strong><br /><br />
					Removed columns <em>mantis_user_table</em>:<br /><code>".
						implode(", ",array_keys(self::$a_userTable_newColumns))."</code><br /><br />
					Removed database tables (<em>mantis_plugin_mite_NAME_table</em>):<br /><em>".
						implode(", ",array_keys(self::$a_newDBTables))."</em>
				</div>";
		}
	}//uninstall
	
/****************************************************
 * Callback raised during the initialization of all plugins
 */	
	public function init() {
		
		plugin_event_hook_many(array(
			'EVENT_PLUGIN_INIT' => 'setEventHooks',
			'EVENT_CORE_READY'  => 'initPlugin'
		));
	}//init
	
# MANTIS EVENT CALLBACKS			  
###################################
	
/*****************************************************
 * Handle the EVENT_PLUGIN_INIT callback
 * This is the start point for plugin actions
 */ 
	function setEventHooks() {
  		
  	// more functions defined in core/plugin_api.php 
		plugin_event_hook('EVENT_MENU_ACCOUNT','addConfigLink_userAccount');
    	plugin_event_hook('EVENT_VIEW_BUG_DETAILS','addTimeEntryRow_bugDetail');
    	plugin_event_hook('EVENT_LAYOUT_RESOURCES','insertLayoutResources');
	}//setEventHooks
	
/**
 * Enter description here...
 *
 */
	public function initPlugin() {
		self::initMiteObjects();
	}//initPlugin
	
	
/****************************************************
 * Returns links to external css and javascript files specific for the current page
 */	
	public function insertLayoutResources() {
		
	############	
	# VARS 
	#######	
	/*
	 * @local array
	 */
		$a_filesToInclude = $a_currentResource = array();
	/*
	 * @local strings
	 */	
		$s_markupPattern = $s_codeToInclude = $s_pluginDirPath = $s_currentPage = '';
		
	############	
	# ACTION 
	#######
		$s_currentPage = basename($_SERVER['SCRIPT_NAME']);
		
		if (isset($this->a_pageResources[$s_currentPage])) {
			$a_currentResource = $this->a_pageResources[$s_currentPage];
		}
		elseif (isset($GLOBALS['t_basename']) && isset($GLOBALS['t_action']) &&
				isset($this->a_pageResources[$GLOBALS['t_basename']."/".$GLOBALS['t_action']])) {
					
			$a_currentResource = $this->a_pageResources[$GLOBALS['t_basename']."/".$GLOBALS['t_action']];
		}
		else return;
		
		
		$a_filesToInclude = array_merge_recursive($this->a_generalResources,$a_currentResource);	
		
		$s_pluginDirPath = helper_mantis_url("plugins/".plugin_get_current()."/");
		
		foreach ($a_filesToInclude as $s_fileType => $a_files) {
			
			switch ($s_fileType) {
				case 'css':
					$s_markupPattern = '<link rel="stylesheet" media="screen" type="text/css" href="%s" />';
					break;
				case 'js':
					$s_markupPattern = '<script type="text/javascript" charset="utf-8" src="%s"></script>';
					break;	
			}
			
			foreach ($a_files as $s_file) {
				$s_codeToInclude .= "\t".sprintf($s_markupPattern,
						    					 $s_pluginDirPath.$s_fileType."/".$s_file) . "\n";
			}
		}
		
		return "<!-- resources Mantis2mite start -->\n".
			   $s_codeToInclude . 
			   "<!-- resources Mantis2mite end -->\n";
		
	}//includeFiles
	
  
/*****************************************************
 * Adds a link in the user account preferences to configure the plugin
 */
	function addConfigLink_userAccount($i_eventType,$params) {
  		return "<a href='".plugin_page("user_account")."'><em>mite</em></a>";
  	}//addConfigLink_userAccount
  
  	
/*****************************************************
 * Adds a row for time entries after the 'Status' row in the bug view
 */	
  	function addTimeEntryRow_bugDetail($c_eventName,$i_bugId, $b_advancedView) {
		
  	# don't add the time entry row, if 
  	# - the user can't see time entries of others AND
  	# - has verified connection to a MITE account
  	##################################################	
  		if ((current_user_get_field('access_level') < 
			 plugin_config_get('mite_timetracks_visible_threshold_level')) &&
			!current_user_get_field(Mantis2mitePlugin::DB_FIELD_CONNECT_VERIFIED)) {
				
			return;	
		}
  		
  	/*
  	 * @local
  	 */	
  		$s_output = "
  			<tr ".helper_alternate_class().">
	  			<td class='category'>".
  					lang_get('plugin_mite_time_entries')."
  					<span class='plugin_mite_link_to_settings'>
  						[ <a href='".
  							helper_mantis_url("plugin.php?page=".plugin_get_current()."/user_account")."'>".
  							lang_get('plugin_mite_link_to_settings').
  						"</a> ]
  					</span>
  				</td>
	  			<td colspan='5' class='plugin_mite_time_entries'>";
	  			
	  	if (current_user_get_field(Mantis2mitePlugin::DB_FIELD_CONNECT_VERIFIED)) {
			$s_output .= "		
  					<form id='plugin_mite_frm_new_time_entry'>
  						<div id='plugin_mite_show_new_time_entry_form'>
	  						<a href='#' title='[ctrl-t]' accesskey='t' class='addTimeEntry'>".
		  						lang_get('plugin_mite_show_new_time_entry_form') . "	
	  						</a>
	  					</div>
  						<div id='plugin_mite_new_time_entry'>
	  					</div><!-- plugin_mite_new_time_entry -->
	  					<input type='hidden' name='plugin_mite_current_bug' value='".$i_bugId."' />
  						<input type='hidden' name='plugin_mite_current_project' 
  						   	   value='".bug_get_field($i_bugId,'project_id')."' />
  					</form>";
	  	}
	  	
	  	$s_output .= "
	  		<div id='plugin_mite_time_entries'></div>
	
  			<input type='hidden' id='plugin_mite_current_bug' value='".$i_bugId."' />
	  		<input type='hidden' id='plugin_mite_current_project' 
	  			   value='".bug_get_field($i_bugId,'project_id')."' />
  		
			<div id='plugin_mite_messages'>
				<div>
					<a class='closeBtn' href='#'>".lang_get('plugin_mite_msg_close_message')."</a>
					<p></p>
				</div>
  				<input type='hidden' 
  					   value='".helper_mantis_url("plugins/".plugin_get_current()."/")."' 
  					   id='plugin_mite_path' />
  				<input type='hidden' id='plugin_mite_msg_error_adding_time_entry_fnf' 
  					   value='".lang_get('plugin_mite_msg_error_adding_time_entry_fnf')."' />
  				<input type='hidden' id='plugin_mite_msg_error_adding_time_entry' 
  					   value='".lang_get('plugin_mite_msg_error_adding_time_entry')."' />
  				<input type='hidden' id='plugin_mite_msg_success_adding_time_entry' 
  					   value='".lang_get('plugin_mite_msg_success_adding_time_entry')."' />	   
  				<input type='hidden' id='plugin_mite_msg_error_loading_time_entries_fnf' 
  					   value='".lang_get('plugin_mite_msg_error_loading_time_entries_fnf')."' />
  				<input type='hidden' id='plugin_mite_msg_missing_time_entry_hours' 
  					   value='".lang_get('plugin_mite_msg_missing_time_entry_hours')."' />
  				<input type='hidden' id='plugin_mite_msg_adding_new_time_entry' 
  					   value='".lang_get('plugin_mite_msg_adding_new_time_entry')."' />
				<input type='hidden' id='plugin_mite_loading_time_entries' 
  					   value='".lang_get('plugin_mite_loading_time_entries')."' />
				<input type='hidden' id='plugin_mite_show_new_time_entry_form' 
  					   value='".lang_get('plugin_mite_show_new_time_entry_form')."' />
  				<input type='hidden' id='plugin_mite_confirm_deleting_time_entry' 
  					   value='".lang_get('plugin_mite_confirm_deleting_time_entry')."' />
  				<input type='hidden' id='plugin_mite_msg_error_deleting_time_entry_fnf' 
  					   value='".lang_get('plugin_mite_msg_error_deleting_time_entry_fnf')."' />
  				<input type='hidden' id='plugin_mite_msg_success_deleting_time_entry' 
  					   value='".lang_get('plugin_mite_msg_success_deleting_time_entry')."' />
				<input type='hidden' id='plugin_mite_msg_error_invalid_date' 
  					   value='".lang_get('plugin_mite_msg_error_invalid_date')."' />
  				<input type='hidden' id='plugin_mite_deleting_time_entry' 
  					   value='".lang_get('plugin_mite_deleting_time_entry')."' />  						   
  					   
  			</div><!-- plugin_mite_messages -->
  		</td>
  		</tr>";
  				
	  	
	  	echo $s_output;
  	}//addTimeEntryRow_bugDetail

	
# CLASS METHODS
###################################		
	
/****************************************************
 * only if the user is logged in and has verified the connection to a MITE account
 * inits 
 * - $o_miteUserData: containting MITE data and bindings of the user
 * - $o_miteRemote: neccessary to send requests to the MITE API
 * 
 * @return boolean
 */	
	public static function initMiteObjects() {
		
	# do nothing if the user is not logged in	
		if (!auth_get_current_user_cookie()) return;
		
	# only fill session with user data, if there's a user currently logged in	
		if (current_user_get_field(Mantis2mitePlugin::DB_FIELD_CONNECT_VERIFIED)) {
			
			self::$o_miteUserData = new miteUserData(auth_get_current_user_id());
			self::$o_miteRemote = new miteRemote(self::getDecodedUserValue(self::DB_FIELD_API_KEY),
												 self::getDecodedUserValue(self::DB_FIELD_ACCOUNT_NAME));
			
		}
		return true;
	}//initMiteObjects
  	
  	
  	
/****************************************************
 * returns an object providing methods to communicate with the MITE API 
 *
 * @return object 
 */
	public static function getMiteRemote() {
		
		return self::$o_miteRemote;
	}
	

/****************************************************
 * returns an object to containing all relevant values for Mantis2mite for the current user
 *
 * @return object 
 */
	public static function getMiteUserData() {
		
		return self::$o_miteUserData;
	}//getMiteUserData
	
	
/****************************************************
 * Searches for the user value $s_name and applies decodeValue() on it if available
 *
 * @param string $s_name
 * 
 * @return boolean|string false if the value does not exist or the value 
 */
	private static function getDecodedUserValue($s_name) {
		
	# supress Mantis warning in case the values does not exist	
		$s_value = @current_user_get_field($s_name);

		if($s_name) return self::decodeValue($s_value);
		
		else return false;
		
	}//getDecodedUserValue
  	
  	
/*****************************************************
 * This method is called from a partial.
 * It checks if the partial was called with an AJAX request 
 * and puts this plugin on the plugin stack to access all methods and properties of it
 */	
	static function initPartial() {
		
	# !!! POSSIBLE SCRIPT EXIT !!!
	# if the file calling this methods is not accessed with an AJAX request
	#######################################################################
		if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
			($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest')) {
	
			exit("This script can only be accessed with a valid parameter set and an AJAX request.");
		}
		
		plugin_push_current("Mantis2mite");
	}//initPartial
		
	
/*****************************************************
 * Returns the current or given datetime in a MySQL compatible format
 * within the timezone of the MITE server
 */	
	static function mysqlDate($s_dateTime = null,$b_dateOnly = false) {
		
	/*
	 * @local strings
	 */	
		$s_mysqlDate = $s_dateFormat = '';
		
		$s_localTimezone = date_default_timezone_get();
		date_default_timezone_set(self::TIMEZONE_MITE_SERVER);
		
		$s_dateFormat = 'Y-m-d H:i:s';
		
		if ($b_dateOnly)
			$s_dateFormat = 'Y-m-d';
		
		if ($s_dateTime)
			$s_mysqlDate = date($s_dateFormat,strtotime($s_dateTime));
		else
			$s_mysqlDate = date($s_dateFormat);
		
		date_default_timezone_set($s_localTimezone);
		
		return $s_mysqlDate;
	}//mysqlDate
	
	
/*****************************************************
 * @TODO use strong encryption methods instead of obfuscating the value
 * NOTE: This method actually only obfuscates the value using 'base64_encode' making it harder to directly 
 * read user data out of the database. 
 */	
	static function encodeValue($s_value,$s_salt = null) {
		
		return base64_encode($s_value);
	}//encodeValue
	
	
/*****************************************************
 * @TODO use strong encryption methods instead of obfuscating the value
 * NOTE: This method only deobfuscates the value 
 */	
	static function decodeValue($s_value,$s_salt = null) {
		
		return base64_decode($s_value);
	}//decodeValue
	
	
/*****************************************************
 * Replaces defined placeholders in $s_text by their values
 */	
	static function replacePlaceHolders($s_text,$i_bugId) {
		
	/*
	 * @local string
	 */	
		$s_modifiedText = '';
		
		$s_modifiedText = str_replace("{bug_id}",$i_bugId,$s_text);
		$s_modifiedText = str_replace("{bug_summary}",bug_get_field($i_bugId,'summary'),$s_modifiedText);
		$s_modifiedText = str_replace("{bug_description}",
									  bug_get_text_field($i_bugId,'description'),
									  $s_modifiedText);
		$s_modifiedText = str_replace("{bug_category}",
									  category_full_name( bug_get_field($i_bugId,'category_id'),false),
									  $s_modifiedText);
		$s_modifiedText = str_replace("{project_id}",bug_get_field($i_bugId,'project_id'),$s_modifiedText);
		$s_modifiedText = str_replace("{project_name}",
									  project_get_name(bug_get_field($i_bugId,'project_id')),
									  $s_modifiedText);
		$s_modifiedText = str_replace("{user_id}",current_user_get_field('id'),$s_modifiedText);
		$s_modifiedText = str_replace("{user_name}",current_user_get_field('username'),$s_modifiedText);
		
	# '@L@' is a special placeholder for a '+' since jquery's serialize function
	# replaces all spaces also with a '+'
	############################################################################'
		$s_modifiedText = str_replace("@L@","+",$s_modifiedText);
		
		return $s_modifiedText;
		
	}//replaceTimeEntryNotePlaceHolders
	
}//Mantis2mitePlugin
?>