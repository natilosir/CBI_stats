<?php

namespace App\Http\Controllers\Excel;

use App\Http\Controllers\Controller;
use App\Models\CBI\Report;
use App\Models\CBI\Sheet;
use Illuminate\Http\Request;

class ExcelController extends Controller {

    use View;

    private $excelReader;

    public function __construct( ExcelReaderService $excelReader ) {
        $this->excelReader = $excelReader;
    }

    /**
     * @throws \Exception
     */
    public function upload( $id ) {
        $report = Report::findOrFail($id);

        return app(ExcelReaderService::class)->processFromUrl($report);
    }

    public function showSheets( $id ) {
        $report = Report::where('id', $id)
            ->first();

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

        //        return response()->json($sheetGrids);

        return view('excel.sheets_grid', compact('sheetGrids'));
    }

    public function extractPortfolioStructured( Request $request ) {
        $report = Report::where('letter_serial', $request->serial)
            ->firstOrFail();

        $result = [];

        Sheet::where('report_id', $report->id)
            ->each(function ( $sheet ) use ( &$result ) {
                $sheet->load([ 'rows.cells.dictionary' ]);

                /*
                |--------------------------------------------------------------------------
                | 1. Grid
                |--------------------------------------------------------------------------
                */
                $grid = [];
                foreach ( $sheet->rows as $row ) {
                    foreach ( $row->cells as $cell ) {
                        $grid[$row->row_index][$cell->column_index] = $cell;
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | 2. تشخیص ردیف هدر (anchor)
                |--------------------------------------------------------------------------
                */
                $anchorRow = null;

                foreach ( $grid as $r => $row ) {
                    foreach ( $row as $cell ) {
                        if ( trim($cell->dictionary->value ?? '') === 'تغییرات طی دوره' ) {
                            $anchorRow = $r;
                        }
                    }
                }

                if ( $anchorRow === null ) {
                    return;
                }

                $headerRows = [
                    $grid[$anchorRow] ?? [],
                    $grid[$anchorRow + 1] ?? [],
                    $grid[$anchorRow + 2] ?? [],
                ];

                /*
                |--------------------------------------------------------------------------
                | 3. تعریف مپ فیلدها
                |--------------------------------------------------------------------------
                */
                $fieldMap = [
                    'تعداد'                       => 'count',
                    'بهای تمام شده'               => 'total_cost',
                    'مبلغ فروش'                   => 'total_cost',
                    'قیمت بازار'                  => 'market_price',
                    'خالص ارزش فروش'              => 'net_sale_value',
                    'درصد به کل دارایی‌های صندوق' => 'asset_ratio',
                ];

                $columnDefinition = [];

                foreach ( $headerRows[2] as $col => $cell ) {
                    $fieldTitle = trim($cell->dictionary->value ?? '');
                    if ( !isset($fieldMap[$fieldTitle]) ) {
                        continue;
                    }

                    $section = null;
                    $sub     = null;

                    // ردیف ۱
                    $top = trim($headerRows[0][$col]->dictionary->value ?? '');

                    // ردیف ۲
                    $mid = trim($headerRows[1][$col]->dictionary->value ?? '');

                    if ( str_contains($top, 'تغییرات طی دوره') ) {
                        $section = 'period_changes';

                        if ( str_contains($mid, 'خرید') ) {
                            $sub = 'buy';
                        }
                        elseif ( str_contains($mid, 'فروش') ) {
                            $sub = 'sell';
                        }
                    }

                    if ( preg_match('/1404\/09\/30/', $top) ) {
                        $section = 'asset';
                    }

                    if ( $section ) {
                        $columnDefinition[$col] = [
                            'section' => $section,
                            'sub'     => $sub,
                            'field'   => $fieldMap[$fieldTitle],
                        ];
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | 4. شروع دیتای شرکت‌ها
                |--------------------------------------------------------------------------
                */
                $startRow = $anchorRow + 3;
                $maxRow   = max(array_keys($grid));

                for ( $r = $startRow; $r <= $maxRow; $r ++ ) {
                    if ( !isset($grid[$r][1]) ) {
                        continue;
                    }

                    $company = trim($grid[$r][1]->dictionary->value ?? '');
                    if ( $company === '' || str_contains($company, 'جمع') ) {
                        continue;
                    }

                    if ( !isset($result[$company]) ) {
                        $result[$company] = [
                            'period_changes' => [
                                'buy'  => [ 'count' => '', 'total_cost' => '' ],
                                'sell' => [ 'count' => '', 'total_cost' => '' ],
                            ],
                            'asset'          => [
                                'count'          => '',
                                'market_price'   => '',
                                'total_cost'     => '',
                                'net_sale_value' => '',
                                'asset_ratio'    => '',
                            ],
                        ];
                    }

                    foreach ( $columnDefinition as $col => $def ) {
                        $value = trim($grid[$r][$col]->dictionary->value ?? '');

                        if ( $def['section'] === 'period_changes' ) {
                            $result[$company]['period_changes']
                            [$def['sub']]
                            [$def['field']] = $value;
                        }
                        else {
                            $result[$company]['asset']
                            [$def['field']] = $value;
                        }
                    }
                }
            });

        return response()->json($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

}
