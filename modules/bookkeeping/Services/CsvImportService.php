<?php

namespace App\Services;

use App\Models\BankTransaction;

class CsvImportService
{
    public function parse(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Could not read CSV file.');
        }

        $header = array_map('strtolower', array_map('trim', fgetcsv($handle) ?: []));
        $dateIdx = $this->col($header, ['date', 'booking date', 'datum']);
        $descIdx = $this->col($header, ['description', 'name', 'details', 'omschrijving']);
        $amountIdx = $this->col($header, ['amount', 'bedrag', 'value']);
        $refIdx = $this->col($header, ['reference', 'ref']);

        if ($dateIdx === null || $descIdx === null || $amountIdx === null) {
            fclose($handle);
            throw new \RuntimeException('CSV must include date, description, and amount columns.');
        }

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (! array_filter($line)) {
                continue;
            }
            $rows[] = [
                'date' => $this->parseDate($line[$dateIdx] ?? ''),
                'description' => trim($line[$descIdx] ?? ''),
                'amount' => $this->parseAmount($line[$amountIdx] ?? '0'),
                'reference' => $refIdx !== null ? trim($line[$refIdx] ?? '') : null,
            ];
        }
        fclose($handle);

        return $rows;
    }

    public function import(array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if ($row['description'] === '') {
                continue;
            }
            BankTransaction::create($row);
            $count++;
        }

        return $count;
    }

    private function col(array $header, array $candidates): ?int
    {
        foreach ($candidates as $c) {
            $i = array_search($c, $header, true);
            if ($i !== false) {
                return (int) $i;
            }
        }

        return null;
    }

    private function parseAmount(string $v): float
    {
        $v = str_replace(['€', ' ', '.'], '', $v);
        $v = str_replace(',', '.', $v);

        return (float) $v;
    }

    private function parseDate(string $v): string
    {
        $v = trim($v);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)
            ? $v
            : date('Y-m-d', strtotime($v) ?: time());
    }
}
