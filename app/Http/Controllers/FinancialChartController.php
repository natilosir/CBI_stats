<?php

namespace App\Http\Controllers;

use App\Models\CBI\Report;
use App\Models\CBI\Sheet;
use App\Models\Financial;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;

class FinancialChartController extends Controller {
    /**
     * نمایش صفحه نمودار
     */
    public function showChart() {
        return view('chart');
    }

    /**
     * دریافت داده‌ها برای اولین رکورد یا بر اساس ID
     */
    public function getDataById( $id ) {
        $firstItem = Report::find($id);

        if ( !$firstItem ) {
            return response()->json([ 'error' => 'رکورد یافت نشد.' ], 404);
        }

        $sheetGrids = [];

        Sheet::where('report_id', $id)
            ->orderBy('index')
            ->chunk(50, function ( $sheetChunk ) use ( &$sheetGrids ) {
                $sheetChunk->load([
                    'rows'       => function ( $q ) {
                        $q->orderBy('row_index');
                    },
                    'rows.cells' => function ( $q ) {
                        $q->orderBy('column_index');
                    },
                    // ⭐️ dictionary برای سلول‌ها
                    'rows.cells.dictionary',
                    // ⭐️ dictionary برای نام شیت
                    'name',
                ]);

                foreach ( $sheetChunk as $sheet ) {
                    $totalRows = (int) ( $sheet->total_rows ?? 0 );
                    $totalCols = (int) ( $sheet->total_columns ?? 0 );

                    $grid = [];

                    foreach ( $sheet->rows as $row ) {
                        $r         = (int) $row->row_index;
                        $totalRows = max($totalRows, $r);

                        foreach ( $row->cells as $cell ) {
                            $c = (int) $cell->column_index;

                            $spanEnd = $c + (int) ( $cell->col_span ?? 1 ) - 1;

                            $totalCols = max($totalCols, $spanEnd);

                            $grid[$r][$c] = $cell;
                        }
                    }

                    $sheetGrids[$sheet->id] = [
                        'sheet'     => $sheet,
                        'grid'      => $grid,
                        'totalRows' => $totalRows,
                        'totalCols' => $totalCols,
                    ];
                }
            });
        return response()->json($sheetGrids);
    }

    /**
     * دریافت داده‌های نمودار برای یک عنوان خاص (جستجو با LIKE)
     */
    public function getData( $title ) {
        $firstItem = Financial::where('title', 'LIKE', "%{$title}%")
            ->first();

        if ( !$firstItem ) {
            return response()->json([ 'error' => 'داده‌ای برای عنوان مورد نظر یافت نشد.' ], 404);
        }

        return $this->buildResponse($firstItem->title);
    }

    /**
     * ساخت پاسخ JSON مشترک
     */
    private function buildResponse( string $exactTitle ) {
        $items = Financial::where('title', $exactTitle)
            ->orderBy('month', 'asc')
            ->get()
            ->unique(fn( $item ) => $item->title . '|' . $item->month);

        if ( $items->isEmpty() ) {
            return response()->json([ 'error' => 'داده‌ای یافت نشد.' ], 404);
        }

        $labels   = [];
        $datasets = [
            'value'            => [],
            'growth'           => [],
            'growth_yoy'       => [],
            'growth_end'       => [],
            'share_current'    => [],
            'share_previous'   => [],
            'share_growth_end' => [],
        ];

        foreach ( $items as $item ) {
            $jalali   = Verta::parse($item->month);
            $labels[] = $jalali->format('F Y');

            $datasets['value'][]            = (float) $item->value;
            $datasets['growth'][]           = (float) $item->growth;
            $datasets['growth_yoy'][]       = (float) $item->growth_yoy;
            $datasets['growth_end'][]       = (float) $item->growth_end;
            $datasets['share_current'][]    = (float) $item->share_current;
            $datasets['share_previous'][]   = (float) $item->share_previous;
            $datasets['share_growth_end'][] = (float) $item->share_growth_end;
        }

        return response()->json([
            'title'    => $exactTitle,
            'labels'   => $labels,
            'datasets' => $datasets,
            // در صورت نیاز: 'sheets' => [] را از کنترلر مربوطه بگیر
        ]);
    }
}