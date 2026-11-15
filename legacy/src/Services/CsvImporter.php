<?php

declare(strict_types=1);

namespace Foodeza\Bookkeeping\Services;

use Foodeza\Bookkeeping\Database;

final class CsvImporter
{
    /** @return list<array{date: string, description: string, amount: float, reference: ?string}> */
    public static function parse(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Could not read CSV file.');
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            throw new \RuntimeException('CSV file is empty.');
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $dateIdx = self::findColumn($header, ['date', 'booking date', 'datum']);
        $descIdx = self::findColumn($header, ['description', 'name', 'details', 'omschrijving']);
        $amountIdx = self::findColumn($header, ['amount', 'bedrag', 'value']);
        $refIdx = self::findColumn($header, ['reference', 'ref', 'transaction id']);

        if ($dateIdx === null || $descIdx === null || $amountIdx === null) {
            fclose($handle);
            throw new \RuntimeException('CSV must include date, description, and amount columns.');
        }

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (count(array_filter($line)) === 0) {
                continue;
            }

            $amount = self::parseAmount((string) ($line[$amountIdx] ?? '0'));
            $rows[] = [
                'date' => self::parseDate((string) ($line[$dateIdx] ?? '')),
                'description' => trim((string) ($line[$descIdx] ?? '')),
                'amount' => $amount,
                'reference' => $refIdx !== null ? trim((string) ($line[$refIdx] ?? '')) : null,
            ];
        }

        fclose($handle);

        return $rows;
    }

    /** @param list<array{date: string, description: string, amount: float, reference: ?string}> $rows */
    public static function import(array $rows): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO bank_transactions (date, description, amount, reference) VALUES (?, ?, ?, ?)'
        );
        $count = 0;

        foreach ($rows as $row) {
            if ($row['description'] === '') {
                continue;
            }
            $stmt->execute([$row['date'], $row['description'], $row['amount'], $row['reference']]);
            $count++;
        }

        return $count;
    }

    /** @param list<string> $candidates */
    private static function findColumn(array $header, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $idx = array_search($candidate, $header, true);
            if ($idx !== false) {
                return (int) $idx;
            }
        }

        return null;
    }

    private static function parseAmount(string $value): float
    {
        $value = str_replace(['€', ' '], '', $value);
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);

        return (float) $value;
    }

    private static function parseDate(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        $ts = strtotime($value);

        return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    }
}
