<?php

use App\Http\Controllers\Api\GoogleFormOrderController;
use App\Http\Controllers\Api\GoogleFormOrderEventController;
use Illuminate\Support\Facades\Route;

Route::get('/google-form-orders/meta', [GoogleFormOrderController::class, 'meta']);

Route::apiResource('google-form-order-events', GoogleFormOrderEventController::class)
    ->parameters(['google-form-order-events' => 'googleFormOrderEvent']);

Route::post('/google-form-order-events/{googleFormOrderEvent}/import', [GoogleFormOrderEventController::class, 'import']);
Route::post('/google-form-order-events/{googleFormOrderEvent}/updates', [GoogleFormOrderEventController::class, 'storeUpdate']);
Route::post('/google-form-order-events/{googleFormOrderEvent}/send-confirmations', [GoogleFormOrderEventController::class, 'sendConfirmations']);
Route::post('/google-form-order-events/{googleFormOrderEvent}/updates/{update}/send', [GoogleFormOrderEventController::class, 'sendUpdate']);

Route::post('/google-form-orders/{googleFormOrder}/cancel', [GoogleFormOrderController::class, 'cancel']);
Route::patch('/google-form-orders/{googleFormOrder}/status', [GoogleFormOrderController::class, 'updateStatus']);
Route::get('/google-form-orders/{googleFormOrder}/whatsapp', [GoogleFormOrderController::class, 'whatsapp']);

Route::apiResource('google-form-orders', GoogleFormOrderController::class)
    ->parameters(['google-form-orders' => 'googleFormOrder'])
    ->only(['index', 'store', 'show', 'update', 'destroy']);
