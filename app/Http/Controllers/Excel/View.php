<?php
namespace App\Http\Controllers\Excel;

use App\Models\CBI\Report;
use App\Models\CBI\Sheet;

trait View {
    use SmartSheetParserTrait;

    public function extractId( $id ) {
        $report = Report::where('id', $id)->firstOrFail();
        $sheet = Sheet::where('report_id', $report->id)->where('index', 0)->first();

        if ( !$sheet ) {
            return response()->json([]);
        }

        // تلاش برای پردازش با الگوی استاندارد
        $parsed = $this->parseMonetarySheet($sheet);

        // اعتبارسنجی خروجی برای اطمینان از درست شناسایی شدن ستون‌ها
        $isValid = false;
        if ($parsed && !empty($parsed['cols']['mande_current'])) {
            foreach ($parsed['grid'] as $r => $row) {
                // پیدا کردن اولین ردیف داده (ردیفی که ستون B آن پر است)
                if (isset($row[2]) && !empty($this->cellValue($row[2]))) {
                    $val = $this->cellValue($row[$parsed['cols']['mande_current']] ?? '');
                    // حذف کاراکترهای اضافی برای بررسی عدد بودن
                    $cleanVal = str_replace(['/', ',', '-', ' ', '٪', '%'], '', $val);

                    // اگر مقدار استخراج شده عدد، علامت # یا خالی باشد، ستون درست است
                    // اما اگر متن باشد (مثل "پایه پولی") یعنی ستون اشتباه تشخیص داده شده
                    if (is_numeric($cleanVal) || $cleanVal === '#' || $cleanVal === '') {
                        $isValid = true;
                    }
                    break;
                }
            }
        }

        // اگر الگوی اول نامعتبر بود، از الگوی دوم (سال‌های بدون ماه در هدر) استفاده می‌کنیم
        if (!$isValid) {
            $parsed = $this->parseMonetarySheetType2($sheet);
        }

        if ( !$parsed ) {
            return response()->json([]);
        }

        $result = $this->buildTreeFromGrid($parsed['grid'], $parsed['cols'] ?? []);
        $result = $this->filterEmptyChildren($result);
        $result[] = $parsed['grid'];

        return response()->json($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * حذف گره‌هایی که children خالی دارند با استفاده از کلکشن
     */
    private function filterEmptyChildren( array $nodes ): array {
        return collect($nodes)
            ->map(function ( $node ) {
                if ( isset($node['children']) && is_array($node['children']) ) {
                    $node['children'] = $this->filterEmptyChildren($node['children']);
                }
                return $node;
            })
            ->filter(function ( $node ) {
                if ( isset($node['children']) ) {
                    return !empty($node['children']);
                }
                return true;
            })
            ->values()
            ->toArray();
    }
}