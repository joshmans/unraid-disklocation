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
	 *  functions_smart.php - SMART-attribute and temperature-unit conversion/parsing
	 *  helpers. Split out of functions.php (see ROADMAP.md) along with the other
	 *  functions_*.php files - no logic changed.
	 */
	
	function smart_units_to_bytes($units, $block, $factor = 1000, $lba = false) {
		if($lba) {
			return $units * $block;
		}
		else {
			return $units * $block * $factor;
		}
	}
	
	function temperature_conv($float, $input, $output) {
		// temperature_conv(floatnumber, F, C) : from F to C
		
		// Celcius to Farenheit, [F]=[C]*9/5+32 | [C]=5/9*([F]-32)
		// Celcius to Kelvin, 0C = 273.15K
		
		$result = 0;
		
		if(is_numeric($float) && ($input != $output)) {
			if($output == "C") {
				if($input == "F") {
					$result = ($float-32)*5/9;
				}
				if($input == "K") {
					$result = $float-273.15;
				}
			}
			if($output == "F") {
				if($input == "C") {
					$result = $float*9/5+32;
				}
				if($input == "K") {
					$result = ($float-273.15)*9/5+32;
				}
			}
			if($output == "K") {
				if($input == "C") {
					$result = $float+273.15;
				}
				if($input == "F") {
					$result = (($float-32)*5/9)+273.15;
				}
			}
		}
		else {
			$result = $float;
		}
		
		if($result) {
			return $result;
		}
		else {
			return false;
		}
	}
	
	function get_smart_rotation($input) {
		switch($input) {
			case -2:
				$smart_rotation = "NVMe SSD";
				break;
			case -1:
				$smart_rotation = "SSD";
				break;
			case 0:
				$smart_rotation = "N/A";
				break;
			case null:
				$smart_rotation = "N/A";
				break;
			default:
				$smart_rotation = $input . " RPM";
		}
		return $smart_rotation;
	}
	
	function get_smart_cache($device) { // output MB
		$smart_id_data = shell_exec("smartctl " . $device . " -l gplog,0x30,2 | grep 0000420");
		$values = array();
		$values = explode(" ", $smart_id_data);
		
		$bytes = hexdec($values[4].$values[5].$values[6].$values[7]);
		
		return $bytes / 1024 / 1024;
	}
	
	function check_smart_files() { // return true if files found
		if(file_exists(DISKLOCATION_TMP_PATH . "/smart")) {
			$dir = array_diff(scandir(DISKLOCATION_TMP_PATH . "/smart"), array('..', '.'));
			return ( empty($dir) ? false : true );
		}
		else return false;
	}
	
	function sanitize_smart_type_flag($value) {
		// $value ends up interpolated, unquoted, directly into several shell_exec() calls
		// (cronjob.php, hddcheck.php, benchmark.php) that run smartctl/hdparm, because it
		// needs to stay as multiple distinct shell tokens (e.g. "-d sat,0" or
		// "-d areca,1/2 /dev/sdb") rather than a single escapeshellarg()-quoted argument.
		// Since it originates from an admin-configurable Unraid SMART setting rather than
		// unauthenticated user input, the risk is limited to a trusted admin/root context -
		// but we still strip anything outside smartctl's -d TYPE[,PORT[/EPORT]] syntax and
		// a "/dev/name" path (letters, digits, comma, slash, dot, dash, underscore, colon,
		// space) so stray shell metacharacters (; | & ` $ ( ) newlines, etc.) can never reach
		// the shell, whether typed by mistake or introduced via a corrupted/malicious config.
		if(empty($value)) {
			return $value;
		}
		return preg_replace('/[^A-Za-z0-9_,\/\.\-: ]/', '', $value);
	}
?>
