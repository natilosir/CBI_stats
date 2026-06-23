<?php

use App\Http\Controllers\Excel\ExcelController;

Route::prefix('excel')
    ->name('excel.')
    ->group(function () {
        Route::get('upload/{id}', [ ExcelController::class, 'upload' ]);
        Route::get('sheets/{id}', [ ExcelController::class, 'showSheets' ]);
        Route::get('json/{id}', [ ExcelController::class, 'extractPortfolioId' ]);
    });

Route::get('/', fn() => view('dashboard'))->name('dashboard');
Route::get('/test', function () {});