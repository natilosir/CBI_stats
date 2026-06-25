<?php

namespace App\Http\Controllers\Excel;

use App\Models\CBI\Sheet;
use Illuminate\Support\Str;

trait SmartSheetParserTrait {
    /**
     * پردازش شیت جدول پولی بانک مرکزی
     */
    protected function parseMonetarySheet( Sheet $sheet, string $targetMonth ): ?array {
        $sheet->load([ 'rows.cells.dictionary', 'name' ]);
        $grid = $this->buildGrid($sheet);
        if ( empty($grid) ) return null;

        $totalRows = (int) ( $sheet->total_rows ?? 0 );
        $totalCols = (int) ( $sheet->total_columns ?? 0 );

        foreach ( $grid as $r => $row ) {
            $totalRows = max($totalRows, $r);
            foreach ( $row as $c => $cell ) {
                $spanEnd   = $c + (int) ( $cell->col_span ?? 1 ) - 1;
                $totalCols = max($totalCols, $spanEnd);
            }
        }

        $monthMap   = [
            '01' => 'فروردین',
            '02' => 'اردیبهشت',
            '03' => 'خرداد',
            '04' => 'تیر',
            '05' => 'مرداد',
            '06' => 'شهریور',
            '07' => 'مهر',
            '08' => 'آبان',
            '09' => 'آذر',
            '10' => 'دی',
            '11' => 'بهمن',
            '12' => 'اسفند',
        ];
        $monthNames = array_values($monthMap);

        // 1. Detect current month & year
        $currentMonth = null;
        $currentYear  = 0;
        for ( $r = 1; $r <= 10; $r ++ ) {
            if ( !isset($grid[$r]) ) continue;
            foreach ( $grid[$r] as $c => $cell ) {
                $val = $this->cellValue($cell);
                if ( preg_match('/^(\d{4})$/', $val, $m) ) {
                    $year     = (int) $m[1];
                    $monthVal = $this->cellValue($grid[$r - 1][$c] ?? null);
                    if ( in_array($monthVal, $monthNames) ) {
                        if ( $year > $currentYear || ( $year == $currentYear && array_search($monthVal, $monthNames) > array_search($currentMonth, $monthNames) ) ) {
                            $currentYear  = $year;
                            $currentMonth = $monthVal;
                        }
                    }
                }
            }
        }

        // Fallback
        if ( !$currentMonth || !$currentYear ) {
            $year         = (int) substr($targetMonth, 0, 4);
            $month        = substr($targetMonth, 4, 2);
            $currentMonth = $monthMap[$month] ?? 'خرداد';
            $currentYear  = $year;
        }

        // ***** FIX: prevYear is always currentYear - 1 *****
        $prevYear = $currentYear - 1;
        // prevMonth is only used for share_mande_prev fallback (we now rely on 'اسفند')
        $monthIndex = array_search($currentMonth, $monthNames);
        $prevMonth  = $monthIndex > 0 ? $monthNames[$monthIndex - 1] : 'اسفند';

        // 2. Map parent categories (unchanged)
        $parentCategory = [];
        for ( $r = 1; $r <= 10; $r ++ ) {
            if ( !isset($grid[$r]) ) continue;
            foreach ( $grid[$r] as $c => $cell ) {
                $val  = $this->cellValue($cell);
                $span = (int) ( $cell->col_span ?? 1 );
                if ( Str::contains($val, 'سهم از رشد') ) {
                    for ( $i = 0; $i < $span; $i ++ ) $parentCategory[$c + $i] = 'share_growth';
                }
                elseif ( Str::contains($val, 'رشد') ) {
                    for ( $i = 0; $i < $span; $i ++ ) $parentCategory[$c + $i] = 'growth';
                }
                elseif ( Str::contains($val, 'مانده') && !Str::contains($val, 'سهم مانده') ) {
                    for ( $i = 0; $i < $span; $i ++ ) $parentCategory[$c + $i] = 'mande';
                }
            }
        }

        // 3. Find columns
        $cols = [
            'mande_current'           => null,
            'growth_yoy'              => null,
            'growth_end'              => null,
            'share_growth_yoy'        => null,
            'share_growth_end'        => null,
            'share_mande_current'     => null,
            'share_mande_prev'        => null,
            'share_section_start_row' => null,
        ];

        for ( $r = 1; $r <= 10; $r ++ ) {
            if ( !isset($grid[$r]) ) continue;
            foreach ( $grid[$r] as $c => $cell ) {
                $val = $this->cellValue($cell);
                $cat = $parentCategory[$c] ?? null;

                if ( $cat === 'mande' ) {
                    if ( in_array($val, $monthNames) ) {
                        $yearBelow = $this->cellValue($grid[$r + 1][$c] ?? null);
                        if ( $val === $currentMonth && (int) $yearBelow === $currentYear ) {
                            $cols['mande_current'] = $c;
                        }
                    }
                }
                elseif ( $cat === 'growth' || $cat === 'share_growth' ) {
                    // ***** FIX: growth_end = "به اسفند" + prevYear, growth_yoy = "به" + currentMonth + prevYear *****
                    if ( Str::startsWith($val, 'به') ) {
                        // Year-over-year: "به" + currentMonth + prevYear
                        if ( Str::contains($val, $currentMonth) && Str::contains($val, (string) $prevYear) ) {
                            if ( $cat === 'growth' ) $cols['growth_yoy'] = $c;
                            else $cols['share_growth_yoy'] = $c;
                        }
                        // End of previous year: "به اسفند" + prevYear
                        if ( Str::contains($val, 'اسفند') && Str::contains($val, (string) $prevYear) ) {
                            if ( $cat === 'growth' ) $cols['growth_end'] = $c;
                            else $cols['share_growth_end'] = $c;
                        }
                    }
                }
            }
        }

        // 4. Find share section (unchanged logic, but prevYear is now correct)
        for ( $r = 1; $r <= $totalRows; $r ++ ) {
            if ( !isset($grid[$r]) ) continue;
            foreach ( $grid[$r] as $c => $cell ) {
                $val = $this->cellValue($cell);
                if ( Str::contains($val, 'سهم مانده اجزا') ) {
                    $cols['share_section_start_row'] = $r;
                    for ( $sr = $r + 1; $sr <= $r + 3; $sr ++ ) {
                        if ( !isset($grid[$sr]) ) continue;
                        foreach ( $grid[$sr] as $sc => $scell ) {
                            $sval = $this->cellValue($scell);
                            // Current header: currentMonth + currentYear
                            if ( Str::contains($sval, $currentMonth) && Str::contains($sval, (string) $currentYear) ) {
                                $cols['share_mande_current'] = $sc;
                            }
                            // Previous header: always "اسفند" + prevYear
                            if ( Str::contains($sval, 'اسفند') && Str::contains($sval, (string) $prevYear) ) {
                                $cols['share_mande_prev'] = $sc;
                            }
                        }
                    }
                    break 2;
                }
            }
        }

        return [
            'grid'      => $grid,
            'totalRows' => $totalRows,
            'totalCols' => $totalCols,
            'cols'      => $cols,
        ];
    }

