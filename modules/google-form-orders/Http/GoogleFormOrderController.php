<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoogleFormOrder;
use App\Models\GoogleFormOrderEvent;
use App\Services\ContactService;
use App\Services\WhatsAppMessageService;
use App\Support\RecordDates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GoogleFormOrderController extends Controller
{
    public function __construct(
        private ContactService $contacts,
        private WhatsAppMessageService $whatsapp,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $from = $request->get('from');
        $to = $request->get('to');
        $status = $request->get('status');
        $eventId = $request->get('event_id');

        $query = GoogleFormOrder::with(['customer', 'event'])->orderByDesc('submitted_on')->orderByDesc('id');
        if ($eventId) {
            $query->where('event_id', $eventId);
        }
        if ($from) {
            $query->where('event_date', '>=', $from);
        }
        if ($to) {
            $query->where('event_date', '<=', $to);
        }
        if ($status) {
            $query->where('status', $status);
        }

        return response()->json($query->get());
    }

    public function show(GoogleFormOrder $googleFormOrder): JsonResponse
    {
        return response()->json($googleFormOrder->load(['customer', 'event']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $event = GoogleFormOrderEvent::findOrFail($data['event_id']);

        $phone = trim((string) ($data['whatsapp_phone'] ?? $data['customer_phone'] ?? ''));
        $data['customer_id'] = $this->contacts->findOrCreateCustomer(
            $data['customer_name'] ?? null,
            $data['customer_id'] ?? null,
            [
                'email' => $data['customer_email'] ?? null,
                'phone' => $phone !== '' ? $phone : null,
            ]
        );
        $data['event_date'] = $event->event_date->format('Y-m-d');
        $data['location'] = $data['location'] ?? $event->location;
        $data['whatsapp_phone'] = $phone !== '' ? $phone : null;
        $data['reference'] = $this->nextReference($data['submitted_on']);
        $data = $this->prepare($data);

        $order = GoogleFormOrder::create($data);

        return response()->json($order->load(['customer', 'event']), 201);
    }

    public function update(Request $request, GoogleFormOrder $googleFormOrder): JsonResponse
    {
        $data = $this->validated($request, $googleFormOrder);
        $phone = array_key_exists('whatsapp_phone', $data)
            ? trim((string) ($data['whatsapp_phone'] ?? ''))
            : trim((string) ($data['customer_phone'] ?? $googleFormOrder->whatsapp_phone ?? ''));
        $data['customer_id'] = $this->contacts->findOrCreateCustomer(
            $data['customer_name'] ?? null,
            $data['customer_id'] ?? $googleFormOrder->customer_id,
            [
                'email' => $data['customer_email'] ?? null,
                'phone' => $phone !== '' ? $phone : null,
            ]
        );
        $data['whatsapp_phone'] = $phone !== '' ? $phone : null;
        $data = $this->prepare($data, $googleFormOrder);

        $googleFormOrder->update($data);

        return response()->json($googleFormOrder->fresh(['customer', 'event']));
    }

    public function cancel(GoogleFormOrder $googleFormOrder): JsonResponse
    {
        $googleFormOrder->update(['status' => 'cancelled']);

        return response()->json($googleFormOrder->fresh(['customer', 'event']));
    }

    public function updateStatus(Request $request, GoogleFormOrder $googleFormOrder): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(config('foodeza.google_form_order_statuses'))),
        ]);

        $googleFormOrder->update(['status' => $data['status']]);

        return response()->json($googleFormOrder->fresh(['customer', 'event']));
    }

    public function destroy(GoogleFormOrder $googleFormOrder): JsonResponse
    {
        $googleFormOrder->delete();

        return response()->json(['ok' => true]);
    }

    public function whatsapp(GoogleFormOrder $googleFormOrder): JsonResponse
    {
        $order = $googleFormOrder->load(['customer', 'event']);
        $link = $this->whatsapp->confirmationLink($order->event, $order);
        if (! $link) {
            return response()->json(['message' => 'Order has no phone number for WhatsApp.'], 422);
        }

        return response()->json($link);
    }

    public function meta(): JsonResponse
    {
        return response()->json([
            'services' => config('foodeza.services'),
            'statuses' => config('foodeza.google_form_order_statuses'),
            'form_url' => config('foodeza.google_form_url'),
            'default_confirmation_template' => GoogleFormOrderEvent::defaultConfirmationTemplate(),
            'template_placeholders' => ['{name}', '{date}', '{time}', '{location}', '{items}', '{reference}', '{event}'],
        ]);
    }

    private function validated(Request $request, ?GoogleFormOrder $existing = null): array
    {
        if ($request->input('form_response_id') === '') {
            $request->merge(['form_response_id' => null]);
        }

        $eventId = $request->input('event_id', $existing?->event_id);

        return $request->validate([
            'event_id' => ($existing ? 'sometimes|' : '').'required|integer|exists:google_form_order_events,id',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'submitted_on' => RecordDates::dateRules(),
            'service_type' => 'nullable|in:'.implode(',', config('foodeza.services')),
            'items' => 'required|string',
            'guest_count' => 'nullable|integer|min:0',
            'location' => 'nullable|string|max:255',
            'whatsapp_phone' => 'nullable|string|max:50',
            'status' => 'required|in:'.implode(',', array_keys(config('foodeza.google_form_order_statuses'))),
            'form_response_id' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('google_form_orders', 'form_response_id')
                    ->where(fn ($q) => $q->where('event_id', $eventId))
                    ->ignore($existing?->id),
            ],
            'notes' => 'nullable|string',
        ]);
    }

    private function prepare(array $data, ?GoogleFormOrder $existing = null): array
    {
        if (array_key_exists('form_response_id', $data) || ! $existing) {
            $responseId = trim((string) ($data['form_response_id'] ?? ''));
            if ($responseId === '') {
                $responseId = hash('sha256', implode('|', [
                    $data['event_id'] ?? $existing?->event_id ?? '',
                    $data['customer_name'] ?? '',
                    $data['customer_phone'] ?? '',
                    $data['submitted_on'] ?? '',
                    $data['items'] ?? '',
                    microtime(true),
                ]));
            }
            $data['form_response_id'] = $responseId;
        } else {
            unset($data['form_response_id']);
        }
        unset($data['customer_name'], $data['customer_email'], $data['customer_phone']);
        if (array_key_exists('whatsapp_phone', $data)) {
            $phone = trim((string) ($data['whatsapp_phone'] ?? ''));
            $data['whatsapp_phone'] = $phone !== '' ? $phone : null;
        }

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
