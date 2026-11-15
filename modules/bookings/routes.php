<?php

use App\Http\Controllers\Api\BookingController;
use Illuminate\Support\Facades\Route;

Route::get('/bookings/meta', [BookingController::class, 'meta']);
Route::apiResource('bookings', BookingController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
