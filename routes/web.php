<?php

use App\Http\Controllers\Excel\ExcelController;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Support\Facades\Http;

Route::prefix('excel')
    ->name('excel.')
    ->group(function () {
        Route::get('upload/{id}', [ ExcelController::class, 'upload' ]);
        Route::get('sheets/{id}', [ ExcelController::class, 'showSheets' ]);
        Route::get('json/{id}', [ ExcelController::class, 'extractId' ]);
    });

Route::get('/', fn() => view('dashboard'))
    ->name('dashboard');

Route::get('/test', function () {
    $response = Http::withHeaders([
        'User-Agent'                => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language'           => 'en-US,en;q=0.9,fa;q=0.8',
        'Accept-Encoding'           => 'gzip, deflate, br',
        'Connection'                => 'keep-alive',
        'Upgrade-Insecure-Requests' => '1',
    ])
        ->get('https://cbi.ir/simplelist/35569.aspx');

    return $response->body();
});