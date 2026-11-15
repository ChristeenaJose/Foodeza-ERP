<?php

namespace App\Http\Controllers\Api\Menu;

use App\Http\Controllers\Controller;
use App\Models\Allergen;
use App\Models\MenuLocation;
use App\Models\MenuServiceType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MenuSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'service_types' => MenuServiceType::orderBy('sort_order')->get(),
            'locations' => MenuLocation::orderBy('name')->get(),
            'allergens' => Allergen::orderBy('name')->get(),
        ]);
    }

    public function storeServiceType(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:menu_service_types,slug',
            'sort_order' => 'sometimes|integer|min:0',
            'active' => 'sometimes|boolean',
        ]);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        return response()->json(MenuServiceType::create($data), 201);
    }

    public function updateServiceType(Request $request, MenuServiceType $menuServiceType): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:menu_service_types,slug,'.$menuServiceType->id,
            'sort_order' => 'sometimes|integer|min:0',
            'active' => 'sometimes|boolean',
        ]);
        $menuServiceType->update($data);

        return response()->json($menuServiceType->fresh());
    }

    public function storeLocation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:menu_locations,slug',
            'notes' => 'nullable|string',
            'active' => 'sometimes|boolean',
        ]);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        return response()->json(MenuLocation::create($data), 201);
    }

    public function updateLocation(Request $request, MenuLocation $menuLocation): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:menu_locations,slug,'.$menuLocation->id,
            'notes' => 'nullable|string',
            'active' => 'sometimes|boolean',
        ]);
        $menuLocation->update($data);

        return response()->json($menuLocation->fresh());
    }
}
