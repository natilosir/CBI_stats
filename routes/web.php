<?php

use App\Core\Excel\ExcelController;

Route::prefix('excel')->name('excel.')->group(function () {
    Route::get('/upload', [ExcelController::class, 'upload']);
    Route::get('/sheets', [ExcelController::class, 'showSheets']);
    Route::get('/json', [ExcelController::class, 'extractPortfolioId']);
});


Route::get('/test', function () {

});