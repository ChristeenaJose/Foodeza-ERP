<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\ContactService;
use App\Support\RecordDates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private ContactService $contacts) {}

    public function index(Request $request): JsonResponse
    {
        $from = $request->get('from');
        $to = $request->get('to');
        $status = $request->get('status');
        $service = $request->get('service_type');

        $query = Booking::with('customer')->orderBy('event_date')->orderBy('start_time');
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

    public function show(Booking $booking): JsonResponse
    {
        return response()->json($booking->load('customer'));
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
        $data['title'] = $this->titleFor($data);
        $data['reference'] = $this->nextReference($data['event_date']);
        $data = $this->prepare($data);

        $booking = Booking::create($data);

        return response()->json($booking->load('customer'), 201);
    }

    public function update(Request $request, Booking $booking): JsonResponse
    {
        $data = $this->validated($request);
        $data['customer_id'] = $this->contacts->findOrCreateCustomer(
            $data['customer_name'] ?? null,
            $data['customer_id'] ?? $booking->customer_id,
            [
                'email' => $data['customer_email'] ?? null,
                'phone' => $data['customer_phone'] ?? null,
            ]
        );
        $data['title'] = $this->titleFor($data);
        $data = $this->prepare($data);

        $booking->update($data);

        return response()->json($booking->fresh('customer'));
    }

    public function destroy(Booking $booking): JsonResponse
    {
        $booking->delete();

        return response()->json(['ok' => true]);
    }

    public function meta(): JsonResponse
    {
        return response()->json([
            'services' => config('foodeza.services'),
            'statuses' => config('foodeza.booking_statuses'),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'customer_id' => 'nullable|integer|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'title' => 'nullable|string|max:255',
            'service_type' => 'required|in:'.implode(',', config('foodeza.services')),
            'event_date' => RecordDates::dateRules(),
            'start_time' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'end_time' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'location' => 'nullable|string|max:255',
            'guest_count' => 'nullable|integer|min:0',
            'status' => 'required|in:'.implode(',', array_keys(config('foodeza.booking_statuses'))),
            'quoted_amount' => 'nullable|numeric|min:0',
            'deposit_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);
    }

    private function prepare(array $data): array
    {
        $data['start_time'] = $this->normalizeTime($data['start_time'] ?? null);
        $data['end_time'] = $this->normalizeTime($data['end_time'] ?? null);
        unset($data['customer_name'], $data['customer_email'], $data['customer_phone']);

        return $data;
    }

    private function titleFor(array $data): string
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        $customer = trim((string) ($data['customer_name'] ?? ''));
        $service = $data['service_type'];

        return $customer !== '' ? "{$service} — {$customer}" : $service;
    }

    private function nextReference(string $date): string
    {
        $year = substr($date, 0, 4);
        $prefix = "BK-{$year}-";
        $last = Booking::where('reference', 'like', $prefix.'%')->orderByDesc('reference')->value('reference');
        $n = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    private function normalizeTime(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return substr($value, 0, 5);
    }
}
