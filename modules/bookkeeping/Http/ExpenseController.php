<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Expense;
use App\Services\ContactService;
use App\Services\FileStorageService;
use App\Services\InvoiceService;
use App\Support\RecordDates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    public function __construct(
        private ContactService $contacts,
        private InvoiceService $invoices,
        private FileStorageService $files,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $from = $request->get('from');
        $to = $request->get('to');
        $categoryId = $request->get('category_id');

        $query = Expense::with(['supplier', 'category', 'receiptAttachment'])->orderByDesc('date');
        if ($from) {
            $query->where('date', '>=', $from);
        }
        if ($to) {
            $query->where('date', '<=', $to);
        }
        if ($categoryId) {
            $query->where('category_id', (int) $categoryId);
        }

        return response()->json($query->get());
    }

    public function show(Expense $expense): JsonResponse
    {
        return response()->json($expense->load(['supplier', 'category', 'receiptAttachment']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => RecordDates::dateRules(),
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'supplier_name' => 'nullable|string|max:255',
            'category_id' => 'required|integer|exists:categories,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
            'proof_type' => 'nullable|string|in:'.implode(',', array_keys(config('foodeza.expense_proof_types', []))),
            'receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $vat = $this->invoices->calculateVat((float) $data['amount']);
        $data['supplier_id'] = $this->contacts->findOrCreateSupplier(
            $data['supplier_name'] ?? null,
            $data['supplier_id'] ?? null
        );
        $data['vat_amount'] = $vat['vat_amount'];
        $data['proof_type'] = $this->resolveProofType($request);
        unset($data['supplier_name']);

        if ($request->hasFile('receipt')) {
            $data['receipt_attachment_id'] = $this->files->store($request->file('receipt'), 'receipt')->id;
            $data['proof_type'] = 'uploaded';
        }
        unset($data['receipt']);

        $expense = Expense::create($data);

        return response()->json($expense->load(['supplier', 'category', 'receiptAttachment']), 201);
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        $data = $request->validate([
            'date' => RecordDates::dateRules(),
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'supplier_name' => 'nullable|string|max:255',
            'category_id' => 'required|integer|exists:categories,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
            'proof_type' => 'nullable|string|in:'.implode(',', array_keys(config('foodeza.expense_proof_types', []))),
            'receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $vat = $this->invoices->calculateVat((float) $data['amount']);
        $data['supplier_id'] = $this->contacts->findOrCreateSupplier(
            $data['supplier_name'] ?? null,
            $data['supplier_id'] ?? null
        );
        $data['vat_amount'] = $vat['vat_amount'];
        $data['proof_type'] = $this->resolveProofType($request);
        unset($data['supplier_name']);

        if ($request->hasFile('receipt')) {
            $expense->load('receiptAttachment');
            $old = $expense->receiptAttachment;
            $data['receipt_attachment_id'] = $this->files->store($request->file('receipt'), 'receipt')->id;
            $data['proof_type'] = 'uploaded';
            if ($old && Storage::disk('local')->exists($old->stored_path)) {
                Storage::disk('local')->delete($old->stored_path);
            }
            $old?->delete();
        }
        unset($data['receipt']);

        $expense->update($data);

        return response()->json($expense->fresh(['supplier', 'category', 'receiptAttachment']));
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $expense->delete();

        return response()->json(['ok' => true]);
    }

    public function categories(): JsonResponse
    {
        $fixedNames = config('foodeza.expense_groups.fixed_asset.categories', ['Fixed assets']);
        $startupNames = config('foodeza.expense_groups.startup.categories', ['Startup costs']);

        $categories = Category::where('type', 'expense')->orderBy('name')->get()->map(function ($cat) use ($fixedNames, $startupNames) {
            $group = 'operating';
            if (in_array($cat->name, $fixedNames, true)) {
                $group = 'fixed_asset';
            } elseif (in_array($cat->name, $startupNames, true)) {
                $group = 'startup';
            }

            return array_merge($cat->toArray(), ['group' => $group]);
        });

        $groups = collect(config('foodeza.expense_groups', []))->map(function ($meta, $key) {
            return [
                'key' => $key,
                'label' => $meta['label'] ?? $key,
                'description' => $meta['description'] ?? null,
            ];
        })->values();

        return response()->json([
            'groups' => $groups,
            'categories' => $categories,
        ]);
    }

    private function resolveProofType(Request $request): string
    {
        if ($request->hasFile('receipt')) {
            return 'uploaded';
        }

        $allowed = array_keys(config('foodeza.expense_proof_types', []));
        $type = (string) $request->input('proof_type', 'none');

        return in_array($type, $allowed, true) ? $type : 'none';
    }
}
