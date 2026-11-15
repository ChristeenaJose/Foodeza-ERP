<?php

declare(strict_types=1);

namespace Foodeza\Bookkeeping\Models;

use Foodeza\Bookkeeping\Database;
use PDO;

final class Expense
{
    public static function all(int $limit = 100): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT e.*, s.name as supplier_name, c.name as category_name,
                    a.original_name as receipt_name, a.stored_name as receipt_path
             FROM expenses e
             LEFT JOIN suppliers s ON s.id = e.supplier_id
             JOIN categories c ON c.id = e.category_id
             LEFT JOIN attachments a ON a.id = e.receipt_attachment_id
             ORDER BY e.date DESC, e.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT e.*, s.name as supplier_name, c.name as category_name
             FROM expenses e
             LEFT JOIN suppliers s ON s.id = e.supplier_id
             JOIN categories c ON c.id = e.category_id
             WHERE e.id = ?'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO expenses (date, supplier_id, category_id, amount, vat_amount, description, receipt_attachment_id, bank_transaction_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $data['date'],
            $data['supplier_id'] ?? null,
            $data['category_id'],
            $data['amount'],
            $data['vat_amount'] ?? 0,
            $data['description'] ?? null,
            $data['receipt_attachment_id'] ?? null,
            $data['bank_transaction_id'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function delete(int $id): bool
    {
        return Database::connection()->prepare('DELETE FROM expenses WHERE id = ?')->execute([$id]);
    }

    public static function byCategory(?string $from = null, ?string $to = null): array
    {
        $sql = 'SELECT c.name as category_name, SUM(e.amount) as total, SUM(e.vat_amount) as vat, COUNT(e.id) as count
                FROM expenses e
                JOIN categories c ON c.id = e.category_id
                WHERE 1=1';
        $params = [];

        if ($from) {
            $sql .= ' AND e.date >= ?';
            $params[] = $from;
        }
        if ($to) {
            $sql .= ' AND e.date <= ?';
            $params[] = $to;
        }

        $sql .= ' GROUP BY c.id ORDER BY total DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @return list<array{month: string, total: float}> */
    public static function byMonth(int $months = 12): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT strftime('%Y-%m', date) as month, SUM(amount) as total
             FROM expenses
             WHERE date >= date('now', ?)
             GROUP BY month
             ORDER BY month"
        );
        $stmt->execute(['-' . $months . ' months']);

        return $stmt->fetchAll();
    }

    public static function totals(?string $from = null, ?string $to = null): array
    {
        $sql = 'SELECT SUM(amount) as expenses, SUM(vat_amount) as vat_paid, COUNT(*) as count FROM expenses WHERE 1=1';
        $params = [];

        if ($from) {
            $sql .= ' AND date >= ?';
            $params[] = $from;
        }
        if ($to) {
            $sql .= ' AND date <= ?';
            $params[] = $to;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return [
            'expenses' => (float) ($row['expenses'] ?? 0),
            'vat_paid' => (float) ($row['vat_paid'] ?? 0),
            'count' => (int) ($row['count'] ?? 0),
        ];
    }
}
