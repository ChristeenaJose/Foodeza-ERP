<?php

declare(strict_types=1);

namespace Foodeza\Bookkeeping\Models;

use Foodeza\Bookkeeping\Database;
use PDO;

final class BankTransaction
{
    public static function all(bool $unmatchedOnly = false): array
    {
        $sql = 'SELECT * FROM bank_transactions';
        if ($unmatchedOnly) {
            $sql .= ' WHERE matched_income_id IS NULL AND matched_expense_id IS NULL';
        }
        $sql .= ' ORDER BY date DESC, id DESC';

        return Database::connection()->query($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM bank_transactions WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function matchIncome(int $bankId, int $incomeId): void
    {
        $pdo = Database::connection();
        $pdo->prepare('UPDATE bank_transactions SET matched_income_id = ? WHERE id = ?')
            ->execute([$incomeId, $bankId]);
        $pdo->prepare('UPDATE incomes SET bank_transaction_id = ? WHERE id = ?')
            ->execute([$bankId, $incomeId]);
    }

    public static function matchExpense(int $bankId, int $expenseId): void
    {
        $pdo = Database::connection();
        $pdo->prepare('UPDATE bank_transactions SET matched_expense_id = ? WHERE id = ?')
            ->execute([$expenseId, $bankId]);
        $pdo->prepare('UPDATE expenses SET bank_transaction_id = ? WHERE id = ?')
            ->execute([$bankId, $expenseId]);
    }

    public static function createFromImport(array $row): int
    {
        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO bank_transactions (date, description, amount, reference) VALUES (?, ?, ?, ?)'
        )->execute([$row['date'], $row['description'], $row['amount'], $row['reference']]);

        return (int) $pdo->lastInsertId();
    }
}
