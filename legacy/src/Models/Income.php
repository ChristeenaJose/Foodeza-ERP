<?php

declare(strict_types=1);

namespace Foodeza\Bookkeeping\Models;

use Foodeza\Bookkeeping\Database;
use PDO;

final class Income
{
    public static function all(int $limit = 100): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT i.*, c.name as customer_name
             FROM incomes i
             LEFT JOIN customers c ON c.id = i.customer_id
             ORDER BY i.date DESC, i.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT i.*, c.name as customer_name
             FROM incomes i
             LEFT JOIN customers c ON c.id = i.customer_id
             WHERE i.id = ?'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO incomes (date, customer_id, service_type, amount, vat_amount, payment_method, invoice_id, invoice_number, notes, bank_transaction_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $data['date'],
            $data['customer_id'] ?? null,
            $data['service_type'],
            $data['amount'],
            $data['vat_amount'] ?? 0,
            $data['payment_method'],
            $data['invoice_id'] ?? null,
            $data['invoice_number'] ?? null,
            $data['notes'] ?? null,
            $data['bank_transaction_id'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function delete(int $id): bool
    {
        $stmt = Database::connection()->prepare('DELETE FROM incomes WHERE id = ?');

        return $stmt->execute([$id]);
    }

    /** @param list<string> $services */
    public static function byService(string $from, string $to, array $services): array
    {
        $byService = self::groupedByService($from, $to);
        $result = [];

        foreach ($services as $service) {
            $result[] = [
                'service' => $service,
                'total' => $byService[$service]['total'] ?? 0.0,
                'vat' => $byService[$service]['vat'] ?? 0.0,
                'count' => $byService[$service]['count'] ?? 0,
            ];
        }

        return $result;
    }

    /** @return array<string, array{total: float, vat: float, count: int}> */
    private static function groupedByService(string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT service_type, SUM(amount) as total, SUM(vat_amount) as vat, COUNT(*) as count
             FROM incomes WHERE date >= ? AND date <= ?
             GROUP BY service_type"
        );
        $stmt->execute([$from, $to]);
        $rows = $stmt->fetchAll();
        $map = [];

        foreach ($rows as $row) {
            $map[$row['service_type']] = [
                'total' => (float) $row['total'],
                'vat' => (float) $row['vat'],
                'count' => (int) $row['count'],
            ];
        }

        return $map;
    }

    /** @return list<array{month: string, total: float}> */
    public static function byMonth(int $months = 12): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT strftime('%Y-%m', date) as month, SUM(amount) as total
             FROM incomes
             WHERE date >= date('now', ?)
             GROUP BY month
             ORDER BY month"
        );
        $stmt->execute(['-' . $months . ' months']);

        return $stmt->fetchAll();
    }

    public static function totals(?string $from = null, ?string $to = null): array
    {
        $sql = 'SELECT SUM(amount) as revenue, SUM(vat_amount) as vat_collected, COUNT(*) as count FROM incomes WHERE 1=1';
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
            'revenue' => (float) ($row['revenue'] ?? 0),
            'vat_collected' => (float) ($row['vat_collected'] ?? 0),
            'count' => (int) ($row['count'] ?? 0),
        ];
    }
}
