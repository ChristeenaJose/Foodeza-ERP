<?php

use App\Http\Controllers\Api\GoogleFormOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/google-form-orders/meta', [GoogleFormOrderController::class, 'meta']);
Route::apiResource('google-form-orders', GoogleFormOrderController::class)
    ->parameters(['google-form-orders' => 'googleFormOrder'])
    ->only(['index', 'store', 'show', 'update', 'destroy']);
