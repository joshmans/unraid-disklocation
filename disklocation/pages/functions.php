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
	
	if(!strstr($_SERVER["SCRIPT_NAME"], "page_system.php")) {
		require_once("variables.php");
		include("load_settings.php");
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
	
	function debug($output, $file, $line, $program, $input = '') { // $output = 0: off | 1: write logfile | 2: return log | 3: write logfile & return log
		if($output) {
			if($output && $line && $program && $input) {
				$log = "[" . date("H:i:s") . "] " . $file . ":" . $line . " @ " . $program . ": " . ( is_array($input) ? print_r($input, true) : $input ) . "\n";
			}
			
			if($output == 1 || $output == 3) {
				file_put_contents(DISKLOCATION_TMP_PATH . "/disklocation.log", $log, FILE_APPEND);
				if($output == 2) {
					return true;
				}
			}
			
			if($output == 2 || $output == 3) {
				return $log;
			}
		}
		else {
			return false;
		}
	}
	
	function config($file, $operation, $key = '', $val = '') { // file, [r]ead/[w]rite, key (req. write), value (req. write)
		if($operation == 'w') {
			if(!file_exists($file)) {
				mkdir(dirname($file), 0755, true);
				touch($file);
			}
			$config_json = file_get_contents($file);
			$config_json = json_decode($config_json, true);
			$config_json[$key] = $val;
			$config_json = json_encode($config_json, JSON_PRETTY_PRINT);
			if(file_put_contents($file, $config_json)) {
				return true;
			}
			else {
				return false;
			}
		}
		if($operation == 'r') {
			$config_json = file_get_contents($file);
			$config_json = json_decode($config_json, true);
			if($key) {
				return $config_json[$key];
			}
			else {
				return $config_json;
			}
		}
		else return false;
	}
	function config_array($file, $operation, $array = '') { // file, [r]ead/[w]rite, array (req. write)
		if($operation == 'w' && is_array($array)) {
			if(!file_exists($file)) {
				mkdir(dirname($file), 0755, true);
				touch($file);
			}
			
			$func_array = json_encode($array, JSON_PRETTY_PRINT);
			
			if(file_put_contents($file, $func_array)) {
				return true;
			}
			else {
				return false;
			}
		}
		if($operation == 'r') {
			$contents = file_get_contents($file);
			$func_array = json_decode($contents, true);
			return $func_array;
		}
		else return false;
	}
	
	function write_ini_file($file, $array = []) { // from Lawrence Cherone @ stackoverflow.com
		// check first argument is string
		if (!is_string($file)) {
			throw new \InvalidArgumentException('Function argument 1 must be a string.');
		}
		
		// check second argument is array
		if (!is_array($array)) {
			throw new \InvalidArgumentException('Function argument 2 must be an array.');
		}
		
		// process array
		$data = array();
		foreach ($array as $key => $val) {
		if (is_array($val)) {
			$data[] = "[$key]";
			foreach ($val as $skey => $sval) {
			if (is_array($sval)) {
				foreach ($sval as $_skey => $_sval) {
				if (is_numeric($_skey)) {
					$data[] = $skey.'[] = '.(is_numeric($_sval) ? $_sval : (ctype_upper($_sval) ? $_sval : '"'.$_sval.'"'));
				} else {
					$data[] = $skey.'['.$_skey.'] = '.(is_numeric($_sval) ? $_sval : (ctype_upper($_sval) ? $_sval : '"'.$_sval.'"'));
				}
				}
			} else {
				$data[] = $skey.' = '.(is_numeric($sval) ? $sval : (ctype_upper($sval) ? $sval : '"'.$sval.'"'));
			}
			}
		} else {
			$data[] = $key.' = '.(is_numeric($val) ? $val : (ctype_upper($val) ? $val : '"'.$val.'"'));
		}
		// empty line
		$data[] = null;
		}
		
		// open file pointer, init flock options
		$fp = fopen($file, 'w');
		$retries = 0;
		$max_retries = 100;
		
		if (!$fp) {
		return false;
		}
		
		// loop until get lock, or reach max retries
		do {
		if ($retries > 0) {
			usleep(rand(1, 5000));
		}
		$retries += 1;
		} while (!flock($fp, LOCK_EX) && $retries <= $max_retries);
		
		// couldn't get the lock
		if ($retries == $max_retries) {
		return false;
		}
		
		// got lock, write data
		fwrite($fp, implode(PHP_EOL, $data).PHP_EOL);
		
		// release lock
		flock($fp, LOCK_UN);
		fclose($fp);
		
		return true;
	}
	
	function bscode2html($text, $strip = false) {
		$text = preg_replace("/\*(.*?)\*/", "<b>$1</b>", $text);
		$text = preg_replace("/_(.*?)_/", "<i>$1</i>", $text);
		$text = preg_replace("/\[b\](.*)\[\/b\]/", "<b>$1</b>", $text);
		$text = preg_replace("/\[i\](.*)\[\/i\]/", "<i>$1</i>", $text);
		$text = preg_replace("/\[tiny\](.*)\[\/tiny\]/", "" . ( !$strip ? "<span style=\"font-size: xx-small;\">$1</span>" : "$1" ) . "", $text);
		$text = preg_replace("/\[small\](.*)\[\/small\]/", "" . ( !$strip ? "<span style=\"font-size: x-small;\">$1</span>" : "$1" ) . "", $text);
		$text = preg_replace("/\[medium\](.*)\[\/medium\]/", "" . ( !$strip ? "<span style=\"font-size: medium;\">$1</span>" : "$1" ) . "", $text);
		$text = preg_replace("/\[large\](.*)\[\/large\]/", "" . ( !$strip ? "<span style=\"font-size: large;\">$1</span>" : "$1" ) . "", $text);
		$text = preg_replace("/\[huge\](.*)\[\/huge\]/", "" . ( !$strip ? "<span style=\"font-size: x-large;\">$1</span>" : "$1" ) . "", $text);
		$text = preg_replace("/\[massive\](.*)\[\/massive\]/", "" . ( !$strip ? "<span style=\"font-size: xx-large;\">$1</span>" : "$1" ) . "", $text);
		$text = preg_replace("/\[color:((?:[0-9a-fA-F]{3}){1,2})\](.*)\[\/color\]/", "" . ( !$strip ? "<span style=\"color: #$1;\">$2</span>" : "$2" ) . "", $text);
		$text = preg_replace("/\[br\]/", "<br />", $text);
		
		if($text) {
			return $text;
		}
		else {
			return false;
		}
	}
	
	function keys_to_content($input, $array) {
		if(is_array($array) && !empty($input)) {
			$keys = array_map(function($value) { return '/\b'.$value.'\b/u'; }, array_keys($array));
			$data = preg_replace($keys, array_values($array), $input);
			return $data;
		}
		else {
			return false;
		}
	}
	
	function get_table_order($select, $sort, $return = '0', $test = '') { // $return = 0: list() = multi-arrays || 1: SQL command variables || 2(column)/3(sort): validation + $test = string of valid inputs (eg. '1,1,0,0,....0')
		$select = preg_replace('/\s+/', '', $select);
		$sort = preg_replace('/\s+/', '', $sort);
		$table = array( // Table names:
			"groupid", "tray", "device", "node", "pool", "name", "lun", "manufacturer", "model", "serial", "capacity", "cache", "rotation", "formfactor", "manufactured", "purchased", "installed", "removed", "warranty", "expires", "comment", "smart_units_read", "smart_units_written", "smart_status", "temp", "powerontime_hours", "powerontime", "loadcycle", "nvme_available_spare", "nvme_available_spare_threshold", "endurance", "firmware"
		);
		$input = array( // User input names - must also match $sort:
			"group", "tray", "device", "node", "pool", "name", "lun", "manufacturer", "model", "serial", "capacity", "cache", "rotation", "formfactor", "manufactured", "purchased", "installed", "removed", "warranty", "expires", "comment", "read", "written", "status", "temp", "powerontime_hours", "powerontime", "loadcycle", "nvme_spare", "nvme_spare_thres", "endurance", "firmware"
		);
		$nice_names = array(
			"Group", "Tray", "Path", "Node", "Pool", "Name", "LUN", "Manufacturer", "Device Model", "S/N", "Capacity", "Cache", "Rotation", "FF", "Manufactured", "Purchased", "Installed", "Removed", "Warranty", "Expires", "Comment", "Read", "Written", "Status", "Temperature", "Powered Hours", "Powered", "Cycles", "Spare", "Spare Threshold", "Endurance", "Firmware"
		);
		$full_names = array(
			"Group", "Tray", "Path", "Node", "Pool Name", "Disk Name", "Logic Unit Number", "Manufacturer", "Device Model", "Serial Number", "Capacity", "Cache Size", "Rotation", "Form Factor", "Manufactured Date", "Purchased Date", "Installed Date", "Removed Date", "Warranty Period", "Warranty Expires", "Comment", "Smart Units Read", "Smart Units Written", "Status", "Temperature", "Power On Time Hours", "Power On Time", "Load Cycle Count", "Available Spare", "Available Spare Threshold", "Endurance", "Firmware Version"
		);
		$input_form = array(
			//                10                  20                  30
			1,1,0,0,0,0,0,0,0,0,0,0,0,0,1,1,1,0,1,0,1,0,0,0,0,0,0,0,0,0,0,0
		);
		
		if($select == "all") {
			$select = implode(",", $input);
			$sort = "asc:group";
		}
		
		if($select == "allowed") {
			$select = implode(",", $input);
			$sort = "asc:".implode(",", $input);
		}
		
		$table_sql = array_combine($table, $input);
		$table_user = array_combine($input, $table);
		$table_names = array_combine($nice_names, $input);
		$table_full = array_combine($full_names, $input);
		$table_forms = array_combine($input, $input_form);
		if($return >= 2 && !empty($test)) {
			$allowed_inputs = explode(",", $test);
			$table_allowed = array_combine($input, $allowed_inputs);
		}
		
		$select = explode(",", $select);
		$sort_dir = explode(":", $sort);
		$sort_col = explode(",", $sort_dir[1]);
		
		$return_table = array();
		$return_names = array();
		$return_full = array();
		$return_forms = array();
		$return_allow_colm = array();
		$return_allow_sort = array();
		$return_errors = array();
		
		if($return != 4) {
			if($return != 3) {
				$arr_length = count($select);
				for($i=0;$i<$arr_length;$i++) {
					$return_table[$i] = array_search($select[$i], $table_sql);
					$return_names[$i] = array_search($select[$i], $table_names);
					$return_full[$i] = array_search($select[$i], $table_full);
					$return_forms[$i] = $table_forms[$select[$i]];
					if($return == 2 && !empty($test)) {
						if($table_allowed[$select[$i]] == 0) {
							$return_allow_colm[$select[$i]] = $table_allowed[$select[$i]];
						}
					}
					if(!$return_table[$i]) {
						$return_errors[] = $select[$i];
					}
				}
			}
			
			if($return != 2) {
				$return_sort = array();
				$arr_length = count($sort_col);
				for($i=0;$i<$arr_length;$i++) {
					$check_sort[$i] = array_search($sort_col[$i], $table_sql);
					$return_sort[] = $table_user[$sort_col[$i]];
					if($return == 3 && !empty($test)) {
						if($table_allowed[$sort_col[$i]] == 0) {
							$return_allow_sort[$sort_col[$i]] = $table_allowed[$sort_col[$i]];
						}
					}
					if(!$check_sort[$i]) {
						$return_errors[] = $sort_col[$i];
					}
				}
				for($i=0;$i<count($return_sort);$i++) {
					$return_sort_str .= $return_sort[$i] . " SORT_" . strtoupper($sort_dir[0]);
					if($return_sort[$i+1]) { $return_sort_str .= ","; }
				}
			}
		}
		else {
			foreach($table_allowed as $table => $value) {
				if($value == 1) {
					$return_table[] = $table;
				}
			}
			sort($return_table);
		}
		
		if($sort_dir[0] != "asc" && $sort_dir[0] != "desc") {
			return "Sort direction is invalid.\n";
		}
		
		switch($return) {
			case 0:
				return [$select, $return_table, $return_names, $return_full, $return_forms]; // user, column, gui, fulltext(hover), forms
				break;
			case 1:
				//return array( // the old way with SQL
				//	"db_select" => $return_table,
				//	"db_sort" => implode(",", $return_sort),
				//	"db_dir" => strtoupper($sort_dir[0])
				//);
				return array(
					"db_select" => $return_table,
					"db_sort" => $return_sort_str,
					"db_dir" => strtoupper($sort_dir[0])
				);
				
				break;
			case 2:
			case 3:
				if($return_errors) {
					return $return_errors;
				}
				else {
					return false;
				}
				break;
			case 4:
				return $return_table;
				
				break;
			default:
				return false;
		}
	}
	
	function list_array($array, $type, $tray = '', $valign = 'middle') {
		if($type == "html") {
			return array(
				"groupid" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px;\">" . stripslashes(htmlspecialchars($array["group_name"])) . "</td>",
				"tray" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px; text-align: right;\">" . $tray . "</td>",
				"device" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px; text-align: right;\">" . $array["device"] . "</td>",
				"pool" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px;\">" . $array["pool"] . "</td>",
				"name" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px;\"><a class=\"none\" style=\"text-decoration: underline;\" href=\"/Main/Device?name=" . $array["name"] . "\">" . $array["name"] . "</a></td>",
				"node" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px;\">" . $array["node"] . "</td>",
				"lun" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px;\">" . $array["lun"] . "</td>",
				"manufacturer" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px;\">" . $array["manufacturer"] . "</td>",
				"model" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px;\">" . $array["model"] . "</td>",
				"serial" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px;\">" . $array["serial"] . "</td>",
				"capacity" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px; text-align: right;\">" . $array["capacity"] . "</td>",
				"cache" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px; text-align: right;\">" . $array["cache"] . "</td>",
				"rotation" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px; text-align: right;\">" . $array["rotation"] . "</td>",
				"formfactor" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px; text-align: right;\">" . $array["formfactor"] . "</td>",
				"smart_status" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px; text-align: center;\">" . $array["smart_status"] . "</td>",
				"temp" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px; text-align: left;\">" . $array["temp"] . " " . ( !empty($array["temp"]) && !empty($array["hotTemp"]) && !empty($array["maxTemp"]) ? "(" . $array["hotTemp"] . "/" . $array["maxTemp"] . ")" : null ) . "</td>",
				"powerontime_hours" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px; text-align: right;\">" . $array["powerontime_hours"] . "</span></td>",
				"powerontime" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px;\">" . $array["powerontime"] . "</span></td>",
				"loadcycle" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px; text-align: right;\">" . $array["loadcycle"] . "</td>",
				"endurance" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px; text-align: right;\">" . $array["endurance"] . "</td>",
				"firmware" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px; text-align: right;\">" . $array["firmware"] . "</td>",
				"smart_units_read" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px; text-align: right;\">" . $array["smart_units_read"] . "</td>",
				"smart_units_written" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px; text-align: right;\">" . $array["smart_units_written"] . "</td>",
				"nvme_available_spare" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px; text-align: right;\">" . $array["nvme_available_spare"] . "</td>",
				"nvme_available_threshold" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px; text-align: right;\">" . $array["nvme_available_threshold"] . "</td>",
				"installed" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px; text-align: right;\">" . $array["installed"] . "</td>",
				"removed" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px; text-align: right;\">" . $array["removed"] . "</td>",
				"manufactured" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px; text-align: right;\">" . $array["manufactured"] . "</td>",
				"purchased" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px; text-align: right;\">" . $array["purchased"] . "</td>",
				"warranty" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px; text-align: right;\">" . $array["warranty"] . "</td>",
				"expires" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px;\">" . $array["expires"] . "</td>",
				"comment" => "<td style=\"vertical-align: " . $valign . "; white-space: nowrap; padding: 0 10px 0 10px;\">" . bscode2html(stripslashes(htmlspecialchars($array["comment"]))) . "</td>"
			);
		}
		else {
			return array(
				"groupid" => "" . stripslashes($array["group_name"]) . "",
				"tray" => "" . $tray . "",
				"device" => "" . $array["device"] . "",
				"pool" => "" . $array["pool"] . "",
				"name" => "" . $array["name"] . "",
				"node" => "" . $array["node"] . "",
				"lun" => "" . $array["lun"] . "",
				"manufacturer" => "" . $array["manufacturer"] . "",
				"model" => "" . $array["model"] . "",
				"serial" => "" . $array["serial"] . "",
				"capacity" => "" . $array["capacity"] . "",
				"cache" => "" . $array["cache"] . "",
				"rotation" => "" . $array["rotation"] . "",
				"formfactor" => "" . $array["formfactor"] . "",
				"smart_status" => "" . $array["smart_status"] . "",
				"temp" => "" . $array["temp"] . "",
				"powerontime_hours" => "" . $array["powerontime_hours"] . "",
				"powerontime" => "" . $array["powerontime"] . "",
				"loadcycle" => "" . $array["loadcycle"] . "",
				"endurance" => "" . $array["endurance"] . "",
				"firmware" => "" . $array["firmware"] . "",
				"smart_units_read" => "" . $array["smart_units_read"] . "",
				"smart_units_written" => "" . $array["smart_units_written"] . "",
				"nvme_available_spare" => "" . $array["nvme_available_spare"] . "",
				"nvme_available_threshold" => "" . $array["nvme_available_threshold"] . "",
				"installed" => "" . $array["installed"] . "",
				"removed" => "" . $array["removed"] . "",
				"manufactured" => "" . $array["manufactured"] . "",
				"purchased" => "" . $array["purchased"] . "",
				"warranty" => "" . $array["warranty"] . "",
				"expires" => "" . $array["expires"] . "",
				"comment" => "" . stripslashes($array["comment"]) . ""
			);
		}
	}
	
	// function from: https://stackoverflow.com/questions/16251625/how-to-create-and-download-a-csv-file-from-php-script
	function array_to_csv_download($array, $filename = "output.tsv", $delimiter="\t") {
		// open raw memory as file so no temp files needed, you might run out of memory though
		$f = fopen('php://memory', 'w'); 
		// loop over the input array
		foreach ($array as $line) { 
			// generate csv lines from the inner arrays
			fputcsv($f, $line, $delimiter); 
		}
		// reset the file pointer to the start of the file
		fseek($f, 0);
		// tell the browser it's going to be a csv file
		//header('Content-Type: text/csv');
		header('Content-Type: application/csv');
		// tell the browser we want to save it instead of displaying it
		header('Content-Disposition: attachment; filename="'.$filename.'";');
		// make php send the generated csv lines to the browser
		fpassthru($f);
	}
	
	function array_to_json_download($data, $filename = "output.json") {
		// $data is expected to be a plain associative/indexed array ready for json_encode()
		// (unlike array_to_csv_download, this does not need a header row baked into the array)
		$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		header('Content-Type: application/json');
		header('Content-Disposition: attachment; filename="'.$filename.'";');
		header('Content-Length: ' . strlen($json));
		echo $json;
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
	
	function human_filesize($bytes, $decimals = 2, $unit = false) {
		if($bytes) {
			if(!$unit) {
				$size = array('iB','kiB','MiB','GiB','TiB','PiB','EiB','ZiB','YiB');
				$bytefactor = 1024;
			}
			else{ 
				$size = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');
				$bytefactor = 1000;
			}
			
			$factor = floor((strlen($bytes) - 1) / 3);
			return sprintf("%.{$decimals}f", $bytes / pow($bytefactor, $factor)) . @$size[$factor];
		}
		else {
			return false;
		}
	}
	
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
	
	function zfs_check($status) {
		if(preg_match("/\bstate\b/i", $status)) {
			return $status;
		}
		else {
			return 0;
		}
	}
	
	function zfs_parser($str) {
		$result = array();
		
		$pools_pattern = "/pool:.*errors:.*(\n\n|$)/Uis";
		preg_match_all($pools_pattern, $str, $pools, PREG_SET_ORDER);
		
		$i = 0;
		while($i < count($pools)) {
			$pattern = "/((pool|state|scan|errors): (.*)?\n|(config):[\s]+(.*)?\s\n)/Uis";
			preg_match_all($pattern, $pools[$i][0], $matches, PREG_SET_ORDER);
			
			foreach($matches as $match) {
				$length = count($match);
				$result[$i][$match[$length-2]] = $match[$length-1];
			}
			
			$i++;
		}
		
		return $result;
	}
	
	function zfs_node($disk, $array) {
		$key = array_search($disk, array_column($array["blockdevices"], 'serial'));
		
		$results = array(
			'name' => $array["blockdevices"][$key]["name"],
			'serial' => $array["blockdevices"][$key]["serial"],
			'path' => $array["blockdevices"][$key]["path"],
			'node' => str_replace("/dev/", "", $array["blockdevices"][$key]["path"])
		);
		
		return $results;
	}
	
	function zfs_disk($disk, $zfs_config, $lsblk_array, $config = 0) {
		$zfs_node = zfs_node($disk, $lsblk_array);
		
		$i_loop = 0;
		while($i_loop < count($zfs_config)) {
			$disks = explode("\n", $zfs_config[$i_loop]["config"]);
			// Array $match: 0 = disk-by-id | 1 = state | 2 = read | 3 = write | 4 = cksum
			for($i=0; $i < count($disks); ++$i) {
				if(preg_match("/(".$disk."|".$zfs_node["node"].")/", $disks[$i])) {
					return ( !empty($config) ? $zfs_config[$i_loop] : explode(":", preg_replace("/\s+/", ":", trim($disks[$i]))) );
				}
			}
			$i_loop++;
		}
	}
	
	function seconds_to_time($seconds, $array = '', $format = '') {
		$seconds = (int)$seconds;
		try {
			$dateTime = new DateTime();
			$dateTime->sub(new DateInterval("PT{$seconds}S"));
			$interval = (new DateTime())->diff($dateTime);
			$pieces = explode(' ', $interval->format('%y %m %d'));
			$intervals = ( ($format == "short") ? ['Y', 'M', 'D'] : [' year', ' month', ' day'] );
			$result = [];
			foreach ($pieces as $i => $value) {
				if (!$value) {
					continue;
				}
				$periodName = $intervals[$i];
				if ($value > 1 && $format != "short") {
					$periodName .= 's';
				}
				$result_arr[$intervals[$i]] = $value;
				$result[] = "{$value}{$periodName}";
			}
			if($array) {
				return $result_arr;
			}
			else {
				if($format == "short") {
					return implode(' ', $result);
				}
				else {
					return implode(', ', $result);
				}
			}
		}
		catch(Exception) {
			return false;
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
	
	function array_duplicates($array) {
		return count(array_filter($array)) !== count(array_unique(array_filter($array)));
	}
	
	function recursive_array_search($needle,$haystack) { // from php.net: buddel
		if(is_array($haystack)) {
			foreach($haystack as $key=>$value) {
				$current_key=$key;
				if($needle===$value OR (is_array($value) && recursive_array_search($needle,$value) !== false)) {
					return $current_key;
				}
			}
		}
		return false;
	}
	
	function sort_array() { // from php.net: jimpoz
		$args = func_get_args();
		$data = array_shift($args);
		foreach ($args as $n => $field) {
			if (is_string($field)) {
				$tmp = array();
				foreach ($data as $key => $row) {
					$tmp[$key] = $row[$field];
					$args[$n] = $tmp;
				}
			}
		}
		$args[] = &$data;
		call_user_func_array('array_multisort', $args);
		return array_pop($args);
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
	
	function use_stylesheet($css = '') {
		if(is_file(EMHTTP_ROOT . "" . DISKLOCATION_PATH . "/pages/styles/" . $css . "")) {
			unlink(EMHTTP_ROOT . "" . DISKLOCATION_PATH . "/pages/styles/signals.css");
			symlink(EMHTTP_ROOT . "" . DISKLOCATION_PATH . "/pages/styles/" . $css . "", EMHTTP_ROOT . "" . DISKLOCATION_PATH . "/pages/styles/signals.css");
			touch(EMHTTP_ROOT . "" . DISKLOCATION_PATH . "/pages/styles/signals.css", time(), time());
			return EMHTTP_ROOT . "" . DISKLOCATION_PATH . "/pages/styles/" . $css . "";
		}
		else {
			return false;
		}
	}
	
	// lsscsi -bg
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
	
	function slugify_brand_name($name) {
		// Normalizes a brand name into the filename convention used under manufacturers/,
		// e.g. "Western Digital" -> "westerndigital", "SK hynix" -> "skhynix".
		$slug = strtolower(trim($name));
		$slug = preg_replace('/[^a-z0-9]+/', '', $slug);
		return $slug;
	}
	
	function detect_drive_brand($manufacturer_raw, $model) {
		// Best-effort brand detection for the tray map's optional manufacturer logo (see
		// get_drive_brand_logo()). $manufacturer_raw is smartctl's own model_family lookup,
		// which is fairly reliable when present but is curated per drive family and often
		// comes back empty - notably for many SSDs, which frequently identify themselves
		// via a generic/OEM controller rather than a brand-specific one. In that case we
		// fall back to matching common model-number prefixes. Neither source is exhaustive,
		// which is exactly why this can be overridden per-device on the Tray Allocations
		// page rather than relied on blindly.
		$haystack = strtolower(($manufacturer_raw ?? "") . " " . ($model ?? ""));
		if(trim($haystack) === "") {
			return null;
		}
		
		// [brand slug => array of substrings/prefixes to match, checked in order]
		$brand_patterns = array(
			"westerndigital"	=> array("western digital", "wdc ", "^wd"),
			"seagate"			=> array("seagate", "^st[0-9]"),
			"toshiba"			=> array("toshiba", "^dt0", "^mg0", "^hdwd", "^hdwn"),
			"hgst"				=> array("hgst", "^huh", "^hus"),
			"samsung"			=> array("samsung", "^mz", "^pm"),
			"crucial"			=> array("crucial", "^ct[0-9].*ssd"),
			"micron"			=> array("micron", "^mtfd"),
			"sandisk"			=> array("sandisk", "^sds"),
			"kingston"			=> array("kingston", "^sa400", "^skc", "^suv", "^ov[0-9]"),
			"intel"				=> array("intel", "^ssdsc", "^ssdpe"),
			"adata"				=> array("adata", "^asu", "^su[0-9]"),
			"corsair"			=> array("corsair", "^cssd"),
			"skhynix"			=> array("sk hynix", "hynix", "^hfs", "^pc[0-9]"),
			"lexar"				=> array("lexar", "^ln[dm]"),
			"teamgroup"			=> array("team group", "teamgroup", "^t-force"),
			"patriot"			=> array("patriot", "^psf", "^pss"),
			"siliconpower"		=> array("silicon power", "^sp[0-9]"),
			"transcend"			=> array("transcend", "^ts[0-9].*ssd"),
			"pny"				=> array("pny", "^cs9"),
			"fujitsu"			=> array("fujitsu", "^mja"),
			"hitachi"			=> array("hitachi", "^hds"),
		);
		
		foreach($brand_patterns as $slug => $patterns) {
			foreach($patterns as $pattern) {
				if($pattern[0] === "^") {
					if(preg_match('/' . substr($pattern, 1) . '/i', trim(strtolower($model ?? "")))) {
						return $slug;
					}
				}
				else if(strpos($haystack, $pattern) !== false) {
					return $slug;
				}
			}
		}
		
		return null;
	}
	
	function is_valid_image_url($url) {
		// Deliberately strict: https only, real host, path ending in a known image
		// extension. This exists so a per-device override can optionally point at a
		// manufacturer-hosted logo directly (hotlinked, not stored in this repo) rather
		// than a local manufacturers/ file - see get_drive_brand_logo(). Rejects
		// javascript:/data:/http: and anything without an image extension, since this
		// value ends up directly in an <img src="">.
		if(!is_string($url) || $url === "") {
			return false;
		}
		if(!preg_match('#^https://#i', $url)) { // https only - no plain http, no other schemes
			return false;
		}
		if(!filter_var($url, FILTER_VALIDATE_URL)) {
			return false;
		}
		$host = parse_url($url, PHP_URL_HOST);
		if(empty($host)) {
			return false;
		}
		$path = parse_url($url, PHP_URL_PATH);
		if(empty($path)) {
			return false;
		}
		$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
		$allowed_ext = array('svg', 'png', 'jpg', 'jpeg', 'webp', 'gif');
		return in_array($ext, $allowed_ext, true);
	}
	
	function get_drive_brand_logo($manufacturer_raw, $model, $manufacturer_override = null) {
		// Looks for an SVG logo the admin (or a contributor to their fork) has placed under
		// pages/styles/manufacturers/{slug}.svg. Disk Location does not ship any logos
		// itself - see pages/styles/manufacturers/README.md for why, and how to add your
		// own. Returns "" if no matching file exists, so callers can fall back gracefully
		// (e.g. to the generic HDD/SSD/NVMe type icon from get_drive_type_icon()).
		//
		// The override also accepts a direct https image URL instead of a brand name -
		// e.g. a manufacturer's own hosted logo - rendered as a hotlink rather than a
		// file stored in this repo. This plugin never picks that URL itself; it's
		// entirely the admin's choice per device. See is_valid_image_url() for exactly
		// what's accepted, and manufacturers/README.md for the tradeoffs of hotlinking.
		if(!empty($manufacturer_override) && is_valid_image_url($manufacturer_override)) {
			$host = parse_url($manufacturer_override, PHP_URL_HOST);
			$label = "Manufacturer logo (" . $host . ")";
			return "<a class='info' style=\"margin: 0;\"><img src=\"" . htmlspecialchars($manufacturer_override) . "\" referrerpolicy=\"no-referrer\" loading=\"lazy\" style=\"height: 13px; width: auto; vertical-align: middle;\" alt=\"" . htmlspecialchars($label) . "\" /><span>" . htmlspecialchars($label) . "</span></a>";
		}
		
		$slug = ( !empty($manufacturer_override) ? slugify_brand_name($manufacturer_override) : detect_drive_brand($manufacturer_raw, $model) );
		if(empty($slug)) {
			return "";
		}
		
		$logo_file_disk = "/usr/local/emhttp" . DISKLOCATION_PATH . "/pages/styles/manufacturers/" . $slug . ".svg";
		if(!file_exists($logo_file_disk)) {
			return "";
		}
		
		$logo_url = DISKLOCATION_PATH . "/pages/styles/manufacturers/" . $slug . ".svg";
		$label = ( !empty($manufacturer_override) ? $manufacturer_override : ucfirst($slug) );
		return "<a class='info' style=\"margin: 0;\"><img src=\"" . htmlspecialchars($logo_url) . "\" style=\"height: 13px; width: auto; vertical-align: middle;\" alt=\"" . htmlspecialchars($label) . "\" /><span>" . htmlspecialchars($label) . "</span></a>";
	}
	
	function get_drive_type_icon($rotation) {
		// Reuses the same $rotation convention as get_smart_rotation(): -2 = NVMe SSD,
		// -1 = SATA/SAS SSD, 0/null = unknown, positive = HDD at that RPM. Returns an
		// inline SVG (rather than a font-icon class) so it renders identically regardless
		// of which icon font Unraid's webGUI happens to bundle. Uses a plain native
		// title="" tooltip rather than the '.info' class the other tray status icons use -
		// see the note at the return statement below for why.
		//
		// These are original filled glyphs (not any vendor/manufacturer/org logo - that
		// kind of artwork is trademarked and we deliberately don't reproduce it here, see
		// pages/styles/manufacturers/README.md), colored to stand out against the tray
		// tile backgrounds rather than blend into the surrounding text the way a
		// currentColor outline did.
		//
		// Rendered at 28x28 (up from the original 13x13) and placed by devices.php as an
		// absolutely positioned badge inside the device-info column
		// (.flex-container-middle_*), top-right next to the tray title - not inline in
		// the <br />-stacked status-icon column with the ~13-16px orbs (too small there
		// once filled/colored). `position: relative` for this is scoped to that
		// device-info column specifically, not the shared tray tile div - putting it
		// there instead changed the containing block (and so the on-hover position) of
		// every other icon's '.info' tooltip span in that tile, not just this one's.
		// devices.php also sets min-width: 0 and overflow-wrap: break-word on that same
		// column: without them, an unbreakable run of text (e.g. a serial number) plus
		// this icon's reserved width can together exceed a narrow tray's available space,
		// and the column - along with this icon anchored to its edge - renders outside
		// the tray tile's visible bounds instead of shrinking/wrapping to fit.
		//
		// Colors are deliberately outside the red/yellow/green/grey already used by the
		// temp/SMART status orbs, so it can't be misread as a status.
		switch(true) {
			case ($rotation == -2): // NVMe - stylized M.2 stick: body, two chips, four contact pins
				$svg = "<svg viewBox='0 0 32 32' width='28' height='28' xmlns='http://www.w3.org/2000/svg'><rect x='2' y='9' width='28' height='14' rx='2' fill='#2196F3'/><rect x='6' y='13' width='8' height='6' rx='1' fill='#BBDEFB'/><rect x='17' y='13' width='8' height='6' rx='1' fill='#BBDEFB'/><rect x='6' y='23' width='3' height='4' fill='#2196F3'/><rect x='11' y='23' width='3' height='4' fill='#2196F3'/><rect x='16' y='23' width='3' height='4' fill='#2196F3'/><rect x='21' y='23' width='3' height='4' fill='#2196F3'/></svg>";
				$label = "NVMe SSD";
				break;
			case ($rotation == -1): // SATA/SAS SSD - drive casing with corner screws and a label strip
				$svg = "<svg viewBox='0 0 32 32' width='28' height='28' xmlns='http://www.w3.org/2000/svg'><rect x='2' y='3' width='28' height='24' rx='2' fill='#26A69A'/><circle cx='5.5' cy='6.5' r='1' fill='#004D40'/><circle cx='26.5' cy='6.5' r='1' fill='#004D40'/><circle cx='5.5' cy='23.5' r='1' fill='#004D40'/><circle cx='26.5' cy='23.5' r='1' fill='#004D40'/><rect x='7' y='9' width='18' height='2.5' rx='1' fill='#B2DFDB'/><rect x='7' y='14' width='18' height='2.5' rx='1' fill='#B2DFDB'/><rect x='7' y='19' width='12' height='2.5' rx='1' fill='#B2DFDB'/></svg>";
				$label = "SSD";
				break;
			case (!empty($rotation) && $rotation > 0): // HDD - drive casing with a spinning-platter ring motif
				$svg = "<svg viewBox='0 0 32 32' width='28' height='28' xmlns='http://www.w3.org/2000/svg'><rect x='2' y='4' width='28' height='24' rx='3' fill='#78909C'/><circle cx='16' cy='15' r='7' fill='none' stroke='#CFD8DC' stroke-width='2'/><circle cx='16' cy='15' r='2.5' fill='#CFD8DC'/><rect x='7' y='24' width='18' height='2.5' rx='1' fill='#CFD8DC'/></svg>";
				$label = $rotation . " RPM";
				break;
			default: // unknown - don't show an icon at all, consistent with the other status icons when data is unavailable
				return "";
		}
		// Deliberately a plain native title="" tooltip, not Unraid's own '.info' class (the
		// pattern every other tray status icon uses). That pattern misbehaved - on-hover
		// jumping - once this icon moved out of the status-icon column into the device-info
		// column, in a way this project can't debug further without visibility into
		// Unraid's own core tooltip JS/CSS (not part of this plugin/repo). A native
		// tooltip is fully browser-standard and can't have that failure mode.
		return "<span title=\"Drive type: " . htmlspecialchars($label) . "\" style=\"display: inline-block; vertical-align: middle;\">" . $svg . "</span>";
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
	
	function check_smart_files() { // return true if files found
		if(file_exists(DISKLOCATION_TMP_PATH . "/smart")) {
			$dir = array_diff(scandir(DISKLOCATION_TMP_PATH . "/smart"), array('..', '.'));
			return ( empty($dir) ? false : true );
		}
		else return false;
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
?>
