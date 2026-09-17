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
	 *  functions_export.php - TSV/JSON download helpers, split out of functions.php (see
	 *  ROADMAP.md) along with the other functions_*.php files - no logic changed.
	 */
	
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
?>
