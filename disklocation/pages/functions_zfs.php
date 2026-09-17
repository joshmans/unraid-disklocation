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
	 *  functions_zfs.php - `zpool status` output parsing. Split out of functions.php
	 *  (see ROADMAP.md) along with the other functions_*.php files - no logic changed.
	 */
	
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
?>
