@php
	/** تبدیل ستون اکسل (A, AC, ...) به عدد */
	function excelColumnToNumber($column)
	{
		$column = strtoupper($column);
		$number = 0;
		for ($i = 0; $i < strlen($column); $i++) {
			$number = $number * 26 + (ord($column[$i]) - 64);
		}
		return $number;
	}
@endphp

		<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="UTF-8">
	<title>نمایش شیت‌های اکسل</title>

	<style>
		* {
			box-sizing: border-box;
			}

		body {
			font-family: 'IRANSans', Tahoma, sans-serif;
			background: #f2f4f7;
			margin: 0;
			padding: 16px;
			font-size: 13px;
			color: #333;
			}

		.sheet-wrapper {
			background: #fff;
			border-radius: 8px;
			box-shadow: 0 2px 12px rgba(0,0,0,.06);
			margin-bottom: 24px;
			padding: 14px;
			}

		/* Header */
		.sheet-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 10px;
			padding-bottom: 8px;
			border-bottom: 1px solid #eee;
			}

		.sheet-title {
			font-size: 14px;
			font-weight: 600;
			}

		.sheet-meta {
			font-size: 11px;
			color: #888;
			}

		/* Table */
		.table-container {
			overflow-x: auto;
			}

		table.excel-grid {
			border-collapse: collapse;
			min-width: 100%;
			background: #fff;
			}

		table.excel-grid td {
			border: 1px solid #e1e5ea;
			padding: 6px 8px;
			min-width: 70px;
			font-size: 12px;
			line-height: 1.6;
			vertical-align: middle;
			text-align: right;
			white-space: pre-wrap;
			}

		table.excel-grid tr:nth-child(even) td {
			background: #fafbfc;
			}

		table.excel-grid tr:hover td {
			background: #f0f6ff;
			}

		/* Footer */
		.footer-info {
			margin-top: 8px;
			font-size: 11px;
			color: #666;
			text-align: left;
			}

		/* Empty */
		.no-data {
			text-align: center;
			color: #999;
			padding: 40px 0;
			}
	</style>
</head>

<body>

@forelse($sheetGrids as $sheetId => $sheetData)

	@php
		$sheet     = $sheetData['sheet'];
		$grid      = $sheetData['grid'];
		$totalRows = (int) $sheetData['totalRows'];
		$totalCols = (int) $sheetData['totalCols'];

		if (!is_numeric($totalCols)) {
			$totalCols = excelColumnToNumber($totalCols);
		}

		$covered = array_fill(1, $totalRows, array_fill(1, $totalCols, false));
	@endphp

	<div class="sheet-wrapper">

		<div class="sheet-header">
			<div class="sheet-title">
				{{ $sheet->name_value ?? 'بدون نام' }}
			</div>
			<div class="sheet-meta">
				شیت {{ $sheet->index }}
			</div>
		</div>

		<div class="table-container">
			<table class="excel-grid">
				<tbody>
				@for($row = 1; $row <= $totalRows; $row++)
					<tr>
						@for($col = 1; $col <= $totalCols; $col++)
							@php
								if ($covered[$row][$col]) continue;

								$cell = $grid[$row][$col] ?? null;
								$rowspan = (int)($cell->row_span ?? 1);
								$colspan = (int)($cell->col_span ?? 1);

								for ($r = 0; $r < $rowspan; $r++) {
									for ($c = 0; $c < $colspan; $c++) {
										if ($r === 0 && $c === 0) continue;
										$rr = $row + $r;
										$cc = $col + $c;
										if ($rr <= $totalRows && $cc <= $totalCols) {
											$covered[$rr][$cc] = true;
										}
									}
								}
							@endphp

							<td rowspan="{{ $rowspan }}" colspan="{{ $colspan }}">{{$cell?->RealValue}}</td>
						@endfor
					</tr>
				@endfor
				</tbody>
			</table>
		</div>

		<div class="footer-info">
			{{ $totalRows }} سطر • {{ $totalCols }} ستون
		</div>

	</div>

@empty
	<div class="no-data">
		هیچ شیتی برای نمایش وجود ندارد
	</div>
@endforelse

</body>
</html>
