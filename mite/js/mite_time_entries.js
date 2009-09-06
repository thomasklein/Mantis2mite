$(window).load(function(){
	
	if (!MITE.isInitialized()) {
		console.error('Error in "mite_user_account.js": Javascript object "MITE" was not initalized! ' +
					  'Check "mite.js" for errors.');
		return;
	}
	MITE_TE.init();
});	
	

// ##########################
// Create a new NAMESPACE for all actions on a bug view page
// and return an object with methods to access it.
// ##########################	
var MITE_TE = function() {	
	
//############
// private VARS
//#######	
	var $o_frmNewTimeEntry = $o_newTimeEntry = $o_linkShowNewTimeEntryFrm = $o_timeEntries = null,
		b_initialized = false;	
	
//############	
// private METHODS
//#######	
	
/*****************************************************
 * Store often uses selectors and plugin messages
 */	
	var initVars = function() {
	
		$o_frmNewTimeEntry = $('#plugin_mite_frm_new_time_entry'),
		$o_newTimeEntry = $('#plugin_mite_new_time_entry'),
		$o_linkShowNewTimeEntryFrm = $('#plugin_mite_link_show_new_time_entry_form'),
		$o_timeEntries = $('#plugin_mite_time_entries');
	}
	
	
/*****************************************************
 * Set event handler for the initial state of the page
 */	
	var setInitialEventHandler = function () {
	
		$o_linkShowNewTimeEntryFrm.click(function(e) {
			
			$o_linkShowNewTimeEntryFrm.css({"color" : "#888888"});
			$o_newTimeEntry.slideDown("slow",
									  function(){$('#plugin_mite_date_new_time_entry').focus().select();});
			e.preventDefault();
			return false;
		});
	}	
	
/*****************************************************
 * Performs an AJAX request to load an input mask for a new time entry
 * and paste it into the bug overview
 */	
	var loadNewTimeEntryPartial = function() {
			
		$.ajax({
			type: "GET",
			dataType: "text",
			data: {bug_id:$('#plugin_mite_current_bug').val(),
				   project_id:$('#plugin_mite_current_project').val()},
			url: MITE.getPathToPartial('time_entry_form_fields_display'),
			error: function(XMLHttpRequest, textStatus, errorThrown) {
				MITE.showMsg('error',MITE.getMsg('errorLoadingTimeEntries_fnf'));
				MITE.printToConsole(
					'failedAjaxRequest',
					{file   : MITE.getPathToPartial('time_entry_form_fields_display'),
					 details: textStatus}
				);
			},
			success: function(data) {
				$o_newTimeEntry.html(data);
	   		},
	   		complete: function() {
	   			initNewTimeEntryPartialHandler();
	   		}
		});
	}//loadNewTimeEntryPartial
	
/*****************************************************
 * Performs an AJAX request to load all time entries for the current bug
 * and paste it into the bug overview
 */	
	var loadTimeEntriesPartial = function() {
		
		$o_timeEntries.html(MITE.addIndicator(MITE.getMsg('loadingTimeEntries')));
		
		$.ajax({
			type: "GET",
			dataType: "text",
			data: {bug_id:$('#plugin_mite_current_bug').val(),
				   project_id:$('#plugin_mite_current_project').val()},
			url: MITE.getPathToPartial('time_entries_display'),
			error: function(XMLHttpRequest, textStatus, errorThrown) {
				MITE.showMsg('error',MITE.getMsg('errorLoadingTimeEntries_fnf'));
				MITE.printToConsole(
					'failedAjaxRequest',
					{file   : MITE.getPathToPartial('time_entries_display'),
					 details: textStatus}
				);
			},
			success: function(data) {
				$o_timeEntries.html(data);
	   		},
	   		complete: function() {
	   			$('#plugin_mite_loading_time_entries').hide();
	   			initTimeEntriesPartialHandler();
	   		}
		});
	}//loadTimeEntriesPartial
	
/*****************************************************
 * Init all DOM elements necessary to handle time entries 
 */		
	var initTimeEntriesPartialHandler = function() {
		
		var $o_btnDeleteEntry 			 	  = $('.plugin_mite_delete_time_entry'),
			$o_linksShowNote	  			  = $('.plugin_mite_time_entry_show_note'),
			$o_linksShowOtherUsersTimeEntries = $('.plugin_mite_time_show_entries_other_user'),
			$o_otherUsersTimeEntries  	 	  = $('.plugin_mite_time_entries_other_user'),
			s_initialTextDeleteTimeEntry 	  = $o_btnDeleteEntry.val(), 
			i_idMantis = i_idMite = 0;
		
		$o_otherUsersTimeEntries.css('display','none');
		
		$o_linksShowOtherUsersTimeEntries.each(function(i){
			
			$(this).click(function(e) {
				
				$o_otherUsersTimeEntries.eq(i).toggle(0);
				e.preventDefault();
				return false;
			});
		
		});
		
		$o_linksShowNote.each(function(){
			$(this).click(function(e) {
				e.preventDefault();
				return false;
			});
		
		});
		
	/*****************************************************
	 * Adds click event handler to each 'delete time entry' link.
	 * After clicking the link, an AJAX request to partial 'time_entry_process' is performed
	 * which deletes the entry from the MANTIS database and from MITE
	 */	
		$o_btnDeleteEntry.each(function(){
			
			$(this).click(function(e) {
				
				if (confirm(MITE.getMsg('confirmDeletingTimeEntry'))) {
					
					$(this).attr('disabled', true)
							.html(MITE.addIndicator(MITE.getMsg('deletingTimeEntry')));
					
					$.ajax({
						type: "POST",
						dataType: "xml",
						data: {action:'deleteEntry',
					   		   data:$(this).parent("form").serialize()},
						url: MITE.getPathToPartial('time_entry_process'),
						error: function(XMLHttpRequest, textStatus, errorThrown) {
							MITE.showMsg('error',MITE.getMsg('errorDeletingTimeEntry_fnf'));
							MITE.printToConsole(
								'failedAjaxRequest',
								{file   : MITE.getPathToPartial('time_entry_process'),
								 details: textStatus}
							);
						},
						success: function(xmlData) {
							MITE.checkXMLData(xmlData,
											  'DeletingTimeEntry',
											  function(){loadTimeEntriesPartial();});
			    		},
			    		complete: function() {
			    			$o_btnDeleteEntry.attr('disabled', true)
			    							 .html(s_initialTextDeleteTimeEntry);
			    		}
			  		});
				}
			
				e.preventDefault();
				return false;
			});
		});
	}//initTimeEntriesPartialHandler
	
	
/*****************************************************
 * Called after the partial for all time entries was loaded
 * to bind the newly added DOM elements 
 */	
	var initNewTimeEntryPartialHandler = function () {
		
		var	$o_btnAddNewTimeEntry 	  = $('#plugin_mite_add_new_time_entry'),
			$o_linkCancelAdding 	  = $('#plugin_mite_cancel_adding_time_entry'),
			$o_fieldDateNewTimeEntry  = $('#plugin_mite_date_new_time_entry'),
			$o_fieldHoursNewTimeEntry = $('#plugin_mite_hours_new_time_entry'),
			$o_fieldNoteNewTimeEntry  = $('#plugin_mite_note_new_time_entry'),
			$o_sbProjectNewTimeEntry  = $('#plugin_mite_projects_new_time_entry'),
			$o_sbServiceNewTimeEntry  = $('#plugin_mite_services_new_time_entry'),
			$o_linkUserInputHelper	  = $('.plugin_mite_user_input_helper'),
			$o_txtUserInputHelper	  = $('.plugin_mite_user_input_helper_text'),
			s_initialDate 			  = $o_fieldDateNewTimeEntry.val();
			s_initialNote 			  = $o_fieldNoteNewTimeEntry.val();
		
		
		$o_linkCancelAdding.click(function(e) {
			$o_newTimeEntry.slideUp("slow",
				function(){
					$o_linkShowNewTimeEntryFrm.css({"color" : "#5E7EDB"});
					$o_fieldHoursNewTimeEntry.val('0:00');
					$o_sbProjectNewTimeEntry.find('option:first').attr('selected','selected');
					$o_sbServiceNewTimeEntry.find('option:first').attr('selected','selected');
					$o_fieldDateNewTimeEntry.val(s_initialDate);
					$o_fieldNoteNewTimeEntry.val(s_initialNote);
				});
			e.preventDefault();
			return false;
		});
		
		
	/*****************************************************
	 * Prevents the new time entry form from submitting
	 * This is only for Safarie and IE
	 */
		$o_frmNewTimeEntry.submit(function(e) {
			
			e.preventDefault();
			processNewTimeEntryFormData();
			return false;
		});
		
		
	/*****************************************************
	 * Click handler calling function to process the form data 
	 */	
		$o_btnAddNewTimeEntry.click(function(e) {
			
			e.preventDefault();
			processNewTimeEntryFormData();
			return false;
		});
		
		
	/*****************************************************
	 * Performs AJAX request to partial 'time_entry_process' which sends
	 * a new time entry (based on the formular params) to MITE 
	 * and add it to the MANTIS database 
	 */		
		var processNewTimeEntryFormData = function() {
		
			var s_oldText = $o_btnAddNewTimeEntry.html();
			
			
	// check if the given date is valid		
		if (($o_fieldDateNewTimeEntry.val() == '') || 
			(!MITE.checkForValidDate($o_fieldDateNewTimeEntry.val()))) {
				
				MITE.showMsg("error",MITE.getMsg('invalidDate'));
				$o_fieldDateNewTimeEntry.focus().select();
				return false;
			}	
			
			
		// check for a given time 	
			if ($o_fieldHoursNewTimeEntry.val() == '0:00') {
				
				MITE.showMsg("error",MITE.getMsg('missingTimeEntryHours'));
				$o_fieldHoursNewTimeEntry.focus().select();
				return false;
			}
			
			$o_btnAddNewTimeEntry.attr('disabled', true)
								 .html(MITE.addIndicator(MITE.getMsg('addingNewTimeEntry')));
			
		// replace all '+' signs with an alias since jquery's serialize function 
		// will replace all spaces with a '+', so there's later on no way to tell
		// if the '+' was a space or not
			
			$o_fieldNoteNewTimeEntry.val($o_fieldNoteNewTimeEntry.val().replace(/\+/g,"@L@"));
			
			$.ajax({
				type: "POST",
				dataType: "xml",
				data: {action:'addEntry',
					   data:$o_frmNewTimeEntry.serialize()},
				url: MITE.getPathToPartial('time_entry_process'),
				error: function(XMLHttpRequest, textStatus, errorThrown) {
					MITE.showMsg('error',MITE.getMsg('errorAddingTimeEntry_fnf'));
					MITE.printToConsole(
						'failedAjaxRequest',
						{file   : MITE.getPathToPartial('time_entry_process'),
						 details: textStatus}
					);
				},
				success: function(xmlData) {
					
					MITE.checkXMLData(xmlData,'AddingTimeEntry',function(){
						$o_fieldHoursNewTimeEntry.val('0:00');
						$o_sbProjectNewTimeEntry.find('option:first').attr('selected','selected');
						$o_sbServiceNewTimeEntry.find('option:first').attr('selected','selected');
						$o_fieldDateNewTimeEntry.val(s_initialDate);
						$o_fieldNoteNewTimeEntry.val(s_initialNote);
						loadTimeEntriesPartial();
					});
				
	    		},
	    		complete: function() {
	    			$o_btnAddNewTimeEntry.html(s_oldText).attr('disabled', false);
	    			
	    			if ($o_sbProjectNewTimeEntry.length)
	    				$o_sbProjectNewTimeEntry.focus();
	    				
	    			else if ($o_sbServiceNewTimeEntry.length)
	    				$o_sbServiceNewTimeEntry.focus();
	    		}
	  		});
		}	
		
		
	/*****************************************************
	 * Shows the helper text when hovering a helper link 
	 */
		$o_linkUserInputHelper.each(function(i){
			
			$(this).mouseenter(function(){
				
				$(this).hide();
				$o_txtUserInputHelper[i].style.display = 'inline';
			});
		});
		
	/*****************************************************
	 * Hides the helper text when leaving it   
	 */	
		$o_txtUserInputHelper.each(function(i){
		
			$(this).mouseleave(function(){
				
				$o_linkUserInputHelper[i].style.display = 'inline';
				$(this).hide();
			});
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
			loadTimeEntriesPartial();
			
		// show only the form for a new time entry if the user has connected his MITE account	
			if ($o_frmNewTimeEntry.length) loadNewTimeEntryPartial();
			
			b_initialized = true;
			
		},//init
	
	/*********************************************************
	* Returns true if all necessary vars were initialized
	*/	
		isInitialized : function () {return b_initialized;}
	
	};//END of MITE_TE return values
	
}();//execute function instantly to return the object in the global namespace  