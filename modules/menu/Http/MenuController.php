<?php

namespace App\Http\Controllers\Api\Menu;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuEntry;
use App\Models\MenuSection;
use App\Services\Menu\MenuAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenuController extends Controller
{
    public function meta(): JsonResponse
    {
        return response()->json([
            'menu_types' => [
                'lunch' => 'Lunch Menu',
                'food_truck' => 'Food Truck / Market Menu',
                'catering' => 'Catering Menu',
                'private_event' => 'Private Event Menu',
                'evening_snacks' => 'Evening / Snacks Menu',
                'online' => 'Online Menu',
            ],
            'statuses' => ['draft', 'active', 'scheduled', 'expired', 'archived'],
            'service_types' => \App\Models\MenuServiceType::where('active', true)->orderBy('sort_order')->get(),
            'locations' => \App\Models\MenuLocation::where('active', true)->orderBy('name')->get(),
            'categories' => \App\Models\MenuCategory::where('active', true)->whereNull('archived_at')->orderBy('sort_order')->get(),
            'products' => \App\Models\Product::where('active', true)->orderBy('name')->get(['id', 'name', 'sku', 'default_price', 'category_id']),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Menu::with(['serviceTypes', 'locations'])->orderByDesc('updated_at');
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }
        if ($request->filled('menu_type')) {
            $query->where('menu_type', $request->get('menu_type'));
        }
        if ($q = $request->get('q')) {
            $query->where('name', 'like', "%{$q}%");
        }

        return response()->json($query->get());
    }

    public function show(Menu $menu): JsonResponse
    {
        return response()->json($this->loadMenu($menu));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $serviceIds = $data['service_type_ids'] ?? [];
        $locationIds = $data['location_ids'] ?? [];
        unset($data['service_type_ids'], $data['location_ids']);

        $menu = DB::transaction(function () use ($data, $serviceIds, $locationIds) {
            $menu = Menu::create($data);
            $menu->serviceTypes()->sync($serviceIds);
            $menu->locations()->sync($locationIds);

            return $menu;
        });

        MenuAuditLogger::log('menu.created', Menu::class, $menu->id, null, $menu->toArray());

        return response()->json($this->loadMenu($menu), 201);
    }

    public function update(Request $request, Menu $menu): JsonResponse
    {
        $old = $menu->toArray();
        $data = $this->validated($request);
        $serviceIds = $data['service_type_ids'] ?? null;
        $locationIds = $data['location_ids'] ?? null;
        unset($data['service_type_ids'], $data['location_ids']);

        DB::transaction(function () use ($menu, $data, $serviceIds, $locationIds) {
            $menu->update($data);
            if (is_array($serviceIds)) {
                $menu->serviceTypes()->sync($serviceIds);
            }
            if (is_array($locationIds)) {
                $menu->locations()->sync($locationIds);
            }
        });

        MenuAuditLogger::log('menu.updated', Menu::class, $menu->id, $old, $menu->fresh()->toArray());

        return response()->json($this->loadMenu($menu->fresh()));
    }

    public function destroy(Menu $menu): JsonResponse
    {
        $menu->update(['status' => 'archived']);
        $menu->delete();
        MenuAuditLogger::log('menu.archived', Menu::class, $menu->id);

        return response()->json(['ok' => true]);
    }

    public function syncBuilder(Request $request, Menu $menu): JsonResponse
    {
        $data = $request->validate([
            'sections' => 'required|array',
            'sections.*.id' => 'nullable|integer|exists:menu_sections,id',
            'sections.*.name' => 'required|string|max:255',
            'sections.*.category_id' => 'nullable|integer|exists:menu_categories,id',
            'sections.*.sort_order' => 'sometimes|integer|min:0',
            'sections.*.entries' => 'nullable|array',
            'sections.*.entries.*.id' => 'nullable|integer|exists:menu_entries,id',
            'sections.*.entries.*.product_id' => 'required|integer|exists:products,id',
            'sections.*.entries.*.product_variant_id' => 'nullable|integer|exists:product_variants,id',
            'sections.*.entries.*.price' => 'nullable|numeric|min:0',
            'sections.*.entries.*.description' => 'nullable|string',
            'sections.*.entries.*.featured' => 'sometimes|boolean',
            'sections.*.entries.*.available' => 'sometimes|boolean',
            'sections.*.entries.*.min_quantity' => 'nullable|integer|min:0',
            'sections.*.entries.*.max_quantity' => 'nullable|integer|min:0',
            'sections.*.entries.*.sort_order' => 'sometimes|integer|min:0',
            'sections.*.entries.*.addon_ids' => 'nullable|array',
            'sections.*.entries.*.addon_ids.*' => 'integer|exists:addons,id',
        ]);

        DB::transaction(function () use ($menu, $data) {
            $keepSectionIds = [];
            $keepEntryIds = [];

            foreach ($data['sections'] as $si => $sectionData) {
                $entries = $sectionData['entries'] ?? [];
                unset($sectionData['entries']);

                if (! empty($sectionData['id'])) {
                    $section = MenuSection::where('menu_id', $menu->id)->where('id', $sectionData['id'])->firstOrFail();
                    $section->update([
                        'name' => $sectionData['name'],
                        'category_id' => $sectionData['category_id'] ?? null,
                        'sort_order' => $sectionData['sort_order'] ?? $si,
                    ]);
                } else {
                    $section = $menu->sections()->create([
                        'name' => $sectionData['name'],
                        'category_id' => $sectionData['category_id'] ?? null,
                        'sort_order' => $sectionData['sort_order'] ?? $si,
                    ]);
                }
                $keepSectionIds[] = $section->id;

                foreach ($entries as $ei => $entryData) {
                    $addonIds = $entryData['addon_ids'] ?? [];
                    unset($entryData['addon_ids']);
                    $payload = [
                        'menu_id' => $menu->id,
                        'menu_section_id' => $section->id,
                        'product_id' => $entryData['product_id'],
                        'product_variant_id' => $entryData['product_variant_id'] ?? null,
                        'price' => $entryData['price'] ?? null,
                        'description' => $entryData['description'] ?? null,
                        'featured' => $entryData['featured'] ?? false,
                        'available' => $entryData['available'] ?? true,
                        'min_quantity' => $entryData['min_quantity'] ?? null,
                        'max_quantity' => $entryData['max_quantity'] ?? null,
                        'sort_order' => $entryData['sort_order'] ?? $ei,
                    ];

                    if (! empty($entryData['id'])) {
                        $entry = MenuEntry::where('menu_id', $menu->id)->where('id', $entryData['id'])->firstOrFail();
                        $entry->update($payload);
                    } else {
                        $entry = MenuEntry::create($payload);
                    }
                    $entry->addons()->sync($addonIds);
                    $keepEntryIds[] = $entry->id;
                }
            }

            MenuEntry::where('menu_id', $menu->id)->whereNotIn('id', $keepEntryIds)->delete();
            MenuSection::where('menu_id', $menu->id)->whereNotIn('id', $keepSectionIds)->delete();
        });

        MenuAuditLogger::log('menu.builder_updated', Menu::class, $menu->id);

        return response()->json($this->loadMenu($menu->fresh()));
    }

    private function loadMenu(Menu $menu): Menu
    {
        return $menu->load([
            'serviceTypes',
            'locations',
            'sections.category',
            'sections.entries.product.category',
            'sections.entries.variant',
            'sections.entries.addons',
            'entries.product',
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'menu_type' => 'required|in:lunch,food_truck,catering,private_event,evening_snacks,online',
            'status' => 'required|in:draft,active,scheduled,expired,archived',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'available_days' => 'nullable|array',
            'available_days.*' => 'integer|between:0,6',
            'available_time_start' => 'nullable|date_format:H:i',
            'available_time_end' => 'nullable|date_format:H:i',
            'currency' => 'sometimes|string|size:3',
            'seasonal' => 'sometimes|boolean',
            'notes' => 'nullable|string',
            'service_type_ids' => 'nullable|array',
            'service_type_ids.*' => 'integer|exists:menu_service_types,id',
            'location_ids' => 'nullable|array',
            'location_ids.*' => 'integer|exists:menu_locations,id',
        ]);
    }
}
