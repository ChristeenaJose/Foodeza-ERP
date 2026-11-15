<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use ZipArchive;

class SpreadsheetReader
{
    public function rows(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));

        return match ($ext) {
            'csv', 'txt' => $this->readCsv($file->getRealPath()),
            'xlsx' => $this->readXlsx($file->getRealPath()),
            'xls' => throw new RuntimeException('Legacy .xls is not supported. Please save as .xlsx or .csv and try again.'),
            default => throw new RuntimeException('Upload a .csv or .xlsx file exported from Google Forms.'),
        };
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('Could not read CSV file.');
        }

        $header = null;
        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if ($header === null) {
                if (! array_filter($line, fn ($v) => trim((string) $v) !== '')) {
                    continue;
                }
                $header = array_map(fn ($v) => trim((string) $v), $line);

                continue;
            }
            if (! array_filter($line, fn ($v) => trim((string) $v) !== '')) {
                continue;
            }
            $assoc = [];
            foreach ($header as $i => $key) {
                if ($key === '') {
                    continue;
                }
                $assoc[$key] = trim((string) ($line[$i] ?? ''));
            }
            if ($assoc) {
                $rows[] = $assoc;
            }
        }
        fclose($handle);

        if ($header === null) {
            throw new RuntimeException('CSV file is empty.');
        }

        return $rows;
    }

    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Could not open XLSX file.');
        }

        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $xml = @simplexml_load_string($sharedXml);
            if ($xml !== false) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $shared[] = (string) $si->t;
                    } else {
                        $parts = [];
                        foreach ($si->r as $run) {
                            $parts[] = (string) $run->t;
                        }
                        $shared[] = implode('', $parts);
                    }
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) {
            throw new RuntimeException('XLSX file has no worksheet.');
        }

        $sheet = @simplexml_load_string($sheetXml);
        if ($sheet === false) {
            throw new RuntimeException('Could not parse XLSX worksheet.');
        }

        $grid = [];
        foreach ($sheet->sheetData->row as $row) {
            $rIndex = ((int) $row['r']) - 1;
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                if (! preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) {
                    continue;
                }
                $cIndex = $this->columnIndex($m[1]);
                $type = (string) ($cell['t'] ?? '');
                $value = isset($cell->v) ? (string) $cell->v : '';
                if ($type === 's') {
                    $value = $shared[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = isset($cell->is->t) ? (string) $cell->is->t : '';
                }
                $grid[$rIndex][$cIndex] = trim($value);
            }
        }

        if (! $grid) {
            throw new RuntimeException('XLSX worksheet is empty.');
        }

        ksort($grid);
        $headerRow = array_shift($grid);
        if (! $headerRow) {
            throw new RuntimeException('XLSX file has no header row.');
        }
        ksort($headerRow);
        $maxCol = max(array_keys($headerRow));
        $headers = [];
        for ($i = 0; $i <= $maxCol; $i++) {
            $headers[$i] = trim((string) ($headerRow[$i] ?? ''));
        }

        $rows = [];
        foreach ($grid as $row) {
            $assoc = [];
            $empty = true;
            foreach ($headers as $i => $key) {
                if ($key === '') {
                    continue;
                }
                $value = trim((string) ($row[$i] ?? ''));
                $assoc[$key] = $value;
                if ($value !== '') {
                    $empty = false;
                }
            }
            if (! $empty) {
                $rows[] = $assoc;
            }
        }

        return $rows;
    }

    private function columnIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $n = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $n = $n * 26 + (ord($letters[$i]) - 64);
        }

        return $n - 1;
    }
}
