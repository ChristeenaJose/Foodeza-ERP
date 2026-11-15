<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;

class InvoiceBuilderService
{
    public function __construct(private InvoiceService $invoiceService) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{description: string, quantity: float, unit_price: float}>  $items
     */
    public function create(array $data, array $items = []): Invoice
    {
        $items = $this->normalizeItems($items, $data);

        if (! empty($items)) {
            $data['subtotal'] = round(array_sum(array_column($items, 'amount')), 2);
        }

        $amounts = $this->invoiceService->calculateVat(
            (float) $data['subtotal'],
            isset($data['vat_rate']) ? (float) $data['vat_rate'] : null
        );

        $data['invoice_number'] = $data['invoice_number'] ?? $this->invoiceService->nextNumber(
            $this->invoiceService->yearFromDate($data['date'])
        );
        $data = array_merge($data, $amounts);

        if (empty($data['due_date']) && ! empty($data['date'])) {
            $days = (int) config('foodeza.payment_terms_days', 14);
            $data['due_date'] = date('Y-m-d', strtotime($data['date']." +{$days} days"));
        }

        $invoice = Invoice::create($data);

        foreach ($items as $index => $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'amount' => $item['amount'],
                'sort_order' => $index,
            ]);
        }

        return $invoice->fresh(['customer', 'items']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{description?: string, quantity?: float, unit_price?: float}>  $items
     * @return list<array{description: string, quantity: float, unit_price: float, amount: float}>
     */
    private function normalizeItems(array $items, array $data): array
    {
        $normalized = [];

        foreach ($items as $item) {
            $description = trim($item['description'] ?? '');
            if ($description === '') {
                continue;
            }

            $quantity = max(0.01, (float) ($item['quantity'] ?? 1));
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            if ($unitPrice <= 0) {
                continue;
            }

            $normalized[] = [
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => round($quantity * $unitPrice, 2),
            ];
        }

        if ($normalized === [] && ! empty($data['service_type']) && ! empty($data['subtotal'])) {
            $normalized[] = [
                'description' => $data['service_type'],
                'quantity' => 1,
                'unit_price' => (float) $data['subtotal'],
                'amount' => (float) $data['subtotal'],
            ];
        }

        return $normalized;
    }
}
