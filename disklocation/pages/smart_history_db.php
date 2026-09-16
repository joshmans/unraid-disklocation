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
	 *  smart_history_db.php - a new, purpose-built SQLite store for time-series SMART
	 *  readings (temp, power-on hours, sector counts, wear level, overall status), one row
	 *  per device per completed full SMART scan. This is intentionally separate from the
	 *  legacy SQLite schema in sqlite_tables.php: that one is deprecated migration
	 *  scaffolding only (pre-flat-file-config installs), never queried by the live code
	 *  path, and was never a time series to begin with - it only ever held the latest
	 *  values, the same as devices.json does today.
	 */

	require_once("variables.php");

	define("SMART_HISTORY_DB_DEFAULT", UNRAID_CONFIG_PATH . "" . DISKLOCATION_PATH . "/smart_history.sqlite");
	define("SMART_HISTORY_DB_VERSION", 1);

	function smart_history_db_path() {
		global $smart_history_db_path;

		return ( !empty($smart_history_db_path) ? $smart_history_db_path : SMART_HISTORY_DB_DEFAULT );
	}

	class SmartHistoryDB extends SQLite3 {
		function __construct($path) {
			if(!is_dir(dirname($path))) {
				mkdir(dirname($path), 0755, true);
			}
			$this->open($path);
			$this->busyTimeout(5000);
		}
	}

	// Lazily opens (and, on first use, creates) the SMART history database. Cached per
	// request/process so a full scan's per-device loop doesn't reopen the file for every
	// drive; a changed $smart_history_db_path (settings save) still opens a fresh handle.
	function smart_history_connect() {
		static $db = null;
		static $connected_path = null;

		$path = smart_history_db_path();

		if($db === null || $connected_path !== $path) {
			$db = new SmartHistoryDB($path);

			$db->exec("
				CREATE TABLE IF NOT EXISTS smart_history (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					hash TEXT NOT NULL,
					scanned_at TEXT NOT NULL,
					temp INTEGER,
					power_on_hours INTEGER,
					reallocated_sectors INTEGER,
					pending_sectors INTEGER,
					uncorrectable_sectors INTEGER,
					wear_level INTEGER,
					smart_status TEXT
				);
			");
			$db->exec("CREATE INDEX IF NOT EXISTS idx_smart_history_hash_time ON smart_history(hash, scanned_at);");
			$db->exec("PRAGMA user_version = " . SMART_HISTORY_DB_VERSION . ";");

			$connected_path = $path;
		}

		return $db;
	}

	// Pulls the handful of metrics we track over time out of a decoded
	// `smartctl -x --json` array. Attribute IDs 5/197/198 (Reallocated_Sector_Ct,
	// Current_Pending_Sector, Offline_Uncorrectable) match Unraid's own default
	// smEvents warning attributes (see $get_default_smEvents in variables.php), so
	// history can only ever surface what the plugin already treats as worth a warning.
	function smart_history_extract_metrics($smart_array) {
		$metrics = array(
			"temp" => null,
			"power_on_hours" => null,
			"reallocated_sectors" => null,
			"pending_sectors" => null,
			"uncorrectable_sectors" => null,
			"wear_level" => null,
			"smart_status" => null
		);

		if(!is_array($smart_array)) {
			return $metrics;
		}

		$metrics["temp"] = $smart_array["temperature"]["current"] ?? ($smart_array["nvme_smart_health_information_log"]["temperature"] ?? null);
		$metrics["power_on_hours"] = $smart_array["power_on_time"]["hours"] ?? null;

		if(isset($smart_array["smart_status"]["passed"])) {
			$metrics["smart_status"] = ( $smart_array["smart_status"]["passed"] ? "PASSED" : "FAILED" );
		}

		if(isset($smart_array["ata_smart_attributes"]["table"]) && is_array($smart_array["ata_smart_attributes"]["table"])) {
			foreach($smart_array["ata_smart_attributes"]["table"] as $attribute) {
				switch($attribute["id"] ?? null) {
					case 5:		// Reallocated_Sector_Ct
						$metrics["reallocated_sectors"] = $attribute["raw"]["value"] ?? null;
						break;
					case 197:	// Current_Pending_Sector
						$metrics["pending_sectors"] = $attribute["raw"]["value"] ?? null;
						break;
					case 198:	// Offline_Uncorrectable
						$metrics["uncorrectable_sectors"] = $attribute["raw"]["value"] ?? null;
						break;
				}
			}
		}

		// SSD wear level: ATA "Percentage Used Endurance Indicator" (same source page_info/
		// cronjob already read for the "endurance" field), falling back to the NVMe log.
		if(isset($smart_array["ata_device_statistics"]["pages"]) && is_array($smart_array["ata_device_statistics"]["pages"])) {
			foreach($smart_array["ata_device_statistics"]["pages"] as $page) {
				if(($page["name"] ?? null) == "Solid State Device Statistics" && isset($page["table"]) && is_array($page["table"])) {
					foreach($page["table"] as $entry) {
						if(($entry["name"] ?? null) == "Percentage Used Endurance Indicator") {
							$metrics["wear_level"] = 100 - $entry["value"];
						}
					}
				}
			}
		}

		if(isset($smart_array["nvme_smart_health_information_log"]["percentage_used"])) {
			$metrics["wear_level"] = 100 - $smart_array["nvme_smart_health_information_log"]["percentage_used"];
		}

		return $metrics;
	}

	function smart_history_bind($stmt, $param, $value, $type) {
		if($value === null) {
			$stmt->bindValue($param, null, SQLITE3_NULL);
		}
		else {
			$stmt->bindValue($param, $value, $type);
		}
	}

	// Insert one history row for $hash from an already-decoded smartctl JSON array.
	// Called once per device at the end of a completed full SMART scan (cronjob.php).
	function smart_history_insert($hash, $smart_array) {
		$metrics = smart_history_extract_metrics($smart_array);

		$db = smart_history_connect();

		$stmt = $db->prepare("
			INSERT INTO smart_history (hash, scanned_at, temp, power_on_hours, reallocated_sectors, pending_sectors, uncorrectable_sectors, wear_level, smart_status)
			VALUES (:hash, :scanned_at, :temp, :power_on_hours, :reallocated_sectors, :pending_sectors, :uncorrectable_sectors, :wear_level, :smart_status)
		");

		if(!$stmt) {
			return false;
		}

		smart_history_bind($stmt, ":hash", $hash, SQLITE3_TEXT);
		// UTC "YYYY-MM-DD HH:MM:SS", matching SQLite's own datetime('now') format so the
		// retention pruning below can compare/filter without any timezone ambiguity.
		smart_history_bind($stmt, ":scanned_at", gmdate("Y-m-d H:i:s"), SQLITE3_TEXT);
		smart_history_bind($stmt, ":temp", $metrics["temp"], SQLITE3_INTEGER);
		smart_history_bind($stmt, ":power_on_hours", $metrics["power_on_hours"], SQLITE3_INTEGER);
		smart_history_bind($stmt, ":reallocated_sectors", $metrics["reallocated_sectors"], SQLITE3_INTEGER);
		smart_history_bind($stmt, ":pending_sectors", $metrics["pending_sectors"], SQLITE3_INTEGER);
		smart_history_bind($stmt, ":uncorrectable_sectors", $metrics["uncorrectable_sectors"], SQLITE3_INTEGER);
		smart_history_bind($stmt, ":wear_level", $metrics["wear_level"], SQLITE3_INTEGER);
		smart_history_bind($stmt, ":smart_status", $metrics["smart_status"], SQLITE3_TEXT);

		return $stmt->execute() !== false;
	}

	// Deletes rows older than $retention_years. 0 (or empty) disables pruning - "forever".
	function smart_history_prune($retention_years) {
		if(empty($retention_years) || $retention_years <= 0) {
			return true;
		}

		$db = smart_history_connect();

		$stmt = $db->prepare("DELETE FROM smart_history WHERE scanned_at < datetime('now', :cutoff)");
		if(!$stmt) {
			return false;
		}
		$stmt->bindValue(":cutoff", "-" . intval($retention_years) . " years", SQLITE3_TEXT);

		return $stmt->execute() !== false;
	}
?>
