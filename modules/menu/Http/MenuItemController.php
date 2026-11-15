<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenuItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MenuItem::with('ingredients.inventoryItem')
            ->orderBy('category')
            ->orderByRaw("CASE WHEN menu_group IS NULL OR menu_group = '' THEN 1 ELSE 0 END")
            ->orderBy('menu_group')
            ->orderBy('name');

        if ($category = $request->get('category')) {
            $query->where('category', $category);
        }
        if ($service = $request->get('service_type')) {
            $query->whereJsonContains('service_types', $service);
        }
        if ($request->has('available')) {
            $query->where('available', $request->boolean('available'));
        }

        return response()->json($query->get());
    }

    public function show(MenuItem $menuItem): JsonResponse
    {
        return response()->json($menuItem->load('ingredients.inventoryItem'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $ingredients = $data['ingredients'] ?? [];
        unset($data['ingredients']);

        $item = DB::transaction(function () use ($data, $ingredients) {
            $item = MenuItem::create($data);
            $this->syncIngredients($item, $ingredients);

            return $item;
        });

        return response()->json($item->load('ingredients.inventoryItem'), 201);
    }

    public function update(Request $request, MenuItem $menuItem): JsonResponse
    {
        $data = $this->validated($request);
        $ingredients = $data['ingredients'] ?? [];
        unset($data['ingredients']);

        DB::transaction(function () use ($menuItem, $data, $ingredients) {
            $menuItem->update($data);
            $this->syncIngredients($menuItem, $ingredients);
        });

        return response()->json($menuItem->fresh()->load('ingredients.inventoryItem'));
    }

    public function destroy(MenuItem $menuItem): JsonResponse
    {
        $menuItem->delete();

        return response()->json(['ok' => true]);
    }

    public function meta(): JsonResponse
    {
        return response()->json([
            'categories' => config('foodeza.menu_categories'),
            'services' => config('foodeza.services'),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|in:'.implode(',', config('foodeza.menu_categories')),
            'menu_group' => 'nullable|string|max:255',
            'service_types' => 'required|array|min:1',
            'service_types.*' => 'in:'.implode(',', config('foodeza.services')),
            'price' => 'required|numeric|min:0',
            'available' => 'sometimes|boolean',
            'allergens' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'ingredients' => 'nullable|array',
            'ingredients.*.inventory_item_id' => 'required|integer|exists:inventory_items,id',
            'ingredients.*.quantity' => 'required|numeric|min:0.001',
        ]);
    }

    private function syncIngredients(MenuItem $item, array $ingredients): void
    {
        $item->ingredients()->delete();

        foreach ($ingredients as $line) {
            $item->ingredients()->create([
                'inventory_item_id' => $line['inventory_item_id'],
                'quantity' => $line['quantity'],
            ]);
        }
    }
}
