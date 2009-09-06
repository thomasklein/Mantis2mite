<?php
# MantisBT - a php based bugtracking system
# Copyright (C) 2002 - 2009  MantisBT Team - mantisbt-dev@lists.sourceforge.net
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

	auth_reauthenticate( );
	access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );
	
	// get the user value of the api key 
	$f_threshold_level = gpc_get_string( 'mite_timetracks_visible_threshold_level', '' );
	
	
	// if something changed
	if( plugin_config_get( 'mite_timetracks_visible_threshold_level' ) != $f_threshold_level ) {
		plugin_config_set( 'mite_timetracks_visible_threshold_level', $f_threshold_level );
	}
	
	print_successful_redirect(plugin_page('config', true ));
