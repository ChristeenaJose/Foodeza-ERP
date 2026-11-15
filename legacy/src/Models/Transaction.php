<?php

declare(strict_types=1);

namespace Foodeza\Bookkeeping\Models;

use Foodeza\Bookkeeping\Database;
use PDO;

final class Transaction
{
    public static function all(int $limit = 50): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT t.*, a.name as account_name, c.name as category_name
             FROM transactions t
             JOIN accounts a ON a.id = t.account_id
             LEFT JOIN categories c ON c.id = t.category_id
             ORDER BY t.date DESC, t.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT t.*, a.name as account_name, c.name as category_name
             FROM transactions t
             JOIN accounts a ON a.id = t.account_id
             LEFT JOIN categories c ON c.id = t.category_id
             WHERE t.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO transactions (date, description, type, amount, account_id, category_id, reference, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $data['date'],
                $data['description'],
                $data['type'],
                $data['amount'],
                $data['account_id'],
                $data['category_id'] ?: null,
                $data['reference'] ?? null,
                $data['notes'] ?? null,
            ]);

            $id = (int) $pdo->lastInsertId();
            $delta = self::balanceDelta($data['type'], (float) $data['amount']);
            Account::updateBalance((int) $data['account_id'], $delta);

            $pdo->commit();

            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function delete(int $id): bool
    {
        $pdo = Database::connection();
        $transaction = self::find($id);

        if ($transaction === null) {
            return false;
        }

        $pdo->beginTransaction();

        try {
            $delta = -self::balanceDelta($transaction['type'], (float) $transaction['amount']);
            Account::updateBalance((int) $transaction['account_id'], $delta);

            $stmt = $pdo->prepare('DELETE FROM transactions WHERE id = ?');
            $stmt->execute([$id]);

            $pdo->commit();

            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function balanceDelta(string $type, float $amount): float
    {
        return match ($type) {
            'income' => $amount,
            'expense' => -$amount,
            default => 0.0,
        };
    }

    /** @return array{income: float, expense: float, net: float} */
    public static function monthlySummary(?string $month = null): array
    {
        $pdo = Database::connection();
        $month = $month ?? date('Y-m');

        $stmt = $pdo->prepare(
            "SELECT type, SUM(amount) as total
             FROM transactions
             WHERE strftime('%Y-%m', date) = ?
             GROUP BY type"
        );
        $stmt->execute([$month]);
        $rows = $stmt->fetchAll();

        $income = 0.0;
        $expense = 0.0;
        foreach ($rows as $row) {
            if ($row['type'] === 'income') {
                $income = (float) $row['total'];
            } elseif ($row['type'] === 'expense') {
                $expense = (float) $row['total'];
            }
        }

        return [
            'income' => $income,
            'expense' => $expense,
            'net' => $income - $expense,
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function byCategory(string $type, ?string $from = null, ?string $to = null): array
    {
        $pdo = Database::connection();
        $sql = "SELECT c.name as category_name, SUM(t.amount) as total, COUNT(t.id) as count
                FROM transactions t
                LEFT JOIN categories c ON c.id = t.category_id
                WHERE t.type = ?";
        $params = [$type];

        if ($from) {
            $sql .= ' AND t.date >= ?';
            $params[] = $from;
        }
        if ($to) {
            $sql .= ' AND t.date <= ?';
            $params[] = $to;
        }

        $sql .= ' GROUP BY c.id ORDER BY total DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @param list<string> $services
     * @return list<array{service: string, total: float, count: int}>
     */
    public static function serviceRevenue(string $from, string $to, array $services): array
    {
        $byCategory = self::byCategory('income', $from, $to);
        $totals = [];
        foreach ($byCategory as $row) {
            $name = $row['category_name'] ?? 'Uncategorized';
            $totals[$name] = [
                'total' => (float) $row['total'],
                'count' => (int) $row['count'],
            ];
        }

        $result = [];
        foreach ($services as $service) {
            $result[] = [
                'service' => $service,
                'total' => $totals[$service]['total'] ?? 0.0,
                'count' => $totals[$service]['count'] ?? 0,
            ];
        }

        return $result;
    }

    /** @return list<array<string, mixed>> */
    public static function filter(?string $type = null, ?string $from = null, ?string $to = null, int $limit = 100): array
    {
        $pdo = Database::connection();
        $sql = 'SELECT t.*, a.name as account_name, c.name as category_name
                FROM transactions t
                JOIN accounts a ON a.id = t.account_id
                LEFT JOIN categories c ON c.id = t.category_id
                WHERE 1=1';
        $params = [];

        if ($type) {
            $sql .= ' AND t.type = ?';
            $params[] = $type;
        }
        if ($from) {
            $sql .= ' AND t.date >= ?';
            $params[] = $from;
        }
        if ($to) {
            $sql .= ' AND t.date <= ?';
            $params[] = $to;
        }

        $sql .= ' ORDER BY t.date DESC, t.id DESC LIMIT ?';
        $params[] = $limit;

        $stmt = $pdo->prepare($sql);
        foreach ($params as $i => $param) {
            $stmt->bindValue($i + 1, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
