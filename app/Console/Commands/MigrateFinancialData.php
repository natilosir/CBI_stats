<?php

namespace App\Console\Commands;

use App\Http\Controllers\Excel\ExcelReaderService;
use App\Http\Controllers\Excel\SmartSheetParserTrait;
use App\Models\CBI\Report;
use App\Models\CBI\Sheet;
use App\Models\Financial;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateFinancialData extends Command {
    use SmartSheetParserTrait;

    protected $signature   = 'cbi:migrate {report_id? : شناسه گزارش خاص (اختیاری)}';
    protected $description = 'استخراج داده‌های مالی از شیت‌ها و ذخیره در financial_items';

    public function handle() {
        $reportId = $this->argument('report_id');
        $query    = Report::query();

        if ( $reportId ) {
            $query->where('id', $reportId);
        }

        $reports = $query->get();

        if ( $reports->isEmpty() ) {
            $this->error('هیچ گزارشی یافت نشد.');
            return self::FAILURE;
        }

        $bar = $this->output->createProgressBar(count($reports));
        $bar->start();

        foreach ( $reports as $report ) {
            $this->line("\nپردازش گزارش: {$report->id} - {$report->title}");

            // پیدا کردن شیت اول (index=0)
            $sheet = Sheet::where('report_id', $report->id)
                ->where('index', 0)
                ->first();

            if ( !$sheet ) {
                $this->warn("  شیتی برای گزارش {$report->id} یافت نشد.");
                $bar->advance();
                continue;
            }

            // استخراج ماه از نام فایل (مثلاً 140401.xls)
            $month = $this->extractMonthFromReport($report);
            if ( !$month ) {
                $this->warn("  ماه از نام فایل گزارش {$report->id} قابل استخراج نیست.");
                $bar->advance();
                continue;
            }

            // پردازش شیت
            $parsed = $this->parseMonetarySheet($sheet, $month);
            if ( !$parsed ) {
                $this->warn("  پردازش شیت برای گزارش {$report->id} ناموفق بود.");
                $bar->advance();
                continue;
            }

            // ساخت درخت
            $tree = $this->buildTreeFromGrid($parsed['grid'], $parsed['cols'] ?? []);
            // حذف گره‌های بدون فرزند
            $tree = $this->filterEmptyChildren($tree);

            // ذخیره در financial_items با تراکنش
            DB::transaction(function () use ( $tree, $report, $month ) {
                // حذف داده‌های قبلی این گزارش (اختیاری)
                Financial::where('report_id', $report->id)
                    ->delete();

                $this->storeTree($tree, $report->id, $month);
            });

            $this->info("  ✓ ذخیره شد.");
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('مهاجرت با موفقیت انجام شد.');
        return self::SUCCESS;
    }

    /**
     * استخراج ماه 6 رقمی از نام فایل
     */
    private function extractMonthFromReport( Report $report ): ?string {
        $fileName = $report->file_name;
        if ( preg_match('/(\d{6})\./', $fileName, $matches) ) {
            return $matches[1];
        }
        return null;
    }

    /**
     * ذخیره بازگشتی درخت در جدول financial_items
     */
    private function storeTree( array $nodes, int $reportId, string $monthStr, ?int $parentId = null, int $level = 0 ) {
        // Convert once per level (or per call) – fine for this use case
        $monthCarbon = $this->convertPersianMonthToCarbon($monthStr);

        foreach ( $nodes as $node ) {
            $item = Financial::create([
                'report_id'        => $reportId,
                'parent_id'        => $parentId,
                'title'            => app(ExcelReaderService::class)->normalizePersianString($node['title']) ?? '',
                'value'            => $node['value'] ?? null,
                'growth'           => $node['growth'] ?? null,
                'growth_yoy'       => $node['growth_yoy'] ?? null,
                'growth_end'       => $node['growth_end'] ?? null,
                'share_current'    => $node['share_current'] ?? null,
                'share_previous'   => $node['share_previous'] ?? null,
                'share_growth_end' => $node['share_growth_end'] ?? null,
                'level'            => $level,
                'month'            => $monthCarbon,
            ]);

            if ( !empty($node['children']) ) {
                $this->storeTree($node['children'], $reportId, $monthStr, $item->id, $level + 1);
            }
        }
    }

    /**
     * حذف گره‌هایی که children خالی دارند (با کلکشن لاراول)
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

    private function convertPersianMonthToCarbon( string $persianMonth ): Carbon {
        $year       = substr($persianMonth, 0, 4);
        $month      = substr($persianMonth, 4, 2);
        $day        = '01';
        $jalaliDate = "{$year}-{$month}-{$day}";
        $verta      = new Verta($jalaliDate);

        return $verta->toCarbon()
            ->startOfDay();
    }
}