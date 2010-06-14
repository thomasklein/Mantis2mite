<?php
# EXIT on access not as AJAX request
	if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
		($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest')) {
		
		exit("This script can only be accessed with an AJAX request.");
	}
	
# EXIT on missing GET param	
	if (!isset($_GET['partial']) || !isset($_GET['contentType'])) {
		exit ("Error in ".__FILE__.": Missing GET param 'partial' or 'contentType'.");
	}
	
# reload mantis environment - otherwise database access is not possible in the partials
# Note: can't move this into 'mitePartialsController' due to PHP warning messages
#######################################################################################
	require_once( '../../../core.php' );//
	plugin_push_current("Mantis2mite");
	
	$o_pluginController = $g_plugin_cache['Mantis2mite'];
	
	try {
	/*
	 * @local object mitePartialsController
	 */	
		$o_pluginPartialsController = $o_pluginController->getMitePartialsController();
		$o_pluginPartialsController->includePartial(urldecode($_GET['partial']),
									  				urldecode($_GET['contentType']));
	}
	catch (Exception $e) {
		echo $e->getMessage();
	}
?>