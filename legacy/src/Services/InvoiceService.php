<?php

declare(strict_types=1);

namespace Foodeza\Bookkeeping\Services;

use Foodeza\Bookkeeping\Database;

final class InvoiceService
{
    public static function peekNumber(): string
    {
        $config = require __DIR__ . '/../../config/app.php';
        $year = (int) date('Y');
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT last_number FROM invoice_sequence WHERE year = ?');
        $stmt->execute([$year]);
        $row = $stmt->fetch();
        $next = ($row ? (int) $row['last_number'] : 0) + 1;

        return sprintf('%s-%d-%04d', $config['invoice_prefix'], $year, $next);
    }

    public static function nextNumber(): string
    {
        $config = require __DIR__ . '/../../config/app.php';
        $year = (int) date('Y');
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT last_number FROM invoice_sequence WHERE year = ?');
            $stmt->execute([$year]);
            $row = $stmt->fetch();
            $next = ($row ? (int) $row['last_number'] : 0) + 1;

            if ($row) {
                $pdo->prepare('UPDATE invoice_sequence SET last_number = ? WHERE year = ?')
                    ->execute([$next, $year]);
            } else {
                $pdo->prepare('INSERT INTO invoice_sequence (year, last_number) VALUES (?, ?)')
                    ->execute([$year, $next]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return sprintf('%s-%d-%04d', $config['invoice_prefix'], $year, $next);
    }

    public static function calculateVat(float $subtotal, ?float $rate = null): array
    {
        $config = require __DIR__ . '/../../config/app.php';
        $rate = $rate ?? (float) $config['vat_rate'];
        $vat = round($subtotal * $rate, 2);

        return [
            'subtotal' => $subtotal,
            'vat_rate' => $rate,
            'vat_amount' => $vat,
            'total' => round($subtotal + $vat, 2),
        ];
    }

    public static function savePdfHtml(int $invoiceId, string $html): int
    {
        $config = require __DIR__ . '/../../config/app.php';
        $filename = 'invoice-' . $invoiceId . '-' . time() . '.html';
        $relative = 'invoices/' . $filename;
        $full = $config['storage'] . '/' . $relative;
        file_put_contents($full, $html);

        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO attachments (original_name, stored_name, mime_type, file_size, type)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $filename,
            $relative,
            'text/html',
            strlen($html),
            'invoice',
        ]);

        $attachmentId = (int) $pdo->lastInsertId();
        $pdo->prepare(
            'UPDATE invoices SET pdf_attachment_id = ? WHERE id = ? AND pdf_attachment_id IS NULL'
        )->execute([$attachmentId, $invoiceId]);

        return $attachmentId;
    }
}
