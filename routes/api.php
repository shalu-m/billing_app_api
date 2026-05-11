<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\EggEntryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Middleware\SimpleTokenAuth;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware(SimpleTokenAuth::class)->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::post('/', [ProductController::class, 'store']);
        Route::get('/low-stock', [ProductController::class, 'lowStock']);
        Route::get('/{product}', [ProductController::class, 'show']);
        Route::put('/{product}', [ProductController::class, 'update']);
        Route::delete('/{product}', [ProductController::class, 'destroy']);
        Route::patch('/{product}/stock', [ProductController::class, 'adjustStock']);
    });

    Route::prefix('purchases')->group(function () {
        Route::get('/', [PurchaseController::class, 'index']);
        Route::post('/', [PurchaseController::class, 'store']);
        Route::put('/{purchase}', [PurchaseController::class, 'update']);
        Route::get('/{purchase}', [PurchaseController::class, 'show']);
        Route::delete('/{purchase}', [PurchaseController::class, 'destroy']);
        Route::post('/product/{product_id}/preview', [PurchaseController::class, 'preview']);
    });

    Route::prefix('bills')->group(function () {
        Route::get('/summary', [BillController::class, 'summary']);
        Route::get('/', [BillController::class, 'index']);
        Route::post('/', [BillController::class, 'store']);
        Route::get('/{bill}', [BillController::class, 'show']);
        Route::delete('/{bill}', [BillController::class, 'destroy']);
    });

    Route::prefix('egg-entries')->group(function () {
        Route::get('/summary', [EggEntryController::class, 'summary']);
        Route::get('/', [EggEntryController::class, 'index']);
        Route::post('/', [EggEntryController::class, 'store']);
        Route::get('/{eggEntry}', [EggEntryController::class, 'show']);
        Route::put('/{eggEntry}', [EggEntryController::class, 'update']);
        Route::delete('/{eggEntry}', [EggEntryController::class, 'destroy']);
    });

    Route::get('/config', [ConfigController::class, 'getConfig']);
});
