<?php
	/*
	 *  Copyright 2019-2026, Ole-Henrik Jakobsen
	 *
	 *  This file is part of Disk Location for Unraid.
	 *
	 *  Disk Location for Unraid is free software: you can redistribute it and/or modify
	 *  it under the terms of the GNU General Public License as published by
	 *  the Free Software Foundation, either version 3 of the License, or
	 *  (at your option) any later version.
	 *
	 *  Disk Location for Unraid is distributed in the hope that it will be useful,
	 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
	 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	 *  GNU General Public License for more details.
	 *
	 *  You should have received a copy of the GNU General Public License
	 *  along with Disk Location for Unraid.  If not, see <https://www.gnu.org/licenses/>.
	 *
	 */
	
	unset($disklocation_page);
	unset($disklocation_layout);
	
	$biggest_tray_group = 0;
	$total_trays_group = 0;
	$datajson = array();
	$array_groups = $get_groups;
	( is_array($array_groups) ?? ksort($array_groups, SORT_NUMERIC) );
	$array_devices = $get_devices;
	//$array_locations = $get_locations; // generated from array_devices.php
	
	$array_trayid = array();
	
	$select_db_devices = ( !empty($select_db_devices) ? $select_db_devices : $select_db_devices_default );
	
	$page_time_load_array = ( $debug || isset($_GET["benchmark"]) ? hrtime(true) : null );
	require_once("array_devices.php");
	$page_time_load["array"] = round((hrtime(true)-$page_time_load_array)/1e+6, 1);
	print(isset($_GET["benchmark"]) ? "<h3 style=\"position: fixed; left: 900px; bottom: 60px; white-space: no-wrap; color: #0099FF; background-color: #111111;\">array: " . round((hrtime(true)-$page_time_load_array)/1e+6, 1) . " ms</h3>\n" : null);
	
	list($table_order_user, $table_order_system, $table_order_name, $table_order_full, $table_order_forms) = get_table_order("all", false);
	
	if(!empty($select_db_devices)) {
		$table_order_user_map = array_map(function($value) { return '/\b'.$value.'\b/u'; }, $table_order_user);
		$select_db_devices_str = preg_replace($table_order_user_map, $table_order_system, $select_db_devices);
	}
	else {
		$select_db_devices_str   = $select_db_devices;
	}
	
	foreach($array_groups as $id => $value) {
		$group_color = "";
		$hide_tray = array();
		$count_bypass_tray = 0;
		$grid_trays = 0;
		
		extract($value);
		
		if(!empty($id)) {
			$gid = $id;
			$groupid = $gid;
			$debug_log = debug($debug, basename(__FILE__), __LINE__, "groupid", $groupid);
			$disklocation_page[$gid] = "";
			$disklocation_layout[$gid] = "";
			$disklocation_alloc[$gid] = "";
			$disklocation_dash[$gid] = "";
			
			$i_arr=0;
			if(!$total_groups || empty($array_locations)) {
				foreach($array_devices as $hash => $array) {
					if(!$array_devices[$hash]["status"]) {
						$datajson[$i_arr] = $array_devices[$hash];
						$datajson[$i_arr]["hash"] = $hash;
						$i_arr++;
					}
				}
			}
			else {
				foreach($array_devices as $hash => $array) {
					if(!$array_devices[$hash]["status"] && $array_locations[$hash]["groupid"] == $gid) {
						$datajson[$i_arr] = $array_devices[$hash];
						$datajson[$i_arr]["hash"] = $hash;
						$datajson[$i_arr] += $array_locations[$hash];
						$i_arr++;
					}
					else {
					}
				}
				$datajson = ( !empty($datajson) ? sort_array($datajson, 'groupid', SORT_ASC, SORT_NUMERIC, 'tray', SORT_ASC, SORT_NUMERIC) : array() );
			}
			
			$total_trays = ( empty($grid_trays) ? $grid_columns * $grid_rows : $grid_trays );
			$total_trays_group += $total_trays;
			
			if($biggest_tray_group < $total_trays) {
				$biggest_tray_group = $total_trays;
			}
			
			//$tray_number_override = tray_number_assign($grid_columns, $grid_rows, $tray_direction, $grid_count, $hide_tray);
			$tray_number_override = ( empty($count_bypass_tray) && is_array($hide_tray) ? tray_number_assign($grid_columns, $grid_rows, $tray_direction, $grid_count, $tray_start_num, $hide_tray) : tray_number_assign($grid_columns, $grid_rows, $tray_direction, $grid_count, $tray_start_num) );
			
			$total_main_trays = 0;
			if($total_trays > ($grid_columns * $grid_rows)) {
				$total_main_trays = $grid_columns * $grid_rows;
				$total_rows_override_trays = ($total_trays - $total_main_trays) / $grid_columns;
				$grid_columns_override_styles = str_repeat(" auto", $total_rows_override_trays);
			}
		
			if($disk_tray_direction == "h") { 
				$insert_break = "<br />";
			}
			else { 
				$insert_break = "";
				
				$tray_swap_height = $tray_height;
				$tray_swap_width = $tray_width;
				
				$tray_height = $tray_swap_width;
				$tray_width = $tray_swap_height;
			}
			
			$debug_log = debug($debug, basename(__FILE__), __LINE__, "total_trays", $total_trays);
			$debug_log = debug($debug, basename(__FILE__), __LINE__, "tray_number_override", $tray_number_override);
			
			$i_empty=1;
			$i_drive=1;
			$i=1;
			$empty_tray = 0;
			$tray_number = 0;
			
			while($i <= $total_trays) {
				$data = isset($datajson[$i_drive-1]) ? $datajson[$i_drive-1] : 0;
				$smart = array();
				$tray_assign = $i;
				$empty_leddiskop = "";
				$empty_ledsmart = "";
				$empty_ledtemp = "";
				$empty_traytext = "";
				
				if(( isset($data["tray"]) ? $data["tray"] : 0 ) != $i) {
					if(!empty($hide_tray)) {
						if($count_bypass_tray) {
							$tray_number = $tray_assign;
							if($hide_tray[$tray_assign]) {
								$total_trays_group = is_int($total_trays_group) ? --$total_trays_group : 0;
							}
						}
						else if(!$hide_tray[$tray_assign]) {
							$tray_number++;
						}
						else {
							$total_trays_group = is_int($total_trays_group) ? --$total_trays_group : 0;
						}
					}
					else {
						$tray_number = $tray_assign;
					}
					
					$debug_log = debug($debug, basename(__FILE__), __LINE__, "tray_assign", $tray_assign);
					$debug_log = debug($debug, basename(__FILE__), __LINE__, "tray_number", $tray_number);
					
					if(!empty($displayinfo["tray"]) && empty($displayinfo["hideemptycontents"])) {
						$empty_tray = (!is_numeric($tray_number_override[$tray_assign]) ? 0 : $tray_number_override[$tray_assign]);
					}
					else {
						$empty_tray = "";
					}
					
					$debug_log = debug($debug, basename(__FILE__), __LINE__, "empty_tray", $empty_tray);
					
					if(isset($displayinfo["leddiskop"]) && $displayinfo["leddiskop"] == 1 && empty($displayinfo["hideemptycontents"])) {
						$empty_leddiskop = get_unraid_disk_status("grey-off", '', '', $force_orb_led);
					}
					if(isset($displayinfo["ledsmart"]) && $displayinfo["ledsmart"] == 1 && empty($displayinfo["hideemptycontents"])) {
						$empty_ledsmart = get_unraid_disk_status("grey-off", '', '', $force_orb_led);
					}
					if(isset($displayinfo["ledtemp"]) && $displayinfo["ledtemp"] == 1 && empty($displayinfo["hideemptycontents"])) {
						$empty_ledtemp = get_unraid_disk_status("grey-off", '', '', $force_orb_led);
					}
					if(empty($displayinfo["hideemptycontents"])) {
						$empty_traytext = "<b>Available disk slot</b>";
					}
					
					$disklocation_page[$gid] .= "
						<div style=\"order: " . $tray_assign . "\">
							<div class=\"flex-container_" . $disk_tray_direction . "\">
								<div style=\"" . ($hide_tray[$tray_assign] ? "border-color: transparent; " : "") . "background-color: " . ($hide_tray[$tray_assign] ? "transparent" : "#" . $color_array["empty"] . "") . "; width: " . $tray_width . "px; height: " . $tray_height . "px;\">
									<div class=\"flex-container-start\" style=\"white-space: nowrap;\">
										" . ($hide_tray[$tray_assign] ? "&nbsp;" : "<b>$empty_tray</b>") . " $insert_break
										" . ($hide_tray[$tray_assign] ? "" : $empty_leddiskop) . " $insert_break
										" . ($hide_tray[$tray_assign] ? "" : $empty_ledsmart) . " $insert_break
										" . ($hide_tray[$tray_assign] ? "" : $empty_ledtemp) . "
									</div>
									<div class=\"flex-container-middle_" . $disk_tray_direction . "\">
										" . ($hide_tray[$tray_assign] ? "&nbsp;" : $empty_traytext) . "
									</div>
									<div class=\"flex-container-end\" style=\"white-space: nowrap;\">
										&nbsp;
									</div>
								</div>
							</div>
						</div>
					";
					
					$add_empty_physical_tray_order = "";
					if($tray_assign != $empty_tray) {
						$add_empty_physical_tray_order = $tray_assign;
					}
					
					$disklocation_layout[$gid] .= "
						<div style=\"order: " . $tray_assign . "\">
							<div class=\"flex-container-layout_" . $disk_tray_direction . "\">
								<div style=\"background-color: #" . $color_array["empty"] . "; width: " . $tray_width/$tray_reduction_factor . "px; height: " . $tray_height/$tray_reduction_factor . "px;\">
									<div class=\"flex-container-start\">
										<b>" .  ( (!$count_bypass_tray && $hide_tray[$tray_assign]) ? "" : $empty_tray ) . "</b>
									</div>
									<div class=\"flex-container-middle_" . $disk_tray_direction . "\">
									</div>
									<div class=\"flex-container-end\">
										<input type=\"checkbox\" name=\"hide_tray[$groupid][$tray_assign]\" value=\"1\" " . (!empty($hide_tray[$tray_assign]) ? "checked=\"checked\"" : null ) . " style=\"background: transparent; margin: 0; padding: 0;\" />
										<!--" . $add_empty_physical_tray_order . "-->
									</div>
								</div>
							</div>
						</div>
					";
					
					$add_empty_physical_tray_order = "";
					if($tray_assign != $empty_tray) {
						$add_empty_physical_tray_order = $empty_tray;
					}
					
					if(!$hide_tray[$tray_assign]) {
						$array_trayid[$gid][$tray_assign] = ( !empty($add_empty_physical_tray_order) ? $add_empty_physical_tray_order : (!is_numeric($tray_number_override[$tray_assign]) ? 0 : $tray_number_override[$tray_assign]) );
					}
					
					$disklocation_alloc[$gid] .= "
						<div style=\"order: " . $tray_assign . "\">
							<div class=\"flex-container-layout_" . $disk_tray_direction . "\">
								<div style=\"background-color: #" . $color_array["empty"] . "; width: " . $tray_width/$tray_reduction_factor . "px; height: " . $tray_height/$tray_reduction_factor . "px;\">
									<div class=\"flex-container-start\" style=\"/*min-height: 15px;*/\">
										<b>" .  ( !$hide_tray[$tray_assign] ? $array_trayid[$gid][$tray_assign] : "-" ) . "</b>
									</div>
									<div class=\"flex-container-middle_" . $disk_tray_direction . "\">
									</div>
									<div class=\"flex-container-end\" style=\"font-size: xx-small;\">
										
									</div>
								</div>
							</div>
						</div>
					";
					
					$disklocation_dash[$gid] .= "
						<div style=\"order: " . $tray_assign . "\">
							<div class=\"flex-container-layout_" . $disk_tray_direction . "\">
								<div style=\"" . ($hide_tray[$tray_assign] ? "border-color: transparent; " : "") . "background-color: " . ($hide_tray[$tray_assign] ? "transparent" : "#" . $color_array["empty"] . "") . "; width: " . $tray_width/$tray_reduction_factor . "px; height: " . $tray_height/$tray_reduction_factor . "px;\">
									<div class=\"flex-container-start\">
										" . ($hide_tray[$tray_assign] ? "&nbsp;" : get_unraid_disk_status("grey-off", '', '', $force_orb_led)) . "
									</div>
									<div class=\"flex-container-middle_" . $disk_tray_direction . "\" style=\"padding: 0 0 10px 0;\">
									</div>
									<div class=\"flex-container-end\" style=\"white-space: nowrap;\">
										" . ($hide_tray[$tray_assign] ? "" : "<b>" . $empty_tray . "</b>") . "
									</div>
								</div>
							</div>
						</div>
					";
					
					$i_empty++;
				}
				else {
					$debug_log = debug($debug, basename(__FILE__), __LINE__, "tray_assign", $tray_assign);
					$device = $data["device"];
					$devicenode = $data["devicenode"];
					$hash = $data["hash"];
					$pool = "";
					$color_override = ( !empty($data["color"]) ? $data["color"] : $group_color );
					$temp_status = 0;
					$temp_status_icon = "";
					$color_status = "";
					$unraid_array_icon = "";
					$physical_traynumber = null;
					
					$drive_type_icon = ( !empty($displayinfo["leddrivetype"]) ? get_drive_type_icon($devices[$hash]["raw"]["rotation"] ?? null) : "" );
					$drive_brand_logo = ( !empty($displayinfo["leddrivelogo"]) ? get_drive_brand_logo($devices[$hash]["raw"]["manufacturer"] ?? null, $devices[$hash]["raw"]["model"] ?? null, $devices[$hash]["raw"]["manufacturer_override"] ?? null) : "" );
					
					if(!$unraid_array[$devicenode]["temp"] || !is_numeric($unraid_array[$devicenode]["temp"])) { // && (!$unraid_array[$devicenode]["temp"] && $unraid_array[$devicenode]["hotTemp"] == 0 && $unraid_array[$devicenode]["maxTemp"] == 0)) {
						$unraid_array[$devicenode]["temp"] = 0;
						
						$temp_status_icon = "<a class='info'><i class='fa fa-circle orb-disklocation grey-orb-disklocation'></i><span>Temperature unavailable</span></a>";
						$temp_status_info = array('orb' => 'fa fa-circle orb-disklocation grey-orb-disklocation', 'color' => 'grey', 'text' => 'N/A');
						$temp_status = 0;
					}
					else {
						if($unraid_array[$devicenode]["temp"] <= $unraid_array[$devicenode]["hotTemp"]) {
							$temp_status_icon = "<a class='info'><i class='fa fa-circle orb-disklocation green-orb-disklocation'></i><span>" . $devices[$hash]["formatted"]["temp"] . "</span></a>";
							$temp_status_info = array('orb' => 'fa fa-circle orb-disklocation green-orb-disklocation', 'color' => 'green', 'text' => $devices[$hash]["formatted"]["temp"]);
							$temp_status = 1;
						}
						if($unraid_array[$devicenode]["temp"] > $unraid_array[$devicenode]["hotTemp"]) {
							$temp_status_icon = "<a class='info' style=\"margin: 0; text-align:left;\"><i class='fa fa-" . ( !$force_orb_led ? 'fire' : 'circle' ) . " orb-disklocation yellow-orb-disklocation yellow-blink-disklocation'></i><span>" . $devices[$hash]["formatted"]["temp"] . " (Warning: &#8805;" . $devices[$hash]["formatted"]["hotTemp"] . ")</span></a>";
							$temp_status_info = array('orb' => "fa fa-" . ( !$force_orb_led ? 'fire' : 'circle' ) . " orb-disklocation yellow-orb-disklocation yellow-blink-disklocation", 'color' => 'yellow', 'text' => $devices[$hash]["formatted"]["temp"]);
							$temp_status = 2;
						}
						if($unraid_array[$devicenode]["temp"] > $unraid_array[$devicenode]["maxTemp"]) {
							$temp_status_icon = "<a class='info'><i class='fa fa-" . ( !$force_orb_led ? 'fire' : 'circle' ) . " orb-disklocation red-blink-disklocation'></i><span>" . $devices[$hash]["formatted"]["temp"] . " (Critical: &#8805;" . $devices[$hash]["formatted"]["maxTemp"] . ")</span></a>";
							$temp_status_info = array('orb' => "fa fa-" . ( !$force_orb_led ? 'fire' : 'circle' ) . " orb-disklocation red-blink-disklocation", 'color' => 'red', 'text' => $devices[$hash]["formatted"]["temp"]);
							$temp_status = 3;
						}
					}
					if(empty($displayinfo["ledtemp"])) {
						$temp_status_icon = "";
					}
					
					// Set $smart_status = 2 if $smart_errors was found AND $smart_status has NOT failed AND disk has NOT been acknowledged, else set initial value:
					$smart_status = ((!empty($devices[$hash]["raw"]["smart_errors"]) && !empty($devices[$hash]["raw"]["smart_status"]) && !get_disk_ack($unraid_array[$data["devicenode"]]["name"])) ? 2 : (!empty($devices[$hash]["raw"]["smart_status"]) ? 1 : 0));
					
					switch($smart_status) {
						case 0:
							$smart_status_icon = "<a class='info' style=\"text-align: left;\"><i class='fa fa-circle orb-disklocation red-orb-disklocation red-blink-disklocation'></i><span>S.M.A.R.T: Failed!<br />" . $devices[$hash]["formatted"]["smart_errors"] . "</span></a>";
							$smart_status_info = array('orb' => 'fa fa-circle orb-disklocation red-orb-disklocation red-blink-disklocation', 'color' => 'red', 'text' => 'Failed');
							break;
						case 1:
							$smart_status_icon = "<a class='info'><i class='fa fa-circle orb-disklocation green-orb-disklocation'></i><span>S.M.A.R.T: Passed</span></a>";
							$smart_status_info = array('orb' => 'fa fa-circle orb-disklocation green-orb-disklocation', 'color' => 'green', 'text' => 'Passed');
							break;
						case 2:
							$smart_status_icon = "<a class='info'><i class='fa fa-circle orb-disklocation yellow-orb-disklocation yellow-blink-disklocation'></i><span>S.M.A.R.T: Warning! " . $devices[$hash]["formatted"]["smart_errors"] . "</span></a>";
							$smart_status_info = array('orb' => 'fa fa-circle orb-disklocation yellow-orb-disklocation yellow-blink-disklocation', 'color' => 'yellow', 'text' => 'Warning');
							break;
						default:
							$smart_status_icon = "<a class='info'><i class='fa fa-circle orb-disklocation grey-orb-disklocation'></i><span>S.M.A.R.T: N/A</span></a>";
							$smart_status_info = array('orb' => 'fa fa-circle orb-disklocation grey-orb-disklocation', 'color' => 'grey', 'text' => 'N/A');
					}
					
					if(!empty($displayinfo["leddiskop"])) {
						$zfs_disk_status = "";
						if($zfs_check) {
							$zfs_disk_status = zfs_disk($data["smart_serialnumber"], $zfs_parser, $lsblk_array);
						}
						
						$unraid_disk_status_color = get_powermode($device, $get_powermode);
						
						if(!empty($zfs_disk_status)) {
							$unraid_array_icon = get_unraid_disk_status($zfs_disk_status[1], '', '', $force_orb_led);
							$unraid_array_info = get_unraid_disk_status($zfs_disk_status[1],'','array', $force_orb_led);
							$color_status = get_unraid_disk_status($zfs_disk_status[1],'','color');
							if($color_status == "green" && ((empty($unraid_array[$devicenode]["color"]) && $unraid_disk_status_color == "green-blink") || $unraid_array[$devicenode]["color"] == "green-blink")) {
								$unraid_array_icon = get_unraid_disk_status('STANDBY', '', '', $force_orb_led);
								$unraid_array_info = get_unraid_disk_status('STANDBY','','array', $force_orb_led);
								$color_status = get_unraid_disk_status('STANDBY','','color');
							}
						}
						else {
							if(!empty($unraid_array[$devicenode]["color"]) && !empty($unraid_array[$devicenode]["status"])) {
								$unraid_array_icon = get_unraid_disk_status($unraid_array[$devicenode]["color"], $unraid_array[$devicenode]["type"], '', $force_orb_led);
								$unraid_array_info = get_unraid_disk_status($unraid_array[$devicenode]["color"], $unraid_array[$devicenode]["type"],'array', $force_orb_led);
								$color_status = get_unraid_disk_status($unraid_array[$devicenode]["color"], $unraid_array[$devicenode]["type"],'color');
							}
							else {
								$unraid_array_icon = get_unraid_disk_status($unraid_disk_status_color, '', '', $force_orb_led);
								$unraid_array_info = get_unraid_disk_status($unraid_disk_status_color,'','array', $force_orb_led);
								$color_status = get_unraid_disk_status($unraid_disk_status_color,'','color');
							}
						}
					}
					
					//$drive_tray_order[$hash] = get_tray_location($get_locations, $hash, $gid);
					
					$drive_tray_order[$hash] = ( isset($get_physical[$phyloc_array[$devicenode]]) ? $get_physical[$phyloc_array[$devicenode]]["tray"] : get_tray_location($get_locations, $hash, $gid) );
					
					$drive_tray_order[$hash] = ( !isset($drive_tray_order[$hash]) ? $tray_assign : $drive_tray_order[$hash] );
					
					if(!empty($displayinfo["tray"])) {
						$physical_traynumber = (!is_numeric($tray_number_override[$drive_tray_order[$hash]]) ? 0 : $tray_number_override[$drive_tray_order[$hash]]);
					}
					else {
						$physical_traynumber_alloc = (!is_numeric($tray_number_override[$drive_tray_order[$hash]]) ? 0 : $tray_number_override[$drive_tray_order[$hash]]);
						$physical_traynumber = "";
					}
					
					$color_array[$hash] = "";
					
					if(!$device_bg_color) { // Disk Type / Heatmap setting.
						switch(strtolower($unraid_array[$devicenode]["type"] ?? '')) {
							case "parity":
								$color_array[$hash] = $bgcolor_parity;
								break;
							case "data":
								$color_array[$hash] = $bgcolor_unraid;
								break;
							case "cache":
								$color_array[$hash] = $bgcolor_cache;
								break;
							case "boot":
								$color_array[$hash] = $bgcolor_flash;
								break;
							default:
								$color_array[$hash] = $bgcolor_others;
						}
						if($color_override) {
							$color_array[$hash] = $color_override;
						}
					}
					else {
						if($unraid_array[$devicenode]["temp"] < $unraid_array[$devicenode]["hotTemp"]) {
							$color_array[$hash] = $bgcolor_cache;
						}
						if($unraid_array[$devicenode]["temp"] >= $unraid_array[$devicenode]["hotTemp"]) {
							$color_array[$hash] = $bgcolor_unraid;
						}
						if($unraid_array[$devicenode]["temp"] >= $unraid_array[$devicenode]["maxTemp"]) {
							$color_array[$hash] = $bgcolor_parity;
						}
						if(!$unraid_array[$devicenode]["temp"] || (!$unraid_array[$devicenode]["temp"] && $unraid_array[$devicenode]["hotTemp"] == 0 && $unraid_array[$devicenode]["maxTemp"] == 0)) {
							$color_array[$hash] = $bgcolor_others;
						}
					}
					
					$add_anim_bg_class = "";
					$color_array_blinker = "";
					if(!empty($displayinfo["flashwarning"]) && ($temp_status == 2 || $smart_status == 2 || $color_status == "yellow")) { // warning
						$color_array_blinker = "blinker-disklocation-yellow-bg";
						$add_anim_bg_class = "class=\"yellow-blink-disklocation-bg\"";
					}
					if(!empty($displayinfo["flashcritical"]) && ($temp_status == 3 || !$smart_status || $color_status == "red")) { // critical
						$color_array_blinker = "blinker-disklocation-red-bg";
						$add_anim_bg_class = "class=\"red-blink-disklocation-bg\"";
					}
					
					// Horizontal trays get the icon+text chip as a bottom-right badge in the
					// device-info column, with reserved space (padding-right, sized to the
					// chip's own rendered width) and shrink/wrap allowances (min-width/
					// overflow-wrap) so a long unbreakable string like a serial number can't
					// push it out of the tile - see get_drive_type_icon()'s comment for why
					// those are needed.
					//
					// Vertical trays get the same chip, bottom-left and rotated 90deg to match
					// .flex-container-middle_v's own writing-mode: vertical-rl text. Confirmed by
					// measuring actual character positions (via Range.getBoundingClientRect() on
					// each character), not by eyeballing a screenshot or reasoning about which way
					// rotate() "should" go - that reasoning gave the wrong sign once already here
					// (see the writing-mode note below for why a quick visual check of rotation
					// direction in isolation, outside this specific inherited-writing-mode
					// context, isn't reliable evidence). That column needs two fixes of its own
					// first though, both confirmed by measuring actual rendered layout rather than
					// trusting a screenshot (which had missed smaller versions of both issues
					// before):
					//   - With no explicit height, vertical-rl content has no block-size limit to
					//     wrap additional columns against, so it grows one tall column
					//     indefinitely and overflows the tile's bottom edge once there's enough
					//     device-info text. flex: 1 1 0 (basis zero, so the flex algorithm - not
					//     content size - drives it) plus min-height: 0 (so it can actually shrink
					//     to that computed size instead of refusing to go below its content's own
					//     intrinsic height, the default) gives it a real height constraint to wrap
					//     against, mirroring the analogous min-width: 0 fix for the horizontal
					//     case's width axis.
					//   - Even with that column height constraint, a single unbreakable token
					//     (e.g. a model string with no spaces, like the serial-heavy ones this
					//     plugin actually renders) still isn't split by a column boundary, so it
					//     overflows past the tile's bottom edge on its own - the same failure mode
					//     the horizontal case already needed overflow-wrap: break-word for,
					//     mirrored here onto the vertical column.
					// flex-shrink: 0 on .flex-container-start keeps the tray-number/status-icon
					// row from being squeezed in exchange - it should stay at its natural size
					// while the device-info column is the one that adapts.
					//
					// The chip's rotation relies on the default (center) transform-origin: an
					// earlier attempt set transform-origin to the same corner as the position
					// anchor (bottom left), which swings a 90deg-rotated box to the *opposite*
					// side of its pivot rather than rotating in place, pushing it outside the
					// tile. Rotating around the chip's own center instead keeps it centered on
					// that same point, so anchoring via bottom/left and rotating around center
					// compose safely for the horizontal axis - but the chip is wider than it is
					// tall (icon + text label, not a square glyph), so the rotated footprint's
					// bottom edge still swings down past the "bottom: 0" anchor by roughly (chip
					// width - chip height) / 2. .flex-container-middle_v's own bottom padding (see
					// disk.css.php) almost absorbs that, but not quite for the widest labels
					// ("HDD"/"SSD") - measurement (not just how it looked in a screenshot) showed
					// a couple of px still spilling past the tile's bottom edge. Anchoring at
					// bottom: 6px instead of bottom: 0 (rather than adding more padding to
					// .flex-container-middle_v itself, which wouldn't help - that padding is
					// inside the same box the anchor is relative to, so the flex algorithm just
					// gives the text content less room without ever moving the anchor point) gives
					// the rotated footprint the little bit of extra clearance it needed, confirmed
					// by re-measuring all three drive types together in a 2x2 grid.
					//
					// The chip's wrapping span also needs writing-mode: horizontal-tb explicitly:
					// it's a child of .flex-container-middle_v, which sets writing-mode:
					// vertical-rl, and writing-mode is inherited. Without resetting it, the chip's
					// own content (the icon + text label) is laid out vertical-rl too, before the
					// transform: rotate() is even applied on top of that - the two rotations (one
					// from the inherited writing-mode, one from the explicit transform) visually
					// cancelled out, so the chip rendered fully upright instead of rotated to match
					// the surrounding text, which is how this was first shipped and reported back
					// as "not rotated at all". Resetting the chip's own writing-mode makes the
					// transform the only rotation in effect - and also means a rotation direction
					// (clockwise vs counter-clockwise) sanity-checked against an *isolated* element
					// with no inherited vertical-rl doesn't actually predict the right sign here:
					// that same inherited writing-mode that caused the "not rotated at all" bug
					// also flips which transform sign visually matches the real (inherited-
					// vertical-rl) text next to it. Only measuring actual character positions in
					// this exact context - both the real device-info text and the chip's own label
					// - settled it: rotate(90deg) is what actually matches, confirmed by
					// Range.getBoundingClientRect() on each character showing the same
					// top-to-bottom reading order in both, not by re-deriving it from first
					// principles (which gave the wrong sign here once already).
					if($disk_tray_direction == "v") {
						$drive_type_icon_start_style = "flex-shrink: 0;";
						$drive_type_icon_column_style = "flex: 1 1 0; min-height: 0; overflow-wrap: break-word; position: relative;";
						$drive_type_icon_middle_html = "<span style=\"position: absolute; bottom: 6px; left: 0; writing-mode: horizontal-tb; transform: rotate(90deg);\">$drive_type_icon</span>";
					}
					else {
						$drive_type_icon_start_style = "";
						$drive_type_icon_column_style = "position: relative; min-width: 0; overflow-wrap: break-word; padding-right: " . ( !empty($drive_type_icon) ? "52px" : "0" ) . ";";
						$drive_type_icon_middle_html = "<span style=\"position: absolute; bottom: 0; right: 0;\">$drive_type_icon</span>";
					}
					
					$disklocation_page[$gid] .= "
						<div style=\"order: " . $drive_tray_order[$hash] . "\">
							<div class=\"flex-container_" . $disk_tray_direction . "\">
								<div id=\"bg1-" . $device . "\" $add_anim_bg_class style=\"background-color: #" . ( !empty($add_anim_bg_class) ? $color_array_blinker : $color_array[$hash] ) . "; width: " . $tray_width . "px; height: " . $tray_height . "px;\">
									<div class=\"flex-container-start\" style=\"white-space: nowrap; $drive_type_icon_start_style\">
										<b>$physical_traynumber</b>$insert_break
										$unraid_array_icon $insert_break
										$smart_status_icon $insert_break
										$temp_status_icon $insert_break
										$drive_brand_logo
									</div>
									<div class=\"flex-container-middle_" . $disk_tray_direction . "\" style=\"$drive_type_icon_column_style\">
										$drive_type_icon_middle_html" . bscode2html(nl2br(stripslashes(htmlspecialchars(keys_to_content($select_db_devices_str, $devices[$hash]["formatted"]))))) . "
									</div>
								</div>
							</div>
						</div>
					";

					$add_physical_tray_order = "";
					if($drive_tray_order[$hash] != $physical_traynumber) {
						$add_physical_tray_order = $drive_tray_order[$hash];
					}
					
					$disklocation_layout[$gid] .= "
						<div style=\"order: " . $drive_tray_order[$hash] . "\">
							<div class=\"flex-container-layout_" . $disk_tray_direction . "\">
								<div id=\"bg2-" . $device . "\" style=\"background-color: #" . $color_array[$hash] . "; width: " . $tray_width/$tray_reduction_factor . "px; height: " . $tray_height/$tray_reduction_factor . "px;\">
									<div class=\"flex-container-start\">
										<b>$physical_traynumber</b>
									</div>
									<div class=\"flex-container-middle_" . $disk_tray_direction . "\">
									</div>
									<div class=\"flex-container-end\">
										<!--" . $add_physical_tray_order . "-->
									</div>
								</div>
							</div>
						</div>
					";
					
					$add_physical_tray_order = "";
					if($drive_tray_order[$hash] != $physical_traynumber) {
						$add_physical_tray_order = $physical_traynumber;
					}
					
					$array_trayid[$gid][$drive_tray_order[$hash]] = ( !empty($add_physical_tray_order) ? $add_physical_tray_order : (!is_numeric($tray_number_override[$drive_tray_order[$hash]]) ? 0 : $tray_number_override[$drive_tray_order[$hash]]) );
					
					$disklocation_alloc[$gid] .= "
						<div style=\"order: " . $drive_tray_order[$hash] . "\">
							<div class=\"flex-container-layout_" . $disk_tray_direction . "\">
								<div id=\"bg3-" . $device . "\" class=\"\" style=\"background-color: #" . $color_array[$hash] . "; width: " . $tray_width/$tray_reduction_factor . "px; height: " . $tray_height/$tray_reduction_factor . "px;\">
									<div class=\"flex-container-start\" style=\"/*min-height: 15px;*/\">
										<b>" . $physical_traynumber . "</b>
										<!--<b>" . $drive_tray_order[$hash] . "</b>-->
									</div>
									<div class=\"flex-container-middle_" . $disk_tray_direction . "\">
									</div>
									<div class=\"flex-container-end\" style=\"font-size: xx-small;\">
										<!--" . $add_physical_tray_order . "-->
									</div>
								</div>
							</div>
						</div>
					";
					
					// SMART=PASS: show array LED
					if($smart_status == 1) {
						$dashboard_orb = $unraid_array_info["orb"];
						
					}
					// TEMP STATUS=warning|critical: show temp warning
					if(isset($temp_status) && $temp_status > 1) { 
						$dashboard_orb = $temp_status_info["orb"];
						
					}
					// SMART=FAIL/WARN: show SMART LED
					if(isset($smart_status) && ($smart_status == 0 || $smart_status == 2)) {
						$dashboard_orb = $smart_status_info["orb"];
						
					}
					$dashboard_text = "" . $temp_status_info["text"] . " | SMART: " . $smart_status_info["text"] . " | " . $unraid_array_info["text"] . "";
					$dashboard_text .= "<br />" . bscode2html(nl2br(stripslashes(htmlspecialchars(keys_to_content($select_db_devices_str, $devices[$hash]["formatted"])))), true) . "";
					
					$disklocation_dash[$gid] .= "
						<div style=\"order: " . $drive_tray_order[$hash] . "\">
							<div class=\"flex-container-layout_" . $disk_tray_direction . "\">
								<div id=\"bg4-" . $device . "\" $add_anim_bg_class style=\"background-color: #" . ( !empty($add_anim_bg_class) ? $color_array_blinker : $color_array[$hash] ) . "; width: " . $tray_width/$tray_reduction_factor . "px; height: " . $tray_height/$tray_reduction_factor . "px;\">
									<div class=\"flex-container-start\" style=\"text-align: center;/*min-height: 15px;*/\">
										<a href=\"/Main/Device?name=" . $devices[$hash]["raw"]["name"] . "\" class='info'><i class='" . $dashboard_orb . "'></i><span style=\"text-align: left;\">" . $dashboard_text . "</span></a>
									</div>
									<div class=\"flex-container-middle_" . $disk_tray_direction . "\" style=\"padding: 0 0 10px 0;\">
									</div>
									<div class=\"flex-container-end\">
										<b>$physical_traynumber</b>
									</div>
								</div>
							</div>
						</div>
					";
					
					$tray_number = $drive_tray_order[$hash];
					$installed_drives[$gid] = $i_drive;
					$i_drive++;
				}
				
				if($total_main_trays == $i) {
					$disklocation_page[$gid] .= "</div><div class=\"grid-container\" style=\"grid-template-rows: " . $grid_columns_override_styles . "; margin: " . $tray_height / 2 . "px;\">";
					$disklocation_layout[$gid] .= "</div><div class=\"grid-container\" style=\"grid-template-rows: " . $grid_columns_override_styles . "; margin: " . $tray_height / 20 . "px;\">";
					$disklocation_alloc[$gid] .= "</div><div class=\"grid-container\" style=\"grid-template-rows: " . $grid_columns_override_styles . "; margin: " . $tray_height / 20 . "px;\">";
					$disklocation_dash[$gid] .= "</div><div class=\"grid-container\" style=\"grid-template-rows: " . $grid_columns_override_styles . "; margin: " . $tray_height / 20 . "px;\">";
				}
				
				$i++;
			}
			$grid_columns_styles[$gid] = str_repeat(" auto", $grid_columns);
			$grid_rows_styles[$gid] = str_repeat(" auto", $grid_rows);
			
			unset($datajson); // delete array
		}
	}
	
	$debug_log = debug($debug, basename(__FILE__), __LINE__, "array_trayid", $array_trayid);
	
	$disklocation_page_out = "";
	
	$array_groups = $get_groups;
	( is_array($array_groups) ?? ksort($array_groups, SORT_NUMERIC) );
	
	foreach($array_groups as $gid => $value) {
		$gid_name = ($array_groups[$gid]["tray_align_txt"] != "hide" ? ( empty($array_groups[$gid]["group_name"]) ? $gid : $array_groups[$gid]["group_name"]) : "");
		
		$css_grid_group = "
			grid-template-columns: " . $grid_columns_styles[$gid] . ";
			grid-template-rows: " . $grid_rows_styles[$gid] . ";
			grid-auto-flow: " . $array_groups[$gid]["grid_count"] . ";
			justify-content: " . (!empty($array_groups[$gid]["tray_align"]) ? $array_groups[$gid]["tray_align"] : "center" ) . ";
		";
		
		$disklocation_page_out_get_float = (!empty($array_groups[$gid]["tray_pos"]) ? $array_groups[$gid]["tray_pos"] : (!empty($dashboard_float) ? $dashboard_float : $tray_pos ) );
		
		$disklocation_page_out .= "
			<div class=\"dl-group-wrap\" style=\"float: " . $disklocation_page_out_get_float . "; vertical-align: top; padding" . ($disklocation_page_out_get_float == "none" ? "-bottom: 40px" : ": 0") . ";\">
				<h2 style=\"text-align: " . (!empty($array_groups[$gid]["tray_align_txt"]) ? $array_groups[$gid]["tray_align_txt"] : "center" ) . "; " . ( $array_groups[$gid]["tray_align_txt"] == "vertical" ? "float: left; writing-mode: vertical-rl;" : null ) . "\">" . stripslashes(htmlspecialchars($gid_name)) . "</h2>
				<div class=\"grid-container\" style=\"$css_grid_group\">
					$disklocation_page[$gid]
				</div>
			</div>
		";
	}
	
	if($db_update == 2) {
		print("<!--");
	}
?>
