<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\RecordDates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $from = $request->get('from');
        $to = $request->get('to');

        $paymentsQuery = Payment::with(['invoice.customer'])->orderByDesc('date');
        if ($from) {
            $paymentsQuery->where('date', '>=', $from);
        }
        if ($to) {
            $paymentsQuery->where('date', '<=', $to);
        }
        $payments = $paymentsQuery->get();

        $totalsQuery = Payment::query();
        if ($from) {
            $totalsQuery->where('date', '>=', $from);
        }
        if ($to) {
            $totalsQuery->where('date', '<=', $to);
        }

        $byMethod = $totalsQuery
            ->select('payment_method', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('payment_method')
            ->get();

        return response()->json([
            'payments' => $payments,
            'totals_by_method' => $byMethod,
            'payment_methods' => config('foodeza.payment_methods'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => RecordDates::dateRules(),
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:'.implode(',', array_keys(config('foodeza.payment_methods'))),
            'invoice_id' => 'nullable|integer|exists:invoices,id',
            'income_id' => 'nullable|integer|exists:incomes,id',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $payment = DB::transaction(function () use ($data) {
            $payment = Payment::create($data);

            if (! empty($data['invoice_id'])) {
                $this->syncInvoiceStatus((int) $data['invoice_id']);
            }

            return $payment;
        });

        return response()->json($payment->load('invoice.customer'), 201);
    }

    private function syncInvoiceStatus(int $invoiceId): void
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $paid = (float) Payment::where('invoice_id', $invoiceId)->sum('amount');

        $status = match (true) {
            $paid >= (float) $invoice->total => 'paid',
            $paid > 0 => 'partial',
            default => 'unpaid',
        };

        $invoice->update(['status' => $status]);
    }
}
