$(window).load(function(){
	
	if (!MITE.isInitialized()) {
		console.error('Error in "mite_user_account.js": Javascript object "MITE" was not initalized! ' +
					  'Check "mite.js" for errors.');
		return;
	}
	MITE_UA.init();
});	
	
// ##########################
// Create a new NAMESPACE for all actions on the user account page
// and return an object with methods to access it.
// ##########################	
var MITE_UA = function() {

//############
// private VARS
//#######	
	var $o_btnCheckAccountData = $o_userBindings = $o_linkChangeAccountName =
		$o_fieldAccountName = $o_btnDisconnectAccountData = 
		$o_connectionStatus = $o_frmAccountData = $o_fieldApiKey = null,
		b_initialized = false;
		 	
//############	
// private METHODS
//#######
	
/*****************************************************
 * Store often uses selectors and plugin messages
 */	
	var initVars = function() {
		
		$o_btnCheckAccountData 	    = $("#plugin_mite_check_account_data"),
		$o_userBindings		   	    = $('#plugin_mite_user_bindings'),
		$o_linkChangeAccountName    = $('#plugin_mite_change_account_name'),
		$o_linkChangeApiKey		    = $('#plugin_mite_change_api_key'),
		$o_fieldAccountName		    = $('#plugin_mite_account_name'),
		$o_btnDisconnectAccountData = $('#plugin_mite_disconnect_account_data'),
		$o_connectionStatus			= $("#plugin_mite_connection_status"),
		$o_frmAccountData			= $("#frm_mite_account_data"),
		$o_fieldApiKey		  	    = $('#plugin_mite_account_api_key');
	}//initVars
	
	
/*****************************************************
 * Set event handler for the initial state of the page
 */	
	var setInitialEventHandler = function () {
	
	 // Prevents the new time entry form from submitting
	 // This is only for Safarie and IE
	 	$o_frmAccountData.submit(function(e) {
			
			e.preventDefault();
			startAccountVerification();
			return false;
		});
			
	 // Click handler calling function to process the form data 
		$o_btnCheckAccountData.click(function(e) {
			
			e.preventDefault();
			startAccountVerification();
			return false;
		});
	}//setInitialEventHandler
	
	
/*****************************************************
 * Performing 3 step process before connecting to the mite api
 * Step 1: Check the account name field and api key field for content
 * Step 2: Check, if old account data is still available and needs to be cleared
 * Step 3: Connect to the mite api via partial; see processAccountData() for details on that  
 */	
	var startAccountVerification = function() {
				
	// show error message in case required fields weren't filled out 		
		if (($o_fieldAccountName.val() == '') || 
			 ($o_fieldApiKey.val() == '')) {
			
			$o_fieldAccountName.focus().select();
			MITE.showMsg("error",MITE.getMsg('missingAccountData'));
			return false;
		}
		
	// use case: the user wants to UPDATE the data of the existing MITE account connection
	// action: fetch data from the current MITE account and compare it with the values already saved
		if ($o_fieldAccountName.attr("readonly") && $o_fieldApiKey.attr("readonly")) {
			
			processAccountData();	
		}
		
	// use case: the user wants CHANGE his API-KEY
	// action: try to fetch data from the current MITE account with the new api key
		else if ($o_fieldAccountName.attr("readonly")) {
		
			processAccountData();
		}
		
	// use case: the user CHANGED his ACCOUNT DATA
	// actions: clear the old data of the last MITE connection get new data from a new MITE account
		else if ($('.plugin_mite_positive_connection_status').length) {
		
			disconnectAccount(function(){processAccountData();});
		}
		
	// use case: user connects for the FIRST TIME to a MITE account 
	// or a former MITE was disconnected and a new is now connected
	// action: fetch all data from the new MITE account
		else processAccountData();
		
		return true;
	}//startAccountVerification
	
	
/*****************************************************
 * Sends ajax request to verify the MITE account and 
 * retrieve current projects and services from the MITE API
 */	
	var processAccountData = function() {
	
		var s_oldText = $o_btnCheckAccountData.html();
		
		$o_btnCheckAccountData.attr('disabled', true)
							  .html(MITE.addIndicator(MITE.getMsg('checkingAccountData')));
		
		$.ajax({
			type: "POST",
			dataType: "xml",
			url: MITE.makePartialPath('user_account_connect_and_update','xml'),
			data: $o_frmAccountData.serialize(),
			error: function(XMLHttpRequest, textStatus) {
				
				MITE.showMsg('error',MITE.getMsg('errorUpdatingAccountData'));
				MITE.printToConsole(
					'failedAjaxRequest',
					{file   : MITE.makePartialPath('user_account_connection_update'),
					 details: textStatus}
				);
			},
			success: function(xmlData) {
				
				if ($(xmlData).find('messages').length) {
					
					$o_connectionStatus.removeClass();
					
					if (MITE.getState('connectionActive'))
						MITE.showMsg('success',MITE.getMsg('successUpdatingAccountData'));
					else {
						MITE.showMsg('success',MITE.getMsg('successVerification'));
						MITE.setState('connectionActive',true);
					}
						
					$o_connectionStatus.html(MITE.getMsg('connectionVerified'))
									   .addClass('plugin_mite_positive_connection_status');
					
					
					$o_frmAccountData.children('.config_fields').removeClass('mite_user_account_inactive')
																.addClass('mite_user_account_active');
					
					
				// set field for the account name 'readonly'
					$o_fieldAccountName.attr("readonly","readonly").removeClass().addClass('readonly');
					
					$o_fieldApiKey.clone()
								  .attr({readonly:'readonly',type:'password'})
								  .removeClass().addClass('readonly')
								  .replaceAll($o_fieldApiKey);
				// refresh selector	
					$o_fieldApiKey = $('#plugin_mite_account_api_key'); 
					
				// set field for the API-Key 'readonly'	
					$o_fieldApiKey.attr("readonly","readonly").removeClass().addClass('readonly');
					
				// show button to disconnect the account	
					$o_btnDisconnectAccountData.show();
					
				// show link to change the account	
					$o_linkChangeAccountName.show();
					$o_linkChangeApiKey.show();
					
				// resfresh selection since the dom has changed 
					$("#plugin_mite_last_updated").html($(xmlData).find('messages').attr('datetimestamp'));
					initBindingArea();
				}
				else if ($(xmlData).find('errors').length) {
					
					$o_connectionStatus.removeClass();
					
					var s_errors = '';
					
					$(xmlData).find('error').each(function() {
						s_errors += "<li>" + this.textContent + "</li>";
					});
					
					s_errors = "<small><ul>" + s_errors + "</ul></small>";
					
					$o_connectionStatus.html(MITE.getMsg('connectionUnverified'))
									   .addClass('plugin_mite_negative_connection_status');
					MITE.showMsg("error",MITE.getMsg('errorVerification') + s_errors);
				}
				else {
					MITE.showMsg("error",MITE.getMsg('databaseError'));
					MITE.printToConsole('applicationError',
										{file   : MITE.makePartialPath('user_account_connect_and_update'),
										 details   : xmlData.childNodes[1].textContent});
				
				}
    		},
    		complete: function() {
    			$o_btnCheckAccountData.html(s_oldText).attr('disabled', false);
    		}
  		});
	}
	
/*****************************************************
 * Function to retrieve user bindings in HTML and placing it
 * right under the connection box with a jQuery fadeIn effect
 */	
	var initBindingArea = function() {
		
	// if the mite account was not verified yet, do not display anything	
		if (!MITE.getState('connectionActive'))
			return;
		
		$o_userBindings.html(MITE.addIndicator(MITE.getMsg('loadingBindingsArea')));
		
	// displays the loading info message
		$o_userBindings.show();
		
		$.ajax({
			dataType: "text",
			url: MITE.makePartialPath('user_account_bindings_display','text'),
			error: function(XMLHttpRequest, textStatus) {
				
				MITE.showMsg('error',MITE.getMsg('errorLoadingBindingArea'));
				MITE.printToConsole(
					'failedAjaxRequest',
					{file   : MITE.makePartialPath('user_account_bindings_display'),
					 details: textStatus}
				);
			},
			success: function(data) {
				initBindingEvents(data);
    		},
    		complete: function() {
    			$o_userBindings.fadeIn("slow");
    		}
  		});
	}
	
/*****************************************************
* Sends ajax request to delete all MITE data of the user from Mantis
*/	
	var disconnectAccount = function(fn_onSuccess) {
		
	// save the initial button text
		var s_txtBtnDisonnect = $o_btnDisconnectAccountData.html();
		
		$o_btnDisconnectAccountData.attr('disabled', true)
          							   .html(MITE.addIndicator(MITE.getMsg('disconnectingAccount')));
          	
       	$.ajax({
			dataType: "xml",
			url: MITE.makePartialPath('user_account_disconnect','xml'),
			error: function(XMLHttpRequest, textStatus) {
				
				MITE.showMsg("error",MITE.getMsg('errorDisconnectingAccount'));
				MITE.printToConsole(
					'failedAjaxRequest',
					{file   : MITE.makePartialPath('user_account_connection_unbind'),
					 details: textStatus}
				);
			},
			success: function(data) {
				
				$o_btnDisconnectAccountData.attr('disabled', false)
          							   .html(s_txtBtnDisonnect);
				
				fn_onSuccess();
    		}
  		});
	}
	
	
/*****************************************************
 * Bind click events for the user bindings area 
 * gets called only if the user IS CONNECTED to a MITE account!
 */	
	var initBindingEvents = function(data) {
		
		$o_userBindings.hide();
		$o_userBindings.html(data);
	
		var	$o_btnSaveBindings = $("#plugin_mite_save_bindings"),
			$o_frmBindings 	   = $("#frm_mite_mantis_bindings");
		
	// unbind former events in case the bindingArea 
	// gets initialized several times on one page visit 	
		$o_linkChangeAccountName.unbind();
		$o_linkChangeApiKey.unbind();
		$o_btnSaveBindings.unbind();
		$o_btnDisconnectAccountData.unbind();
		
	// show links to change the account data fields	
		$o_linkChangeAccountName.show();
		$o_linkChangeApiKey.show();
	
	/*****************************************************
	 * Prevents the new time entry form from submitting
	 * This is only for Safarie and IE
	 */
		$o_frmBindings.submit(function(e) {
			
			e.preventDefault();
			proccessBindings();
			return false;
		});
		
			
	/*****************************************************
	 * Click handler calling function to process the form data 
	 */
		$o_btnSaveBindings.click(function(e) {
			
			e.preventDefault();
			proccessBindings();
			return false;
		});
	
	
	/*****************************************************
	 * Sends ajax request to save all bindings of MITE projects/services to MANTIS projects
	 */	
		var proccessBindings = function () {
		
			var s_oldText = $o_btnSaveBindings.html();
				
			$o_btnSaveBindings.attr('disabled', true)
							  .html(MITE.addIndicator($("#plugin_mite_save_bindings_active").val()));
			
			$.ajax({
				type: "POST",
				dataType: "xml",
				url: MITE.makePartialPath('user_account_bindings_update','xml'),
				data: $o_frmBindings.serialize(),
				error: function(XMLHttpRequest, textStatus, errorThrown) {
					
					MITE.showMsg('error',MITE.getMsg('errorSavingBindings'));
					MITE.printToConsole(
						'failedAjaxRequest',
						{file   : MITE.makePartialPath('user_account_bindings_update'),
						 details: textStatus}
					);
				},
				success: function(data) {
					MITE.showMsg('success',MITE.getMsg('successSavingBindings'));
	    		},
	    		complete: function() {
	    		// re-init the whole binding area to preserve the reset-button function	
	    			initBindingArea();
	    		}
	  		});
		}
			
	/*****************************************************
 	* Reactivates the field for the account name AND the api key
 	* This is the start point for the use case of changing the account
 	*/	
		$o_linkChangeAccountName.click(function(e) { 
			
			if (confirm(MITE.getMsg('confirmChangingAccount'))) {
				
			// hide all user bindings - visual alert to show him, what happens
			// if he commits the formular 	
				$o_userBindings.fadeOut("slow");
				
			// hide 'change' links	
				$o_linkChangeAccountName.hide();
				$o_linkChangeApiKey.hide();
				
			// hide button to disconnect the account	
				$o_btnDisconnectAccountData.hide();
				
				$o_fieldAccountName.attr("readonly","")
								   .removeClass()
								   .focus()
								   .select();
								   
				$o_fieldApiKey.clone()
							  .attr({readonly:'',type:'text'})
							  .removeClass()
							  .replaceAll($o_fieldApiKey)
							  .val('');//delete
			// refresh selector	
				$o_fieldApiKey = $('#plugin_mite_account_api_key');
			}
			
			e.preventDefault();							   
			return false;				   
		});
	
	
	/*****************************************************
	* Reactivates the field for the API key
	* This is the start point for the use case of changing api key
	*/
		$o_linkChangeApiKey.click(function(e) {
		
			if (confirm(MITE.getMsg('confirmChangingApiKey'))) {
					
					$o_fieldApiKey.clone()
								  .attr({readonly:'',type:'text'})
								  .removeClass()
								  .replaceAll($o_fieldApiKey)
								  .val('')//delete
								  .focus()
								  .select();
				// refresh selector	
					$o_fieldApiKey = $('#plugin_mite_account_api_key');
					
				// hide 'change' links	
					$o_linkChangeAccountName.hide();
					$o_linkChangeApiKey.hide();
				}
				
				e.preventDefault();							   
				return false;
		});
	
	
	/*****************************************************
	* Calls disconnectAccount() if the user confirms the warning message
	*/	
		$o_btnDisconnectAccountData.click(function(e) {
			
			if (confirm(MITE.getMsg('confirmDisconnectingAccount'))) {
	           	
	           	var s_txtBtnDisonnect = $o_btnDisconnectAccountData.html();
	           	
	           	disconnectAccount(function() {
	           		MITE.showMsg("success",MITE.getMsg('successDisconnectingAccount'));
					
				// empty user data fields	
	    			$o_fieldAccountName.val('').attr({readonly:''}).removeClass();
	    			$o_fieldApiKey.clone()
								  .attr({readonly:'',type:'text'})
								  .removeClass()
								  .replaceAll($o_fieldApiKey)
								  .val('');//delete
					
				// refresh selector	
					$o_fieldApiKey = $('#plugin_mite_account_api_key');
				
				// hide the whole user bindings area
					$o_userBindings.hide();
				
				// hide links to change the field values
					$o_linkChangeAccountName.hide();
					$o_linkChangeApiKey.hide();
					 
				// reset connection status
					$o_connectionStatus.html(MITE.getMsg('connectionUnverified'))
									   .addClass('plugin_mite_negative_connection_status');
								   
					$o_frmAccountData.children('.config_fields').removeClass('mite_user_account_inactive')
																.addClass('mite_user_account_inactive');
								   
					$o_btnDisconnectAccountData.hide();
					$o_btnDisconnectAccountData.attr('disabled', false)
											   .html(s_txtBtnDisonnect);//reset button text
				});
		  	}
		  	e.preventDefault();							   
			return false;
		});
	}
	
//############	
// public METHODS
//#######	
	return {
	
	/*********************************************************
	* Init the namespace vars
	*/
		init : function () {
			initVars();
			setInitialEventHandler();
			initBindingArea();
			b_initialized = true;
			
		},//init
	
	/*********************************************************
	* Returns true if all necessary vars were initialized
	*/	
		isInitialized : function () {return b_initialized;}
	
	};//END of MITE_UA return values
	
}();//execute function instantly to return the object in the global namespace  