<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\FixedExpense;
use App\Services\ContactService;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FixedExpenseController extends Controller
{
    public function __construct(
        private ContactService $contacts,
        private InvoiceService $invoices,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = FixedExpense::with('category')->orderBy('section')->orderBy('name');
        if ($request->filled('section')) {
            $query->where('section', $request->get('section'));
        }
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        $items = $query->get();
        $month = $request->get('month');

        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $from = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $to = $from->copy()->endOfMonth();

            $items->each(function (FixedExpense $fixed) use ($from, $to) {
                $fixed->setAttribute(
                    'imported_this_month',
                    Expense::where('category_id', $fixed->category_id)
                        ->whereDate('date', '>=', $from->toDateString())
                        ->whereDate('date', '<=', $to->toDateString())
                        ->where('description', 'like', '%'.$fixed->name.'%')
                        ->exists()
                );
            });
        }

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $item = FixedExpense::create($data);

        return response()->json($item->load('category'), 201);
    }

    public function update(Request $request, FixedExpense $fixedExpense): JsonResponse
    {
        $fixedExpense->update($this->validated($request));

        return response()->json($fixedExpense->fresh()->load('category'));
    }

    public function destroy(FixedExpense $fixedExpense): JsonResponse
    {
        $fixedExpense->update(['active' => false]);

        return response()->json(['ok' => true]);
    }

    /**
     * Import one or more fixed expenses into the expenses ledger for a month.
     * Amount on the template is treated as gross (paid); stored expense amount is net.
     */
    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:fixed_expenses,id',
            'month' => 'required|date_format:Y-m',
        ]);

        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $imported = [];
        $skipped = [];

        foreach (FixedExpense::with('category')->whereIn('id', $data['ids'])->where('active', true)->get() as $fixed) {
            $day = min(max((int) $fixed->day_of_month, 1), $month->daysInMonth);
            $date = $month->copy()->day($day)->toDateString();

            $exists = Expense::where('category_id', $fixed->category_id)
                ->whereDate('date', '>=', $month->toDateString())
                ->whereDate('date', '<=', $month->copy()->endOfMonth()->toDateString())
                ->where('description', 'like', '%'.$fixed->name.'%')
                ->exists();

            if ($exists) {
                $skipped[] = ['id' => $fixed->id, 'name' => $fixed->name, 'reason' => 'already_imported'];
                continue;
            }

            $gross = (float) $fixed->amount;
            $split = $this->invoices->splitInclusive($gross);
            $supplierId = $this->contacts->findOrCreateSupplier($fixed->supplier_name);

            $expense = Expense::create([
                'date' => $date,
                'supplier_id' => $supplierId,
                'category_id' => $fixed->category_id,
                'amount' => $split['net'],
                'vat_amount' => $split['vat_amount'],
                'description' => $fixed->name.($fixed->notes ? ' — '.$fixed->notes : ''),
                'proof_type' => $fixed->proof_type ?: 'bank_statement',
            ]);

            $imported[] = $expense->load(['supplier', 'category']);
        }

        return response()->json([
            'imported' => $imported,
            'skipped' => $skipped,
            'count' => count($imported),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|integer|exists:categories,id',
            'amount' => 'required|numeric|min:0.01',
            'supplier_name' => 'nullable|string|max:255',
            'section' => 'required|in:insurance,rent,other',
            'day_of_month' => 'sometimes|integer|min:1|max:28',
            'proof_type' => 'nullable|string|in:'.implode(',', array_keys(config('foodeza.expense_proof_types', []))),
            'notes' => 'nullable|string',
            'active' => 'sometimes|boolean',
        ]);
    }
}
