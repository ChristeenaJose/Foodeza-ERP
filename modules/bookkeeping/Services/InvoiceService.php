<?php

namespace App\Services;

use App\Models\InvoiceSequence;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function peekNumber(?int $year = null): string
    {
        $year = $year ?? (int) date('Y');
        $row = DB::table('invoice_sequences')->where('year', $year)->first();
        $next = ($row ? (int) $row->last_number : 0) + 1;

        return sprintf('%s-%d-%04d', config('foodeza.invoice_prefix'), $year, $next);
    }

    public function nextNumber(?int $year = null): string
    {
        $year = $year ?? (int) date('Y');

        return DB::transaction(function () use ($year) {
            $row = DB::table('invoice_sequences')->where('year', $year)->lockForUpdate()->first();
            $next = ($row ? (int) $row->last_number : 0) + 1;

            if ($row) {
                DB::table('invoice_sequences')->where('year', $year)->update(['last_number' => $next]);
            } else {
                DB::table('invoice_sequences')->insert(['year' => $year, 'last_number' => $next]);
            }

            return sprintf('%s-%d-%04d', config('foodeza.invoice_prefix'), $year, $next);
        });
    }

    public function yearFromDate(string $date): int
    {
        return (int) date('Y', strtotime($date));
    }

    public function calculateVat(float $subtotal, ?float $rate = null): array
    {
        if (config('foodeza.kleinunternehmer', false)) {
            $subtotal = round($subtotal, 2);

            return [
                'subtotal' => $subtotal,
                'vat_rate' => 0.0,
                'vat_amount' => 0.0,
                'total' => $subtotal,
            ];
        }

        $rate = $rate ?? (float) config('foodeza.vat_rate');
        $vat = round($subtotal * $rate, 2);

        return [
            'subtotal' => $subtotal,
            'vat_rate' => $rate,
            'vat_amount' => $vat,
            'total' => round($subtotal + $vat, 2),
        ];
    }

    /** Split a cash amount that already includes VAT. Under Kleinunternehmer, paid amount = expense (no VAT split). */
    public function splitInclusive(float $gross, ?float $rate = null): array
    {
        $gross = round($gross, 2);

        if (config('foodeza.kleinunternehmer', false)) {
            return [
                'gross' => $gross,
                'net' => $gross,
                'vat_rate' => 0.0,
                'vat_amount' => 0.0,
            ];
        }

        $rate = $rate ?? (float) config('foodeza.vat_rate');
        $net = round($gross / (1 + $rate), 2);
        $vat = round($gross - $net, 2);

        return [
            'gross' => $gross,
            'net' => $net,
            'vat_rate' => $rate,
            'vat_amount' => $vat,
        ];
    }
}
