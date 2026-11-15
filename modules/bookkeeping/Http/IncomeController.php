<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Income;
use App\Services\ContactService;
use App\Services\InvoiceService;
use App\Support\RecordDates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncomeController extends Controller
{
    public function __construct(
        private ContactService $contacts,
        private InvoiceService $invoices,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $from = $request->get('from');
        $to = $request->get('to');

        $query = Income::with('customer')->orderByDesc('date');
        if ($from) {
            $query->where('date', '>=', $from);
        }
        if ($to) {
            $query->where('date', '<=', $to);
        }

        return response()->json($query->get());
    }

    public function show(Income $income): JsonResponse
    {
        return response()->json($income->load('customer'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => RecordDates::dateRules(),
            'customer_id' => 'nullable|integer|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
            'service_type' => 'required|in:'.implode(',', config('foodeza.services')),
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:'.implode(',', array_keys(config('foodeza.payment_methods'))),
            'invoice_id' => 'nullable|integer|exists:invoices,id',
            'invoice_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $vat = $this->invoices->calculateVat((float) $data['amount']);
        $data['customer_id'] = $this->contacts->findOrCreateCustomer(
            $data['customer_name'] ?? null,
            $data['customer_id'] ?? null
        );
        $data['vat_amount'] = $vat['vat_amount'];
        unset($data['customer_name']);

        $income = Income::create($data);

        return response()->json($income->load('customer'), 201);
    }

    public function update(Request $request, Income $income): JsonResponse
    {
        $data = $request->validate([
            'date' => RecordDates::dateRules(),
            'customer_id' => 'nullable|integer|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
            'service_type' => 'required|in:'.implode(',', config('foodeza.services')),
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:'.implode(',', array_keys(config('foodeza.payment_methods'))),
            'invoice_id' => 'nullable|integer|exists:invoices,id',
            'invoice_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $vat = $this->invoices->calculateVat((float) $data['amount']);
        $data['customer_id'] = $this->contacts->findOrCreateCustomer(
            $data['customer_name'] ?? null,
            $data['customer_id'] ?? $income->customer_id
        );
        $data['vat_amount'] = $vat['vat_amount'];
        unset($data['customer_name']);

        $income->update($data);

        return response()->json($income->fresh('customer'));
    }

    public function destroy(Income $income): JsonResponse
    {
        $income->delete();

        return response()->json(['ok' => true]);
    }

    public function meta(): JsonResponse
    {
        return response()->json([
            'services' => config('foodeza.services'),
            'payment_methods' => config('foodeza.payment_methods'),
            'suggested_invoice_number' => $this->invoices->peekNumber(),
        ]);
    }
}
