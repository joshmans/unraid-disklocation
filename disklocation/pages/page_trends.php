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
	 *  page_trends.php - charts the SMART history captured by smart_history_db.php (temp,
	 *  wear level, sector counts) per device. Built on top of $devices (populated earlier
	 *  in this same request by devices.php/array_devices.php - see disklocation_devices.page,
	 *  Menu position 1, which always runs first).
	 */

	$temp_unit = ( !empty($GLOBALS["display"]["unit"]) ? $GLOBALS["display"]["unit"] : 'C' );

	$trend_devices = array();

	if(is_array($devices)) {
		foreach($devices as $hash => $data) {
			$raw = $data["raw"];
			$rows = smart_history_get($hash);

			$label = trim(( !empty($raw["name"]) ? $raw["name"] : $raw["node"] ) . " - " . $raw["model"] . " (" . $raw["serial"] . ")");

			$timestamps = array();
			$temp = array();
			$wear = array();
			$reallocated = array();
			$pending = array();
			$uncorrectable = array();
			$has_temp = false;
			$has_wear = false;
			$has_sectors = false;

			foreach($rows as $row) {
				$timestamps[] = $row["scanned_at"];

				if($row["temp"] !== null) {
					$temp[] = round(temperature_conv((float)$row["temp"], 'C', $temp_unit), 1);
					$has_temp = true;
				}
				else {
					$temp[] = null;
				}

				$wear[] = ( $row["wear_level"] !== null ? (int)$row["wear_level"] : null );
				$has_wear = ( $has_wear || $row["wear_level"] !== null );

				$reallocated[] = ( $row["reallocated_sectors"] !== null ? (int)$row["reallocated_sectors"] : null );
				$pending[] = ( $row["pending_sectors"] !== null ? (int)$row["pending_sectors"] : null );
				$uncorrectable[] = ( $row["uncorrectable_sectors"] !== null ? (int)$row["uncorrectable_sectors"] : null );
				$has_sectors = ( $has_sectors || $row["reallocated_sectors"] !== null || $row["pending_sectors"] !== null || $row["uncorrectable_sectors"] !== null );
			}

			$trend_devices[$hash] = array(
				"label" => $label,
				"count" => count($rows),
				"latest_status" => ( !empty($rows) ? end($rows)["smart_status"] : null ),
				"timestamps" => $timestamps,
				"temp" => $temp,
				"tempUnit" => $temp_unit,
				"wear" => $wear,
				"reallocated" => $reallocated,
				"pending" => $pending,
				"uncorrectable" => $uncorrectable,
				"hasTemp" => $has_temp,
				"hasWear" => $has_wear,
				"hasSectors" => $has_sectors
			);
		}
	}

	// sort by label so the page order matches other tables (device name / model), not hash
	uasort($trend_devices, function($a, $b) { return strnatcasecmp($a["label"], $b["label"]); });

	$any_history = false;
	foreach($trend_devices as $d) {
		if($d["count"] >= 2) { $any_history = true; break; }
	}
?>
<link type="text/css" rel="stylesheet" href="<?autov("" . DISKLOCATION_PATH . "/pages/styles/help.css")?>">
<style type="text/css">
	<?php include("/usr/local/emhttp/plugins/disklocation/pages/styles/disk.css.php"); ?>
</style>
<script type="text/javascript" src="<?autov("" . DISKLOCATION_PATH . "/pages/script/chart.umd.min.js")?>"></script>

<blockquote class="inline_help" style="white-space: wrap;">
	Temperature, SSD/NVMe wear level, and reallocated/pending/uncorrectable sector counts over time, one full row per device per completed SMART scan (~twice daily). Retention and the database location are configurable under "Configuration".
</blockquote>

<?php if(!$any_history) { ?>
	<table><tr><td style="padding: 10px 10px 10px 10px;">
		<h1>Not enough SMART history yet</h1>
		<p>
			History is captured automatically during the twice-daily full SMART scan cron job, so a new install (or a fresh database after changing the path/retention settings) starts empty. Check back after the next scheduled scan, or trigger one manually from "System".
		</p>
	</td></tr></table>
<?php } else { ?>
	<div id="dl-trend-grid" class="dl-trend-grid">
		<?php foreach($trend_devices as $hash => $d) { ?>
			<div class="dl-trend-card">
				<h3><?php echo htmlspecialchars($d["label"]); ?></h3>
				<?php if($d["count"] < 2) { ?>
					<p class="dl-trend-empty">Not enough history yet (<?php echo $d["count"]; ?> scan<?php echo ($d["count"] == 1 ? "" : "s"); ?> recorded) - check back after a couple more full scans.</p>
				<?php } else { ?>
					<?php if($d["hasTemp"]) { ?>
						<canvas class="dl-trend-canvas" data-dl-chart="temp" data-dl-hash="<?php echo htmlspecialchars($hash); ?>"></canvas>
					<?php } ?>
					<?php if($d["hasWear"]) { ?>
						<canvas class="dl-trend-canvas" data-dl-chart="wear" data-dl-hash="<?php echo htmlspecialchars($hash); ?>"></canvas>
					<?php } ?>
					<?php if($d["hasSectors"]) { ?>
						<canvas class="dl-trend-canvas" data-dl-chart="sectors" data-dl-hash="<?php echo htmlspecialchars($hash); ?>"></canvas>
					<?php } ?>
					<?php if(!$d["hasTemp"] && !$d["hasWear"] && !$d["hasSectors"]) { ?>
						<p class="dl-trend-empty">This device's SMART data doesn't include any of the tracked metrics (uncommon - typically an enclosure/controller reporting a limited SCSI attribute set).</p>
					<?php } ?>
				<?php } ?>
			</div>
		<?php } ?>
	</div>

	<script type="application/json" id="dl-trend-data"><?php echo json_encode($trend_devices, JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
	<script type="text/javascript" src="<?autov("" . DISKLOCATION_PATH . "/pages/script/trends_script.js")?>"></script>
<?php } ?>
