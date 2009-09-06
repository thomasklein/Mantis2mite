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
	
	html_page_top( lang_get( 'plugin_mite_title' ) );
	print_manage_menu( );

############	
# VARS 
#######	
/**
 * @local arrays
 */		
	$a_configParams = $a_accessLevels = $a_numName = array();
	
/*
 * @local string
 */	
	$s_text = '';

############	
# ACTION 
#######		
	$a_accessLevels = explode(",",lang_get("access_levels_enum_string"));
	
	foreach ($a_accessLevels as $s_accessLevel) {
		
		$a_numName = explode(":",$s_accessLevel);
		$a_possibleValues[$a_numName[0]] = $a_numName[1];
	}
	
	$a_configParams['mtvtl'] = array(
		'name'				=> 'mite_timetracks_visible_threshold_level',
		'value' 			=> plugin_config_get('mite_timetracks_visible_threshold_level'),
		'possibleValues' 	=> $a_possibleValues,
		'header'			=> lang_get('plugin_mite_timetracks_visible_threshold_level'),
		'help' 				=> '');
	
/**
 * @local string
 */
	$s_text = "
		<div style='width:450px; margin: 1em auto; border: 1px solid #000'>
		<form action='".plugin_page( 'config_edit' )."' method='post'>
		<table width='100%'>
		<tr>
			<td class='form-title' colspan='2'>
				".lang_get( 'plugin_mite_title' ) . ': ' . lang_get( 'plugin_mite_config' )."
			</td>
		</tr>
		
		<tr ".helper_alternate_class( ).">
			<td class='category'>
				<label for='".$a_configParams['mtvtl']['name']."'>".
					$a_configParams['mtvtl']['header']."</label>
			</td>
			<td>
				<select id='".$a_configParams['mtvtl']['name']."' 
						name='".$a_configParams['mtvtl']['name']."'>";
	
		foreach($a_configParams['mtvtl']['possibleValues'] as $i_value => $s_name) {
			
			$s_text .= "<option value='".$i_value."'";
			
			if (isset($a_configParams['mtvtl']['value']) && 
				($a_configParams['mtvtl']['value'] == $i_value)) {
					
				$s_text .= " selected='selected' ";
			}
			
			$s_text .= ">".ucfirst($s_name)."</option>";
		}
		
	$s_text .= "
				</select>
				<span class='help'>".$a_configParam['help']."</span>
			</td>
		</tr>
		<tr>
			<td class='center' colspan='2'>
				<input type='submit' class='button' value='".lang_get( 'change_configuration' )."' />
			</td>
		</tr>
		</table>
		</form>
		</div>
	";
	
	echo $s_text;
	
	html_page_bottom( __FILE__ );
?>