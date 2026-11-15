<?php

declare(strict_types=1);

namespace Foodeza\Bookkeeping\Controllers;

use Foodeza\Bookkeeping\Models\Account;
use Foodeza\Bookkeeping\Models\Category;
use Foodeza\Bookkeeping\Models\Transaction;
use Foodeza\Bookkeeping\View;

final class TransactionController
{
    public function index(): void
    {
        $type = $_GET['type'] ?? null;
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;

        View::render('transactions/index', [
            'transactions' => Transaction::filter($type, $from, $to),
            'filters' => compact('type', 'from', 'to'),
        ]);
    }

    public function create(): void
    {
        View::render('transactions/create', [
            'accounts' => Account::all(),
            'incomeCategories' => Category::byType('income'),
            'expenseCategories' => Category::byType('expense'),
        ]);
    }

    public function store(): void
    {
        $data = $this->validate($_POST);

        if ($data === null) {
            View::redirect('/transactions/new');
        }

        Transaction::create($data);
        View::flash('Transaction recorded successfully.');
        View::redirect('/transactions');
    }

    public function destroy(): void
    {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0 && Transaction::delete($id)) {
            View::flash('Transaction deleted.');
        } else {
            View::flash('Could not delete transaction.', 'error');
        }

        View::redirect('/transactions');
    }

    /** @param array<string, mixed> $input */
    private function validate(array $input): ?array
    {
        $type = $input['type'] ?? '';
        $amount = (float) ($input['amount'] ?? 0);
        $accountId = (int) ($input['account_id'] ?? 0);
        $description = trim($input['description'] ?? '');
        $date = $input['date'] ?? '';

        if (!in_array($type, ['income', 'expense'], true)) {
            View::flash('Invalid transaction type.', 'error');
            return null;
        }

        if ($amount <= 0) {
            View::flash('Amount must be greater than zero.', 'error');
            return null;
        }

        if ($accountId <= 0 || Account::find($accountId) === null) {
            View::flash('Please select a valid account.', 'error');
            return null;
        }

        if ($description === '') {
            View::flash('Description is required.', 'error');
            return null;
        }

        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            View::flash('Please provide a valid date.', 'error');
            return null;
        }

        $categoryId = (int) ($input['category_id'] ?? 0);
        if ($type === 'income' && $categoryId <= 0) {
            View::flash('Please select a service.', 'error');
            return null;
        }

        return [
            'type' => $type,
            'amount' => $amount,
            'account_id' => $accountId,
            'category_id' => $categoryId,
            'description' => $description,
            'date' => $date,
            'reference' => trim($input['reference'] ?? '') ?: null,
            'notes' => trim($input['notes'] ?? '') ?: null,
        ];
    }
}
