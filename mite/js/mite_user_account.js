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
		$o_linkChangeApiKey = $o_fieldAccountName = $o_btnDisconnectAccountData = 
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
		$o_linkChangeApiKey 	    = $('#plugin_mite_change_api_key'),
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
			processAccountData();
			return false;
		});
		
			
	 // Click handler calling function to process the form data 
		$o_btnCheckAccountData.click(function(e) {
			
			e.preventDefault();
			processAccountData();
			return false;
		});
	}//setInitialEventHandler
	
/*****************************************************
 * Sends ajax request to verify the MITE account and 
 * retrieve current projects and services from the MITE API
 */	
	var processAccountData = function() {
	
		var s_oldText = $o_btnCheckAccountData.html();
		
	// show error message in case required fields weren't filled out 		
		if (($o_fieldAccountName.val() == '') || 
			 ($o_fieldApiKey.val() == '')) {
			
			$o_fieldAccountName.focus().select();
			MITE.showMsg("error",MITE.getMsg('missingAccountData'));
			return false;
		}
		$o_btnCheckAccountData.attr('disabled', true)
							  .html(MITE.addIndicator(MITE.getMsg('checkingAccountData')));
		
		$.ajax({
			type: "POST",
			dataType: "xml",
			url: MITE.getPathToPartial('user_account_connect_and_update'),
			data: $o_frmAccountData.serialize(),
			error: function(XMLHttpRequest, textStatus) {
				
				MITE.showMsg('error',MITE.getMsg('errorUpdatingAccountData'));
				MITE.printToConsole(
					'failedAjaxRequest',
					{file   : MITE.getPathToPartial('user_account_connection_update'),
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
										{file   : MITE.getPathToPartial('user_account_connect_and_update'),
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
			url: MITE.getPathToPartial('user_account_bindings_display'),
			error: function(XMLHttpRequest, textStatus) {
				
				MITE.showMsg('error',MITE.getMsg('errorLoadingBindingArea'));
				MITE.printToConsole(
					'failedAjaxRequest',
					{file   : MITE.getPathToPartial('user_account_bindings_display'),
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
 * Bind click events for the user bindings area 
 */	
	var initBindingEvents = function(data) {
		
		$o_userBindings.hide();
		$o_userBindings.html(data);
	
		var	$o_btnSaveBindings = $("#plugin_mite_save_bindings"),
			$o_frmBindings 	   = $("#frm_mite_mantis_bindings");
		
	// unbind former events in case the bindingArea 
	// gets initialized several times on one page visit 	
		$o_linkChangeApiKey.unbind();
		$o_linkChangeAccountName.unbind();
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
				url: MITE.getPathToPartial('user_account_bindings_update'),
				data: $o_frmBindings.serialize(),
				error: function(XMLHttpRequest, textStatus, errorThrown) {
					
					MITE.showMsg('error',MITE.getMsg('errorSavingBindings'));
					MITE.printToConsole(
						'failedAjaxRequest',
						{file   : MITE.getPathToPartial('user_account_bindings_update'),
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
 	* Reactivates the field for the account name
 	*/	
		$o_linkChangeAccountName.click(function(e) { 
			$o_fieldAccountName.attr("readonly","")
							   .removeClass()
							   .focus()
							   .select();
			e.preventDefault();							   
			return false;				   
		});
	
	/*****************************************************
 	* Reactivates the field for the api key
 	*/	
		$o_linkChangeApiKey.click(function(e) { 
			$o_fieldApiKey.clone()
						  .attr({readonly:'',type:'text'})
						  .removeClass()
						  .replaceAll($o_fieldApiKey)
						  .focus()
						  .select()
						  .val('');//delete
		// refresh selector	
			$o_fieldApiKey = $('#plugin_mite_account_api_key');  
		    e.preventDefault();							   
			return false;
		});

	/*****************************************************
	* Sends ajax request to verify the MITE account and 
	* retrieve current projects and services from the MITE API
	*/	
		$o_btnDisconnectAccountData.click(function(e) {
			
		// save the initial button text
			var s_txtBtnDisonnect = $o_btnDisconnectAccountData.html();
			
			if (confirm(MITE.getMsg('confirmDisconnectingAccount'))) {
	           	
	           	$o_btnDisconnectAccountData.attr('disabled', true)
	           							   .html(MITE.addIndicator(MITE.getMsg('disconnectingAccount')));
	           	
	        	$.ajax({
					dataType: "xml",
					url: MITE.getPathToPartial('user_account_disconnect'),
					error: function(XMLHttpRequest, textStatus) {
						
						MITE.showMsg("error",MITE.getMsg('errorDisconnectingAccount'));
						MITE.printToConsole(
							'failedAjaxRequest',
							{file   : MITE.getPathToPartial('user_account_connection_unbind'),
							 details: textStatus}
						);
					},
					success: function(data) {
						$o_btnDisconnectAccountData.attr('disabled', true);
						
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
		    		}
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