<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoogleFormOrder;
use App\Services\ContactService;
use App\Support\RecordDates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GoogleFormOrderController extends Controller
{
    public function __construct(private ContactService $contacts) {}

    public function index(Request $request): JsonResponse
    {
        $from = $request->get('from');
        $to = $request->get('to');
        $status = $request->get('status');
        $service = $request->get('service_type');

        $query = GoogleFormOrder::with('customer')->orderByDesc('submitted_on')->orderByDesc('id');
        if ($from) {
            $query->where('event_date', '>=', $from);
        }
        if ($to) {
            $query->where('event_date', '<=', $to);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($service) {
            $query->where('service_type', $service);
        }

        return response()->json($query->get());
    }

    public function show(GoogleFormOrder $googleFormOrder): JsonResponse
    {
        return response()->json($googleFormOrder->load('customer'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['customer_id'] = $this->contacts->findOrCreateCustomer(
            $data['customer_name'] ?? null,
            $data['customer_id'] ?? null,
            [
                'email' => $data['customer_email'] ?? null,
                'phone' => $data['customer_phone'] ?? null,
            ]
        );
        $data['reference'] = $this->nextReference($data['submitted_on']);
        $data = $this->prepare($data);

        $order = GoogleFormOrder::create($data);

        return response()->json($order->load('customer'), 201);
    }

    public function update(Request $request, GoogleFormOrder $googleFormOrder): JsonResponse
    {
        $data = $this->validated($request);
        $data['customer_id'] = $this->contacts->findOrCreateCustomer(
            $data['customer_name'] ?? null,
            $data['customer_id'] ?? $googleFormOrder->customer_id,
            [
                'email' => $data['customer_email'] ?? null,
                'phone' => $data['customer_phone'] ?? null,
            ]
        );
        $data = $this->prepare($data);

        $googleFormOrder->update($data);

        return response()->json($googleFormOrder->fresh('customer'));
    }

    public function destroy(GoogleFormOrder $googleFormOrder): JsonResponse
    {
        $googleFormOrder->delete();

        return response()->json(['ok' => true]);
    }

    public function meta(): JsonResponse
    {
        return response()->json([
            'services' => config('foodeza.services'),
            'statuses' => config('foodeza.google_form_order_statuses'),
            'form_url' => config('foodeza.google_form_url'),
        ]);
    }

    private function validated(Request $request): array
    {
        if ($request->input('form_response_id') === '') {
            $request->merge(['form_response_id' => null]);
        }

        return $request->validate([
            'customer_id' => 'nullable|integer|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'submitted_on' => RecordDates::dateRules(),
            'event_date' => RecordDates::dateRules(),
            'service_type' => 'required|in:'.implode(',', config('foodeza.services')),
            'items' => 'required|string',
            'guest_count' => 'nullable|integer|min:0',
            'location' => 'nullable|string|max:255',
            'status' => 'required|in:'.implode(',', array_keys(config('foodeza.google_form_order_statuses'))),
            'form_response_id' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('google_form_orders', 'form_response_id')->ignore($request->route('googleFormOrder')),
            ],
            'notes' => 'nullable|string',
        ]);
    }

    private function prepare(array $data): array
    {
        $responseId = trim((string) ($data['form_response_id'] ?? ''));
        $data['form_response_id'] = $responseId === '' ? null : $responseId;
        unset($data['customer_name'], $data['customer_email'], $data['customer_phone']);

        return $data;
    }

    private function nextReference(string $date): string
    {
        $year = substr($date, 0, 4);
        $prefix = "GF-{$year}-";
        $last = GoogleFormOrder::where('reference', 'like', $prefix.'%')->orderByDesc('reference')->value('reference');
        $n = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
