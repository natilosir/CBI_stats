import Chart from 'chart.js/auto';

// ─────────────────────────────────────────────
// پالت رنگ برای هر شاخص
// ─────────────────────────────────────────────
const palette = {
	value:            { border: '#58a6ff', bg: 'rgba(88,166,255,0.08)',   dot: '#58a6ff', label: 'ارزش',                badge: 'Value'            },
	growth:           { border: '#3fb950', bg: 'rgba(63,185,80,0.08)',    dot: '#3fb950', label: 'رشد',                 badge: 'Growth'           },
	growth_yoy:       { border: '#f0883e', bg: 'rgba(240,136,62,0.08)',   dot: '#f0883e', label: 'رشد سالانه',          badge: 'Year over Year'   },
	growth_end:       { border: '#bc8cff', bg: 'rgba(188,140,255,0.08)',  dot: '#bc8cff', label: 'رشد پایان دوره',      badge: 'Growth End'       },
	share_current:    { border: '#ff7b72', bg: 'rgba(255,123,114,0.08)',  dot: '#ff7b72', label: 'سهم جاری',            badge: 'Share Current'    },
	share_previous:   { border: '#ffa657', bg: 'rgba(255,166,87,0.08)',   dot: '#ffa657', label: 'سهم قبلی',            badge: 'Share Previous'   },
	share_growth_end: { border: '#39d353', bg: 'rgba(57,211,83,0.08)',    dot: '#39d353', label: 'سهم رشد پایان دوره',  badge: 'Share Growth End' },
};

let chartInstances = [];

// ─────────────────────────────────────────────
// بارگذاری اولیه با ID=1 یا با جستجو
// ─────────────────────────────────────────────
window.fetchChart = async function(idOrTitle = null) {
	const titleEl       = document.getElementById('titleInput');
	const statusEl      = document.getElementById('statusBar');
	const resultTitleEl = document.getElementById('resultTitle');
	const chartsSection = document.getElementById('chartsContainer');
	const sheetsSection = document.getElementById('sheetsSection');

	let url;
	let isSheetMode = false; // حالت اولیه: نمایش جداول شیت

	if (idOrTitle !== null) {
		// بارگذاری اولیه با ID: نمایش جداول شیت
		url = `/api/chart/data-by-id/${idOrTitle}`;
		isSheetMode = true;
	} else {
		// جستجو با عنوان: نمایش نمودارها
		const q = titleEl.value.trim();
		if (!q) { alert('لطفاً عنوان را وارد کنید.'); return; }
		url = `/api/chart/data/${encodeURIComponent(q)}`;
		isSheetMode = false;
	}

	// ─── ریست UI ───
	statusEl.style.display      = 'flex';
	resultTitleEl.style.display = 'none';
	chartsSection.style.display = 'none';
	sheetsSection.style.display = 'none';
	chartsSection.innerHTML     = '';
	sheetsSection.innerHTML     = '';
	chartInstances.forEach(c => c.destroy());
	chartInstances = [];

	try {
		const res = await fetch(url);
		if (!res.ok) {
			const err = await res.json().catch(() => ({}));
			statusEl.innerHTML = `<span style="color:#ff7b72">⚠️ ${err.error || 'خطا در دریافت داده'}</span>`;
			return;
		}

		const data = await res.json();
		statusEl.style.display = 'none';

		if (isSheetMode) {
			// ─── حالت اولیه: نمایش جداول شیت ───
			renderSheetGrids(data);
		} else {
			// ─── حالت جستجو: نمایش نمودارها ───
			resultTitleEl.innerHTML     = `${data.title || ''} <small>${data.labels?.length ?? 0} ماه</small>`;
			resultTitleEl.style.display = 'block';
			renderCharts(data);
			chartsSection.style.display = 'flex';
		}

	} catch (e) {
		console.error(e);
		statusEl.innerHTML = `<span style="color:#ff7b72">⚠️ خطا در ارتباط با سرور</span>`;
	}
};

