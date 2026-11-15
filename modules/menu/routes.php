<?php

use App\Http\Controllers\Api\Menu\AddonController;
use App\Http\Controllers\Api\Menu\AvailabilityRuleController;
use App\Http\Controllers\Api\Menu\MenuCategoryController;
use App\Http\Controllers\Api\Menu\MenuController;
use App\Http\Controllers\Api\Menu\MenuDashboardController;
use App\Http\Controllers\Api\Menu\MenuSettingsController;
use App\Http\Controllers\Api\Menu\PackageController;
use App\Http\Controllers\Api\Menu\PricingController;
use App\Http\Controllers\Api\Menu\ProductController;
use App\Http\Controllers\Api\MenuItemController;
use Illuminate\Support\Facades\Route;

Route::get('/menu/meta', [MenuItemController::class, 'meta']);
Route::apiResource('menu', MenuItemController::class)
    ->parameters(['menu' => 'menuItem'])
    ->only(['index', 'store', 'show', 'update', 'destroy']);

Route::prefix('menu-mgmt')->group(function () {
    Route::get('/dashboard', [MenuDashboardController::class, 'index']);

    Route::get('/categories', [MenuCategoryController::class, 'index']);
    Route::post('/categories', [MenuCategoryController::class, 'store']);
    Route::put('/categories/{menuCategory}', [MenuCategoryController::class, 'update']);
    Route::delete('/categories/{menuCategory}', [MenuCategoryController::class, 'destroy']);
    Route::post('/categories/reorder', [MenuCategoryController::class, 'reorder']);

    Route::get('/products/meta', [ProductController::class, 'meta']);
    Route::post('/products/bulk', [ProductController::class, 'bulk']);
    Route::post('/products/{product}/prices', [ProductController::class, 'addPrice']);
    Route::put('/products/{product}/variants', [ProductController::class, 'syncVariants']);
    Route::apiResource('products', ProductController::class);

    Route::get('/menus/meta', [MenuController::class, 'meta']);
    Route::put('/menus/{menu}/builder', [MenuController::class, 'syncBuilder']);
    Route::apiResource('menus', MenuController::class);

    Route::apiResource('packages', PackageController::class);
    Route::apiResource('addons', AddonController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('/pricing', [PricingController::class, 'index']);
    Route::apiResource('availability-rules', AvailabilityRuleController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::get('/settings', [MenuSettingsController::class, 'index']);
    Route::post('/settings/service-types', [MenuSettingsController::class, 'storeServiceType']);
    Route::put('/settings/service-types/{menuServiceType}', [MenuSettingsController::class, 'updateServiceType']);
    Route::post('/settings/locations', [MenuSettingsController::class, 'storeLocation']);
    Route::put('/settings/locations/{menuLocation}', [MenuSettingsController::class, 'updateLocation']);
});
