<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Income;
use App\Services\CsvImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankController extends Controller
{
    public function __construct(private CsvImportService $csv) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'transactions' => BankTransaction::orderByDesc('date')->get(),
            'unmatched' => BankTransaction::whereNull('matched_income_id')
                ->whereNull('matched_expense_id')->orderByDesc('date')->get(),
            'recent_income' => Income::with('customer')->orderByDesc('date')->limit(20)->get(),
            'recent_expenses' => Expense::with(['supplier', 'category'])->orderByDesc('date')->limit(20)->get(),
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['csv' => 'required|file|mimes:csv,txt|max:5120']);
        $path = $request->file('csv')->getRealPath();
        $rows = $this->csv->parse($path);
        $count = $this->csv->import($rows);

        return response()->json(['imported' => $count]);
    }

    public function match(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bank_id' => 'required|integer|exists:bank_transactions,id',
            'income_id' => 'nullable|integer|exists:incomes,id',
            'expense_id' => 'nullable|integer|exists:expenses,id',
        ]);

        $bank = BankTransaction::findOrFail($data['bank_id']);

        if (! empty($data['income_id'])) {
            $bank->update(['matched_income_id' => $data['income_id']]);
            Income::where('id', $data['income_id'])->update(['bank_transaction_id' => $bank->id]);
        } elseif (! empty($data['expense_id'])) {
            $bank->update(['matched_expense_id' => $data['expense_id']]);
            Expense::where('id', $data['expense_id'])->update(['bank_transaction_id' => $bank->id]);
        }

        return response()->json(['ok' => true]);
    }
}
