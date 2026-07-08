<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MemberManagementController;
use App\Http\Controllers\SiteManagementController;
use App\Http\Controllers\SupplierManagementController;
use App\Http\Controllers\TransactionManagementController;
use App\Http\Controllers\TubeBarcodeManagementController;
use App\Http\Controllers\TubeContentTypeManagementController;
use App\Http\Controllers\TubeManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->name('auth.login');
        Route::get('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('auth.logout');
    });
    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('account')->group(function () {
            Route::get('detail', [AccountController::class, 'detail'])->name('account.detail');
        });
        Route::prefix('sites')->group(function () {
            Route::get('/', [SiteManagementController::class, 'all'])->name('site.all');
            Route::post('index', [SiteManagementController::class, 'index'])->name('site.index');
            Route::post('/', [SiteManagementController::class, 'create'])->name('site.create');
            Route::prefix('{uid}')->group(function () {
                Route::get('/', [SiteManagementController::class, 'detail'])->name('site.detail');
                Route::post('/', [SiteManagementController::class, 'update'])->name('site.update');
                Route::delete('/', [SiteManagementController::class, 'delete'])->name('site.delete');
            });
        });
        Route::prefix('suppliers')->group(function () {
            Route::get('/', [SupplierManagementController::class, 'all'])->name('supplier.all');
            Route::post('index', [SupplierManagementController::class, 'index'])->middleware('permission:view-supplier')->name('supplier.index');
            Route::post('/', [SupplierManagementController::class, 'create'])->middleware('permission:create-supplier')->name('supplier.create');
            Route::prefix('{uid}')->group(function () {
                Route::get('/', [SupplierManagementController::class, 'detail'])->middleware('permission:view-supplier')->name('supplier.detail');
                Route::post('/', [SupplierManagementController::class, 'update'])->middleware('permission:update-supplier')->name('supplier.update');
                Route::delete('/', [SupplierManagementController::class, 'delete'])->middleware('permission:delete-supplier')->name('supplier.delete');
            });
        });
        Route::prefix('tube-content-types')->group(function () {
            Route::get('/', [TubeContentTypeManagementController::class, 'all'])->name('content-type.all');
            Route::post('index', [TubeContentTypeManagementController::class, 'index'])->middleware('permission:view-tube-content-type')->name('content-type.index');
            Route::post('/', [TubeContentTypeManagementController::class, 'create'])->middleware('permission:create-tube-content-type')->name('content-type.create');
            Route::prefix('{uid}')->group(function () {
                Route::get('/', [TubeContentTypeManagementController::class, 'detail'])->middleware('permission:view-tube-content-type')->name('content-type.detail');
                Route::post('/', [TubeContentTypeManagementController::class, 'update'])->middleware('permission:update-tube-content-type')->name('content-type.update');
                Route::delete('/', [TubeContentTypeManagementController::class, 'delete'])->middleware('permission:delete-tube-content-type')->name('content-type.delete');
            });
        });
        Route::prefix('members')->group(function () {
            Route::get('/', [MemberManagementController::class, 'all'])->name('content-type.all');
            Route::post('index', [MemberManagementController::class, 'index'])->middleware('permission:view-member')->name('member.index');
            Route::post('/', [MemberManagementController::class, 'create'])->middleware('permission:create-member')->name('member.create');
            Route::prefix('{uid}')->group(function () {
                Route::get('/', [MemberManagementController::class, 'detail'])->middleware('permission:view-member')->name('member.detail');
                Route::post('/', [MemberManagementController::class, 'update'])->middleware('permission:update-member')->name('member.update');
                Route::delete('/', [MemberManagementController::class, 'delete'])->middleware('permission:delete-member')->name('member.delete');
            });
        });
        Route::prefix('tubes')->group(function () {
            Route::post('index', [TubeManagementController::class, 'index'])->middleware('permission:view-tube')->name('tube.index');
            Route::post('/', [TubeManagementController::class, 'create'])->middleware('permission:create-tube')->name('tube.create');
            Route::prefix('{uid}')->group(function () {
                Route::get('/', [TubeManagementController::class, 'detail'])->middleware('permission:view-tube')->name('tube.detail');
                Route::post('/', [TubeManagementController::class, 'update'])->middleware('permission:update-tube')->name('tube.update');
                Route::delete('/', [TubeManagementController::class, 'delete'])->middleware('permission:delete-tube')->name('tube.delete');
            });
        });
        Route::prefix('tube-barcodes')->group(function () {
            Route::post('index', [TubeBarcodeManagementController::class, 'index'])->middleware('permission:view-tube-barcode')->name('tube-barcode.index');
            Route::post('update', [TubeBarcodeManagementController::class, 'update'])->middleware('permission:update-tube-barcode')->name('tube-barcode.update');
        });
        Route::prefix('transactions')->group(function () {
            Route::post('index', [TransactionManagementController::class, 'index'])->middleware('permission:view-transaction')->name('transaction.index');
            Route::post('/', [TransactionManagementController::class, 'create'])->middleware('permission:create-transaction')->name('transaction.create');
            Route::prefix('{uid}')->group(function () {
                Route::post('create-items', [TransactionManagementController::class, 'createItems'])->middleware('permission:create-items-transaction')->name('transaction.create-items');
                Route::get('/', [TransactionManagementController::class, 'detail'])->middleware('permission:view-transaction')->name('transaction.detail');
                Route::delete('/', [TransactionManagementController::class, 'delete'])->middleware('permission:delete-transaction')->name('transaction.delete');
                Route::delete('delete-item', [TransactionManagementController::class, 'deleteItem'])->middleware('permission:delete-transaction-item')->name('transaction.delete-item');
            });
        });
    });
});
