<?php

declare(strict_types=1);

namespace Foodeza\Bookkeeping\Controllers;

use Foodeza\Bookkeeping\Models\Expense;
use Foodeza\Bookkeeping\Models\Income;
use Foodeza\Bookkeeping\Models\Invoice;
use Foodeza\Bookkeeping\Models\Report;
use Foodeza\Bookkeeping\View;

final class ReportController
{
    public function index(): void
    {
        View::redirect('/reports/profit-loss');
    }

    public function profitLoss(): void
    {
        $config = require __DIR__ . '/../../config/app.php';
        $month = $_GET['month'] ?? date('Y-m');
        $from = $_GET['from'] ?? ($month . '-01');
        $to = $_GET['to'] ?? date('Y-m-t', strtotime($from));

        View::render('reports/profit-loss', [
            'from' => $from,
            'to' => $to,
            'month' => $month,
            'profit' => Report::monthlyProfit($month),
            'serviceRevenue' => Income::byService($from, $to, $config['services']),
            'expenseByCategory' => Expense::byCategory($from, $to),
            'profitByMonth' => Report::profitByMonth(12),
            'revenueByMonth' => Income::byMonth(12),
        ]);
    }

    public function vat(): void
    {
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');

        View::render('reports/vat', [
            'from' => $from,
            'to' => $to,
            'report' => Report::vatReport($from, $to),
        ]);
    }

    public function outstanding(): void
    {
        View::render('reports/outstanding', [
            'invoices' => Invoice::outstanding(),
        ]);
    }

    public function attachments(): void
    {
        $pdo = \Foodeza\Bookkeeping\Database::connection();
        $receipts = $pdo->query(
            "SELECT a.*, e.date, e.amount, s.name as supplier_name
             FROM attachments a
             JOIN expenses e ON e.receipt_attachment_id = a.id
             LEFT JOIN suppliers s ON s.id = e.supplier_id
             WHERE a.type = 'receipt'
             ORDER BY a.created_at DESC"
        )->fetchAll();

        $invoiceFiles = $pdo->query(
            "SELECT a.*, i.invoice_number, i.date, i.total
             FROM attachments a
             JOIN invoices i ON i.pdf_attachment_id = a.id
             WHERE a.type = 'invoice'
             ORDER BY a.created_at DESC"
        )->fetchAll();

        View::render('reports/attachments', [
            'receipts' => $receipts,
            'invoiceFiles' => $invoiceFiles,
        ]);
    }

    public function download(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $pdo = \Foodeza\Bookkeeping\Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM attachments WHERE id = ?');
        $stmt->execute([$id]);
        $file = $stmt->fetch();

        if (!$file) {
            http_response_code(404);
            exit('File not found');
        }

        $path = \Foodeza\Bookkeeping\Services\FileStorage::fullPath($file['stored_name']);
        if (!file_exists($path)) {
            http_response_code(404);
            exit('File not found on disk');
        }

        header('Content-Type: ' . ($file['mime_type'] ?? 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . $file['original_name'] . '"');
        readfile($path);
        exit;
    }
}
