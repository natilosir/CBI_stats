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

//                return response()->json($sheetGrids);

        return view('excel.sheets_grid', compact('sheetGrids'));
    }

}
