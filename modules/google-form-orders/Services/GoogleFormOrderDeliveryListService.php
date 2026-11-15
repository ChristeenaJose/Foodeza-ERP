<?php

namespace App\Services;

use App\Models\GoogleFormOrder;
use App\Models\GoogleFormOrderEvent;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GoogleFormOrderDeliveryListService
{
    /** @var list<string> */
    private array $metaKeyHints = [
        'timestamp', 'username', 'email', 'e_mail', 'email_address',
        'full_name', 'name', 'customer', 'customer_name', 'your_name', 'naam',
        'phone', 'phone_number', 'mobile', 'whatsapp', 'telephone', 'tel', 'handynummer',
        'form_response_id', 'response_id', 'submission_id', 'id',
        'submitted', 'submitted_on', 'service', 'service_type', 'location', 'pickup_location',
    ];

    /** @var list<string> */
    private array $notesKeyHints = [
        'special_instructions', 'instructions', 'requests', 'request',
        'notes', 'note', 'comments', 'opmerkingen', 'any_special_instructions_or_requests',
    ];

    public function build(GoogleFormOrderEvent $event): array
    {
        $orders = $event->orders()
            ->with('customer')
            ->where('status', '!=', 'cancelled')
            ->orderBy('id')
            ->get();

        $columns = $this->discoverColumns($orders);
        $rows = [];
        $columnTotals = array_fill_keys(array_keys($columns), 0);
        $grandTotal = 0.0;

        foreach ($orders as $order) {
            $qtys = [];
            $amount = 0.0;
            foreach ($columns as $colKey => $col) {
                $qty = $this->qtyForColumn($order, $col);
                $qtys[$colKey] = $qty;
                $columnTotals[$colKey] += $qty;
                $amount += $qty * $col['price'];
            }
            $grandTotal += $amount;

            $rows[] = [
                'name' => $order->customer?->name ?: '—',
                'phone' => $order->whatsapp_phone ?: $order->customer?->phone ?: '—',
                'qtys' => $qtys,
                'notes' => $this->notesForOrder($order),
                'amount' => $amount,
                'reference' => $order->reference,
            ];
        }

        return [
            'event' => $event,
            'columns' => array_values($columns),
            'rows' => $rows,
            'column_totals' => array_values($columnTotals),
            'grand_total' => $grandTotal,
            'order_count' => count($rows),
        ];
    }

    public function pdf(GoogleFormOrderEvent $event)
    {
        $data = $this->build($event);
        $filename = $this->filename($event, 'pdf');

        return Pdf::loadView('google-form-orders.delivery-list', [
            'list' => $data,
            'company' => config('foodeza'),
        ])
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    public function csv(GoogleFormOrderEvent $event): StreamedResponse
    {
        $data = $this->build($event);
        $filename = $this->filename($event, 'csv');

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            $headers = ['Full Name', 'Phone Number'];
            foreach ($data['columns'] as $col) {
                $headers[] = $col['label'];
            }
            $headers[] = 'Any special instructions or requests?';
            $headers[] = 'Amount';
            fputcsv($out, $headers);

            foreach ($data['rows'] as $row) {
                $line = [$row['name'], $row['phone']];
                foreach (array_values($row['qtys']) as $qty) {
                    $line[] = $qty > 0 ? $qty : '';
                }
                $line[] = $row['notes'] ?? '';
                $line[] = number_format($row['amount'], 2, '.', '');
                fputcsv($out, $line);
            }

            $totals = ['', 'TOTAL'];
            foreach ($data['column_totals'] as $total) {
                $totals[] = $total > 0 ? $total : '';
            }
            $totals[] = '';
            $totals[] = number_format($data['grand_total'], 2, '.', '');
            fputcsv($out, $totals);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function filename(GoogleFormOrderEvent $event, string $ext): string
    {
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($event->name)) ?: 'delivery-list';
        $base = trim($base, '-');

        return $base.'-delivery-list.'.$ext;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, GoogleFormOrder>  $orders
     * @return array<string, array{key: string, label: string, price: float}>
     */
    private function discoverColumns($orders): array
    {
        $columns = [];

        foreach ($orders as $order) {
            $payload = is_array($order->raw_payload) ? $order->raw_payload : [];
            foreach ($payload as $key => $value) {
                $key = (string) $key;
                if ($this->isMetaKey($key) || $this->isNotesKey($key)) {
                    continue;
                }
                $price = $this->extractPrice($key);
                if ($price === null) {
                    // Quantity-looking menu columns without price still appear if value is numeric.
                    if (! is_numeric(trim((string) $value))) {
                        continue;
                    }
                    $price = 0.0;
                }
                $norm = $this->normalizeKey($key);
                if (! isset($columns[$norm])) {
                    $columns[$norm] = [
                        'key' => $key,
                        'label' => $key,
                        'price' => $price,
                    ];
                } elseif ($columns[$norm]['price'] <= 0 && $price > 0) {
                    $columns[$norm]['price'] = $price;
                }
            }

            // Fallback: parse "Label (€x.xx): qty" lines from items text.
            foreach ($this->parseItemsText((string) $order->items) as $parsed) {
                $norm = $this->normalizeKey($parsed['label']);
                if (! isset($columns[$norm])) {
                    $columns[$norm] = [
                        'key' => $parsed['label'],
                        'label' => $parsed['label'],
                        'price' => $parsed['price'],
                    ];
                }
            }
        }

        return $columns;
    }

    private function qtyForColumn(GoogleFormOrder $order, array $col): int
    {
        $payload = is_array($order->raw_payload) ? $order->raw_payload : [];
        foreach ($payload as $key => $value) {
            if ($this->normalizeKey((string) $key) === $this->normalizeKey($col['key'])) {
                $qty = $this->toQty($value);

                return $qty;
            }
        }

        foreach ($this->parseItemsText((string) $order->items) as $parsed) {
            if ($this->normalizeKey($parsed['label']) === $this->normalizeKey($col['label'])) {
                return $parsed['qty'];
            }
        }

        return 0;
    }

    private function notesForOrder(GoogleFormOrder $order): ?string
    {
        if ($order->notes) {
            return $order->notes;
        }
        $payload = is_array($order->raw_payload) ? $order->raw_payload : [];
        foreach ($payload as $key => $value) {
            if ($this->isNotesKey((string) $key)) {
                $text = trim((string) $value);

                return $text !== '' ? $text : null;
            }
        }

        return null;
    }

    /**
     * @return list<array{label: string, price: float, qty: int}>
     */
    private function parseItemsText(string $items): array
    {
        $parsed = [];
        foreach (preg_split('/\r\n|\r|\n/', $items) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line === '(no order details)') {
                continue;
            }
            // "Chicken Cutlet (€2.00): 2" or "Chicken Cutlet (€2.00):2"
            if (preg_match('/^(.+?)\s*:\s*(\d+)\s*$/u', $line, $m)) {
                $label = trim($m[1]);
                $price = $this->extractPrice($label) ?? 0.0;
                $parsed[] = ['label' => $label, 'price' => $price, 'qty' => (int) $m[2]];
            }
        }

        return $parsed;
    }

    private function extractPrice(string $label): ?float
    {
        if (preg_match('/\(\s*€?\s*([\d]+[.,]\d{1,2}|\d+)\s*€?\s*\)/u', $label, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }

    private function toQty(mixed $value): int
    {
        $text = trim((string) $value);
        if ($text === '' || ! is_numeric($text)) {
            return 0;
        }

        return max(0, (int) round((float) $text));
    }

    private function normalizeKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? $key;

        return trim($key, '_');
    }

    private function isMetaKey(string $key): bool
    {
        $norm = $this->normalizeKey($key);
        foreach ($this->metaKeyHints as $hint) {
            if ($norm === $hint || str_starts_with($norm, $hint.'_') || str_ends_with($norm, '_'.$hint)) {
                return true;
            }
        }

        return false;
    }

    private function isNotesKey(string $key): bool
    {
        $norm = $this->normalizeKey($key);
        foreach ($this->notesKeyHints as $hint) {
            if ($norm === $hint || str_contains($norm, $hint)) {
                return true;
            }
        }

        return false;
    }
}