    protected function buildGrid( Sheet $sheet ): array {
        $grid = [];
        foreach ( $sheet->rows as $row ) {
            foreach ( $row->cells as $cell ) {
                $grid[$row->row_index][$cell->column_index] = $cell;
            }
        }
        return $grid;
    }

    protected function cellValue( $cell ): string {
        return trim((string) ( $cell->dictionary->value ?? '' ));
    }

    /**
     * ساخت درخت با استفاده از ایندکس‌های اصلی و معنایی ستون‌ها
     */
    protected function buildTreeFromGrid( array $grid, array $cols = [] ): array {
        ksort($grid);

        $mandeCurrent      = $cols['mande_current'] ?? null;
        $growthYoy         = $cols['growth_yoy'] ?? null;
        $growthEnd         = $cols['growth_end'] ?? null;
        $shareGrowthYoy    = $cols['share_growth_yoy'] ?? null;
        $shareGrowthEnd    = $cols['share_growth_end'] ?? null;
        $shareMandeCurrent = $cols['share_mande_current'] ?? null;
        $shareMandePrev    = $cols['share_mande_prev'] ?? null;
        $shareSectionStart = $cols['share_section_start_row'] ?? null;

        $parentRows = [];
        foreach ( $grid as $rowIdx => $row ) {
            if ( isset($row[1]) && !empty($this->cellValue($row[1])) ) {
                $parentRows[] = $rowIdx;
            }
        }

        $tree = [];
        foreach ( $parentRows as $idx => $rowIdx ) {
            $cellA         = $grid[$rowIdx][1];
            $rowSpan       = (int) ( $cellA->row_span ?? 1 );
            $endRow        = $rowIdx + $rowSpan - 1;
            $nextParentIdx = $parentRows[$idx + 1] ?? null;
            if ( $nextParentIdx !== null && $nextParentIdx <= $endRow ) {
                $endRow = $nextParentIdx - 1;
            }

            $node = [
                'title'    => $this->cellValue($cellA),
                'children' => [],
            ];

            $shareNode      = null;
            $inShareSection = false;

            for ( $r = $rowIdx; $r <= $endRow; $r ++ ) {
                if ( !isset($grid[$r]) ) continue;
                $rowData = $grid[$r];
                if ( !isset($rowData[2]) || empty($this->cellValue($rowData[2])) ) continue;

                $cellB  = $rowData[2];
                $bTitle = $this->cellValue($cellB);

                $isShareSection = ( $shareSectionStart && $r >= $shareSectionStart );

                if ( $isShareSection && !$inShareSection ) {
                    $inShareSection = true;
                    $shareNode      = [
                        'title'    => 'سهم مانده اجزا از مانده نقدينگي',
                        'children' => [],
                        'headers'  => [
                            'current'  => $shareMandeCurrent ? $this->cellValue($grid[$shareSectionStart + 1][$shareMandeCurrent] ?? '') : '',
                            'previous' => $shareMandePrev ? $this->cellValue($grid[$shareSectionStart + 1][$shareMandePrev] ?? '') : '',
                        ],
                    ];
                }

                // استخراج مقادیر بر اساس ستون‌های پیدا شده
                $value        = $mandeCurrent ? $this->cellValue($rowData[$mandeCurrent] ?? '') : null;
                $growth       = $growthYoy ? $this->cellValue($rowData[$growthYoy] ?? '') : null;
                $growthEndVal = $growthEnd ? $this->cellValue($rowData[$growthEnd] ?? '') : null;

                $shareCurrent      = null;
                $sharePrev         = null;
                $shareGrowthEndVal = $shareGrowthEnd ? $this->cellValue($rowData[$shareGrowthEnd] ?? '') : null;

                if ( $isShareSection ) {
                    $shareCurrent = $shareMandeCurrent ? $this->cellValue($rowData[$shareMandeCurrent] ?? '') : null;
                    $sharePrev    = $shareMandePrev ? $this->cellValue($rowData[$shareMandePrev] ?? '') : null;
                }
                else {
                    $shareCurrent = $shareGrowthYoy ? $this->cellValue($rowData[$shareGrowthYoy] ?? '') : null;
                    $sharePrev    = $shareGrowthEnd ? $this->cellValue($rowData[$shareGrowthEnd] ?? '') : null;
                }

                $child = [
                    'title'            => $bTitle,
                    'value'            => $value,
                    'growth'           => $growth ?? '0',
                    'growth_yoy'       => $growth ?? '0',
                    'growth_end'       => $growthEndVal,
                    'share_current'    => $shareCurrent,
                    'share_previous'   => $sharePrev,
                    'share_growth_end' => $shareGrowthEndVal,
                ];

                if ( $inShareSection ) {
                    $shareNode['children'][] = $child;
                }
                else {
                    $node['children'][] = $child;
                }
            }

            if ( $shareNode ) {
                $node['children'][] = $shareNode;
            }
            $tree[] = $node;
        }
        return $tree;
    }
}