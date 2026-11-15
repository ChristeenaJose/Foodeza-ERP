<?php

namespace App\Http\Controllers\Api\Menu;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Services\Menu\MenuAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PackageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Package::with(['items.product', 'serviceTypes'])->orderBy('name');
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        return response()->json($query->get());
    }

    public function show(Package $package): JsonResponse
    {
        return response()->json($package->load(['items.product', 'items.variant', 'serviceTypes']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $items = $data['items'] ?? [];
        $serviceIds = $data['service_type_ids'] ?? [];
        unset($data['items'], $data['service_type_ids']);

        $package = DB::transaction(function () use ($data, $items, $serviceIds) {
            $package = Package::create($data);
            $package->serviceTypes()->sync($serviceIds);
            foreach ($items as $i => $item) {
                $package->items()->create([
                    ...$item,
                    'sort_order' => $item['sort_order'] ?? $i,
                ]);
            }

            return $package;
        });

        MenuAuditLogger::log('package.created', Package::class, $package->id);

        return response()->json($package->load(['items.product', 'serviceTypes']), 201);
    }

    public function update(Request $request, Package $package): JsonResponse
    {
        $data = $this->validated($request);
        $items = $data['items'] ?? null;
        $serviceIds = $data['service_type_ids'] ?? null;
        unset($data['items'], $data['service_type_ids']);

        DB::transaction(function () use ($package, $data, $items, $serviceIds) {
            $package->update($data);
            if (is_array($serviceIds)) {
                $package->serviceTypes()->sync($serviceIds);
            }
            if (is_array($items)) {
                $package->items()->delete();
                foreach ($items as $i => $item) {
                    $package->items()->create([
                        ...$item,
                        'sort_order' => $item['sort_order'] ?? $i,
                    ]);
                }
            }
        });

        MenuAuditLogger::log('package.updated', Package::class, $package->id);

        return response()->json($package->fresh()->load(['items.product', 'serviceTypes']));
    }

    public function destroy(Package $package): JsonResponse
    {
        $package->update(['status' => 'archived']);
        $package->delete();
        MenuAuditLogger::log('package.archived', Package::class, $package->id);

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image_path' => 'nullable|string|max:255',
            'people_count' => 'nullable|integer|min:1',
            'price_per_person' => 'nullable|numeric|min:0',
            'fixed_price' => 'nullable|numeric|min:0',
            'min_quantity' => 'nullable|integer|min:0',
            'max_quantity' => 'nullable|integer|min:0',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'status' => 'required|in:draft,active,scheduled,expired,archived',
            'notes' => 'nullable|string',
            'service_type_ids' => 'nullable|array',
            'service_type_ids.*' => 'integer|exists:menu_service_types,id',
            'items' => 'nullable|array',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.product_variant_id' => 'nullable|integer|exists:product_variants,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.sort_order' => 'sometimes|integer|min:0',
        ]);
    }
}
