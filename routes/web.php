<?php

use App\Http\Controllers\CollateralManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('documents')->group(function () {
    Route::prefix('collateral')->group(function () {
        Route::get('{uid}', [CollateralManagementController::class, 'viewCollateralDocument'])->name('collateral.view-document');
        Route::get('return/{uid}', [CollateralManagementController::class, 'viewReturnCollateralDocument'])->name('collateral.view-return-document');
    });
});
