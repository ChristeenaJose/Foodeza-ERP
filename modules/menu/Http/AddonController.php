<?php

namespace App\Http\Controllers\Api\Menu;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddonController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Addon::with(['product', 'products'])->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'product_id' => 'nullable|integer|exists:products,id',
            'active' => 'sometimes|boolean',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|exists:products,id',
        ]);
        $productIds = $data['product_ids'] ?? [];
        unset($data['product_ids']);
        $addon = Addon::create($data);
        if ($productIds) {
            $addon->products()->sync($productIds);
        }

        return response()->json($addon->load(['product', 'products']), 201);
    }

    public function update(Request $request, Addon $addon): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'product_id' => 'nullable|integer|exists:products,id',
            'active' => 'sometimes|boolean',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|exists:products,id',
        ]);
        $productIds = $data['product_ids'] ?? null;
        unset($data['product_ids']);
        $addon->update($data);
        if (is_array($productIds)) {
            $addon->products()->sync($productIds);
        }

        return response()->json($addon->fresh()->load(['product', 'products']));
    }

    public function destroy(Addon $addon): JsonResponse
    {
        $addon->update(['active' => false]);

        return response()->json(['ok' => true]);
    }
}
