<?php

declare(strict_types=1);

namespace Foodeza\Bookkeeping\Controllers;

use Foodeza\Bookkeeping\Models\Category;
use Foodeza\Bookkeeping\Models\Expense;
use Foodeza\Bookkeeping\Models\Supplier;
use Foodeza\Bookkeeping\Services\FileStorage;
use Foodeza\Bookkeeping\Services\InvoiceService;
use Foodeza\Bookkeeping\View;

final class ExpenseController
{
    public function index(): void
    {
        View::render('expenses/index', [
            'expenses' => Expense::all(),
        ]);
    }

    public function create(): void
    {
        View::render('expenses/create', [
            'suppliers' => Supplier::all(),
            'categories' => Category::expenseCategories(),
        ]);
    }

    public function store(): void
    {
        $data = $this->validate($_POST);

        if ($data === null) {
            View::redirect('/expenses/new');
        }

        if (!empty($data['supplier_name'])) {
            $data['supplier_id'] = Supplier::findOrCreate($data['supplier_name']);
        }

        if (!empty($_FILES['receipt']['name'])) {
            try {
                $upload = FileStorage::storeUpload($_FILES['receipt'], 'receipt');
                $data['receipt_attachment_id'] = $upload['id'];
            } catch (\RuntimeException $e) {
                View::flash($e->getMessage(), 'error');
                View::redirect('/expenses/new');
            }
        }

        $vat = InvoiceService::calculateVat((float) $data['amount']);
        $data['vat_amount'] = $vat['vat_amount'];

        Expense::create($data);
        View::flash('Expense recorded successfully.');
        View::redirect('/expenses');
    }

    public function destroy(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0 && Expense::delete($id)) {
            View::flash('Expense deleted.');
        } else {
            View::flash('Could not delete expense.', 'error');
        }
        View::redirect('/expenses');
    }

    /** @param array<string, mixed> $input */
    private function validate(array $input): ?array
    {
        $categoryId = (int) ($input['category_id'] ?? 0);
        $amount = (float) ($input['amount'] ?? 0);
        $date = $input['date'] ?? '';

        if ($categoryId <= 0 || Category::find($categoryId) === null) {
            View::flash('Please select a category.', 'error');
            return null;
        }

        if ($amount <= 0) {
            View::flash('Amount must be greater than zero.', 'error');
            return null;
        }

        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            View::flash('Please provide a valid date.', 'error');
            return null;
        }

        return [
            'date' => $date,
            'supplier_name' => trim($input['supplier_name'] ?? ''),
            'supplier_id' => (int) ($input['supplier_id'] ?? 0) ?: null,
            'category_id' => $categoryId,
            'amount' => $amount,
            'description' => trim($input['description'] ?? '') ?: null,
        ];
    }
}
