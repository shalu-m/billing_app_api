<?php

use Illuminate\Support\Facades\Route;

// Serve React app for all non-API routes
// This lets React Router handle the frontend routing
Route::get('/{any}', function () {
    return file_get_contents(public_path('index.html'));
})->where('any', '.*');