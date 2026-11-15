<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Support\RecordDates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = InventoryItem::query()->orderBy('name');

        if ($category = $request->get('category')) {
            $query->where('category', $category);
        }
        if ($q = trim((string) $request->get('q'))) {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhere('supplier_name', 'like', "%{$q}%");
            });
        }
        if ($request->boolean('low_stock')) {
            $query->where('reorder_level', '>', 0)
                ->whereColumn('quantity', '<=', 'reorder_level');
        }
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        return response()->json($query->get());
    }

    public function show(InventoryItem $inventoryItem): JsonResponse
    {
        return response()->json($inventoryItem->load(['movements' => fn ($q) => $q->orderByDesc('date')->orderByDesc('id')->limit(20)]));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $opening = (float) ($data['quantity'] ?? 0);

        $item = DB::transaction(function () use ($data, $opening) {
            $item = InventoryItem::create($data);
            if ($opening > 0) {
                InventoryMovement::create([
                    'inventory_item_id' => $item->id,
                    'date' => now()->toDateString(),
                    'type' => 'in',
                    'quantity' => $opening,
                    'reason' => 'Opening stock',
                ]);
            }

            return $item;
        });

        return response()->json($item->fresh(), 201);
    }

    public function update(Request $request, InventoryItem $inventoryItem): JsonResponse
    {
        $data = $this->validated($request, $inventoryItem->id);
        unset($data['quantity']);
        $inventoryItem->update($data);

        return response()->json($inventoryItem->fresh());
    }

    public function destroy(InventoryItem $inventoryItem): JsonResponse
    {
        if ($inventoryItem->menuIngredients()->exists()) {
            return response()->json([
                'message' => 'This item is used on the menu. Remove it from recipes first.',
            ], 422);
        }

        $inventoryItem->delete();

        return response()->json(['ok' => true]);
    }

    public function adjust(Request $request, InventoryItem $inventoryItem): JsonResponse
    {
        $data = $request->validate([
            'date' => RecordDates::dateRules(),
            'type' => 'required|in:in,out,adjust',
            'quantity' => 'required|numeric|min:0.001',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $qty = (float) $data['quantity'];
        $onHand = (float) $inventoryItem->quantity;

        if ($data['type'] === 'out' && $qty > $onHand) {
            return response()->json(['message' => 'Not enough stock on hand.'], 422);
        }

        $inventoryItem->quantity = match ($data['type']) {
            'in' => $onHand + $qty,
            'out' => $onHand - $qty,
            'adjust' => $qty,
        };
        $inventoryItem->save();

        $movement = InventoryMovement::create([
            'inventory_item_id' => $inventoryItem->id,
            'date' => $data['date'],
            'type' => $data['type'],
            'quantity' => $qty,
            'reason' => $data['reason'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'item' => $inventoryItem->fresh(),
            'movement' => $movement,
        ]);
    }

    public function movements(Request $request): JsonResponse
    {
        $query = InventoryMovement::with('item')->orderByDesc('date')->orderByDesc('id');

        if ($from = $request->get('from')) {
            $query->where('date', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $query->where('date', '<=', $to);
        }
        if ($itemId = $request->get('inventory_item_id')) {
            $query->where('inventory_item_id', (int) $itemId);
        }

        return response()->json($query->limit(200)->get());
    }

    public function meta(): JsonResponse
    {
        return response()->json([
            'categories' => config('foodeza.inventory_categories'),
            'units' => config('foodeza.inventory_units'),
            'movement_types' => config('foodeza.inventory_movement_types'),
        ]);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $skuRule = 'nullable|string|max:50|unique:inventory_items,sku';
        if ($ignoreId) {
            $skuRule .= ','.$ignoreId;
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => $skuRule,
            'category' => 'required|in:'.implode(',', config('foodeza.inventory_categories')),
            'unit' => 'required|in:'.implode(',', config('foodeza.inventory_units')),
            'quantity' => 'nullable|numeric|min:0',
            'reorder_level' => 'nullable|numeric|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'supplier_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'active' => 'sometimes|boolean',
        ]);

        if (($data['sku'] ?? null) === '') {
            $data['sku'] = null;
        }
        $data['quantity'] = $data['quantity'] ?? 0;
        $data['reorder_level'] = $data['reorder_level'] ?? 0;

        return $data;
    }
}
