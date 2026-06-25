<?php

namespace App\Console\Commands;

use App\Http\Controllers\Excel\ExcelReaderService;
use App\Jobs\MigrateFinancialDataJob;
use App\Jobs\ProcessCbiReportsJob;
use App\Models\CBI\Report;
use Illuminate\Console\Command;

class Extraction extends Command {
    protected $signature   = 'cbi:extraction {id : شناسه گزارش در جدول cbi_reports}';
    protected $description = 'پردازش فایل اکسل دانلود شده و استخراج داده‌ها';

    public function handle(): int {
        $id     = $this->argument('id');
        $report = Report::find($id);

        if ( !$report ) {
            $this->error("گزارشی با شناسه {$id} یافت نشد.");
            return self::FAILURE;
        }

        $this->info("🔄 پردازش گزارش: {$report->title} (شناسه: {$report->id})");
        $this->line("📁 فایل: {$report->file_name}");
        $this->newLine();

        try {
            /** @var ExcelReaderService $service */
            $service = app(ExcelReaderService::class);
            $results = $service->processFromUrl($report);
        } catch ( \Throwable $e ) {
            $this->error("❌ خطا در پردازش: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }

        if ( empty($results) ) {
            $this->warn("⚠️ هیچ شیتی پردازش نشد.");
            return self::SUCCESS;
        }

        $this->info("✅ تعداد شیت‌های پردازش‌شده: " . count($results));
        $this->newLine();

        // ---------- جدول خلاصه ----------
        $headers = [ 'شیت', 'وضعیت', 'ستون‌های معتبر', 'ردیف‌ها', 'ذخیره شده', 'رد شده' ];
        $rows    = [];

        foreach ( $results as $result ) {
            $status     = $result['status'] ?? 'ناشناخته';
            $statusIcon = match ( $status ) {
                'imported'                 => '✅',
                'skipped_no_valid_columns' => '⏭️',
                default                    => '❓'
            };

            $rows[] = [
                $result['sheet']->value ?? 'نامشخص',
                $statusIcon . ' ' . $status,
                $result['valid_columns_count'] ?? '-',
                $result['original_rows_count'] ?? '-',
                $result['saved_rows_count'] ?? '-',
                $result['skipped_rows'] ?? '-',
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();
        MigrateFinancialDataJob::dispatch($id);
        return self::SUCCESS;
    }
}