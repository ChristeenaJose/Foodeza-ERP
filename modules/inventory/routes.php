<?php

use App\Http\Controllers\Api\InventoryItemController;
use Illuminate\Support\Facades\Route;

Route::get('/inventory/meta', [InventoryItemController::class, 'meta']);
Route::get('/inventory/movements', [InventoryItemController::class, 'movements']);
Route::post('/inventory/{inventoryItem}/adjust', [InventoryItemController::class, 'adjust']);
Route::apiResource('inventory', InventoryItemController::class)
    ->parameters(['inventory' => 'inventoryItem'])
    ->only(['index', 'store', 'show', 'update', 'destroy']);