// ─────────────────────────────────────────────
// رندر جداول شیت از خروجی getDataById
// فرمت: { "sheetId": { sheet, grid, totalRows, totalCols }, ... }
// ─────────────────────────────────────────────
function renderSheetGrids(sheetGrids) {
	const section = document.getElementById('sheetsSection');
	section.style.display = 'block';

	section.innerHTML = `<div class="section-divider"><h2>📋 جداول شیت‌ها</h2></div>`;

	const entries = Object.values(sheetGrids);

	if (!entries.length) {
		section.innerHTML += `<div style="text-align:center;color:var(--muted);padding:60px">هیچ شیتی یافت نشد</div>`;
		return;
	}

	entries.forEach(sheetData => {
		const sheet     = sheetData.sheet;
		const grid      = sheetData.grid;      // { rowIndex: { colIndex: cell } }
		const totalRows = parseInt(sheetData.totalRows) || 0;
		const totalCols = parseInt(sheetData.totalCols) || 0;

		if (!totalRows || !totalCols) return;

		// ─── ساخت جدول ───
		const covered = {};
		let tableHTML = '<table class="excel-grid"><tbody>';

		for (let r = 1; r <= totalRows; r++) {
			tableHTML += '<tr>';
			for (let c = 1; c <= totalCols; c++) {
				if (covered[`${r},${c}`]) continue;

				const cell    = grid[r]?.[c] ?? null;
				const rowspan = parseInt(cell?.row_span ?? 1);
				const colspan = parseInt(cell?.col_span ?? 1);

				// علامت‌گذاری سلول‌های پوشش‌داده‌شده
				for (let dr = 0; dr < rowspan; dr++) {
					for (let dc = 0; dc < colspan; dc++) {
						if (dr === 0 && dc === 0) continue;
						covered[`${r + dr},${c + dc}`] = true;
					}
				}

				// مقدار سلول از dictionary.value
				const val = cell?.dictionary?.value ?? '';

				tableHTML += `<td rowspan="${rowspan}" colspan="${colspan}">${val}</td>`;
			}
			tableHTML += '</tr>';
		}
		tableHTML += '</tbody></table>';

		// ─── نام شیت از name.value یا name_value ───
		const sheetName = sheet?.name?.value ?? sheet?.name_value ?? 'بدون نام';

		const wrapper = document.createElement('div');
		wrapper.className = 'sheet-wrapper';
		wrapper.innerHTML = `
			<div class="sheet-top">
				<span class="sheet-name">${sheetName}</span>
				<span class="sheet-index">شیت ${sheet?.index ?? ''}</span>
			</div>
			<div class="table-scroll">${tableHTML}</div>
			<div class="sheet-footer">${totalRows} سطر &bull; ${totalCols} ستون</div>`;

		section.appendChild(wrapper);
	});
}

// ─────────────────────────────────────────────
// رسم نمودارها (بعد از جستجو)
// ─────────────────────────────────────────────
function renderCharts(data) {
	const container = document.getElementById('chartsContainer');
	const labels    = data.labels;
	const datasets  = data.datasets;

	Object.keys(datasets).forEach(key => {
		const values = datasets[key];
		const p      = palette[key] || { border: '#94a3b8', bg: 'rgba(148,163,184,0.08)', dot: '#94a3b8', label: key, badge: key };
		const isLog  = (key === 'value');

		const card = document.createElement('div');
		card.className = 'chart-card';
		card.innerHTML = `
			<div class="chart-header">
				<span class="chart-dot" style="background:${p.dot}"></span>
				<h3>${p.label}</h3>
				<span class="chart-badge">${p.badge}${isLog ? ' · Log Scale' : ''}</span>
			</div>
			<div class="chart-body">
				<canvas id="canvas-${key}" height="110"></canvas>
			</div>`;
		container.appendChild(card);

		const canvas = document.getElementById(`canvas-${key}`);
		const ctx    = canvas.getContext('2d');

		const grad = ctx.createLinearGradient(0, 0, 0, 420);
		grad.addColorStop(0, p.bg.replace('0.08)', '0.30)'));
		grad.addColorStop(1, p.bg.replace('0.08)', '0)'));

		const chart = new Chart(ctx, {
			type: 'line',
			data: {
				labels,
				datasets: [{
					label: p.label,
					data: values,
					borderColor: p.border,
					backgroundColor: grad,
					borderWidth: 2.5,
					pointRadius: labels.length > 60 ? 0 : 3,
					pointHoverRadius: 6,
					pointBackgroundColor: p.border,
					pointBorderColor: '#161b22',
					pointBorderWidth: 2,
					tension: 0.35,
					fill: true,
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: true,
				interaction: { mode: 'index', intersect: false },
				plugins: {
					legend: { display: false },
					tooltip: {
						rtl: true,
						backgroundColor: '#1c2230',
						borderColor: 'rgba(255,255,255,0.08)',
						borderWidth: 1,
						titleColor: '#e6edf3',
						bodyColor: '#7d8590',
						padding: 12,
						titleFont:  { family: 'IRANSans', size: 12, weight: '600' },
						bodyFont:   { family: 'IRANSans', size: 12 },
						callbacks: {
							label: ctx => ' ' + ctx.parsed.y?.toLocaleString('fa-IR'),
						}
					}
				},
				scales: {
					x: {
						grid: { color: 'rgba(255,255,255,0.04)' },
						ticks: {
							color: '#7d8590',
							font: { family: 'IRANSans', size: 10 },
							maxTicksLimit: 14,
							maxRotation: 35,
						}
					},
					y: {
						type: isLog ? 'logarithmic' : 'linear',
						grid: { color: 'rgba(255,255,255,0.05)' },
						ticks: {
							color: '#7d8590',
							font: { family: 'IRANSans', size: 10 },
							callback(v) {
								if (isLog && v <= 0) return '';
								if (Math.abs(v) >= 1e12) return (v / 1e12).toLocaleString('fa-IR') + 'T';
								if (Math.abs(v) >= 1e9)  return (v / 1e9).toLocaleString('fa-IR') + 'B';
								if (Math.abs(v) >= 1e6)  return (v / 1e6).toLocaleString('fa-IR') + 'M';
								return v.toLocaleString('fa-IR');
							}
						}
					}
				},
				animation: { duration: 600, easing: 'easeOutQuart' }
			}
		});

		chartInstances.push(chart);
	});
}

// ─────────────────────────────────────────────
// راه‌اندازی
// ─────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
	window.fetchChart(1); // بارگذاری اولیه: جداول شیت ID=1

	document.getElementById('titleInput')?.addEventListener('keydown', e => {
		if (e.key === 'Enter') window.fetchChart(null); // جستجو: نمودارها
	});
});