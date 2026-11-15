<?php

namespace App\Services;

use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TaxReportService
{
    public function generate(int $year): array
    {
        $from = Carbon::create($year, 1, 1)->startOfDay();
        $to = Carbon::create($year, 12, 31)->endOfDay();
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        $incomes = Income::with('customer')
            ->whereBetween('date', [$fromStr, $toStr])
            ->orderBy('date')
            ->get();

        $expenses = Expense::with(['supplier', 'category', 'receiptAttachment'])
            ->whereBetween('date', [$fromStr, $toStr])
            ->orderBy('date')
            ->get();

        $classified = $expenses->map(fn ($e) => $this->classifyExpense($e));

        $operating = $classified->where('group', 'operating');
        $fixedAssets = $classified->where('group', 'fixed_asset');
        $startup = $classified->where('group', 'startup');

        $isKlein = (bool) config('foodeza.kleinunternehmer', true);

        // Kleinunternehmer: sales = cash received (no VAT charged). Purchases = amount paid (incl. VAT on bills).
        $salesReceived = round((float) $incomes->sum('amount'), 2);
        $operatingPaid = round((float) $operating->sum('amount_gross'), 2);
        $assetsPaid = round((float) $fixedAssets->sum('amount_gross'), 2);
        $startupPaid = round((float) $startup->sum('amount_gross'), 2);
        $profit = round($salesReceived - $operatingPaid, 2);

        // Keep legacy net fields for compatibility; for KU they match the cash figures above.
        $totalRevenueNet = $salesReceived;
        $totalRevenueGross = $salesReceived;
        $operatingNet = $operatingPaid;
        $assetsNet = $assetsPaid;
        $startupNet = $startupPaid;
        $netProfit = $profit;

        $private = $this->privateTransactions($fromStr, $toStr);
        $missing = $this->missingDocumentation($incomes, $expenses, $fromStr, $toStr);
        $reviewFlags = $this->collectReviewFlags($classified, $incomes, $expenses);

        return [
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'tax_year' => $year,
                'period_from' => $fromStr,
                'period_to' => $toStr,
                'currency' => config('foodeza.currency', 'EUR'),
                'currency_symbol' => config('foodeza.currency_symbol', '€'),
                'kleinunternehmer' => $isKlein,
                'vat_applicable' => ! $isKlein,
            ],
            'executive_summary' => [
                'business_name' => config('foodeza.company', 'Foodeza'),
                'legal_form' => config('tax_report.legal_form'),
                'tax_regime' => config('tax_report.tax_regime'),
                'tax_year' => $year,
                'accounting_method' => config('tax_report.accounting_method'),
                'accounting_basis' => config('tax_report.accounting_basis'),
                'total_revenue_net' => $totalRevenueNet,
                'total_revenue_gross' => $totalRevenueGross,
                'total_sales_received' => $salesReceived,
                'total_operating_expenses_net' => $operatingNet,
                'total_operating_paid' => $operatingPaid,
                'total_fixed_assets_net' => $assetsNet,
                'total_fixed_assets_paid' => $assetsPaid,
                'total_startup_costs_net' => $startupNet,
                'total_startup_paid' => $startupPaid,
                'net_profit_or_loss' => $netProfit,
                'kleinunternehmer' => $isKlein,
                'no_vat_on_sales' => $isKlein,
                'purchases_recorded_gross' => $isKlein,
            ],
            'income_summary' => $this->incomeSummary($incomes, $year, $isKlein),
            'operating_expenses' => $this->operatingExpenseSummary($operating, $year),
            'fixed_assets' => $this->fixedAssetSummary($fixedAssets),
            'startup_costs' => $this->startupSummary($startup),
            'private_transactions' => $private,
            'eur' => $this->eurSummary($salesReceived, $operatingPaid, $assetsPaid, $startupPaid),
            'advisor_package' => [
                'total_revenue_net' => $salesReceived,
                'total_operating_expenses_net' => $operatingPaid,
                'total_fixed_asset_purchases_net' => $assetsPaid,
                'total_startup_costs_net' => $startupPaid,
                'total_private_withdrawals' => $private['withdrawals_total'],
                'total_private_deposits' => $private['deposits_total'],
                'net_profit_or_loss' => $profit,
                'review_flags' => $reviewFlags,
                'notes' => $isKlein
                    ? [
                        'Kleinunternehmerregelung (§ 19 UStG) — keine Umsatzsteuer ausgewiesen',
                        'Verkaufspreise ohne MwSt. an Kunden',
                        'Einkäufe mit dem gezahlten Bruttobetrag (MwSt. auf Rechnungen nicht als Vorsteuer abgezogen)',
                        'EÜR / Einnahmen-Überschuss-Rechnung — einfache Gewinnermittlung',
                    ]
                    : [],
            ],
            'missing_documentation' => $missing,
            'export_tables' => [
                'revenue' => $this->incomeSummary($incomes, $year, $isKlein)['annual'],
                'expenses' => $this->operatingExpenseSummary($operating, $year)['annual'],
                'assets' => $fixedAssets->values()->all(),
                'startup' => $startup->values()->all(),
                'private' => $private,
                'eur' => $this->eurSummary($salesReceived, $operatingPaid, $assetsPaid, $startupPaid),
            ],
        ];
    }

    private function classifyExpense(Expense $expense): array
    {
        $category = $expense->category?->name ?? 'Uncategorized';
        $supplier = $expense->supplier?->name ?? '';
        $description = $expense->description ?? '';
        $haystack = mb_strtolower(trim("{$supplier} {$description}"));
        // Paid amount = amount + VAT stored (or amount alone when KU / no VAT split).
        $amountGross = round((float) $expense->amount + (float) $expense->vat_amount, 2);
        // Kleinunternehmer: use what was paid (no Vorsteuer). Else keep net for AfA hints.
        $amountNet = config('foodeza.kleinunternehmer', true)
            ? $amountGross
            : (float) $expense->amount;
        $flags = [];

        $group = config("tax_report.category_groups.{$category}", 'operating');
        $operatingLabel = config("tax_report.operating_labels.{$category}", 'Other operating expenses');
        $assetCategory = 'Other fixed assets';
        $afa = config('tax_report.afa_suggestions.default');
        $startupLabel = config("tax_report.startup_labels.{$category}", 'Other startup expenses');

        if ($category === 'Fixed assets') {
            $group = 'fixed_asset';
            $assetCategory = $this->guessAssetCategory($haystack);
            $afa = $this->guessAfa($haystack, $amountNet);
        } elseif ($category === 'Startup costs') {
            $group = 'startup';
            $startupLabel = config('tax_report.startup_labels.Startup costs');
        }

        if ($this->containsAny($haystack, config('tax_report.startup_keywords', []))) {
            if (str_contains($haystack, 'gas prüfung') || str_contains($haystack, 'gasprüfung')) {
                $group = 'startup';
                $startupLabel = config('tax_report.startup_labels.gas_inspection');
            } elseif (in_array($category, ['Vehicle inspection', 'Vehicle registration', 'Registration fees', 'Registration'], true)) {
                $group = 'startup';
            }
        }

        if ($group === 'review' || $category === 'Equipment') {
            if ($this->containsAny($haystack, config('tax_report.supply_supplier_keywords', []))) {
                $group = 'operating';
                $operatingLabel = config('tax_report.operating_labels.misc_supplies');
                $flags[] = 'Supplier suggests food supplies — recorded under Equipment; reclassify with Steuerberater';
            } elseif ($this->containsAny($haystack, ['foodtruck', 'food truck', 'buy van', 'van'])) {
                $group = 'fixed_asset';
                $assetCategory = str_contains($haystack, 'van') && ! str_contains($haystack, 'food truck')
                    ? 'Vehicle purchase'
                    : 'Food truck purchase';
                $afa = str_contains($haystack, 'van')
                    ? config('tax_report.afa_suggestions.vehicle')
                    : config('tax_report.afa_suggestions.food_truck');
            } elseif ($this->containsAny($haystack, ['gasanlage', 'gas installation', 'kitchen', 'küche', 'refrigerat'])) {
                $group = 'fixed_asset';
                $assetCategory = str_contains($haystack, 'gasanlage') ? 'Gas installation / food truck equipment' : 'Kitchen equipment';
                $afa = config('tax_report.afa_suggestions.gas_installation');
            } elseif ($amountNet >= (float) config('tax_report.gwg_threshold_net', 800)) {
                $group = 'fixed_asset';
                $assetCategory = 'Capital investment';
                $afa = config('tax_report.afa_suggestions.default');
            } else {
                $group = 'operating';
                $operatingLabel = $amountNet < (float) config('tax_report.gwg_threshold_net', 800)
                    ? config('tax_report.operating_labels.misc_office')
                    : config('tax_report.operating_labels.misc_other');
                $afa = config('tax_report.afa_suggestions.gwg');
                if ($category === 'Equipment') {
                    $flags[] = 'Small equipment item — GWG/Sofortabschreibung prüfen';
                }
            }
        }

        if ($category === 'Registration' || $category === 'Registration fees' || $category === 'Vehicle inspection' || $category === 'Vehicle registration') {
            $group = 'startup';
        }

        if (str_contains($haystack, 'coc') || str_contains($haystack, 'kennzeichen')) {
            $group = 'startup';
        }

        if ($this->expenseHasNoProof($expense)) {
            $flags[] = 'No proof record';
        } elseif (! $expense->receipt_attachment_id && $expense->proof_type !== 'uploaded') {
            $label = config('foodeza.expense_proof_types.'.$expense->proof_type, $expense->proof_type);
            $flags[] = "Proof via {$label} — upload bill when available";
        }

        return [
            'id' => $expense->id,
            'date' => Carbon::parse($expense->date)->toDateString(),
            'supplier' => $supplier ?: '—',
            'description' => $description ?: '—',
            'category' => $category,
            'group' => $group,
            'operating_label' => $operatingLabel,
            'startup_label' => $startupLabel,
            'asset_category' => $assetCategory,
            'afa_suggestion' => $afa,
            'amount_net' => round($amountNet, 2),
            'amount_gross' => $amountGross,
            'vat_amount' => round((float) $expense->vat_amount, 2),
            'has_receipt' => (bool) $expense->receipt_attachment_id,
            'proof_type' => $expense->proof_type ?? 'none',
            'flags' => $flags,
        ];
    }

    private function incomeSummary(Collection $incomes, int $year, bool $kleinunternehmer = true): array
    {
        $map = config('tax_report.income_service_map', []);
        $labels = array_unique(array_merge(array_values($map), ['Other business income']));
        $annual = [];
        foreach ($labels as $label) {
            $annual[$label] = ['count' => 0, 'net' => 0.0, 'gross' => 0.0];
        }

        $monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $key = sprintf('%04d-%02d', $year, $m);
            $monthly[$key] = ['month' => $key, 'net' => 0.0, 'gross' => 0.0, 'by_type' => []];
            foreach ($labels as $label) {
                $monthly[$key]['by_type'][$label] = 0.0;
            }
        }

        $paymentLabels = config('foodeza.payment_methods', []);
        $items = [];

        foreach ($incomes as $income) {
            $label = $map[$income->service_type] ?? 'Other business income';
            // KU: amount = cash from customer (no VAT charged). Ignore stored vat_amount.
            $received = (float) $income->amount;
            if (! $kleinunternehmer) {
                $received = (float) $income->amount + (float) $income->vat_amount;
            }
            $net = $received;
            $gross = $received;
            $monthKey = Carbon::parse($income->date)->format('Y-m');
            $pm = $income->payment_method ?? '';

            $items[] = [
                'id' => $income->id,
                'date' => Carbon::parse($income->date)->toDateString(),
                'customer' => $income->customer?->name ?: '—',
                'service_type' => $income->service_type ?: '—',
                'service_label' => $label,
                'description' => $income->notes ?: '—',
                'invoice_number' => $income->invoice_number ?: '—',
                'payment_method' => $paymentLabels[$pm] ?? ($pm ?: '—'),
                'amount' => round($received, 2),
            ];

            $annual[$label]['count']++;
            $annual[$label]['net'] += $net;
            $annual[$label]['gross'] += $gross;

            if (isset($monthly[$monthKey])) {
                $monthly[$monthKey]['net'] += $net;
                $monthly[$monthKey]['gross'] += $gross;
                $monthly[$monthKey]['by_type'][$label] += $net;
            }
        }

        foreach ($annual as &$row) {
            $row['net'] = round($row['net'], 2);
            $row['gross'] = round($row['gross'], 2);
        }
        unset($row);

        $monthlyRows = array_values(array_map(function ($row) {
            $row['net'] = round($row['net'], 2);
            $row['gross'] = round($row['gross'], 2);
            foreach ($row['by_type'] as $k => $v) {
                $row['by_type'][$k] = round($v, 2);
            }

            return $row;
        }, $monthly));

        $totalNet = round(array_sum(array_column($annual, 'net')), 2);
        $totalGross = round(array_sum(array_column($annual, 'gross')), 2);

        return [
            'annual' => $annual,
            'monthly' => $monthlyRows,
            'total_net' => $totalNet,
            'total_gross' => $totalGross,
            'items' => $items,
            'count' => count($items),
        ];
    }

    private function operatingExpenseSummary(Collection $operating, int $year): array
    {
        $labels = array_values(config('tax_report.operating_labels', []));
        $annual = [];
        foreach (array_unique($labels) as $label) {
            $annual[$label] = ['count' => 0, 'net' => 0.0, 'gross' => 0.0];
        }

        $monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $key = sprintf('%04d-%02d', $year, $m);
            $monthly[$key] = ['month' => $key, 'net' => 0.0, 'gross' => 0.0];
        }

        $usePaid = (bool) config('foodeza.kleinunternehmer', true);

        foreach ($operating as $item) {
            $label = $item['operating_label'];
            if (! isset($annual[$label])) {
                $annual[$label] = ['count' => 0, 'net' => 0.0, 'gross' => 0.0];
            }
            $paid = $item['amount_gross'];
            $net = $usePaid ? $paid : $item['amount_net'];
            $annual[$label]['count']++;
            $annual[$label]['net'] += $net;
            $annual[$label]['gross'] += $paid;

            $monthKey = substr($item['date'], 0, 7);
            if (isset($monthly[$monthKey])) {
                $monthly[$monthKey]['net'] += $net;
                $monthly[$monthKey]['gross'] += $paid;
            }
        }

        foreach ($annual as &$row) {
            $row['net'] = round($row['net'], 2);
            $row['gross'] = round($row['gross'], 2);
        }
        unset($row);

        $monthlyRows = array_values(array_map(function ($row) {
            $row['net'] = round($row['net'], 2);
            $row['gross'] = round($row['gross'], 2);

            return $row;
        }, $monthly));

        return [
            'annual' => $annual,
            'monthly' => $monthlyRows,
            'total_net' => round(array_sum(array_column($annual, 'net')), 2),
            'total_gross' => round(array_sum(array_column($annual, 'gross')), 2),
            'items' => $operating->values()->all(),
        ];
    }

    private function fixedAssetSummary(Collection $assets): array
    {
        return [
            'total_net' => round((float) $assets->sum('amount_net'), 2),
            'total_gross' => round((float) $assets->sum('amount_gross'), 2),
            'items' => $assets->values()->all(),
        ];
    }

    private function startupSummary(Collection $startup): array
    {
        $byLabel = [];
        foreach ($startup as $item) {
            $label = $item['startup_label'];
            if (! isset($byLabel[$label])) {
                $byLabel[$label] = ['count' => 0, 'net' => 0.0, 'gross' => 0.0];
            }
            $byLabel[$label]['count']++;
            $byLabel[$label]['net'] += $item['amount_net'];
            $byLabel[$label]['gross'] += $item['amount_gross'];
        }
        foreach ($byLabel as &$row) {
            $row['net'] = round($row['net'], 2);
            $row['gross'] = round($row['gross'], 2);
        }
        unset($row);

        return [
            'by_type' => $byLabel,
            'total_net' => round((float) $startup->sum('amount_net'), 2),
            'total_gross' => round((float) $startup->sum('amount_gross'), 2),
            'items' => $startup->values()->all(),
        ];
    }

    private function privateTransactions(string $from, string $to): array
    {
        $transactions = BankTransaction::whereBetween('date', [$from, $to])->orderBy('date')->get();
        $withdrawals = [];
        $deposits = [];

        foreach ($transactions as $tx) {
            $desc = mb_strtolower($tx->description ?? '');
            $isPrivate = str_contains($desc, 'privat') || str_contains($desc, 'private') || str_contains($desc, 'entnahme') || str_contains($desc, 'einlage');
            if (! $isPrivate) {
                continue;
            }
            $row = [
                'date' => Carbon::parse($tx->date)->toDateString(),
                'description' => $tx->description,
                'amount' => round((float) $tx->amount, 2),
                'reference' => $tx->reference,
            ];
            if ($tx->amount < 0 || str_contains($desc, 'entnahme')) {
                $withdrawals[] = $row;
            } else {
                $deposits[] = $row;
            }
        }

        return [
            'withdrawals' => $withdrawals,
            'deposits' => $deposits,
            'withdrawals_total' => round(array_sum(array_column($withdrawals, 'amount')), 2),
            'deposits_total' => round(array_sum(array_column($deposits, 'amount')), 2),
            'note' => count($transactions) === 0
                ? 'No bank transactions imported — private withdrawals/deposits may be missing.'
                : (count($withdrawals) + count($deposits) === 0 ? 'No private transactions identified in bank data.' : null),
        ];
    }

    private function missingDocumentation(Collection $incomes, Collection $expenses, string $from, string $to): array
    {
        $expensesWithoutReceipts = $expenses->filter(fn ($e) => $this->expenseHasNoProof($e))->map(fn ($e) => [
            'id' => $e->id,
            'date' => Carbon::parse($e->date)->toDateString(),
            'supplier' => $e->supplier?->name,
            'description' => $e->description,
            'amount_gross' => round((float) $e->amount + (float) $e->vat_amount, 2),
        ])->values()->all();

        $unmatchedBank = BankTransaction::whereBetween('date', [$from, $to])
            ->whereNull('matched_income_id')
            ->whereNull('matched_expense_id')
            ->orderBy('date')
            ->get()
            ->map(fn ($tx) => [
                'date' => Carbon::parse($tx->date)->toDateString(),
                'description' => $tx->description,
                'amount' => round((float) $tx->amount, 2),
            ])->values()->all();

        $unpaidInvoices = Invoice::whereBetween('date', [$from, $to])
            ->where('status', '!=', 'paid')
            ->get(['id', 'invoice_number', 'date', 'total', 'status'])
            ->map(fn ($i) => [
                'invoice_number' => $i->invoice_number,
                'date' => $i->date->format('Y-m-d'),
                'total' => round((float) $i->total, 2),
                'status' => $i->status,
            ])->values()->all();

        $expensesOnHandOnly = $expenses->filter(fn ($e) => ! $e->receipt_attachment_id && ($e->proof_type ?? 'none') !== 'none' && ($e->proof_type ?? 'none') !== 'uploaded')->count();

        $inconsistencies = [];
        if (config('foodeza.kleinunternehmer', true)) {
            $vatOnIncome = round((float) $incomes->sum('vat_amount'), 2);
            if ($vatOnIncome > 0) {
                $inconsistencies[] = 'Kleinunternehmer aktiv, aber Einnahmen enthalten berechnete USt — Steuerberater prüfen.';
            }
        }

        $equipmentMisc = $expenses->filter(fn ($e) => $e->category?->name === 'Equipment' && $this->containsAny(
            mb_strtolower(($e->supplier?->name ?? '').' '.($e->description ?? '')),
            config('tax_report.supply_supplier_keywords', [])
        ))->count();
        if ($equipmentMisc > 0) {
            $inconsistencies[] = "{$equipmentMisc} Metro/grocery purchases recorded under Equipment — reclassification recommended.";
        }

        return [
            'expenses_without_receipts' => $expensesWithoutReceipts,
            'expenses_without_receipts_count' => count($expensesWithoutReceipts),
            'expenses_copy_on_hand_count' => $expensesOnHandOnly,
            'expenses_proof_summary' => $this->proofSummary($expenses),
            'unmatched_bank_transactions' => $unmatchedBank,
            'unpaid_invoices' => $unpaidInvoices,
            'inconsistencies' => $inconsistencies,
        ];
    }

    private function eurSummary(float $revenue, float $operating, float $assets, float $startup): array
    {
        $lines = config('tax_report.eur_lines', []);

        return [
            'lines' => [
                ['label' => $lines['income'] ?? 'Betriebseinnahmen', 'amount' => round($revenue, 2)],
                ['label' => $lines['operating_expenses'] ?? 'Betriebsausgaben', 'amount' => round(-$operating, 2)],
                ['label' => $lines['depreciation'] ?? 'AfA', 'amount' => 0.0, 'note' => 'AfA auf aktivierte Anlagegüter separat durch Steuerberater'],
                ['label' => 'Hinweis Anlagegüter / Gründungskosten', 'amount' => round($assets + $startup, 2), 'note' => 'Nicht in laufender EÜR-Betriebsausgabe enthalten'],
            ],
            'business_profit_or_loss' => round($revenue - $operating, 2),
            'note' => 'Vereinfachte EÜR auf Basis der Buchführung. Anlagegüter und Gründungskosten sind ausgewiesen, aber nicht in den Betriebsausgaben enthalten.',
        ];
    }

    private function collectReviewFlags(Collection $classified, Collection $incomes, Collection $expenses): array
    {
        $flags = [];

        if (config('foodeza.kleinunternehmer', true)) {
            $flags[] = 'Kleinunternehmerregelung (§ 19 UStG) — USt-Behandlung mit Steuerberater abstimmen.';
        }

        $assetTotal = round((float) $classified->where('group', 'fixed_asset')->sum('amount_net'), 2);
        if ($assetTotal > 0) {
            $flags[] = "Anlagegüter netto €{$assetTotal} — Aktivierung und AfA erforderlich.";
        }

        $missingReceipts = $expenses->filter(fn ($e) => $this->expenseHasNoProof($e))->count();
        if ($missingReceipts > 0) {
            $flags[] = "{$missingReceipts} Ausgaben ohne Beleg im System.";
        }

        foreach ($classified as $item) {
            foreach ($item['flags'] as $flag) {
                if (! in_array($flag, $flags, true)) {
                    $flags[] = $flag;
                }
            }
        }

        return $flags;
    }

    private function expenseHasNoProof(Expense $expense): bool
    {
        return ! $expense->receipt_attachment_id && ($expense->proof_type ?? 'none') === 'none';
    }

    private function proofSummary(Collection $expenses): array
    {
        $labels = config('foodeza.expense_proof_types', []);
        $summary = [];
        foreach ($labels as $key => $label) {
            $summary[$label] = $expenses->filter(function ($e) use ($key) {
                if ($key === 'uploaded') {
                    return (bool) $e->receipt_attachment_id || ($e->proof_type ?? 'none') === 'uploaded';
                }

                return ($e->proof_type ?? 'none') === $key;
            })->count();
        }

        return $summary;
    }

    private function guessAssetCategory(string $haystack): string
    {
        if ($this->containsAny($haystack, ['foodtruck', 'food truck'])) {
            return 'Food truck purchase';
        }
        if ($this->containsAny($haystack, ['van', 'sprinter', 'buy van'])) {
            return 'Vehicle purchase';
        }
        if ($this->containsAny($haystack, ['gasanlage', 'gas installation'])) {
            return 'Gas installation / food truck equipment';
        }
        if ($this->containsAny($haystack, ['kitchen', 'küche', 'refrigerat', 'kühl'])) {
            return 'Kitchen equipment';
        }
        if ($this->containsAny($haystack, ['pos', 'kasse', 'computer', 'software'])) {
            return 'POS / computer equipment';
        }

        return 'Capital investment';
    }

    private function guessAfa(string $haystack, float $amountNet): string
    {
        if ($amountNet < (float) config('tax_report.gwg_threshold_net', 800)) {
            return config('tax_report.afa_suggestions.gwg');
        }
        if ($this->containsAny($haystack, ['foodtruck', 'food truck', 'van', 'sprinter'])) {
            return config('tax_report.afa_suggestions.food_truck');
        }
        if ($this->containsAny($haystack, ['kitchen', 'küche', 'gasanlage'])) {
            return config('tax_report.afa_suggestions.gas_installation');
        }

        return config('tax_report.afa_suggestions.default');
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}
