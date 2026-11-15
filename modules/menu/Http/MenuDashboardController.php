<?php

namespace App\Http\Controllers\Api\Menu;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\Package;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class MenuDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $recentMenus = Menu::orderByDesc('updated_at')->limit(8)->get(['id', 'name', 'status', 'menu_type', 'updated_at']);

        return response()->json([
            'active_products' => Product::where('active', true)->whereNull('deleted_at')->count(),
            'unavailable_products' => Product::where('availability_status', '!=', 'available')->whereNull('deleted_at')->count(),
            'active_menus' => Menu::where('status', 'active')->count(),
            'draft_menus' => Menu::where('status', 'draft')->count(),
            'seasonal_menus' => Menu::where('seasonal', true)->whereIn('status', ['active', 'scheduled', 'draft'])->count(),
            'active_packages' => Package::where('status', 'active')->count(),
            'recent_menus' => $recentMenus,
        ]);
    }
}
