<?php

use App\Http\Controllers\Excel\ExcelController;
use App\Http\Controllers\FinancialChartController;
use App\Models\Financial;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Support\Facades\Http;

Route::get('/', fn() => view('dashboard'))
    ->name('dashboard');

Route::prefix('excel')
    ->name('excel.')
    ->group(function () {
        Route::get('upload/{id}', [ ExcelController::class, 'upload' ]);
        Route::get('sheets/{id}', [ ExcelController::class, 'showSheets' ]);
        Route::get('json/{id}', [ ExcelController::class, 'extractId' ]);
    });

Route::get('chart', [ FinancialChartController::class, 'showChart' ]);
Route::get('api/chart/data-by-id/{id}', [ FinancialChartController::class, 'getDataById' ]);
Route::get('api/chart/data/{title}', [ FinancialChartController::class, 'getData' ]);

Route::get('/test', function () {});