<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

class MigrateFinancialDataJob implements ShouldQueue {
    use Queueable;

    protected int $reportId;

    /**
     * Create a new job instance.
     */
    public function __construct( int $reportId ) {
        $this->reportId = $reportId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void {
        Artisan::call('cbi:migrate', [
            'report_id' => $this->reportId,
        ]);
    }
}