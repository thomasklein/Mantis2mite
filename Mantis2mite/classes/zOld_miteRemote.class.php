<?php
/* CLASS miteRemote
 * 
 * @description provides methods to communicate with the MITE API
 * @package mite.plugins
 * @author Thomas Klein <thomas.klein83@gmail.com>
 * @license MIT License
 * 
 */
class miteRemote {
	
############
# PROPERTIES
#######	
	private $s_header;
	private $i_port;
	private $s_miteAccountUrl;
	private $s_sslPrefix;
	
	private static $instance = null;//necessary to act as singleton
	
############
# CONSTANTS
#######	
	const MITE_DOMAIN = 'mite.yo.lk';
	const REQUEST_TIMEOUT = 5;
	const EXCEPTION_RSRC_NOT_FOUND = 100;
	const EXCEPTION_UNPARSABLE_XML = 101;
	const EXCEPTION_WRONG_REQUEST_TYPE = 102;
	const EXCEPTION_MISSING_ACCOUNT_DATA = 103;
	const EXCEPTION_NO_ACCESS = 104;
	const EXCEPTION_TIMED_OUT = 105;
	const EXCEPTION_NO_SERVER_RESPONSE = 106;
	const EXCEPTION_UNEXPECTED_RESPONSE = 107;
	const EXCEPTION_CONNECTION_REFUSED = 108;
	
############	
# METHODS
#######
	
# make silent to act as singleton
	private function __clone() {}
	private function __construct() {}
	
/*****************************************************
 * Returns always the same object instance
 * 
 * @return object miteRemote
 */
	public static function getInstance() {
 
		if (self::$instance === null) self::$instance = new self;
		
		return self::$instance;
	}//getInstance
	
	
/*****************************************************
 * Inits remote with mite account data and builds general request header
 *
 * @param string $s_apiKey
 * @param string $s_accountUrl
 * 
 * @throws EXCEPTION_MISSING_ACCOUNT_DATA
 */	
	public function init($s_apiKey, $s_accountUrl,$b_useSSLSocket = true) {
		
		if (!$s_apiKey || !$s_accountUrl) {
			throw new Exception('Error: Api key or account URL were missing!',
								self::EXCEPTION_MISSING_ACCOUNT_DATA);
			exit;
		}
		
		$this->i_port = 80;
		$this->s_sslPrefix = '';
		$this->s_miteAccountUrl = urlencode($s_accountUrl).".".self::MITE_DOMAIN;
		
		if ($b_useSSLSocket) {
			$this->i_port = 443;
			$this->s_sslPrefix = "ssl://";
		}
		
		$this->s_header = "Host: ".$this->s_miteAccountUrl."\r\n".
						  "X-MiteApiKey: ".$s_apiKey."\r\n".
                   		  "Content-type: application/xml\r\n";
		
	}//init
	
	
/*****************************************************
 * Sends a request to mite and stores possible response data in $this->o_responseXml
 *
 * @param string $s_httpMethod 'post', 'get', 'delete', 'put'
 * @param string $s_rsrcName can be 'time_entries', 'projects', 'services'
 * @param string $s_requestData data for POST or PUT request
 * 
 * @throws 
 * - EXCEPTION_UNPARSABLE_XML
 * - EXCEPTION_WRONG_REQUEST_TYPE
 * - EXCEPTION_CONNECTION_REFUSED
 * - EXCEPTION_RSRC_NOT_FOUND
 * - EXCEPTION_NO_ACCESS
 * - EXCEPTION_TIMED_OUT
 * - EXCEPTION_NO_SERVER_RESPONSE
 * - EXCEPTION_UNEXPECTED_RESPONSE
 * 
 * @return boolean true - if request 
 * 		    
 */	
	public function sendRequest($s_httpMethod, $s_rsrcName, $s_requestData = '') {
		
	############	
	# VARS 
	#######
	/*
	 * @local objects and resources
	 */	
		$o_context = $o_responseXml = $r_fs = null;
	/*
	 * @local arrays
	 */	
		$a_lastPhpError = array();
	/*
	 * @local strings
	 */
		$s_fullUrl = $s_response = ''; 
		

	############	
	# ACTION 
	#######
		$s_httpMethod = strtoupper($s_httpMethod);
				
	# check 	
		switch ($s_httpMethod) {

			case 'POST': 
				$a_httpOptions['content'] = $s_requestData;
				break;
				
			case 'GET':
			case 'DELETE':
				//nothing to do here...
				break;
				
			// @TODO	
			case 'PUT': 
				break;
				
			default:
				throw new Exception('Error: Passed request type '. $s_httpMethod . 
									' not available!',self::EXCEPTION_WRONG_REQUEST_TYPE);
		}
		
		$request = 
			"$s_httpMethod $this->uri HTTP/1.1\r\n".
			$this->s_header.
		  	"Content-Length: ".strlen($this->data)."\r\n".
			"Connection: close\n\n".
			"$this->data\n";
		
		if ($this->b_useSSLSocket) {
			$r_fs = @fsockopen("ssl://".$this->s_miteAccountUrl,	
							  $this->i_port,
							  &$i_errno,
							  &$s_errstr,
							  self::REQUEST_TIMEOUT);
		}
		else {
			$r_fs = @fsockopen($this->s_miteAccountUrl,
							   $this->i_port,
							   &$i_errno,
							   &$s_errstr,
							   self::REQUEST_TIMEOUT);
		}
		
	# if the connection failed - distinguish error cases	
		if (!$r_fs) {
			
		# get last error message
			$a_lastPhpError = error_get_last();
			
		# if the connection couldn't get established; e.g. port problems 	
			if ($i_errno == 61) {
				throw new Exception('Connection refused: '.
									'<em>'.$a_lastPhpError['message'].'</em>',
									self::EXCEPTION_CONNECTION_REFUSED);
			}
			
		# error 404: URL not found 	
			else if (strpos($a_lastPhpError['message'],'404 Not Found')) {
				
				throw new Exception('Status code 404: '.
									'Resource '.$s_fullUrl.' does not exist!',
									self::EXCEPTION_RSRC_NOT_FOUND);
				
			}
		# error 401: access denied	
			else if (strpos($a_lastPhpError['message'],'401 Authorization')) {
				
				throw new Exception('Status code 401: '.
									'You have no access to '.$s_fullUrl.'. Please recheck the provided '.
									'mite account data in your preferences. Maybe somehting has changed '.
									'since your last visit?',
									self::EXCEPTION_RSRC_NOT_FOUND);
			}
		# error 411: lenth required
			else if (strpos($a_lastPhpError['message'],'411 Length Required')) {
				
				throw new Exception('Status code 411: '.
									'Could create/update resource on '.$s_fullUrl.' due to '.
									'missing content.',
									self::EXCEPTION_RSRC_NOT_FOUND);
				
			}
			
		# error 500: unexpected condition found by the server 
			else if (strpos($a_lastPhpError['message'],'500')) {
				throw new Exception('Status code 500: '.
									'The server encountered an unexpected condition '.
									'when trying to handle the request to '.$s_fullUrl.'.<br />'.
									'<em>'.$a_lastPhpError['message'].'</em>');
			}
			
		# also note unexpected error messages
			else {
				if ($s_httpMethod == 'POST') {
						
				}
				else {
					throw new Exception(
						'There was a problem when trying to access '.$s_fullUrl.'.<br />'.
						'<em>'.$a_lastPhpError['message'].'</em><br />'.
						'http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html '.
						'contains a list of all http status codes and their meanings.',
						self::EXCEPTION_NO_ACCESS);
				}
			}
		}	
		
		else {
			$a_requestInfo = stream_get_meta_data($r_fs);
		    
			if ($a_requestInfo['timed_out']) {
		        throw new Exception('The connection timed out when trying to reach '.$s_fullUrl.'.',
									self::EXCEPTION_TIMED_OUT);
		    }
			
		# get response document	
			$s_response = @stream_get_contents($r_fs);
			
			if ($s_response === FALSE) {
				$a_lastPhpError = error_get_last();
				
				throw new Exception("Problem with contents of ".$s_fullUrl."<br />".
									"<em>".$a_lastPhpError['message']."</em>");
			}
			
			@fclose($r_fs);
			
		# check the returned status code 
			switch ($a_requestInfo['wrapper_data'][10]) {
				
			# Created - new resource created; returns the new resource as response	
				case 'Status: 201':
			# OK - if the resource was deleted returns nothing 
			#	 - if a ressource was requested returns the ressource(-s) as response
				case 'Status: 200':
					
				# nothing more to expect if a resource was deleted	
					if ($s_httpMethod == "delete")
						break;
					
					if (trim($s_response) == '') {
						throw new Exception('Empty server response document for '.$s_fullUrl,
											self::EXCEPTION_NO_SERVER_RESPONSE);
					}
					
					$o_responseXml = @simplexml_load_string($s_response);
	
					if (!$o_responseXml) {
						
						$a_lastPhpError = error_get_last();
						
						throw new Exception('Could not parse resource '.$s_fullUrl.'<br />'.
											'<em>'.$a_lastPhpError['message'].'</em>',
											self::EXCEPTION_UNPARSABLE_XML);
					}
					break;				
			# error: an unexpected http status code was returned	
				default:
					throw new Exception('The response for handling resource '.$s_fullUrl.
										'with method "'.$s_httpMethod.'" was not expected: '.
										'<em>'.$a_requestInfo['wrapper_data'][10].'</em>.',
									self::EXCEPTION_UNEXPECTED_RESPONSE);
			}
		}
		
		//causes error: "Allowed memory size of 33554432 bytes exhausted (tried to allocate 69251463 bytes"
		//echo "<p>".__FILE__."-".__LINE__.": ".self::$s_pathCreatedResource."</p>\n";
		
		return $o_responseXml;
	}//sendRequest
	
}//miteRemote
?>