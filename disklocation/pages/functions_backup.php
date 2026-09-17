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
	 *  functions_backup.php - the settings/database backup, restore, and obsolete-file
	 *  cleanup logic behind page_system.php's "Backup / Restore" section. Split out of
	 *  page_system.php (see ROADMAP.md), which otherwise mixes these with its own page-
	 *  render logic despite nothing else in the plugin calling them - no logic changed.
	 */
	
	function obsolete_files($array, $check = true) {
		foreach($array as $file => $type) {
			if(file_exists($file) && $check === false) {
				if($type == "dir") {
					$iterator = new RecursiveIteratorIterator(
						new RecursiveDirectoryIterator($file, 
						RecursiveDirectoryIterator::SKIP_DOTS),
						RecursiveIteratorIterator::CHILD_FIRST
					);
					foreach ($iterator as $foundfile) {
						if ($foundfile->isDir()) {
							rmdir($foundfile->getPathname());
						} else {
							unlink($foundfile->getPathname());
						}
					}
					rmdir($file);
				}
				else {
					unlink($file);
				}
			}
			else if(file_exists($file) && $check === true) {
				$results[] = $file;
			}
		}
		if(!empty($results) && is_array($results)) {
			return $results;
		}
	}
	
	function compress_file($src_array, $dst) {
		$data = array();
		
		for($i=0; $i < count($src_array); ++$i) {
			$filename = str_replace(UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/", "", $src_array[$i]);
			$data[$filename] = file_get_contents($src_array[$i]);
		}
		$json = json_encode($data);
		$data = gzencode($json, 9);
		file_put_contents($dst, $data);
	}
	
	function decompress_file($src, $dst) {
		$data = file_get_contents($src);
		$gzdata = gzdecode($data);
		
		if(str_contains($src, "sqlite")) {
			file_put_contents($dst, $gzdata);
		}
		else {
			$json = json_decode($gzdata, true);
			foreach($json as $filename => $content) {
				if(!file_exists(dirname($dst . $filename))) { mkdir(dirname($dst . $filename), 0777, 1); }
				file_put_contents($dst . $filename, $json[$filename]);
			}
		}
	}
	
	function database_backup($files, $destination, $backup_filename = 'disklocation') {
		$files = explode(",", $files);
		for($i=0; $i < count($files); ++$i) {
			if(file_exists($files[$i])) {
				$file[] = $files[$i];
			}
		}
		
		if(!empty($file)) {
			$datetime = date("Ymd-His");
			mkdir($destination . "/" . $datetime, 0700, true);
			
			if(in_array(DISKLOCATION_DB, $file)) {
				compress_file($file, $destination . "/" . $datetime . "/" . $backup_filename . ".sqlite.gz");
			}
			else {
				compress_file($file, $destination . "/" . $datetime . "/" . $backup_filename . ".json.gz");
			}
		}
		else {
			return "No files available.";
		}
	}
	function database_restore($file, $destination) {
		if(str_contains($file, "sqlite")) {
			$destination = DISKLOCATION_DB;
			// must delete new json files if restoring old SQLite DB:
			( file_exists(DISKLOCATION_CONF) ? unlink(DISKLOCATION_CONF) : false );
			( file_exists(DISKLOCATION_DEVICES) ? unlink(DISKLOCATION_DEVICES) : false );
			( file_exists(DISKLOCATION_GROUPS) ? unlink(DISKLOCATION_GROUPS) : false );
			( file_exists(DISKLOCATION_LOCATIONS) ? unlink(DISKLOCATION_LOCATIONS) : false );
		}
		
		if(file_exists($file)) {
			decompress_file($file, $destination);
		}
		else {
			return "Database does not exist.";
		}
	}
	function disklocation_system($type, $operation, $file = "", $unknown = false) {
		$array = array();
		$found_unknown = array();
		
		if($type == "backup") {
			if($operation == "list") {
				$i=0;
				if(file_exists(UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/backup")) {
					$backup_dir = array_diff(scandir(UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/backup/"), array('..', '.'));
					foreach($backup_dir as $contents) {
						if(is_dir(UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/backup/" . $contents)) {
							$backup_dir_time = array_diff(scandir(UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/backup/" . $contents), array('..', '.'));
							foreach($backup_dir_time as $dir => $file) {
								if(strstr($file, ".gz")) {
									$array[$i]["file"] = UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/backup/" . $contents . "/" . $file;
									$array[$i]["size"] = filesize(UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/backup/" . $contents . "/" . $file);
									$i++;
								}
								else {
									$found_unknown[UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/backup/" . $contents . "/" . $file] = (is_dir(UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/backup/" . $contents . "/" . $file) ? "dir" : "file" );
									$found_unknown[UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/backup/" . $contents] = (is_dir(UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/backup/" . $contents) ? "dir" : "file" );
								}
							}
							if(empty($backup_dir_time)) {
								$found_unknown[UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/backup/" . $contents] = (is_dir(UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/backup/" . $contents) ? "dir" : "file" );
							}
						}
						else {
							$found_unknown[UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/backup/" . $contents] = (is_dir(UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/backup/" . $contents) ? "dir" : "file" );
						}
					}
				}
				
				if($unknown === true) {
					return (is_array($found_unknown) && !empty($found_unknown) ? $found_unknown : null);
				}
				else {
					if(is_array($array) && !empty($array)) {
						return $array;
					}
					else {
						return false;
					}
				}
				
			}
			if($operation == "restore" && !empty($file)) {
				if(file_exists($file)) {
					database_restore($file, UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/");
				}
			}
			if($operation == "delete" && !empty($file)) {
				foreach($file as $filename) {
					if(file_exists($filename)) {
						unlink($filename);
						( is_dir(str_replace("disklocation.sqlite.gz", "", $filename)) ? rmdir(str_replace("disklocation.sqlite.gz", "", $filename)) : rmdir(str_replace("disklocation.json.gz", "", $filename)) );
					}
				}
			}
			if($operation == "delete_all") {
				array_map('unlink', glob("" . UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/backup/*/*.gz"));
				array_map('rmdir', glob("" . UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/backup/*"));
			}
		}
		
		if($type == "database_lock") {
			if($operation == "list") {
				if(file_exists(DISKLOCATION_LOCK_FILE)) {
					return true;
				}
				else {
					return false;
				}
			}
			if($operation == "delete") {
				unlink(DISKLOCATION_LOCK_FILE);
			}
		}
		
		if($type == "debug") {
			if($operation == "enable") {
				touch(DISKLOCATION_TMP_PATH . "/.debug");
			}
			if($operation == "disable") {
				unlink(DISKLOCATION_TMP_PATH . "/.debug");
			}
			if($operation == "list") {
				if(file_exists("" . DISKLOCATION_TMP_PATH . "/disklocation.log")) {
					return filesize("" . DISKLOCATION_TMP_PATH . "/disklocation.log");
				}
				else {
					return false;
				}
			}
			if($operation == "delete") {
				unlink("" . DISKLOCATION_TMP_PATH . "/disklocation.log");
			}
		}
		
		if($type == "reset") {
			if($operation == "temp" || $operation == "all" || $operation == "wipe") {
				if(file_exists(DISKLOCATION_TMP_PATH . "/smart")) {
					array_map('unlink', glob(DISKLOCATION_TMP_PATH . "/smart/*.json"));
					rmdir(DISKLOCATION_TMP_PATH . "/smart");
				}
				file_exists(DISKLOCATION_TMP_PATH . "/powermode.json") ? unlink(DISKLOCATION_TMP_PATH . "/powermode.json") : null;
				file_exists(DISKLOCATION_TMP_PATH . "/phyloc.json") ? unlink(DISKLOCATION_TMP_PATH . "/phyloc.json") : null;
				file_exists(DISKLOCATION_TMP_PATH . "/lsblk.json") ? unlink(DISKLOCATION_TMP_PATH . "/lsblk.json") : null;
				file_exists(DISKLOCATION_TMP_PATH . "/zpool_status.dat") ? unlink(DISKLOCATION_TMP_PATH . "/zpool_status.dat") : null;
			}
			if($operation == "settings" || $operation == "all" || $operation == "wipe") {
				file_exists(DISKLOCATION_CONF) ? unlink(DISKLOCATION_CONF) : null;
			}
			if($operation == "groups" || $operation == "all" || $operation == "wipe") {
				file_exists(DISKLOCATION_GROUPS) ? unlink(DISKLOCATION_GROUPS) : null;
				file_exists(DISKLOCATION_LOCATIONS) ? unlink(DISKLOCATION_LOCATIONS) : null;
			}
			if($operation == "locations" || $operation == "all" || $operation == "wipe") {
				file_exists(DISKLOCATION_LOCATIONS) ? unlink(DISKLOCATION_LOCATIONS) : null;
			}
			if($operation == "physical" || $operation == "all" || $operation == "wipe") {
				file_exists(DISKLOCATION_PHYSICAL) ? unlink(DISKLOCATION_PHYSICAL) : null;
			}
			if($operation == "devices" || $operation == "all" || $operation == "wipe") {
				file_exists(DISKLOCATION_DEVICES) ? unlink(DISKLOCATION_DEVICES) : null;
			}
			if($operation == "wipe") {
				if(file_exists(DISKLOCATION_TMP_PATH . "/smart")) {
					array_map('unlink', glob(DISKLOCATION_TMP_PATH . "/smart/*.json"));
					rmdir(DISKLOCATION_TMP_PATH . "/smart");
				}
				if(file_exists(UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/backup")) {
					array_map('unlink', glob("" . UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/backup/*/*.gz"));
					array_map('rmdir', glob("" . UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/backup/*"));
					rmdir(UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/backup");
				}
				if(file_exists(UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/benchmark")) {
					array_map('unlink', glob(UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/benchmark/*.json"));
					rmdir(UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/benchmark");
				}
			}
		}

	}
?>
