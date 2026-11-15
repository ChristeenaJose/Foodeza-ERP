<?php

namespace App\Http\Controllers\Api\Menu;

use App\Http\Controllers\Controller;
use App\Models\AvailabilityRule;
use App\Models\Menu;
use App\Models\Package;
use App\Models\Product;
use App\Services\Menu\MenuAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AvailabilityRule::with(['location', 'serviceType'])->orderByDesc('id');
        if ($request->filled('subject_type') && $request->filled('subject_id')) {
            $query->where('subject_type', $request->get('subject_type'))
                ->where('subject_id', $request->integer('subject_id'));
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject_type' => 'required|string|in:'.Product::class.','.Menu::class.','.Package::class,
            'subject_id' => 'required|integer',
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => 'integer|between:0,6',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'location_id' => 'nullable|integer|exists:menu_locations,id',
            'service_type_id' => 'nullable|integer|exists:menu_service_types,id',
            'active' => 'sometimes|boolean',
        ]);

        $rule = AvailabilityRule::create($data);
        MenuAuditLogger::log('availability.created', AvailabilityRule::class, $rule->id);

        return response()->json($rule->load(['location', 'serviceType']), 201);
    }

    public function update(Request $request, AvailabilityRule $availabilityRule): JsonResponse
    {
        $data = $request->validate([
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => 'integer|between:0,6',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'location_id' => 'nullable|integer|exists:menu_locations,id',
            'service_type_id' => 'nullable|integer|exists:menu_service_types,id',
            'active' => 'sometimes|boolean',
        ]);
        $availabilityRule->update($data);
        MenuAuditLogger::log('availability.updated', AvailabilityRule::class, $availabilityRule->id);

        return response()->json($availabilityRule->fresh()->load(['location', 'serviceType']));
    }

    public function destroy(AvailabilityRule $availabilityRule): JsonResponse
    {
        $availabilityRule->delete();

        return response()->json(['ok' => true]);
    }
}
