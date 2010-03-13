<?php
/* CLASS mitePartialsController
 * 
 * @description providing methods to include contents of files 
 * and giving them access to plugin functions and the Mantis environment.
 * Partials are located in the 'partials' directory of this plugin
 * @package Mantis2mite
 * @author Thomas Klein <thomas.klein83@gmail.com>
 * @license MIT-license
 *
 */

class mitePartialsController {
	
############
# CONSTANTS
#######	
	const PATH_TO_PARTIALS = '';
	const EXCEPTION_FILE_NOT_FOUND = 100;
	const EXCEPTION_INVALID_CONTENT_TYPE = 101;
	const CONTENT_TYPE_TEXT = 'text';
	const CONTENT_TYPE_XML = 'xml';

############
# PROPERTIES
#######	
	private static $a_validContentTypes = array(self::CONTENT_TYPE_TEXT,self::CONTENT_TYPE_XML);
	private static $instance;//necessary to act as singleton
	
############	
# METHODS
#######
	
# make silent to act as singleton
	private function __clone() {}
	private function __construct() {}
	
/*****************************************************
 * Returns always the same object instance
 * 
 * @return object mite
 */
	public static function getInstance() {
 
		if (self::$instance === null) self::$instance = new self;
		
		return self::$instance;
	}//getInstance
		
	
/*****************************************************
 * Includes the specified partial and sets a file header depending on the content type  
 *
 * @param string name of the partial file
 * @param string content type to return
 * 
 * @throws EXCEPTION_FILE_NOT_FOUND
 * 		   EXCEPTION_INVALID_CONTENT_TYPE
 */
	public function includePartial($s_partialName,$s_contentType) {
		
	/*
	 * @local string
	 */
		$s_fullIncludePath = self::PATH_TO_PARTIALS.$s_partialName.".php";
		
	# EXCEPTION on file not found
		if (FALSE === file_exists($s_fullIncludePath)) {
		
			throw new Exception('Error: Could not find file !'.$s_fullIncludePath,
								self::EXCEPTION_FILE_NOT_FOUND);
		}

	# prepare content
		switch ($s_contentType) {
			
			case self::CONTENT_TYPE_TEXT: 
			//nothing to do here...
				break;
				
			case self::CONTENT_TYPE_XML:
				
			# prepare to return an xml message
				header('Cache-Control: must-revalidate, pre-check=0, no-store, no-cache, max-age=0, 
						post-check=0');
				header('Content-Type: text/xml; charset=utf-8');
				echo '<?xml version="1.0" encoding="UTF-8"?>';
				
			break;
			
			default:
			# EXCEPTION invalid content type	
				throw new Exception('Error: Content type "'.$s_contentType.'" not supported!',
								self::EXCEPTION_INVALID_CONTENT_TYPE);
				break;
		}
		
	# include partial	
		require_once($s_fullIncludePath);
	
	}//includePartial
	
}//mitePartialsController

?>