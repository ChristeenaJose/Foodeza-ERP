<?php

declare(strict_types=1);

namespace Foodeza\Bookkeeping\Controllers;

use Foodeza\Bookkeeping\Models\BankTransaction;
use Foodeza\Bookkeeping\Models\Expense;
use Foodeza\Bookkeeping\Models\Income;
use Foodeza\Bookkeeping\Services\CsvImporter;
use Foodeza\Bookkeeping\View;

final class BankController
{
    public function index(): void
    {
        View::render('bank/index', [
            'transactions' => BankTransaction::all(),
            'unmatched' => BankTransaction::all(true),
            'recentIncome' => Income::all(20),
            'recentExpenses' => Expense::all(20),
        ]);
    }

    public function import(): void
    {
        if (empty($_FILES['csv']['tmp_name'])) {
            View::flash('Please select a CSV file.', 'error');
            View::redirect('/bank');
        }

        try {
            $rows = CsvImporter::parse($_FILES['csv']['tmp_name']);
            $count = CsvImporter::import($rows);
            View::flash($count . ' bank transactions imported.');
        } catch (\RuntimeException $e) {
            View::flash($e->getMessage(), 'error');
        }

        View::redirect('/bank');
    }

    public function match(): void
    {
        $bankId = (int) ($_POST['bank_id'] ?? 0);
        $incomeId = (int) ($_POST['income_id'] ?? 0);
        $expenseId = (int) ($_POST['expense_id'] ?? 0);

        if ($bankId <= 0) {
            View::flash('Invalid bank transaction.', 'error');
            View::redirect('/bank');
        }

        if ($incomeId > 0) {
            BankTransaction::matchIncome($bankId, $incomeId);
            View::flash('Matched to income.');
        } elseif ($expenseId > 0) {
            BankTransaction::matchExpense($bankId, $expenseId);
            View::flash('Matched to expense.');
        } else {
            View::flash('Select an income or expense to match.', 'error');
        }

        View::redirect('/bank');
    }
}
