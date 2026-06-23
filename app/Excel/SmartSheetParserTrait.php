<?php

namespace App\Core\Excel;

use App\Models\Fund\Sheet;
use Illuminate\Support\Str;

trait SmartSheetParserTrait {
    protected array $smartSheetConfig = [
        'company_column' => [
            'شرکت',
            'نام شرکت',
            'نام اوراق',
            'ناشر',
        ],
        // کلمات کلیدی دقیق برای ستون‌های مورد نظر طبق درخواست شما
        'asset'          => [
            'fields' => [
                'count'        => [ 'تعداد' ],
                'market_price' => [ 'قیمت بازار', 'قیمت بازار هر سهم', 'قیمت ابطال', 'قیمت بازار هر ورقه' ],
                'total_cost'   => [ 'بهای تمام شده' ],
                'net_sale'     => [ 'خالص ارزش فروش', 'خالص ارزش' ],
                'asset_ratio'  => [ 'درصد به کل دارایی', 'درصد از کل دارایی', 'درصد به کل دارایی ها' ],
            ],
        ],
    ];

    /**
     * متد اصلی پردازش
     */
    protected function parseSmartSheet( Sheet $sheet ): array {
        $config = $this->smartSheetConfig;
        $sheet->load([ 'rows.cells.dictionary' ]);

        // ساخت گرید خام (بدون حذف هیچ ستونی)
        $grid = $this->buildGrid($sheet);

        // بررسی اعتبار فایل
        if ( !$this->sheetLooksLikePortfolio($grid, $config) ) {
            return [];
        }

        // یافتن دقیق ستون‌ها بر اساس نام
        $columnMap = $this->mapColumns($grid, $config);

        if ( !isset($columnMap['company']) ) {
            return [];
        }

        $companyCol = $columnMap['company'];
        $startRow   = $this->findStartRow($grid, $companyCol, $config);

        $result = [];

        foreach ( $grid as $rowIndex => $row ) {
            // رد کردن ردیف‌های قبل از هدر
            if ( $rowIndex < $startRow ) continue;

            // اگر ستون شرکت وجود ندارد
            if ( !isset($row[$companyCol]) ) continue;

            $company = $this->normalize($this->cellValue($row[$companyCol]));

            // فیلتر کردن ردیف‌های نامعتبر، خالی یا جمع کل
            if ( $company === '' || is_numeric($company)
                 || Str::contains($company, [ 'جمع', 'مجموع', 'جمع کل', 'جمع‌کل' ]) ) {
                continue;
            }

            $result[$company] = $this->extractRowData($grid, $rowIndex, $columnMap);
        }

        return $result;
    }

    protected function mapColumns( array $grid, array $config ): array {
        $map = [];

        // 1. یافتن ستون نام شرکت (اولین ستونی که پیدا شود)
        $map['company'] = $this->findColumn($grid, (array) $config['company_column']);
        if ( !isset($map['company']) ) return $map;

        // 2. یافتن ستون‌های دارایی
        // استراتژی: جستجو برای "آخرین" (راست‌ترین) ستونی که نامش مچ می‌شود.
        // این کار باعث می‌شود ستون‌های "خرید طی دوره" یا "فروش طی دوره" نادیده گرفته شوند
        // و ستون‌های "پایان دوره" که همیشه در انتهای جدول هستند انتخاب شوند.

        foreach ( $config['asset']['fields'] as $key => $searchTerms ) {
            // یافتن اندیس ستون برای هر فیلد (تعداد، قیمت، بها و ...)
            // اگر پیدا نشد، null برمی‌گردد و در extractRowData هندل می‌شود (مقدار 0)
            $map['asset'][$key] = $this->findLastColumn($grid, $searchTerms);
        }

        return $map;
    }

