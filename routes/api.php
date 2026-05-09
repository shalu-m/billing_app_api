<?php

use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EggEntryController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes  —  All prefixed with /api
|--------------------------------------------------------------------------
*/

// ── Products ───────────────────────────────────────────────────────────────
Route::prefix('products')->group(function () {
    Route::get('/',            [ProductController::class, 'index']);
    Route::post('/',           [ProductController::class, 'store']);
    Route::get('/low-stock',   [ProductController::class, 'lowStock']);
    Route::get('/{product}',   [ProductController::class, 'show']);
    Route::put('/{product}',   [ProductController::class, 'update']);
    Route::delete('/{product}',[ProductController::class, 'destroy']);
    Route::patch('/{product}/stock', [ProductController::class, 'adjustStock']);
});

// ── Bills ──────────────────────────────────────────────────────────────────
Route::prefix('bills')->group(function () {
    Route::get('/summary',  [BillController::class, 'summary']); 
    Route::get('/',         [BillController::class, 'index']);
    Route::post('/',        [BillController::class, 'store']);
    Route::get('/{bill}',   [BillController::class, 'show']);
    Route::delete('/{bill}',[BillController::class, 'destroy']);
});

// ── Egg Entries ────────────────────────────────────────────────────────────
Route::prefix('egg-entries')->group(function () {
    Route::get('/summary',       [EggEntryController::class, 'summary']);  // before /{eggEntry}
    Route::get('/',              [EggEntryController::class, 'index']);
    Route::post('/',             [EggEntryController::class, 'store']);
    Route::get('/{eggEntry}',    [EggEntryController::class, 'show']);
    Route::put('/{eggEntry}',    [EggEntryController::class, 'update']);
    Route::delete('/{eggEntry}', [EggEntryController::class, 'destroy']);
});
