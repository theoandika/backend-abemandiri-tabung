<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SiteManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('admin')->group(function () {
        Route::prefix('auth')->group(function () {
            Route::post('login', [AuthController::class, 'login'])->name('auth.login');
            Route::get('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('auth.logout');
        });
        Route::middleware('auth:sanctum')->group(function () {
            Route::prefix('account')->group(function () {
                Route::get('detail', [AccountController::class, 'detail'])->name('account.detail');
            });
            Route::prefix('sites')->group(function () {
                Route::get('all', [SiteManagementController::class, 'all'])->name('site.all');
                Route::post('index', [SiteManagementController::class, 'index'])->name('site.index');
                Route::post('/', [SiteManagementController::class, 'create'])->name('site.create');
                Route::prefix('{uid}')->group(function () {
                    Route::get('/', [SiteManagementController::class, 'detail'])->name('site.detail');
                    Route::post('/', [SiteManagementController::class, 'update'])->name('site.update');
                    Route::delete('/', [SiteManagementController::class, 'delete'])->name('site.delete');
                });
            });
        });
    });
});
