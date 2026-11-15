<?php

namespace App\Http\Controllers\Api\Menu;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Services\Menu\MenuAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MenuCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MenuCategory::query()->orderBy('sort_order')->orderBy('name');
        if ($request->boolean('active_only')) {
            $query->where('active', true)->whereNull('archived_at');
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $cat = MenuCategory::create($data);
        MenuAuditLogger::log('category.created', MenuCategory::class, $cat->id, null, $cat->toArray());

        return response()->json($cat, 201);
    }

    public function update(Request $request, MenuCategory $menuCategory): JsonResponse
    {
        $old = $menuCategory->toArray();
        $data = $this->validated($request, $menuCategory->id);
        if (isset($data['name']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        $menuCategory->update($data);
        MenuAuditLogger::log('category.updated', MenuCategory::class, $menuCategory->id, $old, $menuCategory->fresh()->toArray());

        return response()->json($menuCategory->fresh());
    }

    public function destroy(MenuCategory $menuCategory): JsonResponse
    {
        $menuCategory->update(['active' => false, 'archived_at' => now()]);
        MenuAuditLogger::log('category.archived', MenuCategory::class, $menuCategory->id);

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:menu_categories,id',
        ]);
        foreach ($data['order'] as $i => $id) {
            MenuCategory::where('id', $id)->update(['sort_order' => $i]);
        }

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $slugRule = 'nullable|string|max:255|unique:menu_categories,slug';
        if ($ignoreId) {
            $slugRule .= ','.$ignoreId;
        }

        return $request->validate([
            'name' => 'required|string|max:255',
            'slug' => $slugRule,
            'sort_order' => 'sometimes|integer|min:0',
            'active' => 'sometimes|boolean',
        ]);
    }
}
