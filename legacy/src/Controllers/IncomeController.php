<?php

declare(strict_types=1);

namespace Foodeza\Bookkeeping\Controllers;

use Foodeza\Bookkeeping\Models\Customer;
use Foodeza\Bookkeeping\Models\Income;
use Foodeza\Bookkeeping\Models\Invoice;
use Foodeza\Bookkeeping\Services\InvoiceService;
use Foodeza\Bookkeeping\View;

final class IncomeController
{
    public function index(): void
    {
        View::render('income/index', [
            'incomes' => Income::all(),
        ]);
    }

    public function create(): void
    {
        $config = require __DIR__ . '/../../config/app.php';

        View::render('income/create', [
            'customers' => Customer::all(),
            'services' => $config['services'],
            'paymentMethods' => $config['payment_methods'],
            'invoices' => Invoice::all(),
            'suggestedInvoiceNumber' => InvoiceService::peekNumber(),
        ]);
    }

    public function store(): void
    {
        $config = require __DIR__ . '/../../config/app.php';
        $data = $this->validate($_POST, $config);

        if ($data === null) {
            View::redirect('/income/new');
        }

        if (!empty($data['customer_name'])) {
            $data['customer_id'] = Customer::findOrCreate($data['customer_name']);
        }

        $vat = InvoiceService::calculateVat((float) $data['amount']);
        $data['vat_amount'] = $vat['vat_amount'];

        Income::create($data);
        View::flash('Income recorded successfully.');
        View::redirect('/income');
    }

    public function destroy(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0 && Income::delete($id)) {
            View::flash('Income entry deleted.');
        } else {
            View::flash('Could not delete income entry.', 'error');
        }
        View::redirect('/income');
    }

  /** @param array<string, mixed> $input */
    private function validate(array $input, array $config): ?array
    {
        $service = $input['service_type'] ?? '';
        $amount = (float) ($input['amount'] ?? 0);
        $paymentMethod = $input['payment_method'] ?? '';
        $date = $input['date'] ?? '';

        if (!in_array($service, $config['services'], true)) {
            View::flash('Please select a valid service type.', 'error');
            return null;
        }

        if ($amount <= 0) {
            View::flash('Amount must be greater than zero.', 'error');
            return null;
        }

        if (!array_key_exists($paymentMethod, $config['payment_methods'])) {
            View::flash('Please select a payment method.', 'error');
            return null;
        }

        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            View::flash('Please provide a valid date.', 'error');
            return null;
        }

        return [
            'date' => $date,
            'customer_name' => trim($input['customer_name'] ?? ''),
            'customer_id' => (int) ($input['customer_id'] ?? 0) ?: null,
            'service_type' => $service,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'invoice_number' => trim($input['invoice_number'] ?? '') ?: null,
            'invoice_id' => (int) ($input['invoice_id'] ?? 0) ?: null,
            'notes' => trim($input['notes'] ?? '') ?: null,
        ];
    }
}