    /**
     * یافتن "آخرین" ستونی که شامل یکی از عبارات جستجو باشد.
     * کاربرد: جلوگیری از انتخاب اشتباه ستون "تعداد" در بخش خرید/فروش به جای بخش پایان دوره.
     */
    protected function findLastColumn( array $grid, array $terms ): ?int {
        $foundCol = null;
        $maxIndex = - 1;

        // جستجو در تمام ردیف‌ها (معمولاً هدرها در 10 خط اول هستند، اما کل گرید را چک می‌کنیم تا مطمئن شویم)
        // برای بهینگی می‌توانیم فقط ۲۰ خط اول را چک کنیم، اما اینجا امنیت اولویت دارد.
        foreach ( $grid as $rowIndex => $row ) {
            // اگر اندیس ردیف خیلی زیاد شد و هنوز چیزی پیدا نکردیم، شاید بهتر باشد متوقف شویم
            // اما فعلا کل گرید را بررسی می‌کنیم.

            foreach ( $row as $col => $cell ) {
                $value = $this->normalize($this->cellValue($cell));
                if ( $value === '' ) continue;

                foreach ( $terms as $term ) {
                    // تطبیق دقیق‌تر یا partial match
                    if ( Str::contains($value, $this->normalize($term)) ) {
                        // همیشه بزرگترین ایندکس ستون را نگه می‌داریم
                        if ( $col > $maxIndex ) {
                            $maxIndex = $col;
                            $foundCol = $col;
                        }
                    }
                }
            }
        }
        return $foundCol;
    }

    /**
     * استخراج داده از ردیف مشخص با استفاده از مپ ستون‌ها
     */
    protected function extractRowData( array $grid, int $row, array $map ) {
        $getFloat = function ( $val ) {
            if ( $val === null || $val === '' ) return 0;
            return (float) str_replace(',', '', $val);
        };

        return [
            'count'        => (int) $getFloat($this->value($grid, $row, $map['asset']['count'] ?? null)),
            'market_price' => $getFloat($this->value($grid, $row, $map['asset']['market_price'] ?? null)),
            'total_cost'   => $getFloat($this->value($grid, $row, $map['asset']['total_cost'] ?? null)),
            'net_sale'     => $getFloat($this->value($grid, $row, $map['asset']['net_sale'] ?? null)),
            'asset_ratio'  => $getFloat($this->value($grid, $row, $map['asset']['asset_ratio'] ?? null)),
        ];
    }

    // --- توابع کمکی ---

    protected function buildGrid( Sheet $sheet ): array {
        $grid = [];
        foreach ( $sheet->rows as $row ) {
            foreach ( $row->cells as $cell ) {
                $grid[$row->row_index][$cell->column_index] = $cell;
            }
        }
        return $grid;
    }

    protected function findStartRow( array $grid, int $companyCol, array $config ): int {
        foreach ( $grid as $rowIndex => $row ) {
            if ( !isset($row[$companyCol]) ) continue;
            $value = $this->normalize($this->cellValue($row[$companyCol]));
            foreach ( (array) $config['company_column'] as $term ) {
                if ( $value === $this->normalize($term) ) return $rowIndex + 1;
            }
        }
        return 1;
    }

    // یافتن اولین ستون (برای نام شرکت)
    protected function findColumn( array $grid, array $terms ): ?int {
        foreach ( $grid as $row ) {
            foreach ( $row as $col => $cell ) {
                $value = $this->normalize($this->cellValue($cell));
                if ( $value === '' ) continue;
                foreach ( $terms as $term ) {
                    if ( Str::contains($value, $this->normalize($term)) ) return $col;
                }
            }
        }
        return null;
    }

    protected function sheetLooksLikePortfolio( array $grid, array $config ): bool {
        foreach ( $grid as $row ) {
            foreach ( $row as $cell ) {
                $value = $this->normalize($this->cellValue($cell));
                if ( $value === '' or Str::contains($value, [ 'ترنج' ]) ) continue;
                foreach ( (array) $config['company_column'] as $term ) {
                    if ( Str::contains($value, $this->normalize($term)) ) return true;
                }
            }
        }
        return false;
    }

    protected function normalize( string $value ): string {
        $value = trim(str_replace([ "\u{202A}", "\u{202B}", "\u{202C}", "\u{200E}", "\u{200F}" ], '', $value));
        $value = str_replace([ "\u{2215}", "\u{FF0F}" ], '/', $value);
        $value = $this->persianToEnglishDigits($value);
        $value = preg_replace('/\x{00A0}/u', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value);
        return trim($value);
    }

    protected function persianToEnglishDigits( string $str ): string {
        $map = [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
            '٬' => ',',
            '٫' => '.',
        ];
        return strtr($str, $map);
    }

    protected function cellValue( $cell ): string {
        return trim((string) ( $cell->dictionary->value ?? '' ));
    }

    protected function value( array $grid, int $row, ?int $col ) {
        if ( $col === null || !isset($grid[$row][$col]) ) {
            return null;
        }
        return $this->normalize($this->cellValue($grid[$row][$col]));
    }
}
