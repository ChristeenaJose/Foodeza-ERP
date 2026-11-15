<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\ContactService;
use App\Services\FileStorageService;
use App\Services\InvoiceBuilderService;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use App\Services\InvoiceSettlementService;
use App\Support\RecordDates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InvoiceController extends Controller
{
    public function __construct(
        private ContactService $contacts,
        private InvoiceService $invoiceService,
        private InvoiceBuilderService $builder,
        private InvoicePdfService $pdf,
        private FileStorageService $files,
        private InvoiceSettlementService $settlement,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            tap(Invoice::with(['customer', 'pdfAttachment', 'items'])->orderByDesc('date'), function ($query) use ($request) {
                if ($request->get('from')) {
                    $query->where('date', '>=', $request->get('from'));
                }
                if ($request->get('to')) {
                    $query->where('date', '<=', $request->get('to'));
                }
            })->get()
        );
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json($invoice->load(['customer', 'pdfAttachment', 'payments', 'items']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'invoice_number' => 'nullable|string|max:50',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'customer_name' => 'required_without:customer_id|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'customer_address' => 'nullable|string',
            'date' => RecordDates::dateRules(),
            'due_date' => RecordDates::optionalDateRules(),
            'service_type' => 'required|in:'.implode(',', config('foodeza.services')),
            'subtotal' => 'nullable|numeric|min:0.01',
            'vat_rate' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'nullable|array|min:1',
            'items.*.description' => 'required_with:items|string|max:500',
            'items.*.quantity' => 'nullable|numeric|min:0.01',
            'items.*.unit_price' => 'required_with:items|numeric|min:0.01',
            'pdf' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $customerId = $this->contacts->findOrCreateCustomer(
            $data['customer_name'] ?? null,
            $data['customer_id'] ?? null
        );

        Customer::where('id', $customerId)->update(array_filter([
            'email' => $data['customer_email'] ?? null,
            'phone' => $data['customer_phone'] ?? null,
            'address' => $data['customer_address'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''));

        $items = $data['items'] ?? [];
        unset($data['items'], $data['customer_name'], $data['customer_email'], $data['customer_phone'], $data['customer_address'], $data['pdf']);

        $data['customer_id'] = $customerId;

        if (empty($data['subtotal']) && empty($items)) {
            return response()->json(['message' => 'Add line items or a subtotal amount.'], 422);
        }

        $invoice = $this->builder->create($data, $items);

        if ($request->hasFile('pdf')) {
            $attachment = $this->files->store($request->file('pdf'), 'invoice');
            $invoice->update(['pdf_attachment_id' => $attachment->id]);
        } else {
            $this->pdf->generateAndStore($invoice);
        }

        return response()->json($invoice->fresh(['customer', 'pdfAttachment', 'items']), 201);
    }

    public function pdf(Invoice $invoice, Request $request): BinaryFileResponse
    {
        $invoice->load(['customer', 'items', 'pdfAttachment']);

        if (! $invoice->pdf_attachment_id || ! $invoice->pdfAttachment) {
            $this->pdf->generateAndStore($invoice);
            $invoice->refresh()->load('pdfAttachment');
        }

        $path = $this->files->fullPath($invoice->pdfAttachment);
        $filename = $invoice->invoice_number.'.pdf';
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }

    public function regeneratePdf(Invoice $invoice): JsonResponse
    {
        $attachment = $this->pdf->generateAndStore($invoice->load(['customer', 'items']));

        return response()->json([
            'ok' => true,
            'attachment_id' => $attachment->id,
            'download_url' => url('/api/invoices/'.$invoice->id.'/pdf'),
        ]);
    }

    public function markPaid(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'payment_method' => 'nullable|string|in:'.implode(',', array_keys(config('foodeza.payment_methods'))),
            'date' => RecordDates::optionalDateRules(),
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $invoice = $this->settlement->markAsPaid($invoice, $data);

        return response()->json([
            'invoice' => $invoice,
            'income_created' => true,
        ]);
    }

    public function outstanding(): JsonResponse
    {
        return response()->json(
            Invoice::with(['customer', 'items'])->whereIn('status', ['unpaid', 'partial'])->orderBy('due_date')->get()
        );
    }

    public function peekNumber(Request $request): JsonResponse
    {
        $year = $request->filled('date')
            ? $this->invoiceService->yearFromDate($request->string('date')->toString())
            : ($request->filled('year') ? (int) $request->get('year') : null);

        return response()->json(['number' => $this->invoiceService->peekNumber($year)]);
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        DB::transaction(function () use ($invoice) {
            $attachment = $invoice->pdfAttachment;

            Payment::where('invoice_id', $invoice->id)->delete();
            Income::where('invoice_id', $invoice->id)->delete();
            $invoice->delete();

            if ($attachment) {
                if (Storage::disk('local')->exists($attachment->stored_path)) {
                    Storage::disk('local')->delete($attachment->stored_path);
                }
                $attachment->delete();
            }
        });

        return response()->json(['ok' => true]);
    }
}
