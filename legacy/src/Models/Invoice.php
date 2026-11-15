<?php

declare(strict_types=1);

namespace Foodeza\Bookkeeping\Models;

use Foodeza\Bookkeeping\Database;
use Foodeza\Bookkeeping\Services\InvoiceService;

final class Invoice
{
    public static function all(): array
    {
        return Database::connection()->query(
            'SELECT i.*, c.name as customer_name, a.stored_name as pdf_path
             FROM invoices i
             JOIN customers c ON c.id = i.customer_id
             LEFT JOIN attachments a ON a.id = i.pdf_attachment_id
             ORDER BY i.date DESC, i.id DESC'
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT i.*, c.name as customer_name, c.email as customer_email,
                    c.phone as customer_phone, c.address as customer_address,
                    a.stored_name as pdf_path
             FROM invoices i
             JOIN customers c ON c.id = i.customer_id
             LEFT JOIN attachments a ON a.id = i.pdf_attachment_id
             WHERE i.id = ?'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $number = $data['invoice_number'] ?? InvoiceService::nextNumber();
        $amounts = InvoiceService::calculateVat(
            (float) $data['subtotal'],
            isset($data['vat_rate']) ? (float) $data['vat_rate'] : null
        );

        $pdo->prepare(
            'INSERT INTO invoices (invoice_number, customer_id, date, due_date, service_type, subtotal, vat_rate, vat_amount, total, status, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $number,
            $data['customer_id'],
            $data['date'],
            $data['due_date'] ?? null,
            $data['service_type'],
            $amounts['subtotal'],
            $amounts['vat_rate'],
            $amounts['vat_amount'],
            $amounts['total'],
            $data['status'] ?? 'unpaid',
            $data['notes'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function updateStatus(int $id, string $status): void
    {
        Database::connection()->prepare('UPDATE invoices SET status = ? WHERE id = ?')
            ->execute([$status, $id]);
    }

    public static function outstanding(): array
    {
        return Database::connection()->query(
            "SELECT i.*, c.name as customer_name
             FROM invoices i
             JOIN customers c ON c.id = i.customer_id
             WHERE i.status IN ('unpaid', 'partial')
             ORDER BY i.due_date ASC, i.date ASC"
        )->fetchAll();
    }

    public static function attachPdf(int $id, int $attachmentId): void
    {
        Database::connection()->prepare('UPDATE invoices SET pdf_attachment_id = ? WHERE id = ?')
            ->execute([$attachmentId, $id]);
    }
}
