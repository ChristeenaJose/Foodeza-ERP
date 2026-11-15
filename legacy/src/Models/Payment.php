<?php

declare(strict_types=1);

namespace Foodeza\Bookkeeping\Models;

use Foodeza\Bookkeeping\Database;
use PDO;

final class Payment
{
    public static function all(int $limit = 100): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.*, i.invoice_number, c.name as customer_name
             FROM payments p
             LEFT JOIN invoices i ON i.id = p.invoice_id
             LEFT JOIN customers c ON c.id = i.customer_id
             ORDER BY p.date DESC, p.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $pdo->prepare(
                'INSERT INTO payments (date, amount, payment_method, invoice_id, income_id, reference, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $data['date'],
                $data['amount'],
                $data['payment_method'],
                $data['invoice_id'] ?? null,
                $data['income_id'] ?? null,
                $data['reference'] ?? null,
                $data['notes'] ?? null,
            ]);

            $id = (int) $pdo->lastInsertId();

            if (!empty($data['invoice_id'])) {
                self::syncInvoiceStatus((int) $data['invoice_id']);
            }

            $pdo->commit();

            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function syncInvoiceStatus(int $invoiceId): void
    {
        $pdo = Database::connection();
        $invoice = Invoice::find($invoiceId);
        if (!$invoice) {
            return;
        }

        $stmt = $pdo->prepare('SELECT SUM(amount) as paid FROM payments WHERE invoice_id = ?');
        $stmt->execute([$invoiceId]);
        $paid = (float) ($stmt->fetch()['paid'] ?? 0);
        $total = (float) $invoice['total'];

        $status = 'unpaid';
        if ($paid >= $total) {
            $status = 'paid';
        } elseif ($paid > 0) {
            $status = 'partial';
        }

        Invoice::updateStatus($invoiceId, $status);
    }

    public static function totalsByMethod(?string $from = null, ?string $to = null): array
    {
        $sql = 'SELECT payment_method, SUM(amount) as total, COUNT(*) as count FROM payments WHERE 1=1';
        $params = [];

        if ($from) {
            $sql .= ' AND date >= ?';
            $params[] = $from;
        }
        if ($to) {
            $sql .= ' AND date <= ?';
            $params[] = $to;
        }

        $sql .= ' GROUP BY payment_method ORDER BY total DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
