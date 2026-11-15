<?php

declare(strict_types=1);

namespace Foodeza\Bookkeeping\Models;

use Foodeza\Bookkeeping\Database;

final class Supplier
{
    public static function all(): array
    {
        return Database::connection()->query(
            'SELECT * FROM suppliers ORDER BY name'
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM suppliers WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO suppliers (name, email, phone) VALUES (?, ?, ?)'
        )->execute([
            $data['name'],
            $data['email'] ?? null,
            $data['phone'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function findOrCreate(string $name): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM suppliers WHERE name = ?');
        $stmt->execute([$name]);
        $row = $stmt->fetch();

        if ($row) {
            return (int) $row['id'];
        }

        return self::create(['name' => $name]);
    }
}
