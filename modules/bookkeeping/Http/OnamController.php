<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnamEntry;
use App\Models\OnamPartner;
use App\Services\FileStorageService;
use App\Services\InvoiceService;
use App\Services\OnamReportService;
use App\Support\RecordDates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class OnamController extends Controller
{
    public function __construct(
        private OnamReportService $reports,
        private FileStorageService $files,
        private InvoiceService $invoices,
    ) {}

    public function summary(): JsonResponse
    {
        return response()->json($this->reports->summary());
    }

    public function partners(): JsonResponse
    {
        return response()->json(OnamPartner::orderBy('name')->get());
    }

    public function storePartner(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'share_percent' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
            'active' => 'nullable|boolean',
        ]);
        $data['active'] = $request->boolean('active', true);

        $partner = OnamPartner::create($data);

        return response()->json($partner, 201);
    }

    public function updatePartner(Request $request, OnamPartner $partner): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'share_percent' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
            'active' => 'nullable|boolean',
        ]);
        if ($request->has('active')) {
            $data['active'] = $request->boolean('active');
        }

        $partner->update($data);

        return response()->json($partner->fresh());
    }

    public function destroyPartner(OnamPartner $partner): JsonResponse
    {
        $partner->delete();

        return response()->json(['ok' => true]);
    }

    public function entries(): JsonResponse
    {
        return response()->json(
            OnamEntry::with(['partner', 'receiptAttachment'])->orderByDesc('date')->orderByDesc('id')->get()
        );
    }

    public function storeEntry(Request $request): JsonResponse
    {
        $data = $this->validateEntry($request);

        if ($request->hasFile('receipt')) {
            $data['receipt_attachment_id'] = $this->files->store($request->file('receipt'), 'receipt')->id;
            $data['proof_type'] = 'uploaded';
        }
        unset($data['receipt']);

        $data = $this->normalizePartner($data);
        $data = $this->applyVat($data);

        $entry = OnamEntry::create($data);

        return response()->json($entry->load(['partner', 'receiptAttachment']), 201);
    }

    public function updateEntry(Request $request, OnamEntry $entry): JsonResponse
    {
        $data = $this->validateEntry($request);

        if ($request->hasFile('receipt')) {
            $entry->load('receiptAttachment');
            $old = $entry->receiptAttachment;
            $data['receipt_attachment_id'] = $this->files->store($request->file('receipt'), 'receipt')->id;
            $data['proof_type'] = 'uploaded';
            if ($old && Storage::disk('local')->exists($old->stored_path)) {
                Storage::disk('local')->delete($old->stored_path);
            }
            $old?->delete();
        }
        unset($data['receipt']);

        $data = $this->normalizePartner($data);
        $data = $this->applyVat($data);

        $entry->update($data);

        return response()->json($entry->fresh(['partner', 'receiptAttachment']));
    }

    public function destroyEntry(OnamEntry $entry): JsonResponse
    {
        $entry->delete();

        return response()->json(['ok' => true]);
    }

    private function validateEntry(Request $request): array
    {
        $data = $request->validate([
            'date' => RecordDates::dateRules(),
            'type' => 'required|in:income,expense,settlement',
            'partner_id' => [
                Rule::requiredIf($request->input('type') === 'settlement'),
                'nullable',
                'integer',
                'exists:onam_partners,id',
            ],
            'paid_by' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'vat_mode' => 'nullable|in:including,none',
            'description' => 'nullable|string|max:500',
            'proof_type' => 'nullable|string|in:'.implode(',', array_keys(config('foodeza.expense_proof_types', []))),
            'notes' => 'nullable|string',
            'receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $data['proof_type'] = $data['proof_type'] ?? 'none';
        $data['amount'] = round((float) $data['amount'], 2);
        $data['vat_mode'] = $data['type'] === 'income'
            ? ($data['vat_mode'] ?? 'none')
            : 'none';

        return $data;
    }

    private function normalizePartner(array $data): array
    {
        if ($data['type'] === 'income') {
            $data['partner_id'] = null;
        }

        return $data;
    }

    private function applyVat(array $data): array
    {
        if (($data['vat_mode'] ?? 'none') === 'including' && $data['type'] === 'income') {
            $split = $this->invoices->splitInclusive((float) $data['amount']);
            $data['vat_amount'] = $split['vat_amount'];
        } else {
            $data['vat_mode'] = 'none';
            $data['vat_amount'] = 0;
        }

        return $data;
    }
}
