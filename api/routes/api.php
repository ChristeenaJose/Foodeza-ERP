<?php

use App\Models\Customer;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok', 'offline' => true]));

Route::get('/config', fn () => response()->json(config('foodeza')));

Route::get('/customers', fn () => Customer::orderBy('name')->get());

foreach ([
    'bookkeeping',
    'bookings',
    'inventory',
    'menu',
    'google-form-orders',
] as $module) {
    require dirname(base_path())."/modules/{$module}/routes.php";
}
