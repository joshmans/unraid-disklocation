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
	 *  This used to be one 1473-line file holding all 49 of this plugin's shared
	 *  functions with no internal organization (config I/O next to ZFS parsing next to
	 *  drive-brand detection). Split by topic into the functions_*.php files below (see
	 *  ROADMAP.md) - no logic changed, purely a move, verified by diffing each function's
	 *  body against this file's pre-split git history. Kept as the single entry point
	 *  every other file already does require_once("functions.php") for, so nothing else
	 *  needed to change.
	 *
	 *  The functions_*.php requires have to come BEFORE variables.php/load_settings.php,
	 *  not after: variables.php calls debug() at its own top level (not inside a
	 *  function), which used to work because PHP hoists every unconditional top-level
	 *  function declared anywhere in a file - even one appearing later in the same file -
	 *  before that file's own first line executes. That's a per-file guarantee, not a
	 *  per-require-chain one: with debug() moved out to functions_core.php, it only
	 *  exists once that require actually runs, so functions_core.php (and the rest) have
	 *  to run first. Confirmed by actually executing this require chain (not just
	 *  php -l, which only checks syntax) - it failed with "Call to undefined function
	 *  debug()" from variables.php when the requires were in the other order.
	 */

	require_once("functions_core.php");
	require_once("functions_export.php");
	require_once("functions_smart.php");
	require_once("functions_zfs.php");
	require_once("functions_devices.php");
	require_once("functions_drive_brand.php");

	if(!strstr($_SERVER["SCRIPT_NAME"], "page_system.php")) {
		require_once("variables.php");
		include("load_settings.php");
	}
?>
