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
	 *  functions_devices.php - per-device/array status, tray allocation, physical
	 *  device-path detection, and the tray-number-assignment algorithm. Split out of
	 *  functions.php (see ROADMAP.md) along with the other functions_*.php files - no
	 *  logic changed.
	 */
	
	function get_unraid_disk_status($color, $type = '', $output = '', $force_orb_led = 0) {
		switch($color) {
			case 'green-on': $orb = 'circle'; $color = 'green'; $blink = ''; $help = 'Normal operation, device is active'; break;
			case 'green-blink': $orb = 'circle'; $color = 'grey'; $blink = 'green'; $help = 'Device is in standby mode (spun-down)'; break;
			case 'blue-on': $orb = 'square'; $color = 'blue'; $blink = ''; $help = 'New device'; break;
			case 'blue-blink': $orb = 'square'; $color = 'grey'; $blink = 'blue'; $help = 'New device, in standby mode (spun-down)'; break;
			case 'yellow-on': $orb = 'warning'; $color = 'yellow'; $blink = 'yellow'; $help = $type =='Parity' ? 'Parity is invalid' : 'Device contents emulated'; break;
			case 'yellow-blink': $orb = 'warning'; $color = 'grey'; $blink = 'yellow'; $help = $type =='Parity' ? 'Parity is invalid, in standby mode (spun-down)' : 'Device contents emulated, in standby mode (spun-down)'; break;
			case 'red-on': $orb = 'times'; $color = 'red'; $blink='red'; $help = $type=='Parity' ? 'Parity device is disabled' : 'Device is disabled, contents emulated'; break;
			case 'red-blink': $orb = 'times'; $color = 'red'; $blink = 'red'; $help = $type=='Parity' ? 'Parity device is disabled' : 'Device is disabled, contents emulated'; break;
			case 'red-off': $orb = 'times'; $color = 'red'; $blink = 'red'; $help = $type =='Parity' ? 'Parity device is missing' : 'Device is missing (disabled), contents emulated'; break;
			case 'grey-off': $orb = 'square'; $color = 'grey'; $blink = ''; $help = 'Device not present'; break;
			// ZFS values
			case 'ONLINE': $orb = 'circle'; $color = 'green'; $blink = ''; $help = 'Normal operation, device is online'; break;
			case 'FAULTED': $orb = 'times'; $color = 'red'; $blink = 'red'; $help = 'Device has faulted'; break;
			case 'DEGRADED': $orb = 'warning'; $color = 'yellow'; $blink = 'yellow'; $help = 'Device is degraded'; break;
			case 'AVAIL': $orb = 'circle'; $color = 'green'; $blink = ''; $help = 'Device is available'; break;
			case 'UNAVAIL': $orb = 'times'; $color = 'red'; $blink = 'red'; $help = 'Device is unavailable'; break;
			case 'OFFLINE': $orb = 'times'; $color = 'red'; $blink = 'red'; $help = 'Device is offline'; break;
			case 'STANDBY': $orb = 'circle'; $color = 'grey'; $blink = 'green'; $help = 'Device is online and in standby mode'; break;
		}
		
		if($force_orb_led == 1) {
			$orb = 'circle';
		}
		
		if($output == "color") {
			return $color;
		}
		if($output == "array") {
			$orb = "fa fa-".$orb." orb-disklocation ".$color."-orb-disklocation " . ( !empty($blink) ? $blink."-blink-disklocation" : null ) . "";
			return array(
				'orb'	=> $orb,
				'color'	=> $color,
				'text'	=> $help
			);
		}
		else {
			return ("<a class='info'><i class='fa fa-$orb orb-disklocation $color-orb-disklocation " . ( !empty($blink) ? $blink."-blink-disklocation" : null ) . "'></i><span>$help</span></a>");
		}
	}
	
	function get_powermode($device, $array) {
		switch($array[$device]) {
			case "ACTIVE":
				return "green-on";
				break;
			case "IDLE":
				return "green-on";
				break;
			case "STANDBY":
				return "green-blink";
				break;
			case "UNKNOWN":
				return "grey-off";
				break;
			default:
				return "grey-off";
		}
	}
	
	function is_tray_allocated($db, $tray, $gid) {
		$array_locations = $db;
		foreach($array_locations as $hash => $array) {
			return ( ($db[$hash]["tray"] == $tray && $db[$hash]["groupid"] == $gid) ? $hash : null );
		}
	}
	
	function get_tray_location($db, $hash, $gid) {
		if($db[$hash]["groupid"] == $gid) {
			return ( empty($db[$hash]["tray"]) ? false : $db[$hash]["tray"] );
		}
	}
	
	function count_table_rows($db) {
		return ( isset($db) ? count($db) : 0 );
	}
	
	function get_disk_ack($device, $file = EMHTTP_VAR . "/" . UNRAID_MONITOR_FILE) {
		$unraid_monitor = parse_ini_file($file, true);
		return (isset($unraid_monitor["smart"][$device.".ack"]) ? true : false);
	}
	
	function set_disk_ack($devices, $file = EMHTTP_VAR . "/" . UNRAID_MONITOR_FILE) {
		$unraid_monitor = parse_ini_file($file, true);
		$devices = explode(",", $devices);
		
		foreach($devices as $disk) {
			$unraid_monitor["smart"][$disk.".ack"] = "true";
		}
		if(!write_ini_file($file, $unraid_monitor)) {
			return false;
		}
		else return true;
	}
	
	function find_and_set_removed_devices_status($db, $locations, $arr_hash) {
		foreach($db as $hash => $array) {
			( ($db[$hash]["status"] != 'd') ? $db_hash[] = $hash : null );
		}
		
		$arr_hash = array_filter($arr_hash);
		$db_hash = array_filter($db_hash);
		
		sort($arr_hash);
		sort($db_hash);
		
		$results = array_diff($db_hash, $arr_hash);
		$old_hash = array_values($results);
		
		$status = "";
		
		for($i=0; $i < count($old_hash); ++$i) {
			if($db[$old_hash[$i]]["status"] != 'r') {
				$db[$old_hash[$i]]["status"] = 'r';
				$db[$old_hash[$i]]["removed"] = date("Y-m-d");
				
				unset($locations[$old_hash[$i]]);
			}
		}
		
		config_array(DISKLOCATION_DEVICES, 'w', $db);
		config_array(DISKLOCATION_LOCATIONS, 'w', $locations);
	}
	
	function force_set_removed_device_status($db, $locations, $hash) {
		foreach($db as $key => $data) {
			if($hash == $key) {
				$db[$hash]["status"] = 'r';
				$db[$hash]["removed"] = date("Y-m-d");
				
				unset($locations[$hash]);
			}
		}
		
		return ( config_array(DISKLOCATION_DEVICES, 'w', $db) && config_array(DISKLOCATION_LOCATIONS, 'w', $locations) ? true : false );
	}
	
	function force_undelete_devices($db, $action) {
		// r = read
		// m = modify
		$ret = 0;
		
		switch($action) {
			case 'r': // read
				$i=0;
				foreach($db as $key => $data) {
					$ret += ( $db[$key]["status"] == 'd' ?? ++$i );
				}
				return $ret;
				
				break;
			case 'm': // modify
				foreach($db as $key => $data) {
					if($db[$key]["status"] == 'd') {
						$db[$key]["status"] = 'r';
					}
				}
				
				return ( config_array(DISKLOCATION_DEVICES, 'w', $db) ? true : false );
				
				break;
			default:
				return false;
		}
	}
	
	function force_reset_color($config, $devices, $groups, $hash = 0) {
		global $bgcolor_parity_default, $bgcolor_unraid_default, $bgcolor_cache_default, $bgcolor_flash_default, $bgcolor_others_default, $bgcolor_empty_default;
		
		if($hash == '*' || $hash == 'all') {
			foreach($devices as $id => $data) { // id=hash not $hash
				$devices[$id]["color"] = '';
			}
			foreach($groups as $id => $data) {
				$groups[$id]["group_color"] = '';
			}
			return ((config_array(DISKLOCATION_DEVICES, 'w', $devices) && config_array(DISKLOCATION_GROUPS, 'w', $groups)) ? true : false );
		}
		else if($hash) {
			$devices[$hash]["color"] = '';
			return config_array(DISKLOCATION_DEVICES, 'w', $devices);
		}
		else {
			foreach($config as $key => $data) {
				$config["bgcolor_parity"] = $bgcolor_parity_default;
				$config["bgcolor_unraid"] = $bgcolor_unraid_default;
				$config["bgcolor_cache"] = $bgcolor_cache_default;
				$config["bgcolor_flash"] = $bgcolor_flash_default;
				$config["bgcolor_others"] = $bgcolor_others_default;
				$config["bgcolor_empty"] = $bgcolor_empty_default;
			}
			return !config_array(DISKLOCATION_CONF, 'w', $config);
		}
	}
	
	function find_device_ports() {
		$path = "/dev/disk/by-path/";
		
		$scandisks = array_values(preg_grep("/part/", array_diff(scandir($path), array('..', '.')), PREG_GREP_INVERT));
		
		for($i=0; $i < count($scandisks); ++$i) {
			$deviceports[str_replace("../../", "", readlink($path . $scandisks[$i]))] = $scandisks[$i];
		}
		
		if($deviceports) {
			return $deviceports;
		}
		else {
			return false;
		}
	}
	
	function dev_by_phy_path($device = null) {
		/*
		Array
		(
		[sdx] => pci-0000:00:00.0-sas-phy0-lun-0
		)
		
		device=sdx
		string(32) "pci-0000:00:00.0-sas-phy0-lun-0"
		*/
		
		$nodes = array();
		
		if(is_dir("/dev/disk/by-path")) {
			$dir = array_diff(scandir("/dev/disk/by-path"), array('..', '.'));
			
			foreach($dir as $phy) {
				if(is_link("/dev/disk/by-path/" . $phy)) {
					if(realpath("/dev/disk/by-path/" . readlink("/dev/disk/by-path/" . $phy))) {
						$node = str_replace("../", "", readlink("/dev/disk/by-path/" . $phy));
						$nodes[$node] = $phy;
					}
				}
			}
		}
		
		if(!empty($nodes)) {
			if(!empty($device)) {
				return $nodes[$device];
			}
			else {
				return $nodes;
			}
		}
		else {
			return null;
		}
	}
	
	function lsscsi_parser($input) {
		// \[(.+:.+:.+:.+)\]\s+(-|(\/dev\/(h|s)d[a-z]{1,})?)\s+((\/dev\/(nvme|sg)[0-9]{1,})(n[0-9]{1,})?)
		$pattern_device = "\[(.+:.+:.+:.+)\]\s+";						// $1
		//$pattern_devnode = "(-|(\/dev\/(h|s)d[a-z]{1,})?)\s+";				// $3 pre 6.9
		//$pattern_scsigendevnode = "((\/dev\/(nvme|sg)[0-9]{1,})(n[0-9]{1,})?)";		// $5 pre 6.9
		$pattern_devnode = "((\/dev\/((h|s)d[a-z]{1,}|nvme[0-9]{1,})(n[0-9]{1,})?))\s+";	// $2
		$pattern_scsigendevnode = "(-|(\/dev\/(sg)[0-9]{1,}))";					// $7
		
		if($input) {
			[$device, $devnode, $scsigendevnode] = explode("|", preg_replace("/" . $pattern_device . "" . $pattern_devnode . "" . $pattern_scsigendevnode . "/iu", "$1|$2|$7", $input));
			
			if($scsigendevnode) {
				$scsigendevnode = ( strstr($scsigendevnode, "-") ? $devnode : $scsigendevnode ); // script uses SG for most things, so we add nvme into it as well.
			}
			
			return array(
				'device'	=> ($device ? trim($device) : ''),
				'devnode'	=> ($devnode ? str_replace("-", "", trim($devnode)) : ''),
				'sgnode'	=> ($scsigendevnode ? trim($scsigendevnode) : '')
			);
		}
		else {
			return array(
				'device'	=> '',
				'devnode'	=> '',
				'sgnode'	=> ''
			);
		}
	}
	
	function check_devicepath_conflict($array, $ignore = 0) { // return difference or 1 if conflict, empty if no conflict
		if(is_array($array) && !empty($array)) {
			if(file_exists(DISKLOCATION_TMP_PATH . "/powermode.json")) {
				$json_array = json_decode(file_get_contents(DISKLOCATION_TMP_PATH . "/powermode.json"), true);
				if(is_array($json_array) && !empty($json_array)) {
					if(empty($ignore)) {
						foreach($array as $key => $value) {
							$devicepath[] = ( $array[$key]["raw"]["status"] != 'r' ? $array[$key]["raw"]["device"] : null );
						}
						sort($devicepath);
						
						$json_powermode = array_diff($json_array, ['UNKNOWN']);
						$powermode = array_keys($json_powermode);
						sort($powermode);
						
						$diff_powermode = array_diff($powermode, array_filter($devicepath));
						
						if(file_exists(DISKLOCATION_TMP_PATH . "/powermode_ignore.json")) {
							$json_ignore_array = json_decode(file_get_contents(DISKLOCATION_TMP_PATH . "/powermode_ignore.json"), true);
							return array_diff($diff_powermode, $json_ignore_array);
						}
						else {
							return $diff_powermode;
						}
					}
					else return null; // null = ignore conflict, might be useful for multiple SAS cable connected backplanes and dual actuator drives.
				}
				else return 1; // 1 = found conflict
			}
			else return 1;
		}
		else return 1;
	}
	
	function parse_hdparm_speed($input) {
		if(isset($input)) {
			list($nothing, $this_device, $this_results) = preg_split('/\r\n|\r|\n/', $input);
			list($garbage, $speed) = explode("=", $this_results);
			list($number, $unit) = explode(" ", trim($speed));
			
			return (is_numeric($number) ? $number : null);
		}
		else return null;
	}
	
	function dirsize($path) {
		$bytestotal = 0;
		$path = realpath($path);
		
		if($path !== false && $path != '' && file_exists($path)) {
			foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $object) {
				$bytestotal += $object->getSize();
			}
		}
		return $bytestotal;
	}
	
	function tray_number_assign($col, $row, $dir = 1, $grid = 'col', $start = 1, $skip = array()) {
		$total = ($col * $row);
		$start = $start-1;
		$data = array();
		$tmp = array();
		$results = array();
		
		switch($dir) {
			case 1: // ok | left / top
				if($grid) {
					/* 1 = left->right|top->bottom:		verified ok	(row: left->right)
						1-2-3
						4-5-6
					*/
					/* 1 = top->bottom|left->right:		verified ok	(column: top->bottom)
						1-3-5
						2-4-6
					*/
					
					$i_keep = 1;
					$i_skip = 1;
					for($i=1; $i <= $total; ++$i) {
						if(empty($skip)) {
							$data[$i] = $i_keep+$start;
							$i_keep++;
						}
						else if(!$skip[$i]) {
							$data[$i] = $i_skip+$start;
							$i_skip++;
						}
					}
					
					$results = $data;
					$results = (count($results) > 1) ? $results : [1=>$start+1];
					$results[0] = $grid;
					ksort($results);
				}
				
				return $results;
				
				break;
				
			case 2: // ok | left | bottom
				if($grid == "row") {
					/* 2 = left->right|bottom->top:		verified ok	(row: left->right)
						4-5-6
						1-2-3
					*/
					$i_col = 0;
					$i_keep = 1;
					$i_skip = 1;
					for($i=$total; $i >= 1; --$i) {
						if($i % $col == 0) {
							$i_col++;
						}
						if(empty($skip)) {
							$data[$i_col][$i] = $i_keep+$start;
							$i_keep++;
						}
						else if(!$skip[$i]) {
							$data[$i_col][$i] = $i_skip+$start;
							$i_skip++;
						}
					}
					
					foreach ($data as $index => $key) {
						$results[$index] = array_combine(array_reverse(array_keys($key)), $key);
					}
					
					$results = (count($results) > 1) ? array_replace(...$results) : [1=>$start+1];
					$results[0] = $grid;
					ksort($results);
				}
				else {
					/* 2 = bottom->top|left->right:		verified ok	(column: top->bottom)
						2-4-6
						1-3-5
					*/
					
					$i_row = 1;
					$i_keep = 1;
					$i_skip = 1;
					for($i=1; $i <= $total; ++$i) {
						if(empty($skip)) {
							$data[$i_row][$i] = $i_keep+$start;
							$i_keep++;
						}
						else if(!$skip[$i]) {
							$data[$i_row][$i] = $i_skip+$start;
							$i_skip++;
						}
						if($i % $row == 0) {
							$i_row++;
						}
					}
					
					foreach ($data as $index => $key) {
						$results[$index] = array_combine(array_reverse(array_keys($key)), $key);
					}
					
					$results = (count($results) > 1) ? array_replace(...$results) : [1=>$start+1];
					$results[0] = $grid;
					ksort($results);
				}
				
				return $results;
				
				break;
				
			case 3: // ok | right / top
				if($grid == "row") {
					/* 3 = right->left|top->bottom:		verified ok	(row: left->right)
						3-2-1
						6-5-4
					*/
					$i_col = 1;
					$i_keep = 1;
					$i_skip = 1;
					
					for($i=1; $i <= $total; ++$i) {
						if(empty($skip)) {
							$data[$i_col][$i] = $i_keep+$start;
							$i_keep++;
						}
						else if(!$skip[$i]) {
							$data[$i_col][$i] = $i_skip+$start;
							$i_skip++;
						}
						if($i % $col == 0) {
							$i_col++;
						}
					}
					
					foreach ($data as $index => $key) {
						$results[$index] = array_combine(array_reverse(array_keys($key)), $key);
					}
					
					$results = (count($results) > 1) ? array_replace(...$results) : [1=>$start+1];
					$results[0] = $grid;
					ksort($results);
				}
				else {
					/* 3 = top->bottom|right->left:		verified ok	(column: top->bottom)
						5-3-1
						6-4-2
					*/
					
					$i_row = 0;
					$i_keep = 1;
					$i_skip = 1;
					
					for($i=$total; $i >= 1; --$i) {
						if($i % $row == 0) {
							$i_row++;
						}
						if(empty($skip)) {
							$data[$i_row][$i] = $i_keep+$start;
							$i_keep++;
						}
						else if(!$skip[$i]) {
							$data[$i_row][$i] = $i_skip+$start;
							$i_skip++;
						}
					}
					
					foreach ($data as $index => $key) {
						$results[$index] = array_combine(array_reverse(array_keys($key)), $key);
					}
					
					$results = (count($results) > 1) ? array_replace(...$results) : [1=>$start+1];
					$results[0] = $grid;
					ksort($results);
				}
				
				return $results;
				
				break;
				
			case 4: // ok | right / bottom
				if($grid) {
					/* 4 = right->left|bottom->top:		verified ok	(row: left->right)
						6-5-4
						3-2-1
					*/
					/* 4 = bottom->top|right->left:		verified ok	(column: top->bottom)
						6-4-2
						5-3-1
					*/
					$i_keep = 1;
					$i_skip = 1;
					for($i=$total; $i >= 1; --$i) {
						if(empty($skip)) {
							$data[$i] = $i_keep+$start;
							$i_keep++;
						}
						else if(!$skip[$i]) {
							$data[$i] = $i_skip+$start;
							$i_skip++;
						}
						else {
							$data[$i] = null;
						}
					}
					
					$results = $data;
					
					$results = (count($results) > 1) ? $results : [1=>$start+1];
					$results[0] = $grid;
					ksort($results);
				}
				
				return $results;
				
				break;

			default:
				return false;
		}
	}
?>
