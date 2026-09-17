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
	 *  functions_drive_brand.php - manufacturer-brand detection/logo lookup and the
	 *  drive-type (HDD/SSD/NVMe) icon chip. Split out of functions.php (see
	 *  ROADMAP.md) along with the other functions_*.php files - no logic changed.
	 */
	
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
		// A bare glyph (even a colorful one) still needed a hover to say what it meant, so
		// this renders as a small chip - glyph plus a short text label ("M.2"/"SSD"/"HDD")
		// side by side - so the type reads at a glance without hovering. The tooltip stays
		// as a fallback with the fuller label (e.g. "NVMe SSD", "7200 RPM").
		//
		// Placed by devices.php as an absolutely positioned badge inside the device-info
		// column (.flex-container-middle_*), bottom-right (horizontal trays) or
		// bottom-left+rotated (vertical trays) - not inline in the <br />-stacked
		// status-icon column with the ~13-16px orbs (too small there once filled/colored
		// with a text label attached). `position: relative` for this is scoped to that
		// device-info column specifically, not the shared tray tile div - putting it
		// there instead changed the containing block (and so the on-hover position) of
		// every other icon's '.info' tooltip span in that tile, not just this one's.
		// devices.php also sets min-width: 0 and overflow-wrap: break-word on that same
		// column: without them, an unbreakable run of text (e.g. a serial number) plus
		// this chip's reserved width can together exceed a narrow tray's available space,
		// and the column - along with this chip anchored to its edge - renders outside
		// the tray tile's visible bounds instead of shrinking/wrapping to fit.
		//
		// Colors are deliberately outside the red/yellow/green/grey already used by the
		// temp/SMART status orbs, so it can't be misread as a status.
		switch(true) {
			case ($rotation == -2): // NVMe - stylized M.2 stick (white body, blue chips) + "M.2" text
				$glyph = "<svg viewBox='0 0 32 32' width='14' height='14' xmlns='http://www.w3.org/2000/svg'><rect x='2' y='9' width='28' height='14' rx='2' fill='#fff'/><rect x='6' y='13' width='8' height='6' rx='1' fill='#1565C0'/><rect x='17' y='13' width='8' height='6' rx='1' fill='#1565C0'/></svg>";
				$short_label = "M.2";
				$chip_bg = "rgba(21,101,192,0.92)";
				$label = "NVMe SSD";
				break;
			case ($rotation == -1): // SATA/SAS SSD - drive casing (white body, teal chips) + "SSD" text
				$glyph = "<svg viewBox='0 0 32 32' width='14' height='14' xmlns='http://www.w3.org/2000/svg'><rect x='2' y='4' width='28' height='24' rx='3' fill='#fff'/><rect x='7' y='11' width='7' height='7' rx='1' fill='#00796B'/><rect x='18' y='11' width='7' height='7' rx='1' fill='#00796B'/></svg>";
				$short_label = "SSD";
				$chip_bg = "rgba(0,121,107,0.92)";
				$label = "SSD";
				break;
			case (!empty($rotation) && $rotation > 0): // HDD - spinning platter (white disc, slate hub) + "HDD" text
				$glyph = "<svg viewBox='0 0 32 32' width='14' height='14' xmlns='http://www.w3.org/2000/svg'><circle cx='16' cy='16' r='13' fill='#fff'/><circle cx='16' cy='16' r='5' fill='#455A64'/></svg>";
				$short_label = "HDD";
				$chip_bg = "rgba(69,90,100,0.92)";
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
		// The chip itself is a solid, saturated color (not relying on translucency over
		// the tile) with a semi-opaque white outline: tray tile background color is
		// admin-configurable (see bgcolor_* settings), so the outline is what keeps the
		// chip legible against every color in the palette, not the fill alone.
		return "<span title=\"Drive type: " . htmlspecialchars($label) . "\" style=\"display: inline-flex; align-items: center; gap: 3px; border-radius: 4px; padding: 2px 5px 2px 3px; border: 1px solid rgba(255,255,255,0.55); background: " . $chip_bg . "; vertical-align: middle;\">" . $glyph . "<span style=\"font-size: 10px; font-weight: 800; letter-spacing: 0.3px; color: #fff; text-shadow: 0 1px 1px rgba(0,0,0,0.4);\">" . htmlspecialchars($short_label) . "</span></span>";
	}
?>
