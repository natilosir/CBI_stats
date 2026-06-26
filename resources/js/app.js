import Chart from 'chart.js/auto';

// ─────────────────────────────────────────────
// پالت رنگ برای هر شاخص
// ─────────────────────────────────────────────
const palette = {
	value: {
		border: '#58A6FF',
		bg: 'rgba(88,166,255,0.08)',
		dot: '#58A6FF',
		label: 'ارزش',
		badge: 'Value'
	},
	growth: {
		border: '#3FB950',
		bg: 'rgba(63,185,80,0.08)',
		dot: '#3FB950',
		label: 'رشد',
		badge: 'Growth'
	},
	growth_yoy: {
		border: '#F0883E',
		bg: 'rgba(240,136,62,0.08)',
		dot: '#F0883E',
		label: 'رشد سالانه',
		badge: 'Year over Year'
	},
	growth_end: {
		border: '#BC8CFF',
		bg: 'rgba(188,140,255,0.08)',
		dot: '#BC8CFF',
		label: 'رشد پایان دوره',
		badge: 'Growth End'
	},
	share_current: {
		border: '#FF7B72',
		bg: 'rgba(255,123,114,0.08)',
		dot: '#FF7B72',
		label: 'سهم جاری',
		badge: 'Share Current'
	},
	share_previous: {
		border: '#FFA657',
		bg: 'rgba(255,166,87,0.08)',
		dot: '#FFA657',
		label: 'سهم قبلی',
		badge: 'Share Previous'
	},
	share_growth_end: {
		border: '#39D353',
		bg: 'rgba(57,211,83,0.08)',
		dot: '#39D353',
		label: 'سهم رشد پایان دوره',
		badge: 'Share Growth End'
	},
};

let chartInstances = [];

// ─────────────────────────────────────────────
// پلاگین Crosshair هوشمند (روی نقاط داده)
// ─────────────────────────────────────────────
const crosshairPlugin = {
	id: 'customCrosshair',
	afterEvent(chart, args) {
		const { event } = args;
		if ( event.type === 'mousemove' ) {
			chart._crosshairX      = event.x;
			chart._crosshairActive = true;

			const elements = chart.getElementsAtEventForMode( event, 'index', { intersect: false }, false );
			if ( elements.length > 0 ) {
				const firstElement = elements[0];
				const datasetIndex = firstElement.datasetIndex;
				const index        = firstElement.index;
				const meta         = chart.getDatasetMeta( datasetIndex );
				const element      = meta.data[index];

				chart._crosshairDataY     = element.y;
				chart._crosshairDataValue = chart.data.datasets[datasetIndex].data[index];
				chart._crosshairDataColor = chart.data.datasets[datasetIndex].borderColor;
			}
		} else if ( event.type === 'mouseout' ) {
			chart._crosshairActive = false;
		}
		chart.draw();
	},
	afterDraw(chart) {
		if ( !chart._crosshairActive ) return;

		const {
			      ctx,
			      chartArea: {
				      left,
				      right,
				      top,
				      bottom
			      }
		      } = chart;
		const x = chart._crosshairX;
		const y = chart._crosshairDataY;

		if ( x < left || x > right ) return;

		ctx.save();

		// ─── خط عمودی ───
		ctx.setLineDash( [ 5, 5 ] );
		ctx.lineWidth   = 1;
		ctx.strokeStyle = 'rgba(255, 255, 255, 0.4)';
		ctx.beginPath();
		ctx.moveTo( x, top );
		ctx.lineTo( x, bottom );
		ctx.stroke();

		// ─── خط افقی (روی نقطه داده) ───
		if ( y >= top && y <= bottom ) {
			ctx.beginPath();
			ctx.moveTo( left, y );
			ctx.lineTo( right, y );
			ctx.stroke();

			ctx.setLineDash( [] );
			ctx.fillStyle   = chart._crosshairDataColor || '#58A6FF';
			ctx.strokeStyle = '#161B22';
			ctx.lineWidth   = 2;
			ctx.beginPath();
			ctx.arc( x, y, 6, 0, Math.PI * 2 );
			ctx.fill();
			ctx.stroke();
		}

		ctx.setLineDash( [] );

		// ─── برچسب محور X (پایین) ───
		const xScale = chart.scales.x;
		const xValue = xScale.getValueForPixel( x );
		const xLabel = chart.data.labels[Math.round( xValue )] || '';

		if ( xLabel ) {
			ctx.font        = 'bold 14px IRANSans, Tahoma';
			const textWidth = ctx.measureText( xLabel ).width;
			const labelX    = Math.max( left, Math.min( x - textWidth / 2 - 12, right - textWidth - 24 ) );

			ctx.fillStyle   = '#30363D';
			ctx.strokeStyle = 'rgba(255,255,255,0.3)';
			ctx.lineWidth   = 1.5;
			ctx.beginPath();
			ctx.roundRect( labelX, bottom + 8, textWidth + 24, 32, 8 );
			ctx.fill();
			ctx.stroke();

			ctx.fillStyle    = '#E6EDF3';
			ctx.textAlign    = 'center';
			ctx.textBaseline = 'middle';
			ctx.fillText( xLabel, labelX + textWidth / 2 + 12, bottom + 24 );
		}

		// ─── برچسب محور Y (چپ) ───
		if ( y >= top && y <= bottom && chart._crosshairDataValue !== null && chart._crosshairDataValue !== undefined ) {
			const dataValue = chart._crosshairDataValue;
			let formattedValue;

			if ( Math.abs( dataValue ) >= 1e12 ) {
				formattedValue = ( dataValue / 1e12 ).toLocaleString( 'fa-IR', { maximumFractionDigits: 2 } ) + ' T';
			} else if ( Math.abs( dataValue ) >= 1e9 ) {
				formattedValue = ( dataValue / 1e9 ).toLocaleString( 'fa-IR', { maximumFractionDigits: 2 } ) + ' B';
			} else if ( Math.abs( dataValue ) >= 1e6 ) {
				formattedValue = ( dataValue / 1e6 ).toLocaleString( 'fa-IR', { maximumFractionDigits: 2 } ) + ' M';
			} else {
				formattedValue = dataValue.toLocaleString( 'fa-IR', { maximumFractionDigits: 2 } );
			}

			ctx.font        = 'bold 16px IRANSans, Tahoma';
			const textWidth = ctx.measureText( formattedValue ).width;
			const labelY    = Math.max( top, Math.min( y - 20, bottom - 40 ) );

			const pointColor = chart._crosshairDataColor || '#58A6FF';
			ctx.fillStyle    = pointColor;
			ctx.strokeStyle  = 'rgba(255,255,255,0.4)';
			ctx.lineWidth    = 2;

			const labelWidth  = textWidth + 32;
			const labelHeight = 40;
			const labelX      = left + 15;

			ctx.beginPath();
			ctx.roundRect( labelX, labelY, labelWidth, labelHeight, 10 );
			ctx.fill();
			ctx.stroke();

			ctx.shadowColor   = 'rgba(0,0,0,0.3)';
			ctx.shadowBlur    = 8;
			ctx.shadowOffsetX = 2;
			ctx.shadowOffsetY = 2;

			ctx.fillStyle    = '#FFFFFF';
			ctx.textAlign    = 'center';
			ctx.textBaseline = 'middle';
			ctx.fillText( formattedValue, labelX + labelWidth / 2, labelY + labelHeight / 2 );

			ctx.shadowColor   = 'transparent';
			ctx.shadowBlur    = 0;
			ctx.shadowOffsetX = 0;
			ctx.shadowOffsetY = 0;
		}

		ctx.restore();
	}
};

