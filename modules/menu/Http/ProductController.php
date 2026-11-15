<?php

namespace App\Http\Controllers\Api\Menu;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use App\Services\Menu\MenuAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function meta(): JsonResponse
    {
        return response()->json([
            'food_types' => ['vegetarian', 'vegan', 'non_vegetarian'],
            'availability_statuses' => ['available', 'unavailable', 'sold_out'],
            'price_types' => ['standard', 'service', 'location', 'seasonal', 'promo', 'catering_per_person'],
            'categories' => \App\Models\MenuCategory::where('active', true)->whereNull('archived_at')->orderBy('sort_order')->get(['id', 'name']),
            'allergens' => \App\Models\Allergen::where('active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'service_types' => \App\Models\MenuServiceType::where('active', true)->orderBy('sort_order')->get(['id', 'name']),
            'locations' => \App\Models\MenuLocation::where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'allergens', 'variants'])
            ->orderBy('name');

        if ($q = $request->get('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhere('short_name', 'like', "%{$q}%");
            });
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }
        if ($request->boolean('vegetarian')) {
            $query->where('is_vegetarian', true);
        }
        if ($request->boolean('vegan')) {
            $query->where('is_vegan', true);
        }
        if ($request->boolean('halal')) {
            $query->where('is_halal', true);
        }
        if ($request->boolean('seasonal')) {
            $query->where('seasonal', true);
        }
        if ($request->filled('availability_status')) {
            $query->where('availability_status', $request->get('availability_status'));
        }
        if ($request->boolean('include_archived')) {
            // include soft-deleted
        } else {
            $query->whereNull('deleted_at');
        }

        return response()->json($query->get());
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json(
            $product->load(['category', 'allergens', 'variants', 'prices.serviceType', 'prices.location', 'addons', 'recipes.items.inventoryItem'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $allergenIds = $data['allergen_ids'] ?? [];
        $variants = $data['variants'] ?? [];
        unset($data['allergen_ids'], $data['variants']);

        if (empty($data['sku'])) {
            $data['sku'] = $this->generateSku($data['name']);
        }

        $product = DB::transaction(function () use ($data, $allergenIds, $variants) {
            $product = Product::create($data);
            $product->allergens()->sync($allergenIds);
            foreach ($variants as $i => $variant) {
                $product->variants()->create([
                    ...$variant,
                    'sort_order' => $variant['sort_order'] ?? $i,
                ]);
            }
            ProductPrice::create([
                'product_id' => $product->id,
                'price_type' => 'standard',
                'price' => $product->default_price,
                'currency' => 'EUR',
            ]);

            return $product;
        });

        MenuAuditLogger::log('product.created', Product::class, $product->id, null, $product->toArray());

        return response()->json($product->load(['category', 'allergens', 'variants']), 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $old = $product->toArray();
        $data = $this->validated($request, $product->id);
        $allergenIds = $data['allergen_ids'] ?? null;
        unset($data['allergen_ids'], $data['variants']);

        DB::transaction(function () use ($product, $data, $allergenIds) {
            $product->update($data);
            if (is_array($allergenIds)) {
                $product->allergens()->sync($allergenIds);
            }
        });

        MenuAuditLogger::log('product.updated', Product::class, $product->id, $old, $product->fresh()->toArray());

        return response()->json($product->fresh()->load(['category', 'allergens', 'variants', 'prices']));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();
        MenuAuditLogger::log('product.archived', Product::class, $product->id);

        return response()->json(['ok' => true]);
    }

    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:products,id',
            'action' => 'required|in:activate,deactivate,set_category,set_availability,archive',
            'category_id' => 'required_if:action,set_category|nullable|integer|exists:menu_categories,id',
            'availability_status' => 'required_if:action,set_availability|nullable|in:available,unavailable,sold_out',
        ]);

        $products = Product::whereIn('id', $data['ids'])->get();
        foreach ($products as $product) {
            match ($data['action']) {
                'activate' => $product->update(['active' => true]),
                'deactivate' => $product->update(['active' => false]),
                'set_category' => $product->update(['category_id' => $data['category_id']]),
                'set_availability' => $product->update(['availability_status' => $data['availability_status']]),
                'archive' => $product->delete(),
            };
        }

        MenuAuditLogger::log('product.bulk', Product::class, null, null, $data);

        return response()->json(['ok' => true, 'count' => $products->count()]);
    }

    public function addPrice(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'product_variant_id' => 'nullable|integer|exists:product_variants,id',
            'price_type' => 'required|in:standard,service,location,seasonal,promo,catering_per_person',
            'service_type_id' => 'nullable|integer|exists:menu_service_types,id',
            'location_id' => 'nullable|integer|exists:menu_locations,id',
            'price' => 'required|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'notes' => 'nullable|string',
        ]);

        $price = $product->prices()->create($data);
        MenuAuditLogger::log('price.created', ProductPrice::class, $price->id, null, $price->toArray());

        return response()->json($price->load(['serviceType', 'location']), 201);
    }

    public function syncVariants(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'variants' => 'required|array',
            'variants.*.id' => 'nullable|integer|exists:product_variants,id',
            'variants.*.name' => 'required|string|max:255',
            'variants.*.sku' => 'nullable|string|max:255',
            'variants.*.portion_size' => 'nullable|string|max:64',
            'variants.*.unit' => 'nullable|string|max:32',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.recipe_multiplier' => 'nullable|numeric|min:0.001',
            'variants.*.available' => 'sometimes|boolean',
            'variants.*.sort_order' => 'sometimes|integer|min:0',
        ]);

        $keepIds = [];
        foreach ($data['variants'] as $i => $row) {
            if (! empty($row['id'])) {
                $variant = ProductVariant::where('product_id', $product->id)->where('id', $row['id'])->firstOrFail();
                $variant->update([
                    ...$row,
                    'sort_order' => $row['sort_order'] ?? $i,
                ]);
                $keepIds[] = $variant->id;
            } else {
                $created = $product->variants()->create([
                    ...$row,
                    'sort_order' => $row['sort_order'] ?? $i,
                ]);
                $keepIds[] = $created->id;
            }
        }
        $product->variants()->whereNotIn('id', $keepIds)->delete();

        return response()->json($product->fresh()->load('variants'));
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $skuRule = 'nullable|string|max:64|unique:products,sku';
        if ($ignoreId) {
            $skuRule .= ','.$ignoreId;
        }

        return $request->validate([
            'sku' => $skuRule,
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|integer|exists:menu_categories,id',
            'subcategory' => 'nullable|string|max:255',
            'image_path' => 'nullable|string|max:255',
            'food_type' => 'required|in:vegetarian,vegan,non_vegetarian',
            'is_vegetarian' => 'sometimes|boolean',
            'is_vegan' => 'sometimes|boolean',
            'is_halal' => 'sometimes|boolean',
            'active' => 'sometimes|boolean',
            'seasonal' => 'sometimes|boolean',
            'kitchen_station' => 'nullable|string|max:255',
            'prep_time_minutes' => 'nullable|integer|min:0',
            'default_price' => 'required|numeric|min:0',
            'vat_rate' => 'nullable|numeric|min:0|max:1',
            'availability_status' => 'sometimes|in:available,unavailable,sold_out',
            'notes' => 'nullable|string',
            'allergen_ids' => 'nullable|array',
            'allergen_ids.*' => 'integer|exists:allergens,id',
            'variants' => 'nullable|array',
            'variants.*.name' => 'required_with:variants|string|max:255',
            'variants.*.sku' => 'nullable|string|max:255',
            'variants.*.portion_size' => 'nullable|string|max:64',
            'variants.*.unit' => 'nullable|string|max:32',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.recipe_multiplier' => 'nullable|numeric|min:0.001',
            'variants.*.available' => 'sometimes|boolean',
            'variants.*.sort_order' => 'sometimes|integer|min:0',
        ]);
    }

    private function generateSku(string $name): string
    {
        $base = strtoupper(Str::slug(Str::limit($name, 20, ''), ''));
        $base = $base !== '' ? $base : 'PRD';
        $sku = $base;
        $i = 1;
        while (Product::withTrashed()->where('sku', $sku)->exists()) {
            $sku = $base.'-'.$i++;
        }

        return $sku;
    }
}
