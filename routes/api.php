<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\EggEntryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Middleware\EnsurePermissionAccess;
use App\Http\Middleware\EnsureShopEnabled;
use App\Http\Middleware\SimpleTokenAuth;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware(SimpleTokenAuth::class)->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::middleware(EnsureShopEnabled::class.':supermarket')->prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index'])
            ->middleware(EnsurePermissionAccess::class.':supermarket.billing,supermarket.products,supermarket.stock');
        Route::post('/', [ProductController::class, 'store'])
            ->middleware(EnsurePermissionAccess::class.':supermarket.products');
        Route::get('/low-stock', [ProductController::class, 'lowStock'])
            ->middleware(EnsurePermissionAccess::class.':supermarket.billing,supermarket.products,supermarket.stock');
        Route::get('/{product}', [ProductController::class, 'show'])
            ->middleware(EnsurePermissionAccess::class.':supermarket.billing,supermarket.products,supermarket.stock');
        Route::put('/{product}', [ProductController::class, 'update'])
            ->middleware(EnsurePermissionAccess::class.':supermarket.products');
        Route::delete('/{product}', [ProductController::class, 'destroy'])
            ->middleware(EnsurePermissionAccess::class.':supermarket.products');
        Route::patch('/{product}/stock', [ProductController::class, 'adjustStock'])
            ->middleware(EnsurePermissionAccess::class.':supermarket.products,supermarket.stock');
    });

    Route::middleware(EnsureShopEnabled::class.':supermarket')->prefix('purchases')->group(function () {
        Route::get('/', [PurchaseController::class, 'index'])
            ->middleware(EnsurePermissionAccess::class.':supermarket.stock');
        Route::post('/', [PurchaseController::class, 'store'])
            ->middleware(EnsurePermissionAccess::class.':supermarket.stock');
        Route::put('/{purchase}', [PurchaseController::class, 'update'])
            ->middleware(EnsurePermissionAccess::class.':supermarket.stock');
        Route::get('/{purchase}', [PurchaseController::class, 'show'])
            ->middleware(EnsurePermissionAccess::class.':supermarket.stock');
        Route::delete('/{purchase}', [PurchaseController::class, 'destroy'])
            ->middleware(EnsurePermissionAccess::class.':supermarket.stock');
        Route::post('/product/{product_id}/preview', [PurchaseController::class, 'preview'])
            ->middleware(EnsurePermissionAccess::class.':supermarket.stock');
    });

    Route::middleware(EnsureShopEnabled::class.':supermarket')->prefix('bills')->group(function () {
        Route::get('/summary', [BillController::class, 'summary'])
            ->middleware(EnsurePermissionAccess::class.':supermarket.reports');
        Route::get('/', [BillController::class, 'index'])
            ->middleware(EnsurePermissionAccess::class.':supermarket.billdetails');
        Route::post('/', [BillController::class, 'store'])
            ->middleware(EnsurePermissionAccess::class.':supermarket.billing');
        Route::get('/{bill}', [BillController::class, 'show'])
            ->middleware(EnsurePermissionAccess::class.':supermarket.billdetails');
        Route::delete('/{bill}', [BillController::class, 'destroy'])
            ->middleware(EnsurePermissionAccess::class.':supermarket.billdetails');
    });

    Route::middleware(EnsureShopEnabled::class.':egg')->prefix('egg-entries')->group(function () {
        Route::get('/summary', [EggEntryController::class, 'summary'])
            ->middleware(EnsurePermissionAccess::class.':egg.reports');
        Route::get('/', [EggEntryController::class, 'index'])
            ->middleware(EnsurePermissionAccess::class.':egg.entry');
        Route::post('/', [EggEntryController::class, 'store'])
            ->middleware(EnsurePermissionAccess::class.':egg.entry');
        Route::get('/{eggEntry}', [EggEntryController::class, 'show'])
            ->middleware(EnsurePermissionAccess::class.':egg.entry');
        Route::put('/{eggEntry}', [EggEntryController::class, 'update'])
            ->middleware(EnsurePermissionAccess::class.':egg.entry');
        Route::delete('/{eggEntry}', [EggEntryController::class, 'destroy'])
            ->middleware(EnsurePermissionAccess::class.':egg.entry');
    });

    Route::get('/config', [ConfigController::class, 'getConfig']);
});
