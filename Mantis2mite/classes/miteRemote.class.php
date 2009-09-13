<?php
/* CLASS miteRemote
 * 
 * @description provides methods to communicate with the MITE API based on the cURL library
 * @package manits.plugins
 * @author Thomas Klein <thomas.klein83@gmail.com>
 * @license MIT License
 * 
 */
class miteRemote {
	
############
# PROPERTIES
#######	
	private $a_curlOptHeader;
	private $s_miteAccountUrl;
	private $a_requestErrors;
	private $o_responseXml;
	
	
############
# CONSTANTS
#######	
	const MITE_DOMAIN = 'mite.yo.lk';
	const MITE_REMOTE_EXCEPTION_RSRC_NOT_FOUND = 100;
	const MITE_REMOTE_EXCEPTION_UNPARSABLE_XML = 101;
	const MITE_REMOTE_EXCEPTION_WRONG_REQUEST_TYPE = 102;
	const MITE_REMOTE_EXCEPTION_MISSING_ACCOUNT_DATA = 103;
	
############	
# METHODS
#######
	
	
/*****************************************************
 * Constructor
 *
 * @param string $s_apiKey
 * @param string $s_accountUrl
 * 
 * @throws MITE_REMOTE_EXCEPTION_MISSING_ACCOUNT_DATA
 */
	public function __construct($s_apiKey, $s_accountUrl) {
		
		if (!$s_apiKey || !$s_accountUrl) {
			throw new Exception('Error: Api key or account URL were missing!',
								MITE_REMOTE_EXCEPTION_MISSING_ACCOUNT_DATA);
			exit;
		}
		
		$this->a_curlOptHeader = array('Content-Type: application/xml',
									   'X-MiteApiKey: '.urlencode($s_apiKey));
		
	// build SSL-URL	
		$this->s_miteAccountUrl = "https://".urlencode($s_accountUrl).".".self::MITE_DOMAIN;
	}//__construct
	
	
/*****************************************************
 * Sends a request to mite and stores possible response data in $this->o_responseXml
 *
 * @param string $s_requestType 'post', 'get', 'delete', 'put'
 * @param string $s_rsrcName can be 'time_entries', 'projects', 'services'
 * @param string $s_requestData data for post request
 * 
 * @throws 
 * - MITE_REMOTE_EXCEPTION_UNPARSABLE_XML
 * - MITE_REMOTE_EXCEPTION_WRONG_REQUEST_TYPE
 * - MITE_REMOTE_EXCEPTION_RSRC_NOT_FOUND
 * 
 * @return boolean false - in case of an error; errors can be obtained by calling 'getErrors'
 * 				   true othewise
 * 		    
 */	
	public function sendRequest($s_requestType, $s_rsrcName, $s_requestData = '') {
		
	############	
	# VARS 
	#######
	/*
	 * @local objects and resources
	 */	
		$r_ch = $o_responseXml = null;
	/*
	 * @local arrays
	 */	
		$a_curlInfo = array();
	/*
	 * @local strings
	 */
		$s_fullUrl = $s_response = ''; 

	############	
	# ACTION 
	#######		
		
	# build resource URI	
		$s_fullUrl = $this->s_miteAccountUrl."/".$s_rsrcName;
		
	# init cURL with general options
		$r_ch = curl_init($s_fullUrl);
		curl_setopt($r_ch, CURLOPT_HEADER, 0);
		curl_setopt($r_ch, CURLOPT_SSL_VERIFYHOST, 0);
	 	curl_setopt($r_ch, CURLOPT_SSL_VERIFYPEER, 0);
	 	curl_setopt($r_ch, CURLOPT_HTTPHEADER,$this->a_curlOptHeader);
	 	curl_setopt($r_ch, CURLOPT_FOLLOWLOCATION, true); 
		curl_setopt($r_ch, CURLOPT_RETURNTRANSFER, true);
	 	 
		switch ($s_requestType) {

			case 'post': 
				
				curl_setopt($r_ch, CURLOPT_POST, true);
				curl_setopt ($r_ch, CURLOPT_POSTFIELDS, $s_requestData);
				break;
				
			case 'get':
				// nothing to do here
				break;
				
			case 'delete':
				curl_setopt($r_ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
				break;
				
			// @TODO	
			case 'put': 
				break;
				
			default:
				throw new Exception('Error: Passed request type '. $s_requestType . ' not available!',
									self::MITE_REMOTE_EXCEPTION_WRONG_REQUEST_TYPE);
				
		}
		
		$s_response = curl_exec($r_ch);
		
	# EXIT on error: unexpected error
		if (curl_errno($r_ch)) {
			$this->addError(curl_error($r_ch));
			return false;
		}
		
		$a_curlInfo = curl_getinfo($r_ch);
		curl_close($r_ch);
		
	# EXIT on error: access to resource denied
		if ($s_response == 'HTTP Basic: Access denied') {
			$this->addError($s_response . ' at ' . $s_fullUrl);
			return false;
		}
		
	# EXIT on error: no http response code was returned	
		if (empty($a_curlInfo['http_code'])) {
			$this->addError('No HTTP code was returned at ' . $s_fullUrl);
			return false;
		}
		
		switch ($a_curlInfo['http_code']) {
			
			# created - new resource created
			case '201':
				
				$this->o_responseXml = simplexml_load_string($s_response);
				break;
				
			# OK - return entries or  
			case '200':
				
				if (trim($s_response) != '') {
					$this->o_responseXml = @simplexml_load_string($s_response);
					if (!$this->o_responseXml) {
						throw new Exception('Could not parse resource '.$s_fullUrl,
											self::MITE_REMOTE_EXCEPTION_UNPARSABLE_XML);
					}
				}
				break;
			# Not found				
			case '404':
				throw new Exception('Resource '.$s_fullUrl.' does not exist!',
									self::MITE_REMOTE_EXCEPTION_RSRC_NOT_FOUND);
			
			default:
			# EXIT on error: an unexpected http code was returned
				$this->addError($a_curlInfo['http_code'] . ' at ' . $s_fullUrl);
				return false;	
		}
		
		return true;
	}//sendRequest
	
	
/*****************************************************
 * Returns response created by the last curl exectuion as php simple xml object
 *
 * @return simple xml object
 */
	public function getReponseXML() {
		
		return $this->o_responseXml;
	}//getReponseXML
	
	
/*****************************************************
 * Adds an error to the list of errors
 *
 * @param string $s_error
 */
	private function addError($s_error) {
		
		$this->a_requestErrors[] = $s_error;
	}//addError
	
	
/*****************************************************
 * Returns errors in $s_format
 *
 * @param string $s_format currently supported is only 'XML'
 * 
 * @return string error message with all errors combined
 */	
	public function getErrors($s_format = 'XML') {
		
		$s_errorMsg = '';
		
		foreach ($this->a_requestErrors as $s_error) {

			$s_errorMsg .= "<error>'$s_response' at $s_error</error>";
		}
		return '<errors class="miteRemote">'.$s_errorMsg.'</errors>';
		
	}//getXMLErrorMsg
	
}//miteRemote
?>