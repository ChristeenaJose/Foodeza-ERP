<?php

declare(strict_types=1);

namespace Foodeza\Bookkeeping;

use PDO;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $config = require __DIR__ . '/../config/app.php';
            $path = $config['database'];
            $dir = dirname($path);

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            self::ensureStorage($config['storage']);

            $isNew = !file_exists($path);

            self::$connection = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            self::$connection->exec('PRAGMA foreign_keys = ON');

            if ($isNew) {
                self::migrate();
                self::recordMigration(2);
                self::seed();
            } else {
                self::runPendingMigrations();
                self::seedDefaults();
            }
        }

        return self::$connection;
    }

    private static function ensureStorage(string $path): void
    {
        foreach (['receipts', 'invoices', 'uploads'] as $subdir) {
            $full = $path . '/' . $subdir;
            if (!is_dir($full)) {
                mkdir($full, 0755, true);
            }
        }
    }

    private static function migrate(): void
    {
        $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
        self::$connection->exec($schema);
    }

    private static function runPendingMigrations(): void
    {
        $pdo = self::$connection;
        $current = self::currentVersion();

        if ($current < 2) {
            $sql = file_get_contents(__DIR__ . '/../database/migrations/002_bookkeeping.sql');
            $pdo->exec($sql);
            self::migrateLegacyTransactions();
            self::recordMigration(2);
        }
    }

    private static function currentVersion(): int
    {
        $pdo = self::$connection;
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version INTEGER PRIMARY KEY,
                applied_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
            )'
        );

        $version = $pdo->query('SELECT MAX(version) as v FROM schema_migrations')->fetch();

        return (int) ($version['v'] ?? 0);
    }

    private static function recordMigration(int $version): void
    {
        self::$connection->prepare(
            'INSERT OR IGNORE INTO schema_migrations (version) VALUES (?)'
        )->execute([$version]);
    }

    private static function migrateLegacyTransactions(): void
    {
        $pdo = self::$connection;
        $hasLegacy = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='transactions'"
        )->fetch();

        if ($hasLegacy === false) {
            return;
        }

        $rows = $pdo->query(
            'SELECT t.*, c.name as category_name
             FROM transactions t
             LEFT JOIN categories c ON c.id = t.category_id
             WHERE t.type IN (\'income\', \'expense\')'
        )->fetchAll();

        $config = require __DIR__ . '/../config/app.php';

        foreach ($rows as $row) {
            if ($row['type'] === 'income') {
                $service = in_array($row['category_name'], $config['services'], true)
                    ? $row['category_name']
                    : ($config['services'][0] ?? 'Catering');

                $pdo->prepare(
                    'INSERT INTO incomes (date, service_type, amount, payment_method, invoice_number, notes, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $row['date'],
                    $service,
                    $row['amount'],
                    'bank_transfer',
                    $row['reference'],
                    $row['description'],
                    $row['created_at'],
                ]);
            } else {
                $catId = self::categoryId($row['category_name'] ?? 'Ingredients', 'expense');
                $pdo->prepare(
                    'INSERT INTO expenses (date, category_id, amount, description, created_at)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([
                    $row['date'],
                    $catId,
                    $row['amount'],
                    $row['description'],
                    $row['created_at'],
                ]);
            }
        }
    }

    private static function categoryId(string $name, string $type): int
    {
        $pdo = self::$connection;
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = ? AND type = ?');
        $stmt->execute([$name, $type]);
        $row = $stmt->fetch();

        if ($row) {
            return (int) $row['id'];
        }

        $pdo->prepare('INSERT INTO categories (name, type) VALUES (?, ?)')->execute([$name, $type]);

        return (int) $pdo->lastInsertId();
    }

    private static function seed(): void
    {
        self::seedCategories();
    }

    private static function seedDefaults(): void
    {
        self::seedCategories();
    }

    private static function seedCategories(): void
    {
        $pdo = self::$connection;
        $config = require __DIR__ . '/../config/app.php';

        $stmt = $pdo->prepare(
            'INSERT OR IGNORE INTO categories (name, type) SELECT ?, ? WHERE NOT EXISTS (
                SELECT 1 FROM categories WHERE name = ? AND type = ?
            )'
        );

        foreach ($config['services'] as $service) {
            $stmt->execute([$service, 'income', $service, 'income']);
        }

        foreach ($config['expense_categories'] as $category) {
            $stmt->execute([$category, 'expense', $category, 'expense']);
        }
    }
}
