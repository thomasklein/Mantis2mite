<?php
/* CLASS miteUserData
 * 
 * @description contains all MITE data of the current user
 * @package Mantis2mite
 * @author Thomas Klein <thomas.klein83@gmail.com>
 * @license MIT-license
 * 
 */

class miteUserData {
	
############
# PROPERTIES
#######	
	private $a_projects;
	private $a_services;
	private $a_bindings;
	private $i_userId;
	private $o_pluginController;
	

############
# METHODS
#######	
	
/*****************************************************
 * Constructor automatically populating the user properties
 * 
 * @param Mantis2mitePlugin $o_pluginController
 * @param int $i_userId
 * 
 * @throws error if the class "Mantis2mitePlugin" does not exist
 */	
	public function __construct($o_pluginController,$i_userId) {
		
		if (!(get_class($o_pluginController) == "Mantis2mitePlugin")) {
			throw new Exception('Error: Necessary plugin class "Mantis2mitePlugin" does not exist!');
			exit;
		}
		
		$this->i_userId = $i_userId;
		$this->o_pluginController = $o_pluginController;
		$this->initUserData();
	}//__construct
	
/*****************************************************
 * 
 */	
	private function initUserData() {
		
	# set marker to init the plugin session vars in case there weren't any available 
		if (!@session_get_string('plugin_mite_status_session_vars')) {
			session_set('plugin_mite_status_session_vars','init');
		}

	# initialize the plugin session vars if marked as such 
		if ((session_get_string('plugin_mite_status_session_vars') == 'init') || 
			(session_get_string('plugin_mite_status_session_vars') == 'reinit')) {
			
			$this->initSessionVars();
			session_set('plugin_mite_status_session_vars','isCurrent');
		}
		
	# init instance properties	
		$this->a_projects = 
			$this->decodeAndOrderByValue(session_get('plugin_mite_user_projects'),'name');
		$this->a_services = 
			$this->decodeAndOrderByValue(session_get('plugin_mite_user_services'),'name');

		$this->a_bindings = session_get('plugin_mite_user_bindungs');
	}//initUserData
									 	 			

/*****************************************************
 * Fetches user data from the database and inserts it in the session 
 */
	private function initSessionVars () {
		
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
		$a_fieldNamesMiteRsrc_id = $a_mantisMiteUserData = array();
	/*
	 * @local strings
	 */
		$s_query = '';
		
	############	
	# ACTION 
	#######	
		session_set('plugin_mite_user_projects','');
		session_set('plugin_mite_user_services','');
		session_set('plugin_mite_user_bindungs','');
		
		$a_fieldNamesMiteRsrc_id = array(Mantis2mitePlugin::API_RSRC_P => 'mite_project_id',
									 	 Mantis2mitePlugin::API_RSRC_S => 'mite_service_id');
									 
		foreach (Mantis2mitePlugin::$a_rsrcTypes as $s_type) {
			
		# get MITE projects/services of the user
			$s_query = "SELECT id,name,".$a_fieldNamesMiteRsrc_id[$s_type].", mite_updated_at 
						FROM ".plugin_table(Mantis2mitePlugin::DB_TABLE_PS).
					   " WHERE user_id = ".$this->i_userId." AND type = '".$s_type."'";
			
			$r_result = db_query_bound($s_query);
			
			$a_miteUserData = array();
			
			if (db_num_rows($r_result) > 0) {
				while ($a_row = db_fetch_array($r_result)) {
					$a_miteUserData[$a_row[$a_fieldNamesMiteRsrc_id[$s_type]]] = $a_row;
				}
			}
			session_set('plugin_mite_user_'.$s_type,$a_miteUserData);
		}
		
	# get MITE - MANTIS bindings of the user
	###########################################
		$s_query = "SELECT type, mite_project_id, mite_service_id, mantis_project_id FROM ".
						plugin_table(Mantis2mitePlugin::DB_TABLE_PSMP).
				   " WHERE user_id=".$this->i_userId;
			
		$r_result = db_query_bound($s_query);
		
		if (db_num_rows($r_result) > 0) {
			
			while ($a_row = db_fetch_array($r_result)) {
				
				$s_type = $a_row['type'];
				$i_dataId = $a_row[Mantis2mitePlugin::$a_fieldNamesMiteRsrcTypes[$s_type]];
				$a_userBindings[$s_type][$i_dataId][] = $a_row['mantis_project_id'];
			}
		}
		
		session_set('plugin_mite_user_bindungs',$a_userBindings);
		
	}//initSessionVars
	
	
/*****************************************************
 * Decodes each property $s_fieldName in $a_values and returns an array ordered by 
 * the property $s_fieldName of each value
 * 
 * E.g: $a_values = array(1 => array('name' => 'B'), 2 => array('name' => 'A'))
 * whereas $s_fieldName = 'name'
 * the function returns
 * 	array(2 => array('name' => 'A'), 1 => array('name' => 'B'))
 * 
 * @param array multidimensional array 
 * contains a list of entries; key is the id and value are the properties of this entry
 * @param string property to compare agains
 * 
 * @return array
 */	
	private function decodeAndOrderByValue($a_values,$s_fieldName) {
		
	/*
	 * @local array
	 */	
		$a_orderedValues = array();
		
		foreach ($a_values as $i_id => $a_props) {
			
			if (isset($a_props[$s_fieldName]))
				$a_props[$s_fieldName] = $this->o_pluginController->decodeValue($a_props[$s_fieldName]);
			
			$a_orderedValues[$i_id] = $a_props;
		}
		
		uasort($a_orderedValues,array("miteUserData", "cmpByName"));
		
		return $a_orderedValues;
	}//decodeAndOrderByValue
	
	
/*****************************************************
 * Custom function to compare the property 'name' of two arrays
 */	
	function cmpByName($a, $b) {
		
		return strcmp($a["name"], $b["name"]);
	}//cmp
	
	
############
# GETTER	
	public function getBindings() {return $this->a_bindings;}
	

/*****************************************************
 * Returns all bindings ordered by Mantis projects as an array
 * 
 * E.g. array([1] => array('services' => array(SERVICE_1,SERVICE_1), 
 *						   'projects' => array(PROJECT_1,PROJECT_2)),
 * 		array([2] => array('services' => array(SERVICE_1,SERVICE_3),
 *						   'projects' => array(PROJECT_1,PROJECT_2)))
 *
 * @return array
 */
	public function getBindingsByMantisProject() {
		
	# return an empty array if the user has currently no bindings	
		if (empty($this->a_bindings)) return array();
	/*
	 * @local array
	 */	
		$a_bindingsByMantisProject = array();
		
		foreach ($this->a_bindings as $s_type => $a_miteRsrc_ids) {
			
			foreach ($a_miteRsrc_ids as $i_rsrc_id => $a_mantisProject_ids) {
				
				foreach ($a_mantisProject_ids as $i_mantisProject_id) {
					
					$a_bindingsByMantisProject[$i_mantisProject_id][$s_type][$i_rsrc_id] = $i_rsrc_id; 
				}
			}
		}
		
		return $a_bindingsByMantisProject;
	}//getBindingsByMantisProject

	
/*****************************************************
 * Return all bindings for the given id of the mantis project as an array
 * 
 * E.g. array('services' => array(SERVICE_1,SERVICE_1), 
 *			  'projects' => array(PROJECT_1,PROJECT_2))
 *
 */
	public function getBindingsForMantisProject($i_mantis_project_id) {

	/**
	 * @local arrays
	 */
		$a_projectBindings = $a_bindingsMantisProjects = array();
		
		$a_bindingsMantisProjects = $this->getBindingsByMantisProject();
		
	# get users MITE projects and services binded to $i_mantis_project_id if any 
	    if (isset($a_bindingsMantisProjects[$i_mantis_project_id])) {
	    	$a_projectBindings = $a_bindingsMantisProjects[$i_mantis_project_id];
	    }
	    
	    return $a_projectBindings;
	}//getBindingsForMantisProject
	

/*****************************************************
 * Return all resources binded to the given Mantis project id as an array
 *
 * @param int $i_mantis_project_id
 * 
 * @return array
 */	
	public function getBindedRsrcesForMantisProject($i_mantis_project_id) {
		
	/**
	 * @local arrays
	 */
		$a_bindedRsres = $a_projectBindings = $a_rsrc = array();
		
		$a_projectBindings = $this->getBindingsForMantisProject($i_mantis_project_id);
	    
	# build select box entries from binded resources    
	    foreach ($a_projectBindings as $s_type => $a_rsrc_ids) {
			
	    	foreach ($a_rsrc_ids as $i_rsrc_id) {
	    		
	    		switch ($s_type) {
	    			
	    			case Mantis2mitePlugin::API_RSRC_P:
	    				$a_rsrc = $this->a_projects[$i_rsrc_id];
	    				break;
	    				
	    			case Mantis2mitePlugin::API_RSRC_S:
						$a_rsrc = $this->a_services[$i_rsrc_id];
	    				break;
	    		}
	    		
	    		$a_bindedRsres[$s_type][$i_rsrc_id] = $a_rsrc;
	    		uasort($a_bindedRsres[$s_type],array("miteUserData", "cmpByName"));
	    	}
		}
		
		return $a_bindedRsres;
	}//getBindedRsrcesForMantisProject
	

/*****************************************************
 * Return all resources not binded to the given Mantis project id as an array
 *
 * @param int $i_mantis_project_id
 * 
 * @return array
 */
	public function getUnbindedRsrcesForMantisProject($i_mantis_project_id) {
		
	/**
	 * @local arrays
	 */
		$a_unbindedRsrces = $a_unbindedRsrc_ids = $a_projectBindings = $a_rsrc = array();
		
		$a_projectBindings = $this->getBindingsForMantisProject($i_mantis_project_id);
		
		
		$a_unbindedRsrc_ids[Mantis2mitePlugin::API_RSRC_P] = array_keys($this->a_projects);
		$a_unbindedRsrc_ids[Mantis2mitePlugin::API_RSRC_S] = array_keys($this->a_services);
		
		if (isset($a_projectBindings[Mantis2mitePlugin::API_RSRC_P])) {
			
			$a_unbindedRsrc_ids[Mantis2mitePlugin::API_RSRC_P] = 
	    		array_diff(array_keys($this->a_projects),
	    			 	   array_keys($a_projectBindings[Mantis2mitePlugin::API_RSRC_P]));
		}
		
		if (isset($a_projectBindings[Mantis2mitePlugin::API_RSRC_S])) {
			
			$a_unbindedRsrc_ids[Mantis2mitePlugin::API_RSRC_S] = 
	    		array_diff(array_keys($this->a_services),
	    			 	   array_keys($a_projectBindings[Mantis2mitePlugin::API_RSRC_S]));
		}
				
		foreach ($a_unbindedRsrc_ids as $s_type => $a_rsrces_id) {
			
    		foreach ($a_rsrces_id as $i_rsrc_id) { 
	    		
	    		switch ($s_type) {
	    			
	    			case Mantis2mitePlugin::API_RSRC_P:
	    				$a_rsrc = $this->a_projects[$i_rsrc_id];
	    				break;
	    				
	    			case Mantis2mitePlugin::API_RSRC_S:
						$a_rsrc = $this->a_services[$i_rsrc_id];
	    				break;
	    		}
	    		$a_unbindedRsrces[$s_type][$i_rsrc_id] = $a_rsrc;
    		}
    	}
    	
		return $a_unbindedRsrces;
	}//getUnbindedRsrcesForMantisProject
	
	
	public function getProjects() {return $this->a_projects;}
	public function getServices() {return $this->a_services;}	
}//miteUserData


?>