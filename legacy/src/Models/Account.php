<?php

declare(strict_types=1);

namespace Foodeza\Bookkeeping\Models;

use Foodeza\Bookkeeping\Database;
use PDO;

final class Account
{
    public static function all(): array
    {
        $pdo = Database::connection();

        return $pdo->query(
            'SELECT * FROM accounts WHERE is_active = 1 ORDER BY type, name'
        )->fetchAll();
    }

    public static function byType(string $type): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT * FROM accounts WHERE type = ? AND is_active = 1 ORDER BY name'
        );
        $stmt->execute([$type]);

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM accounts WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO accounts (name, type, description) VALUES (?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['type'],
            $data['description'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function updateBalance(int $id, float $delta): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE accounts SET balance = balance + ? WHERE id = ?'
        );
        $stmt->execute([$delta, $id]);
    }

    /** @return array<string, float> */
    public static function totalsByType(): array
    {
        $pdo = Database::connection();
        $rows = $pdo->query(
            'SELECT type, SUM(balance) as total FROM accounts WHERE is_active = 1 GROUP BY type'
        )->fetchAll();

        $totals = ['asset' => 0.0, 'liability' => 0.0, 'income' => 0.0, 'expense' => 0.0];
        foreach ($rows as $row) {
            $totals[$row['type']] = (float) $row['total'];
        }

        return $totals;
    }
}
