<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoogleFormOrderEvent;
use App\Models\GoogleFormOrderUpdate;
use App\Services\GoogleFormOrderImportService;
use App\Services\WhatsAppMessageService;
use App\Support\EnsureGoogleFormOrderTables;
use App\Support\RecordDates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GoogleFormOrderEventController extends Controller
{
    public function __construct(
        private GoogleFormOrderImportService $importer,
        private WhatsAppMessageService $whatsapp,
    ) {}

    public function index(): JsonResponse
    {
        EnsureGoogleFormOrderTables::run();

        $events = GoogleFormOrderEvent::query()
            ->withCount([
                'orders',
                'orders as active_orders_count' => fn ($q) => $q->where('status', '!=', 'cancelled'),
            ])
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->get();

        return response()->json($events);
    }

    public function store(Request $request): JsonResponse
    {
        EnsureGoogleFormOrderTables::run();

        $data = $this->validated($request);
        if (empty($data['confirmation_template'])) {
            $data['confirmation_template'] = GoogleFormOrderEvent::defaultConfirmationTemplate();
        }

        $event = GoogleFormOrderEvent::create($data);

        return response()->json($event, 201);
    }

    public function show(GoogleFormOrderEvent $googleFormOrderEvent): JsonResponse
    {
        $event = $googleFormOrderEvent->load([
            'orders' => fn ($q) => $q->with('customer')->orderByDesc('submitted_on')->orderByDesc('id'),
            'updates',
        ]);

        return response()->json($event);
    }

    public function update(Request $request, GoogleFormOrderEvent $googleFormOrderEvent): JsonResponse
    {
        $data = $this->validated($request, true);
        $googleFormOrderEvent->update($data);

        if (isset($data['event_date']) || isset($data['location'])) {
            $sync = [];
            if (isset($data['event_date'])) {
                $sync['event_date'] = $data['event_date'];
            }
            if (isset($data['location'])) {
                $sync['location'] = $data['location'];
            }
            $googleFormOrderEvent->orders()->update($sync);
        }

        return response()->json($googleFormOrderEvent->fresh());
    }

    public function destroy(GoogleFormOrderEvent $googleFormOrderEvent): JsonResponse
    {
        $googleFormOrderEvent->delete();

        return response()->json(['ok' => true]);
    }

    public function import(Request $request, GoogleFormOrderEvent $googleFormOrderEvent): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $result = $this->importer->import($googleFormOrderEvent, $request->file('file'));

        return response()->json([
            ...$result,
            'event' => $googleFormOrderEvent->fresh()->load([
                'orders' => fn ($q) => $q->with('customer')->orderByDesc('submitted_on')->orderByDesc('id'),
            ]),
        ]);
    }

    public function storeUpdate(Request $request, GoogleFormOrderEvent $googleFormOrderEvent): JsonResponse
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'message' => 'required|string',
        ]);

        $update = $googleFormOrderEvent->updates()->create($data);

        return response()->json($update, 201);
    }

    public function sendConfirmations(Request $request, GoogleFormOrderEvent $googleFormOrderEvent): JsonResponse
    {
        $data = $request->validate([
            'order_ids' => 'nullable|array',
            'order_ids.*' => 'integer',
            'mark_sent' => 'nullable|boolean',
        ]);

        $query = $googleFormOrderEvent->orders()->with('customer')->where('status', '!=', 'cancelled');
        if (! empty($data['order_ids'])) {
            $query->whereIn('id', $data['order_ids']);
        }

        $links = [];
        $missingPhone = [];
        foreach ($query->get() as $order) {
            $link = $this->whatsapp->confirmationLink($googleFormOrderEvent, $order);
                if ($link) {
                    $links[] = $link;
                    // Only mark sent when the client explicitly asks (after WhatsApp actually opened).
                    if ($request->boolean('mark_sent', false)) {
                        $order->update(['confirmation_sent_at' => now()]);
                    }
                } else {
                $missingPhone[] = [
                    'order_id' => $order->id,
                    'reference' => $order->reference,
                    'name' => $order->customer?->name,
                ];
            }
        }

        return response()->json([
            'links' => $links,
            'missing_phone' => $missingPhone,
            'count' => count($links),
        ]);
    }

    public function sendUpdate(Request $request, GoogleFormOrderEvent $googleFormOrderEvent, GoogleFormOrderUpdate $update): JsonResponse
    {
        if ($update->event_id !== $googleFormOrderEvent->id) {
            abort(404);
        }

        $data = $request->validate([
            'order_ids' => 'nullable|array',
            'order_ids.*' => 'integer',
            'mark_sent' => 'nullable|boolean',
        ]);

        $query = $googleFormOrderEvent->orders()->with('customer')->where('status', '!=', 'cancelled');
        if (! empty($data['order_ids'])) {
            $query->whereIn('id', $data['order_ids']);
        }

        $links = [];
        $missingPhone = [];
        foreach ($query->get() as $order) {
            $link = $this->whatsapp->updateLink($googleFormOrderEvent, $order, $update->message);
            if ($link) {
                $links[] = $link;
            } else {
                $missingPhone[] = [
                    'order_id' => $order->id,
                    'reference' => $order->reference,
                    'name' => $order->customer?->name,
                ];
            }
        }

        if ($request->boolean('mark_sent', false) && $links) {
            $update->update(['last_sent_at' => now()]);
        }

        return response()->json([
            'links' => $links,
            'missing_phone' => $missingPhone,
            'count' => count($links),
            'update' => $update->fresh(),
        ]);
    }

    private function validated(Request $request, bool $updating = false): array
    {
        // Browsers may send HH:MM:SS from <input type="time">.
        foreach (['start_time', 'end_time'] as $field) {
            $value = $request->input($field);
            if (is_string($value) && preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $value)) {
                $parts = explode(':', $value);
                $request->merge([
                    $field => sprintf('%02d:%02d', (int) $parts[0], (int) $parts[1]),
                ]);
            }
        }

        if (is_string($request->input('event_date'))) {
            $request->merge([
                'event_date' => substr($request->input('event_date'), 0, 10),
            ]);
        }

        $data = $request->validate([
            'name' => ($updating ? 'sometimes|' : '').'required|string|max:255',
            'location' => ($updating ? 'sometimes|' : '').'required|string|max:500',
            'event_date' => array_merge($updating ? ['sometimes'] : [], RecordDates::dateRules()),
            'start_time' => ($updating ? 'sometimes|' : '').'required|date_format:H:i',
            'end_time' => ($updating ? 'sometimes|' : '').'required|date_format:H:i',
            'confirmation_template' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if (isset($data['start_time'], $data['end_time']) && $data['end_time'] <= $data['start_time']) {
            throw ValidationException::withMessages([
                'end_time' => ['End time must be after start time.'],
            ]);
        }

        return $data;
    }
}