Chart.register( crosshairPlugin );

// ─────────────────────────────────────────────
// بارگذاری اولیه با ID=1 یا با جستجو
// ─────────────────────────────────────────────
window.fetchChart = async function(idOrTitle = null) {
	const titleEl       = document.getElementById( 'titleInput' );
	const statusEl      = document.getElementById( 'statusBar' );
	const resultTitleEl = document.getElementById( 'resultTitle' );
	const chartsSection = document.getElementById( 'chartsContainer' );
	const sheetsSection = document.getElementById( 'sheetsSection' );
	let url;
	let isSheetMode     = false;

	if ( idOrTitle !== null ) {
		url         = `/api/chart/data-by-id/${idOrTitle}`;
		isSheetMode = true;
	} else {
		const q = titleEl.value.trim();
		if ( !q ) {
			alert( 'لطفاً عنوان را وارد کنید.' );
			return;
		}
		url         = `/api/chart/data/${encodeURIComponent( q )}`;
		isSheetMode = false;
	}

	statusEl.style.display      = 'flex';
	resultTitleEl.style.display = 'none';
	chartsSection.style.display = 'none';
	sheetsSection.style.display = 'none';
	chartsSection.innerHTML     = '';
	sheetsSection.innerHTML     = '';
	chartInstances.forEach( c => c.destroy() );
	chartInstances = [];

	try {
		const res = await fetch( url );
		if ( !res.ok ) {
			const err          = await res.json().catch( () => ( {} ) );
			statusEl.innerHTML = `<span style="color:#ff7b72">⚠️ ${err.error || 'خطا در دریافت داده'}</span>`;
			return;
		}

		const data             = await res.json();
		statusEl.style.display = 'none';

		if ( isSheetMode ) {
			renderSheetGrids( data );
		} else {
			resultTitleEl.innerHTML     = `${data.title || ''} <small>${data.labels?.length ?? 0} ماه</small>`;
			resultTitleEl.style.display = 'block';
			renderCharts( data );
			chartsSection.style.display = 'flex';
		}
	} catch ( e ) {
		console.error( e );
		statusEl.innerHTML = `<span style="color:#ff7b72">⚠️ خطا در ارتباط با سرور</span>`;
	}
};

