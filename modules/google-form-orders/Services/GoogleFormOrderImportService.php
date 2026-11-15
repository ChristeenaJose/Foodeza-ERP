<?php

namespace App\Services;

use App\Models\GoogleFormOrder;
use App\Models\GoogleFormOrderEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class GoogleFormOrderImportService
{
    public function __construct(
        private SpreadsheetReader $reader,
        private ContactService $contacts,
    ) {}

    public function import(GoogleFormOrderEvent $event, UploadedFile $file): array
    {
        $rows = $this->reader->rows($file);
        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($event, $rows, &$created, &$updated, &$skipped) {
            foreach ($rows as $row) {
                $mapped = $this->mapRow($row, $event);
                if ($mapped === null) {
                    $skipped++;

                    continue;
                }

                $existing = GoogleFormOrder::where('event_id', $event->id)
                    ->where('form_response_id', $mapped['form_response_id'])
                    ->first();

                $customerId = $this->contacts->findOrCreateCustomer(
                    $mapped['customer_name'] ?: null,
                    $existing?->customer_id,
                    [
                        'email' => $mapped['customer_email'] ?: null,
                        'phone' => $mapped['customer_phone'] ?: null,
                    ]
                );

                $payload = [
                    'customer_id' => $customerId,
                    'submitted_on' => $mapped['submitted_on'],
                    'event_date' => $event->event_date->format('Y-m-d'),
                    'service_type' => $mapped['service_type'],
                    'items' => $mapped['items'],
                    'guest_count' => $mapped['guest_count'],
                    'location' => $mapped['location'] ?: $event->location,
                    'raw_payload' => $mapped['raw_payload'],
                    'notes' => $mapped['notes'],
                ];

                if ($existing) {
                    // Keep cancelled status unless the row explicitly sets something else via notes/status later.
                    if ($existing->status !== 'cancelled') {
                        $payload['status'] = $existing->status ?: 'new';
                    }
                    $existing->update($payload);
                    $updated++;
                } else {
                    $payload['event_id'] = $event->id;
                    $payload['form_response_id'] = $mapped['form_response_id'];
                    $payload['reference'] = $this->nextReference($event, $mapped['submitted_on']);
                    $payload['status'] = 'new';
                    GoogleFormOrder::create($payload);
                    $created++;
                }
            }
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'total_rows' => count($rows),
        ];
    }

    private function mapRow(array $row, GoogleFormOrderEvent $event): ?array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[$this->normalizeKey((string) $key)] = trim((string) $value);
        }

        $name = $this->first($normalized, ['name', 'full_name', 'customer', 'customer_name', 'your_name', 'naam']);
        $email = $this->first($normalized, ['email', 'e_mail', 'customer_email', 'email_address']);
        $phone = $this->first($normalized, ['phone', 'mobile', 'whatsapp', 'telephone', 'tel', 'phone_number', 'customer_phone', 'handynummer']);
        $timestamp = $this->first($normalized, ['timestamp', 'submitted', 'submitted_on', 'submission_time', 'response_timestamp']);
        $responseId = $this->first($normalized, ['form_response_id', 'response_id', 'id', 'submission_id']);
        $items = $this->first($normalized, ['items', 'order', 'order_details', 'what_would_you_like', 'menu', 'bestelling', 'order_from_the_form']);
        $guests = $this->first($normalized, ['guests', 'guest_count', 'number_of_people', 'quantity', 'qty', 'persons', 'aantal']);
        $location = $this->first($normalized, ['location', 'pickup_location', 'address', 'place', 'locatie']);
        $service = $this->first($normalized, ['service', 'service_type', 'type']);
        $notes = $this->first($normalized, ['notes', 'note', 'comments', 'opmerkingen']);

        $known = [
            'name', 'full_name', 'customer', 'customer_name', 'your_name', 'naam',
            'email', 'e_mail', 'customer_email', 'email_address',
            'phone', 'mobile', 'whatsapp', 'telephone', 'tel', 'phone_number', 'customer_phone', 'handynummer',
            'timestamp', 'submitted', 'submitted_on', 'submission_time', 'response_timestamp',
            'form_response_id', 'response_id', 'id', 'submission_id',
            'items', 'order', 'order_details', 'what_would_you_like', 'menu', 'bestelling', 'order_from_the_form',
            'guests', 'guest_count', 'number_of_people', 'quantity', 'qty', 'persons', 'aantal',
            'location', 'pickup_location', 'address', 'place', 'locatie',
            'service', 'service_type', 'type',
            'notes', 'note', 'comments', 'opmerkingen',
        ];

        $extras = [];
        foreach ($normalized as $key => $value) {
            if ($value === '' || in_array($key, $known, true)) {
                continue;
            }
            $extras[] = $this->humanize($key).': '.$value;
        }

        if ($items === '' && $extras) {
            $items = implode("\n", $extras);
            $extras = [];
        } elseif ($extras) {
            $items = trim($items."\n".implode("\n", $extras));
        }

        if ($name === '' && $email === '' && $phone === '' && $items === '') {
            return null;
        }

        if ($items === '') {
            $items = '(no order details)';
        }

        $submittedOn = $this->parseDate($timestamp) ?: $event->event_date->format('Y-m-d');
        // Prefer Google Form response id / Timestamp — never include mutable order fields,
        // so re-uploads update the same row instead of creating duplicates.
        $formResponseId = $responseId !== ''
            ? $responseId
            : ($timestamp !== ''
                ? $this->fingerprint($timestamp, $email, $phone, $name)
                : $this->fingerprint($email, $phone, $name, $items));

        $guestCount = $guests !== '' && is_numeric($guests) ? (int) $guests : null;
        $services = config('foodeza.services', []);
        $serviceType = $service !== '' && in_array($service, $services, true)
            ? $service
            : ($services[0] ?? 'Catering');

        return [
            'form_response_id' => $formResponseId,
            'customer_name' => $name,
            'customer_email' => $email,
            'customer_phone' => $phone,
            'submitted_on' => $submittedOn,
            'service_type' => $serviceType,
            'items' => $items,
            'guest_count' => $guestCount,
            'location' => $location,
            'notes' => $notes !== '' ? $notes : null,
            'raw_payload' => $row,
        ];
    }

    private function nextReference(GoogleFormOrderEvent $event, string $date): string
    {
        $year = substr($date, 0, 4);
        $prefix = "GF-{$year}-";
        $last = GoogleFormOrder::where('reference', 'like', $prefix.'%')->orderByDesc('reference')->value('reference');
        $n = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    private function normalizeKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? $key;

        return trim($key, '_');
    }

    private function first(array $normalized, array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if (isset($normalized[$candidate]) && $normalized[$candidate] !== '') {
                return $normalized[$candidate];
            }
        }

        return '';
    }

    private function humanize(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return substr($value, 0, 10);
        }
        $ts = strtotime($value);

        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function fingerprint(string ...$parts): string
    {
        return hash('sha256', implode('|', array_map(fn ($p) => mb_strtolower(trim($p)), $parts)));
    }
}
