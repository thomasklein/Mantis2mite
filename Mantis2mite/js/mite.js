$(window).load(function(){
	
// show elements hidden for users with deactivated javascript 
	$('.plugin_mite_hide_if_no_javascript').each(function(){
		$(this).show();
	});
	
	if ($('#plugin_mite_messages').length)
		MITE.init();
	else {
		console.error('Error in "mite.js": Could not setup object "MITE" due to missing document object ' +
					  '"#plugin_mite_messages".');
	}
});

// ##########################
// Create a new NAMESPACE for plugin functions
// and return an object with methods to access it.
// This way the global namespace stays clean.
// ##########################
var MITE = function() {

//############	
// private VARS
//#######	
	var $o_messageBox = $o_messageBoxMsg = $o_miteLastUpdated = null,
		a_messages = a_states = {},
		s_pathPartialHandler = s_pathImages = '',
		b_initialized = false;
		 	
//############	
// private METHODS
//#######

/*********************************************************
 * Saves often used jquery selectors in vars to avoid multiple function calls
 */ 
	var initVars = function () {
		
	// general vars	
		s_pathPartialHandler = $("#plugin_mite_path").val() + 'partials/handlePartial.php';
		s_pathImages = $("#plugin_mite_path").val() + 'images/' 
		
	// selectors	
		$o_messageBox	   = $('#plugin_mite_messages div:first');
		$o_messageBoxMsg   = $('#plugin_mite_messages p:first');
		$o_messageBoxClose = $('#plugin_mite_messages a:first');
		$o_miteLastUpdated = $("#plugin_mite_last_updated");
		
	// messages
	// fnf = file not found	
		
	// general
		a_messages['databaseError'] = $("#plugin_mite_msg_database_error").val();
			
		
	// user page	
		a_messages['connectionVerified'] = $("#plugin_mite_txt_connection_verified").val();
		a_messages['connectionUnverified'] = $("#plugin_mite_txt_connection_unverified").val();
		a_messages['checkingAccountData'] = $("#plugin_mite_txt_check_account_data_active").val();
		a_messages['successVerification'] = $("#plugin_mite_msg_success_verification").val();
		a_messages['errorVerification'] = $("#plugin_mite_msg_error_verification").val();
		a_messages['successUpdatingAccountData'] = $("#plugin_mite_msg_success_updating_account_data").val();
		a_messages['errorUpdatingAccountData'] = $("#plugin_mite_txt_error_updating_account_data").val();
		a_messages['missingAccountData'] = $("#plugin_mite_msg_missing_account_data").val();
		a_messages['successSavingBindings'] = $("#plugin_mite_msg_success_saving_bindings").val();
		a_messages['errorSavingBindings'] = $("#plugin_mite_msg_error_saving_bindings").val();
		a_messages['confirmChangingApiKey'] = $("#plugin_mite_msg_confirm_changing_api_key").val();
		a_messages['confirmDisconnectingAccount'] = $("#plugin_mite_msg_confirm_disconnecting_account").val();
		a_messages['confirmChangingAccount'] = $("#plugin_mite_msg_confirm_changing_account").val();
		a_messages['successDisconnectingAccount'] = $("#plugin_mite_msg_success_disconnecting_account").val();
		a_messages['disconnectingAccount'] = $("#plugin_mite_disconnecting_account_data_active").val();
		a_messages['errorDisconnectingAccount'] = $("#plugin_mite_msg_error_disconnecting_account").val();
		a_messages['loadingBindingsArea'] = $("#plugin_mite_txt_loading_user_bindings").val();
		a_messages['errorLoadingBindingArea'] = $("#plugin_mite_msg_error_loading_binding_area").val();
		a_messages['savingBindings'] = $("#plugin_mite_save_bindings_active").val();
		
	// time entries	
		a_messages['loadingTimeEntries'] = $("#plugin_mite_loading_time_entries").val();
		a_messages['errorAddingTimeEntry_fnf'] = $("#plugin_mite_msg_error_adding_time_entry_fnf").val();
		a_messages['errorAddingTimeEntry'] = $("#plugin_mite_msg_error_adding_time_entry").val();
		a_messages['successAddingTimeEntry'] = $("#plugin_mite_msg_success_adding_time_entry").val();
		a_messages['errorLoadingTimeEntries_fnf'] = $("#plugin_mite_msg_error_loading_time_entries_fnf").val();
		a_messages['missingTimeEntryHours'] = $("#plugin_mite_msg_missing_time_entry_hours").val();
		a_messages['addingNewTimeEntry'] = $("#plugin_mite_msg_adding_new_time_entry").val();
		a_messages['showNewEntryForm'] = $("#plugin_mite_show_new_time_entry_form").val();
		a_messages['confirmDeletingTimeEntry'] = $("#plugin_mite_confirm_deleting_time_entry").val();
		a_messages['errorDeletingTimeEntry_fnf'] = $("#plugin_mite_msg_error_deleting_time_entry_fnf").val();
		a_messages['deletingTimeEntry'] = $("#plugin_mite_deleting_time_entry").val();
		a_messages['successDeletingTimeEntry'] = $("#plugin_mite_msg_success_deleting_time_entry").val();
		a_messages['errorDeletingTimeEntry'] = $("#plugin_mite_msg_error_deleting_time_entry").val();
		a_messages['invalidDate'] = $("#plugin_mite_msg_error_invalid_date").val();
		
	// states
		a_states['connectionActive'] = ($o_miteLastUpdated.length) ? true : false;	
	}

	
//############	
// public METHODS
//#######	
	return {
		
	/*********************************************************	
	* Returns the plugin message 's_key', if existent
	*/	
		getMsg : function(s_key) {
		
			if(s_key in a_messages) {
				if (a_messages[s_key] != undefined)
					return a_messages[s_key];
				else
					console.error('Error in MITE.getMsg(): ' +
								  'Message with key "' + s_key + '" was not found on this page!');
					return;
			}
			else {
				console.error('Error in MITE.getMsg(): Message with key "' + s_key + '" is not set!');
				return;
			}  
		},//getMsg
		
	/*********************************************************	
	* Adds a new message or resets an existing message
	*/	
		setMsg : function(s_key,m_value) {
			
			a_messages[s_key] = m_value;
		},//setMsg
		
		
	/*********************************************************
	* Returns s_msg with an prepended loading indicator 
	*/	
		addIndicator : function (s_msg) {
			
			return s_msg + ' <img src="' + s_pathImages + 'indicator.gif" /> ';
		},//addIndicator
			
	
	/*********************************************************	
	* Returns the plugin state 's_key', if existent
	*/	
		getState : function(s_key) {
			if(s_key in a_states) {
				if (a_states[s_key] != undefined)
					return a_states[s_key];
				else
					console.error('Error in MITE.getState(): ' +
								  'State with key "' + s_key + '" was not found on this page!');
			}
			else {
				console.error('State with key "' + s_key + '" is not set!');
				return;
			}
		},//getState
		
	/*********************************************************	
	* Adds a new state or resets an existing state
	*/	
		setState : function(s_key,m_value) {
			
			a_states[s_key] = m_value;
		},//setState
		
	/*********************************************************	
	* Returns the full path with file extension to the given partial name 
	*/	
		makePartialPath : function(s_partialName,s_contentType) {
			
			var s_partialPath = s_pathPartialHandler + '?partial=' + s_partialName;
			
			if (s_contentType)
				s_partialPath += '&contentType=' + s_contentType;
			
			return s_partialPath;
		},//makePartialPath
		
	/*****************************************************
	 * Shows a fixed message box on top of the screen with the content 
	 * given as param s_msg. The box gets sticky if the user hovers it
	 */	
		showMsg : function (s_type,s_msg) {
			
			if (!$o_messageBox.length) {
				console.error("Error in MITE.getMsg(): Could not find the MITE message container!");
				return;
			}
			
			var s_cssClas = 'successMsg',
				r_timeOut = null;
			
		// in case the message box is currently active
		// prepare it for the new message 	
			if ($o_messageBoxMsg.html() != '') {
				
				$o_messageBox.stop()
							 .css({"opacity" : "1"});	
				$o_messageBoxClose.css({"visibility" : "hidden"});
			}
			
			$o_messageBoxClose.click(function(e) {
				$o_messageBox.hide();
				$o_messageBoxClose.css({"visibility" : "hidden"});
				e.preventDefault();
				return false;	
			});			  
			
			if (s_type == 'error') {
				s_cssClas = 'errorMsg';
			}
			
			$o_messageBox.removeClass();	
			$o_messageBox.addClass(s_cssClas);
			$o_messageBoxMsg.html(s_msg);
			$o_messageBoxClose.prependTo($o_messageBox);
			$o_messageBox.show();
			
			r_timeOut = window.setTimeout(function(){
				$o_messageBox.fadeOut(2000,function() {
					
					$o_messageBoxMsg.html('');
					
				});
			},1500);
			
		// stop the fade out effect if the user enters the messages box with the mouse
		// and show the close button	
			$o_messageBox.mouseenter(function(){
				$o_messageBoxClose.css({"visibility" : "visible"});
				$o_messageBox.stop()
							 .css({"opacity" : "1","border-width" : "3px","margin-top" : "13px"});
				window.clearTimeout(r_timeOut);
			});
			
			$o_messageBox.mouseleave(function(){
				$o_messageBox.css({"border-width" : "1px", "margin-top" : "15px"});
			});
		},//showMsg
		
		
	/*****************************************************
	 * Performs various actions depending on the child nodes of the given xml object 'xmlData'
	 */		
		checkXMLData : function (xmlData,s_msgBase,fn_success,fn_error) {
		
			if ($(xmlData).find('messages').length) {
				MITE.showMsg("success",MITE.getMsg('success' + s_msgBase));
				
				if (fn_success) fn_success();
			}
			// otherwise there was an error in the executed partial 	
			else {
				
			// if the xml message contains error nodes there was an expected error	
				if ($(xmlData).find('error').length) {
					
					var s_errors = '';
						
					$(xmlData).find('error').each(function() {
						s_errors += "<li>" + this.textContent + "</li>";
					});
					
					s_errors = "<small><ul>" + s_errors + "</ul></small>";	
					MITE.showMsg("error",MITE.getMsg('error'+s_msgBase) + s_errors);
				}
			// otherwise there was an unexcpected error 
			// most probably a raised database error by MANTIS  	
				else {
					MITE.showMsg("error",
								 MITE.getMsg('error'+s_msgBase) + "<br />" + 
								 "<small><ul><li>Your time entry was successfully deleted from <em>mite</em> "+
								 "but a database error occurred! " +
								 "Check the Javascript error console for details.</li></ul></small>");
					
					MITE.printToConsole('applicationError',
										{file   : MITE.makePartialPath('time_entry_process'),
										 details   : xmlData.childNodes[1].textContent});
				}
				
				if (fn_error) fn_error();
			}
		
		},//checkXMLData
		
		
	/*****************************************************
	 * Checks if the given date is in the format yyyy-mm-dd
	 */	
		checkForValidDate : function (s_date) {
		
			var a_dateParts = s_date.split ("-")
				i_day = i_month = i_year = 0,
				o_date = null;
		
			if (a_dateParts.length != 3) return false;
			
			i_year = a_dateParts[0];
			i_month = a_dateParts[1];
			i_day = a_dateParts[2];
		
			o_date = new Date (a_dateParts[0],a_dateParts[1],a_dateParts[2]);
			
			if (i_year != o_date.getFullYear()) return false;
			if (i_month != o_date.getMonth()) return false;
			if (i_day != o_date.getDate()) return false;
		
		   return true;
		},//checkForValidDate
		
	/*****************************************************
	 * Prints a message to the browser javasript console (if supported)
	 */	
		printToConsole : function (s_msgType, o_params) {
		
			if (s_msgType == 'failedAjaxRequest') {
				console.error("No valid xml response was returned. Check '" + 
							  o_params['file'] + "' for existence and errors!");
				console.info("Details: " + o_params['details']);
			}
			else if (s_msgType == 'applicationError') {
				console.error("An application error occurred. Check '" + 
							  o_params['file'] + "' for existence and errors!");
				console.log("Details: " + o_params['details']);
			}
		},//printToConsole
	
		
	/*********************************************************
	* Init the namespace vars
	*/
		init : function () {
			initVars();
			b_initialized = true;
		},//init
		
	
	/*********************************************************
	* Returns true if all necessary vars were initialized
	*/	
		isInitialized : function () {return b_initialized;}
		
	};//END of MITE return values
	
}();//execute function instantly to return the object in the global namespace