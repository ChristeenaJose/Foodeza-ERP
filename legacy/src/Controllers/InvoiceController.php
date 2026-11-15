<?php

declare(strict_types=1);

namespace Foodeza\Bookkeeping\Controllers;

use Foodeza\Bookkeeping\Models\Customer;
use Foodeza\Bookkeeping\Models\Invoice;
use Foodeza\Bookkeeping\Services\FileStorage;
use Foodeza\Bookkeeping\Services\InvoiceService;
use Foodeza\Bookkeeping\View;

final class InvoiceController
{
    public function index(): void
    {
        View::render('invoices/index', [
            'invoices' => Invoice::all(),
            'outstanding' => Invoice::outstanding(),
        ]);
    }

    public function create(): void
    {
        $config = require __DIR__ . '/../../config/app.php';

        View::render('invoices/create', [
            'customers' => Customer::all(),
            'services' => $config['services'],
            'suggestedNumber' => InvoiceService::peekNumber(),
            'vatRate' => $config['vat_rate'],
        ]);
    }

    public function store(): void
    {
        $config = require __DIR__ . '/../../config/app.php';
        $data = $this->validate($_POST, $config);

        if ($data === null) {
            View::redirect('/invoices/new');
        }

        if (!empty($data['customer_name'])) {
            $data['customer_id'] = Customer::findOrCreate($data['customer_name']);
        }

        $id = Invoice::create($data);

        if (!empty($_FILES['pdf']['name'])) {
            try {
                $upload = FileStorage::storeUpload($_FILES['pdf'], 'invoice');
                Invoice::attachPdf($id, $upload['id']);
            } catch (\RuntimeException $e) {
                View::flash('Invoice created but PDF upload failed: ' . $e->getMessage(), 'error');
                View::redirect('/invoices/show?id=' . $id);
            }
        }

        $html = $this->renderInvoiceHtml($id);
        InvoiceService::savePdfHtml($id, $html);

        View::flash('Invoice ' . ($data['invoice_number'] ?? '') . ' created.');
        View::redirect('/invoices/show?id=' . $id);
    }

    public function show(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $invoice = Invoice::find($id);

        if (!$invoice) {
            http_response_code(404);
            View::render('errors/404', ['path' => '/invoices/' . $id]);
            return;
        }

        View::render('invoices/show', ['invoice' => $invoice]);
    }

    public function pdf(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $invoice = Invoice::find($id);

        if (!$invoice) {
            http_response_code(404);
            exit('Invoice not found');
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderInvoiceHtml($id);
        exit;
    }

    public function markPaid(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            Invoice::updateStatus($id, 'paid');
            View::flash('Invoice marked as paid.');
        }
        View::redirect('/invoices/show?id=' . $id);
    }

    /** @param array<string, mixed> $input */
    private function validate(array $input, array $config): ?array
    {
        $subtotal = (float) ($input['subtotal'] ?? 0);
        $service = $input['service_type'] ?? '';
        $date = $input['date'] ?? '';

        if ($subtotal <= 0) {
            View::flash('Amount must be greater than zero.', 'error');
            return null;
        }

        if (!in_array($service, $config['services'], true)) {
            View::flash('Please select a service type.', 'error');
            return null;
        }

        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            View::flash('Please provide a valid date.', 'error');
            return null;
        }

        $customerId = (int) ($input['customer_id'] ?? 0);
        $customerName = trim($input['customer_name'] ?? '');

        if ($customerId <= 0 && $customerName === '') {
            View::flash('Customer is required.', 'error');
            return null;
        }

        return [
            'invoice_number' => trim($input['invoice_number'] ?? '') ?: InvoiceService::nextNumber(),
            'customer_id' => $customerId ?: null,
            'customer_name' => $customerName,
            'date' => $date,
            'due_date' => trim($input['due_date'] ?? '') ?: null,
            'service_type' => $service,
            'subtotal' => $subtotal,
            'vat_rate' => (float) ($input['vat_rate'] ?? $config['vat_rate']),
            'notes' => trim($input['notes'] ?? '') ?: null,
        ];
    }

    private function renderInvoiceHtml(int $id): string
    {
        $config = require __DIR__ . '/../../config/app.php';
        $invoice = Invoice::find($id);

        ob_start();
        require __DIR__ . '/../../views/invoices/pdf.php';

        return (string) ob_get_clean();
    }
}
