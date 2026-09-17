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
	 *  functions_core.php - generic helpers with no domain coupling to anything else in
	 *  this plugin: debug/config-file I/O, table-order/sort/array utilities, and basic
	 *  formatting (bbcode-ish text, byte sizes, durations). Split out of functions.php
	 *  (see ROADMAP.md) along with the other functions_*.php files, purely to make that
	 *  file's original 49 top-level functions navigable by topic - no logic changed.
	 */
	
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
?>
