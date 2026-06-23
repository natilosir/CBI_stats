<?php

namespace App\Core\Excel;

use App\Models\Fund\{Cell, Dictionary, Row, Sheet};
use Illuminate\Support\Facades\{DB, Http, Storage};
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

class ExcelReaderService {
    private string $letter_serial;
    private int    $currentFundId;

    private const MAX_ROWS    = 450;
    private const MAX_COLUMNS = 55;

    public function processFromUrl( $id, $letter_serial ): array {
        $this->letter_serial = $letter_serial;
        $this->currentFundId = $id;

        $uri = 'https://codal.ir/Reports/DownloadFile.aspx';

        $response = Http::withoutVerifying()->timeout(60)->get($uri, [ 'id' => $this->letter_serial ]);

        if ( !$response->successful() ) {
            throw new \Exception("Failed to download Excel file from URL: {$this->letter_serial}");
        }

        $safeFileName     = uniqid() . '.xlsx';
        $relativeTempPath = 'temp/' . $safeFileName;

        Storage::put($relativeTempPath, $response->body());
        $absoluteTempPath = Storage::path($relativeTempPath);

        try {
            return $this->processFile($absoluteTempPath);
        } finally {
            Storage::delete($relativeTempPath);
        }
    }

    public function processFile( string $filePath ): array {
        $reader = IOFactory::createReaderForFile($filePath);

        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($filePath);

        $results    = [];
        $sheetNames = $spreadsheet->getSheetNames();

        $startIndex = 0;

        $sheetsToProcess = array_slice($sheetNames, $startIndex, 7, true);
        foreach ( $sheetsToProcess as $sheetIndex => $sheetName ) {
            $sheet     = $spreadsheet->getSheet($sheetIndex);
            $results[] = $this->processSheet($sheet, $sheetName, $sheetIndex);

            $sheet->disconnectCells();
            unset($sheet);
            gc_collect_cycles();
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        gc_collect_cycles();

        return $results;
    }

    private function processSheet( $sheet, string $sheetName, int $sheetIndex ): array {
        DB::beginTransaction();

        try {
            $nameHash = $this->generateHash($sheetName);

            $dictionaryValues = [
                $nameHash => $sheetName,
            ];

            $this->syncDictionary($dictionaryValues);

            $sheetModel = Sheet::updateOrCreate([
                'fund_id' => $this->currentFundId,
                'index'   => $sheetIndex,
            ], [
                'hash'          => $nameHash,
                'total_rows'    => $sheet->getHighestRow(),
                'total_columns' => Coordinate::columnIndexFromString($sheet->getHighestColumn()),
            ]);

            // 1. نقشه Mergeها
            $mergeMap  = [];
            $hiddenMap = [];

            foreach ( $sheet->getMergeCells() as $range ) {
                $boundaries = Coordinate::rangeBoundaries($range);
                $minCol     = $boundaries[0][0];
                $minRow     = $boundaries[0][1];
                $maxCol     = $boundaries[1][0];
                $maxRow     = $boundaries[1][1];

                $key            = "{$minCol}-{$minRow}";
                $mergeMap[$key] = [
                    'row_span'     => $maxRow - $minRow + 1,
                    'col_span'     => $maxCol - $minCol + 1,
                    'start_row'    => $minRow,
                    'end_row'      => $maxRow,
                    'start_column' => $minCol,
                    'end_column'   => $maxCol,
                ];

                // سلول‌های زیرمجموعه را مخفی علامت‌گذاری کن
                for ( $r = $minRow; $r <= $maxRow; $r ++ ) {
                    for ( $c = $minCol; $c <= $maxCol; $c ++ ) {
                        if ( $r === $minRow && $c === $minCol ) {
                            continue;
                        }
                        $hiddenMap["{$c}-{$r}"] = true;
                    }
                }
            }

            // 2. جمع‌آوری داده‌ها و شناسایی ستون‌های معتبر
            $highestRow = min($sheet->getHighestRow(), self::MAX_ROWS);

            $highestCol = min(Coordinate::columnIndexFromString($sheet->getHighestColumn()), self::MAX_COLUMNS);

            // آرایه برای نگهداری داده‌های هر ستون
            $columnData = [];
            $rowHasData = array_fill(1, $highestRow, false);

            // جمع‌آوری داده‌ها و شناسایی ستون‌های معتبر
            for ( $rowIdx = 1; $rowIdx <= $highestRow; $rowIdx ++ ) {
                for ( $colIdx = 1; $colIdx <= $highestCol; $colIdx ++ ) {
                    $cellKey  = "{$colIdx}-{$rowIdx}";
                    $rowSpan  = 1;
                    $colSpan  = 1;
                    $isHidden = false;

                    if ( isset($mergeMap[$cellKey]) ) {
                        $rowSpan = $mergeMap[$cellKey]['row_span'];
                        $colSpan = $mergeMap[$cellKey]['col_span'];
                    }
                    elseif ( isset($hiddenMap[$cellKey]) ) {
                        $isHidden = true;
                    }

                    if ( $isHidden ) {
                        continue;
                    }

                    $cellObj = $sheet->getCellByColumnAndRow($colIdx, $rowIdx);

                    try {
                        if ( $cellObj->isFormula() ) {
                            $value = $cellObj->getOldCalculatedValue();
                        }
                        else {
                            $value = $cellObj->getValue();
                        }
                    } catch ( \Throwable $e ) {
                        $value = $cellObj->getValue();
                    }

                    if ( !isset($columnData[$colIdx]) ) {
                        $columnData[$colIdx] = [
                            'has_valid_data' => false,
                            'cells'          => [],
                        ];
                    }

                    // بررسی آیا مقدار معتبر است
                    $parsedData   = $this->parseAndPrepareValue($value);
                    $isValidValue = ( $parsedData['type'] !== 'skip' );

                    // اگر مقدار معتبر است، ستون و سطر را علامت‌گذاری کن
                    if ( $isValidValue && $rowIdx > 1 ) { // سطرهای غیر هدر
                        $columnData[$colIdx]['has_valid_data'] = true;
                        $rowHasData[$rowIdx]                   = true;
                    }

                    $columnData[$colIdx]['cells'][$rowIdx] = [
                        'value'        => $parsedData, // ذخیره داده تجزیه شده
                        'rowSpan'      => $rowSpan,
                        'colSpan'      => $colSpan,
                        'isHidden'     => $isHidden,
                        'isMergeStart' => isset($mergeMap[$cellKey]),
                        'isValid'      => $isValidValue,
                    ];
                }
            }

            // 3. شناسایی ستون‌هایی که حداقل یک مقدار معتبر دارند
            $validColumns = [];
            foreach ( $columnData as $colIdx => $data ) {
                if ( $data['has_valid_data'] ) {
                    $validColumns[] = $colIdx;
                }
            }

            // اگر هیچ ستون معتبری وجود ندارد، شیت را رد کن
            if ( empty($validColumns) ) {
                DB::commit();
                return [
                    'sheet'  => $sheetModel->name,
                    'status' => 'skipped_no_valid_columns',
                ];
            }

            sort($validColumns);

            $cellsToInsert = [];
            $stringValues  = [];
            $savedRows     = 0;

            for ( $rowIdx = 1; $rowIdx <= $highestRow; $rowIdx ++ ) {
                if ( !$rowHasData[$rowIdx] && $rowIdx > 1 ) {
                    continue;
                }

                $rowModel = Row::updateOrCreate([
                    'sheet_id'  => $sheetModel->id,
                    'row_index' => $rowIdx,
                ]);
                $savedRows ++;

                foreach ( $validColumns as $newColIdx => $originalColIdx ) {
                    if ( !isset($columnData[$originalColIdx]['cells'][$rowIdx]) ) {
                        continue;
                    }

                    $cellInfo = $columnData[$originalColIdx]['cells'][$rowIdx];

                    if ( $cellInfo['isHidden']
                         || ( $cellInfo['isMergeStart'] === false && ( $cellInfo['rowSpan'] > 1 || $cellInfo['colSpan'] > 1 ) ) ) {
                        continue;
                    }

                    $parsedData = $cellInfo['value'];
                    if ( $parsedData['type'] === 'skip' ) {
                        continue;
                    }

                    $cellData    = [
                        'row_id'       => $rowModel->id,
                        'column_index' => $newColIdx + 1,
                        'column_name'  => Coordinate::stringFromColumnIndex($newColIdx + 1),
                        'row_span'     => $cellInfo['rowSpan'],
                        'col_span'     => $cellInfo['colSpan'],
                    ];
                    $stringValue = $parsedData['value'];
                    $hash        = $this->generateHash($stringValue);

                    $cellData['hash']    = $hash;
                    $stringValues[$hash] = $stringValue;

                    $cellsToInsert[] = $cellData;
                }
            }

            if ( !empty($stringValues) ) {
                $this->syncDictionary($stringValues);
            }

            // 6. ذخیره سلول‌ها
            if ( !empty($cellsToInsert) ) {
                Cell::upsert($cellsToInsert, [ 'row_id', 'column_index' ], [ 'hash', 'row_span', 'col_span' ]);
            }

            $sheetModel->update([
                'total_rows'    => $savedRows,
                'total_columns' => count($validColumns),
            ]);

            DB::commit();

            return [
                'sheet'                  => $sheetModel->name,
                'status'                 => 'imported',
                'valid_columns_count'    => count($validColumns),
                'original_columns_count' => $highestCol,
                'saved_rows_count'       => $savedRows,
                'original_rows_count'    => $highestRow,
                'skipped_rows'           => $highestRow - $savedRows,
            ];
        } catch ( \Throwable $e ) {
            DB::rollBack();
            throw $e;
        }
    }

    public function generateHash( $value ): ?string {
        if ( $value === null || $value === '' || $value === 0 || $value === '0' ) {
            return null;
        }

        return substr(md5((string) $value), 0, 7);
    }

    public function syncDictionary( array $stringValues ): void {
        if ( empty($stringValues) ) {
            return;
        }

        $rows = [];

        foreach ( $stringValues as $hash => $value ) {
            if ( $hash === null ) {
                continue;
            }

            $rows[] = [
                'key'   => $hash,
                'value' => $value,
            ];
        }

        if ( empty($rows) ) {
            return;
        }

        Dictionary::upsert($rows, [ 'key' ], [ 'value' ]);
    }

    /**
     * مقادیر ورودی را تجزیه کرده و نوع آن (numeric, string, skip) را مشخص می‌کند.
     */
    private function parseAndPrepareValue( $value ): array {
        if ( $value instanceof RichText ) {
            $value = $value->getPlainText();
        }

        if ( is_string($value) ) {
            $value = trim($value);
        }

        if ( $value === null || $value === '' ) {
            return [ 'type' => 'skip' ];
        }

        if ( is_numeric($value) ) {
            return [ 'type' => 'numeric', 'value' => (float) $value ];
        }

        if ( is_string($value) && preg_match('/^[0-9,.]+$/', $value) ) {
            $n = str_replace(',', '', $value);
            if ( is_numeric($n) ) {
                return [ 'type' => 'numeric', 'value' => (float) $n ];
            }
        }

        return [ 'type' => 'string', 'value' => (string) $value ];
    }

    private function shouldSkipValue( $value ): bool {
        if ( $value instanceof RichText ) {
            $value = $value->getPlainText();
        }

        if ( is_string($value) ) {
            $value = trim($value);
        }

        if ( $value === null || $value === '' ) {
            return true;
        }

        if ( $value === 0 || $value === '0' ) {
            // در صورتی که 0 مقدار واقعی باشد نباید حذف شود.
            // در این منطق فرض بر این است که 0 به عنوان یک مقدار خالی در نظر گرفته می‌شود.
            // اگر 0 یک مقدار مالی معتبر است، این شرط را حذف کنید.
            return true;
        }

        if ( is_string($value) && str_starts_with($value, '=') ) {
            // مقادیر فرمول
            return true;
        }

        return false;
    }
}