// ─────────────────────────────────────────────
// رندر جداول شیت
// ─────────────────────────────────────────────
function renderSheetGrids(sheetGrids) {
	const section         = document.getElementById( 'sheetsSection' );
	section.style.display = 'block';
	section.innerHTML     = `<div class="section-divider"><h2>📋 جداول شیت‌ها</h2></div>`;
	const entries         = Object.values( sheetGrids );

	if ( !entries.length ) {
		section.innerHTML += `<div style="text-align:center;color:var(--muted);padding:60px">هیچ شیتی یافت نشد</div>`;
		return;
	}

	entries.forEach( sheetData => {
		const sheet     = sheetData.sheet;
		const grid      = sheetData.grid;
		const totalRows = parseInt( sheetData.totalRows ) || 0;
		const totalCols = parseInt( sheetData.totalCols ) || 0;

		if ( !totalRows || !totalCols ) return;

		const covered = {};
		let tableHTML = '<table class="excel-grid"><tbody>';

		for ( let r = 1; r <= totalRows; r ++ ) {
			tableHTML += '<tr>';
			for ( let c = 1; c <= totalCols; c ++ ) {
				if ( covered[`${r},${c}`] ) continue;

				const cell    = grid[r]?.[c] ?? null;
				const rowspan = parseInt( cell?.row_span ?? 1 );
				const colspan = parseInt( cell?.col_span ?? 1 );

				for ( let dr = 0; dr < rowspan; dr ++ ) {
					for ( let dc = 0; dc < colspan; dc ++ ) {
						if ( dr === 0 && dc === 0 ) continue;
						covered[`${r + dr},${c + dc}`] = true;
					}
				}

				const val = cell?.dictionary?.value ?? '';
				tableHTML += `<td rowspan="${rowspan}" colspan="${colspan}">${val}</td>`;
			}
			tableHTML += '</tr>';
		}
		tableHTML += '</tbody></table>';

		const sheetName = sheet?.name?.value ?? sheet?.name_value ?? 'بدون نام';

		const wrapper     = document.createElement( 'div' );
		wrapper.className = 'sheet-wrapper';
		wrapper.innerHTML = `
            <div class="sheet-top">
                <span class="sheet-name">${sheetName}</span>
                <span class="sheet-index">شیت ${sheet?.index ?? ''}</span>
            </div>
            <div class="table-scroll">${tableHTML}</div>
            <div class="sheet-footer">${totalRows} سطر &bull; ${totalCols} ستون</div>`;

		section.appendChild( wrapper );
	} );
}

