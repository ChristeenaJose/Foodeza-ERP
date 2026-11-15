<?php

declare(strict_types=1);

namespace Foodeza\Bookkeeping\Controllers;

use Foodeza\Bookkeeping\Models\Income;
use Foodeza\Bookkeeping\Models\Invoice;
use Foodeza\Bookkeeping\Models\Payment;
use Foodeza\Bookkeeping\View;

final class PaymentController
{
    public function index(): void
    {
        $config = require __DIR__ . '/../../config/app.php';
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');

        View::render('payments/index', [
            'payments' => Payment::all(),
            'totalsByMethod' => Payment::totalsByMethod($from, $to),
            'paymentMethods' => $config['payment_methods'],
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function create(): void
    {
        $config = require __DIR__ . '/../../config/app.php';

        View::render('payments/create', [
            'invoices' => Invoice::outstanding(),
            'incomes' => Income::all(50),
            'paymentMethods' => $config['payment_methods'],
        ]);
    }

    public function store(): void
    {
        $config = require __DIR__ . '/../../config/app.php';
        $data = $this->validate($_POST, $config);

        if ($data === null) {
            View::redirect('/payments/new');
        }

        Payment::create($data);
        View::flash('Payment recorded.');
        View::redirect('/payments');
    }

    /** @param array<string, mixed> $input */
    private function validate(array $input, array $config): ?array
    {
        $amount = (float) ($input['amount'] ?? 0);
        $method = $input['payment_method'] ?? '';
        $date = $input['date'] ?? '';

        if ($amount <= 0) {
            View::flash('Amount must be greater than zero.', 'error');
            return null;
        }

        if (!array_key_exists($method, $config['payment_methods'])) {
            View::flash('Please select a payment method.', 'error');
            return null;
        }

        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            View::flash('Please provide a valid date.', 'error');
            return null;
        }

        return [
            'date' => $date,
            'amount' => $amount,
            'payment_method' => $method,
            'invoice_id' => (int) ($input['invoice_id'] ?? 0) ?: null,
            'income_id' => (int) ($input['income_id'] ?? 0) ?: null,
            'reference' => trim($input['reference'] ?? '') ?: null,
            'notes' => trim($input['notes'] ?? '') ?: null,
        ];
    }
}
