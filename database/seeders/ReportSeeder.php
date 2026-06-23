<?php

namespace Database\Seeders;

use App\Models\CBI\Report;
use Illuminate\Database\Seeder;

class ReportSeeder extends Seeder {
    public function run(): void {
        Report::create([
            'id'        => '1',
            'file_name' => '140412.xls',
        ]);
    }
}