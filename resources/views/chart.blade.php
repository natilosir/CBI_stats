<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>نمودارهای مالی</title>
	@vite(['resources/js/app.js'])
	<style>
		/* ─── ریست و پایه ─── */
		*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

		:root {
			--bg:        #0d1117;
			--surface:   #161b22;
			--surface2:  #1c2230;
			--border:    rgba(255,255,255,0.07);
			--text:      #e6edf3;
			--muted:     #7d8590;
			--accent:    #58a6ff;
			--accent2:   #3fb950;
			--radius:    16px;
			--font:      'IRANSans', 'Vazir', 'Segoe UI', sans-serif;
			}

		body {
			font-family: var(--font);
			background: var(--bg);
			color: var(--text);
			min-height: 100vh;
			direction: rtl;
			padding: 0 0 60px;
			}

		/* ─── هدر ─── */
		.top-bar {
			background: var(--surface);
			border-bottom: 1px solid var(--border);
			padding: 20px 40px;
			display: flex;
			align-items: center;
			gap: 20px;
			position: sticky;
			top: 0;
			z-index: 100;
			backdrop-filter: blur(12px);
			}

		.top-bar h1 {
			font-size: 18px;
			font-weight: 700;
			color: var(--text);
			white-space: nowrap;
			flex-shrink: 0;
			}

		.top-bar h1 span {
			color: var(--accent);
			}

		.search-wrap {
			display: flex;
			gap: 10px;
			flex: 1;
			max-width: 620px;
			margin-right: auto;
			}

		.search-wrap input {
			flex: 1;
			padding: 10px 16px;
			background: var(--bg);
			border: 1.5px solid var(--border);
			border-radius: 10px;
			color: var(--text);
			font-family: var(--font);
			font-size: 14px;
			outline: none;
			transition: border-color .2s, box-shadow .2s;
			}

		.search-wrap input::placeholder { color: var(--muted); }

		.search-wrap input:focus {
			border-color: var(--accent);
			box-shadow: 0 0 0 3px rgba(88,166,255,.15);
			}

		.btn {
			padding: 10px 24px;
			background: var(--accent);
			color: #0d1117;
			border: none;
			border-radius: 10px;
			font-family: var(--font);
			font-size: 14px;
			font-weight: 700;
			cursor: pointer;
			transition: background .15s, transform .15s;
			white-space: nowrap;
			}

		.btn:hover { background: #79c0ff; transform: translateY(-1px); }
		.btn:active { transform: translateY(0); }

		/* ─── اسکلتون / وضعیت ─── */
		#statusBar {
			text-align: center;
			padding: 100px 20px;
			color: var(--muted);
			font-size: 15px;
			display: flex;
			flex-direction: column;
			align-items: center;
			gap: 14px;
			}

		.spinner {
			width: 36px; height: 36px;
			border: 3px solid var(--border);
			border-top-color: var(--accent);
			border-radius: 50%;
			animation: spin .7s linear infinite;
			}

		@keyframes spin { to { transform: rotate(360deg); } }

		/* ─── عنوان نتیجه ─── */
		#resultTitle {
			padding: 36px 40px 10px;
			font-size: 22px;
			font-weight: 700;
			color: var(--text);
			display: none;
			}

		#resultTitle small {
			font-size: 13px;
			font-weight: 400;
			color: var(--muted);
			margin-right: 10px;
			}

		/* ─── کانتینر نمودارها ─── */
		#chartsContainer {
			display: none;
			flex-direction: column;
			gap: 28px;
			padding: 16px 40px 0;
			}

		/* ─── کارت نمودار ─── */
		.chart-card {
			background: var(--surface);
			border: 1px solid var(--border);
			border-radius: var(--radius);
			overflow: hidden;
			transition: box-shadow .25s;
			}

		.chart-card:hover {
			box-shadow: 0 0 0 1px rgba(88,166,255,.2), 0 8px 32px rgba(0,0,0,.3);
			}

		.chart-header {
			padding: 18px 28px 14px;
			display: flex;
			align-items: center;
			gap: 12px;
			border-bottom: 1px solid var(--border);
			}

		.chart-dot {
			width: 10px; height: 10px;
			border-radius: 50%;
			flex-shrink: 0;
			}

		.chart-header h3 {
			font-size: 15px;
			font-weight: 600;
			color: var(--text);
			}

		.chart-badge {
			margin-right: auto;
			padding: 3px 10px;
			border-radius: 20px;
			font-size: 11px;
			font-weight: 600;
			background: rgba(88,166,255,.12);
			color: var(--accent);
			}

		.chart-body {
			padding: 20px 24px 16px;
			}

		.chart-body canvas {
			width: 100% !important;
			display: block;
			}

		/* ─── بخش جداول شیت ─── */
		#sheetsSection {
			display: none;
			padding: 40px 40px 0;
			}

		.section-divider {
			display: flex;
			align-items: center;
			gap: 14px;
			margin-bottom: 24px;
			}

		.section-divider h2 {
			font-size: 17px;
			font-weight: 700;
			color: var(--text);
			white-space: nowrap;
			}

		.section-divider::after {
			content: '';
			flex: 1;
			height: 1px;
			background: var(--border);
			}

		.sheet-wrapper {
			background: var(--surface);
			border: 1px solid var(--border);
			border-radius: var(--radius);
			overflow: hidden;
			margin-bottom: 20px;
			}

		.sheet-top {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 14px 20px;
			border-bottom: 1px solid var(--border);
			background: var(--surface2);
			}

		.sheet-name {
			font-size: 13px;
			font-weight: 600;
			color: var(--text);
			}

		.sheet-index {
			font-size: 11px;
			color: var(--muted);
			}

		.table-scroll {
			overflow-x: auto;
			}

		table.excel-grid {
			border-collapse: collapse;
			width: 100%;
			background: var(--surface);
			}

		table.excel-grid td {
			border: 1px solid rgba(255,255,255,0.055);
			padding: 7px 11px;
			min-width: 80px;
			font-size: 12px;
			line-height: 1.55;
			vertical-align: middle;
			text-align: right;
			white-space: pre-wrap;
			color: var(--text);
			}

		table.excel-grid tr:nth-child(even) td {
			background: rgba(255,255,255,0.025);
			}

		table.excel-grid tr:hover td {
			background: rgba(88,166,255,0.06);
			}

		.sheet-footer {
			padding: 8px 20px;
			font-size: 11px;
			color: var(--muted);
			border-top: 1px solid var(--border);
			text-align: left;
			}

		@media (max-width: 768px) {
			.top-bar { padding: 14px 16px; flex-wrap: wrap; }
			.search-wrap { max-width: 100%; }
			#chartsContainer, #sheetsSection { padding-left: 16px; padding-right: 16px; }
			#resultTitle { padding: 24px 16px 8px; }
			}
	</style>
</head>
<body>

<div class="top-bar">
	<h1>📊 <span>داده‌های مالی</span></h1>
	<div class="search-wrap">
		<input type="text" id="titleInput" placeholder="عنوان مورد نظر را جستجو کنید...">
		<button class="btn" onclick="fetchChart()">جستجو</button>
	</div>
</div>

<div id="statusBar">
	<div class="spinner"></div>
	<span>در حال بارگذاری داده‌های اولیه...</span>
</div>

<div id="resultTitle"></div>
<div id="chartsContainer"></div>
<div id="sheetsSection"></div>

</body>
</html>