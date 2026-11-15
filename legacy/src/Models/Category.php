<?php

declare(strict_types=1);

namespace Foodeza\Bookkeeping\Models;

use Foodeza\Bookkeeping\Database;

final class Category
{
    public static function expenseCategories(): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM categories WHERE type = ? ORDER BY name'
        );
        $stmt->execute(['expense']);

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM categories WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function findByName(string $name, string $type): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM categories WHERE name = ? AND type = ?'
        );
        $stmt->execute([$name, $type]);

        return $stmt->fetch() ?: null;
    }
}
