//  Copyright 2019-2026, Ole-Henrik Jakobsen
//
//  This file is part of Disk Location for Unraid.
//
//  Disk Location for Unraid is free software: you can redistribute it and/or modify
//  it under the terms of the GNU General Public License as published by
//  the Free Software Foundation, either version 3 of the License, or
//  (at your option) any later version.
//
//  Disk Location for Unraid is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
//  You should have received a copy of the GNU General Public License
//  along with Disk Location for Unraid.  If not, see <https://www.gnu.org/licenses/>.

(function() {
	var dataEl = document.getElementById("dl-trend-data");
	if(!dataEl || typeof Chart === "undefined") {
		return;
	}

	var devices = JSON.parse(dataEl.textContent);

	// Unraid's webGUI theme (white/black/azure/gray) sets the page's text color; read it
	// back rather than hardcoding one, so charts stay legible whichever theme is active.
	var textColor = getComputedStyle(document.body).color || "#F2F2F2";
	Chart.defaults.color = textColor;
	Chart.defaults.borderColor = "rgba(128, 128, 128, 0.3)";

	function shortLabel(ts) {
		// "2026-09-16 03:29:53" -> "09-16"
		var m = /^\d{4}-(\d{2}-\d{2})/.exec(ts);
		return m ? m[1] : ts;
	}

	function buildChart(canvas, hash, kind) {
		var d = devices[hash];
		if(!d) { return; }

		var labels = d.timestamps.map(shortLabel);
		var datasets = [];

		if(kind === "temp") {
			datasets.push({ label: "Temperature (°" + d.tempUnit + ")", data: d.temp, borderColor: "#E8A33D", spanGaps: true, tension: 0.15 });
		}
		else if(kind === "wear") {
			datasets.push({ label: "Wear level remaining (%)", data: d.wear, borderColor: "#5B9BD5", spanGaps: true, tension: 0.15 });
		}
		else if(kind === "sectors") {
			datasets.push({ label: "Reallocated sectors", data: d.reallocated, borderColor: "#D9534F", spanGaps: true, stepped: true });
			datasets.push({ label: "Pending sectors", data: d.pending, borderColor: "#F0AD4E", spanGaps: true, stepped: true });
			datasets.push({ label: "Uncorrectable sectors", data: d.uncorrectable, borderColor: "#9B59B6", spanGaps: true, stepped: true });
		}
		else {
			return;
		}

		new Chart(canvas, {
			type: "line",
			data: { labels: labels, datasets: datasets },
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { mode: "index", intersect: false },
				scales: {
					x: { ticks: { autoSkip: true, maxTicksLimit: 8 } },
					y: { beginAtZero: (kind !== "temp") }
				}
			}
		});
	}

	document.querySelectorAll(".dl-trend-canvas").forEach(function(canvas) {
		buildChart(canvas, canvas.getAttribute("data-dl-hash"), canvas.getAttribute("data-dl-chart"));
	});
})();