// ─────────────────────────────────────────────
// رسم نمودارها با ارتفاع ۹۰٪ ویوپورت
// ─────────────────────────────────────────────
function renderCharts(data) {
	const container = document.getElementById( 'chartsContainer' );
	const labels    = data.labels;
	const datasets  = data.datasets;

	Object.keys( datasets ).forEach( key => {
		const values = datasets[key];
		const p      = palette[key] || {
			border: '#94A3B8',
			bg: 'rgba(148,163,184,0.08)',
			dot: '#94A3B8',
			label: key,
			badge: key
		};
		const isLog  = ( key === 'value' );

		// ساخت کارت
		const card               = document.createElement( 'div' );
		card.className           = 'chart-card';
		card.style.display       = 'flex';
		card.style.flexDirection = 'column';
		card.style.height        = '70vh';          // ارتفاع ۹۰٪ ویوپورت
		card.style.minHeight     = '400px';      // حداقل برای موبایل

		// هدر
		const header            = document.createElement( 'div' );
		header.className        = 'chart-header';
		header.style.flexShrink = '0';
		header.innerHTML        = `
            <span class="chart-dot" style="background:${p.dot}"></span>
            <h3>${p.label}</h3>
            <span class="chart-badge">${p.badge}${isLog ? ' · Log Scale' : ''}</span>
        `;
		card.appendChild( header );

		// بدنه (ظرف canvas)
		const body           = document.createElement( 'div' );
		body.className       = 'chart-body';
		body.style.flex      = '1';
		body.style.position  = 'relative';
		body.style.minHeight = '0';           // برای flex

		// المان canvas با ارتفاع ۱۰۰٪ بدنه
		const canvas         = document.createElement( 'canvas' );
		canvas.id            = `canvas-${key}`;
		canvas.style.width   = '100%';
		canvas.style.height  = '100%';
		canvas.style.display = 'block';

		body.appendChild( canvas );
		card.appendChild( body );
		container.appendChild( card );

		// ─── ایجاد نمودار ───
		const ctx  = canvas.getContext( '2d' );
		const grad = ctx.createLinearGradient( 0, 0, 0, 420 );
		grad.addColorStop( 0, p.bg.replace( '0.08)', '0.30)' ) );
		grad.addColorStop( 1, p.bg.replace( '0.08)', '0)' ) );

		const chart = new Chart( ctx, {
			type: 'line',
			data: {
				labels,
				datasets: [
					{
						label: p.label,
						data: values,
						borderColor: p.border,
						backgroundColor: grad,
						borderWidth: 2.5,
						pointRadius: labels.length > 60 ? 0 : 3,
						pointHoverRadius: 8,
						pointBackgroundColor: p.border,
						pointBorderColor: '#161B22',
						pointBorderWidth: 2,
						pointHoverBorderWidth: 3,
						tension: 0.35,
						fill: true,
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,    // اجازه می‌دهد ارتفاع توسط CSS کنترل شود
				layout: {
					padding: {
						left: 20,
						right: 20,
						top: 10,
						bottom: 10
					}
				},
				interaction: {
					mode: 'index',
					intersect: false
				},
				plugins: {
					legend: { display: false },
					tooltip: {
						enabled: true,
						rtl: true,
						backgroundColor: 'rgba(22, 27, 34, 0.98)',
						borderColor: p.border,
						borderWidth: 2,
						titleColor: '#E6EDF3',
						bodyColor: '#E6EDF3',
						padding: {
							top: 20,
							bottom: 20,
							left: 24,
							right: 24
						},
						cornerRadius: 12,
						titleFont: {
							family: 'IRANSans, Tahoma',
							size: 18,
							weight: 'bold'
						},
						bodyFont: {
							family: 'IRANSans, Tahoma',
							size: 16,
							weight: '600'
						},
						titleMarginBottom: 14,
						bodySpacing: 12,
						displayColors: true,
						boxWidth: 18,
						boxHeight: 18,
						boxPadding: 10,
						usePointStyle: true,
						caretSize: 12,
						caretPadding: 16,
						callbacks: {
							title: function(context) {
								return `📅 ${context[0].label}`;
							},
							label: function(context) {
								const value = context.parsed.y;
								if ( value === null || value === undefined ) return '';
								return `  ${p.label}: ${value.toLocaleString( 'fa-IR', { maximumFractionDigits: 2 } )}`;
							},
							labelColor: function(context) {
								return {
									borderColor: p.border,
									backgroundColor: p.border,
									borderWidth: 2,
									borderRadius: 8,
								};
							}
						}
					}
				},
				scales: {
					x: {
						grid: { color: 'rgba(255,255,255,0.04)' },
						ticks: {
							color: '#7D8590',
							font: {
								family: 'IRANSans, Tahoma',
								size: 11
							},
							maxTicksLimit: 14,
							maxRotation: 35,
						}
					},
					y: {
						type: isLog ? 'logarithmic' : 'linear',
						grid: { color: 'rgba(255,255,255,0.05)' },
						ticks: {
							color: '#7D8590',
							font: {
								family: 'IRANSans, Tahoma',
								size: 11
							},
							callback(v) {
								if ( isLog && v <= 0 ) return '';
								if ( Math.abs( v ) >= 1e12 ) return ( v / 1e12 ).toLocaleString( 'fa-IR' ) + 'T';
								if ( Math.abs( v ) >= 1e9 ) return ( v / 1e9 ).toLocaleString( 'fa-IR' ) + 'B';
								if ( Math.abs( v ) >= 1e6 ) return ( v / 1e6 ).toLocaleString( 'fa-IR' ) + 'M';
								return v.toLocaleString( 'fa-IR' );
							}
						}
					}
				},
				animation: {
					duration: 600,
					easing: 'easeOutQuart'
				}
			}
		} );

		chartInstances.push( chart );
	} );

	// پس از رسم همه، یکبار resize اجباری برای تطابق با اندازه‌های واقعی
	requestAnimationFrame( () => {
		chartInstances.forEach( chart => chart.resize() );
	} );
}

// ─────────────────────────────────────────────
// راه‌اندازی اولیه و مدیریت resize
// ─────────────────────────────────────────────
document.addEventListener( 'DOMContentLoaded', () => {
	// بارگذاری پیش‌فرض با ID=1
	window.fetchChart( 1 );

	// جستجو با Enter
	document.getElementById( 'titleInput' )?.addEventListener( 'keydown', e => {
		if ( e.key === 'Enter' ) window.fetchChart( null );
	} );

	// به‌روزرسانی نمودارها هنگام تغییر اندازه پنجره
	let resizeTimer;
	window.addEventListener( 'resize', () => {
		clearTimeout( resizeTimer );
		resizeTimer = setTimeout( () => {
			chartInstances.forEach( chart => chart.resize() );
		}, 200 );
	} );
} );