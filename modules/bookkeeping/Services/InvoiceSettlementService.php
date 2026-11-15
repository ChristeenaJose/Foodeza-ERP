<?php

namespace App\Services;

use App\Models\Income;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceSettlementService
{
    /**
     * Mark invoice paid and record matching income + payment.
     *
     * @param  array{payment_method?: string, date?: string, reference?: string, notes?: string}  $data
     */
    public function markAsPaid(Invoice $invoice, array $data = []): Invoice
    {
        if ($invoice->status === 'paid') {
            throw ValidationException::withMessages([
                'status' => ['This invoice is already marked as paid.'],
            ]);
        }

        $existing = Income::where('invoice_id', $invoice->id)->first();
        if ($existing) {
            $invoice->update(['status' => 'paid']);

            return $invoice->fresh(['customer', 'items', 'pdfAttachment']);
        }

        $paymentMethod = $data['payment_method'] ?? 'bank_transfer';
        $date = $data['date'] ?? now()->toDateString();

        return DB::transaction(function () use ($invoice, $data, $paymentMethod, $date) {
            $income = Income::create([
                'date' => $date,
                'customer_id' => $invoice->customer_id,
                'service_type' => $invoice->service_type,
                'amount' => $invoice->subtotal,
                'vat_amount' => $invoice->vat_amount,
                'payment_method' => $paymentMethod,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'notes' => $data['notes'] ?? "Payment for invoice {$invoice->invoice_number}",
            ]);

            Payment::create([
                'date' => $date,
                'amount' => $invoice->total,
                'payment_method' => $paymentMethod,
                'invoice_id' => $invoice->id,
                'income_id' => $income->id,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $invoice->update(['status' => 'paid']);

            return $invoice->fresh(['customer', 'items', 'pdfAttachment', 'payments']);
        });
    }
}
