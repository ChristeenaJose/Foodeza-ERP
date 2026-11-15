<?php

namespace App\Services;

use App\Models\OnamEntry;
use App\Models\OnamPartner;
use Illuminate\Support\Collection;

class OnamReportService
{
    public function __construct(private InvoiceService $invoices) {}

    public function summary(): array
    {
        $partners = OnamPartner::where('active', true)->orderBy('name')->get();
        $entries = OnamEntry::with(['partner', 'receiptAttachment'])->orderByDesc('date')->orderByDesc('id')->get();
        $vatRate = (float) config('foodeza.vat_rate', 0.21);

        $annotated = $entries->map(fn ($entry) => $this->annotate($entry, $vatRate));

        $incomeEntries = $annotated->where('type', 'income')->values();
        $expenseEntries = $annotated->where('type', 'expense')->values();
        $payoutEntries = $annotated->where('type', 'settlement')->values();

        $totalIncome = round((float) $incomeEntries->sum('amount'), 2);
        $incomeNet = round((float) $incomeEntries->sum('net_amount'), 2);
        $incomeVat = round((float) $incomeEntries->sum('vat_amount'), 2);
        $totalExpenses = round((float) $expenseEntries->sum('amount'), 2);
        $expenseNet = round((float) $expenseEntries->sum('net_amount'), 2);
        $expenseVat = round((float) $expenseEntries->sum('vat_amount'), 2);
        $profit = round($incomeNet - $expenseNet, 2);

        $shareMap = $this->resolveShares($partners);
        $settlements = [];

        foreach ($partners as $partner) {
            $share = $shareMap[$partner->id] ?? 0.0;
            $paid = round((float) $expenseEntries->where('partner_id', $partner->id)->sum('net_amount'), 2);
            $fairCost = round($expenseNet * ($share / 100), 2);
            $reimbursement = round($paid - $fairCost, 2);
            $profitShare = round($profit * ($share / 100), 2);
            $netSettlement = round($reimbursement + $profitShare, 2);
            $settled = round((float) $payoutEntries->where('partner_id', $partner->id)->sum('amount'), 2);
            $remaining = round($netSettlement - $settled, 2);

            $settlements[] = [
                'partner_id' => $partner->id,
                'name' => $partner->name,
                'share_percent' => $share,
                'expenses_paid' => $paid,
                'fair_cost_share' => $fairCost,
                'reimbursement' => $reimbursement,
                'profit_share' => $profitShare,
                'net_settlement' => $netSettlement,
                'settled' => $settled,
                'remaining' => $remaining,
                'meaning' => $remaining >= 0
                    ? 'Still to receive from the group'
                    : 'Still to pay into the group',
            ];
        }

        $unassignedPaid = round((float) $expenseEntries->whereNull('partner_id')->sum('net_amount'), 2);

        return [
            'event' => 'Onam Köln 26',
            'totals' => [
                'income' => $totalIncome,
                'income_net' => $incomeNet,
                'income_vat' => $incomeVat,
                'vat_rate' => $vatRate,
                'expenses' => $totalExpenses,
                'expense_net' => $expenseNet,
                'expense_vat' => $expenseVat,
                'profit' => $profit,
                'income_count' => $incomeEntries->count(),
                'expense_count' => $expenseEntries->count(),
                'unassigned_expenses' => $unassignedPaid,
            ],
            'partners' => $partners,
            'settlements' => $settlements,
            'income_entries' => $incomeEntries->all(),
            'expense_entries' => $expenseEntries->all(),
            'payout_entries' => $payoutEntries->all(),
            'entries' => $annotated->values()->all(),
            'notes' => [
                'This is a partner settlement overview for Onam Köln 26.',
                'Partner settlement uses net amounts after VAT. VAT is not shared.',
                'Each partner’s fair share of costs = net expenses × their % share.',
                'Reimbursement = what they paid (net) − their fair cost share.',
                'Profit share = (net income − net expenses) × their % share.',
                'Net due = reimbursement + profit share (what they should receive before payouts).',
                'Cash paid to a partner is a settlement payout: it does not change profit, only Remaining.',
                'Remaining = net due − cash already given to that partner.',
            ],
        ];
    }

    private function annotate(OnamEntry $entry, float $vatRate): OnamEntry
    {
        $split = $this->splitEntry($entry, $vatRate);
        $entry->setAttribute('vat_mode', $split['vat_mode']);
        $entry->setAttribute('vat_amount', $split['vat_amount']);
        $entry->setAttribute('net_amount', $split['net']);
        $entry->setAttribute('gross_amount', $split['gross']);
        $entry->setAttribute('vat_rate', $split['vat_rate']);

        return $entry;
    }

    /** @return array{vat_mode: string, gross: float, net: float, vat_amount: float, vat_rate: float} */
    private function splitEntry(OnamEntry $entry, float $vatRate): array
    {
        $gross = round((float) $entry->amount, 2);
        $mode = $this->resolveVatMode($entry);

        if ($entry->type !== 'income' || $mode !== 'including') {
            return [
                'vat_mode' => 'none',
                'gross' => $gross,
                'net' => $gross,
                'vat_amount' => 0.0,
                'vat_rate' => $vatRate,
            ];
        }

        $storedVat = round((float) ($entry->vat_amount ?? 0), 2);
        if ($storedVat > 0) {
            return [
                'vat_mode' => 'including',
                'gross' => $gross,
                'net' => round($gross - $storedVat, 2),
                'vat_amount' => $storedVat,
                'vat_rate' => $vatRate,
            ];
        }

        return array_merge(
            $this->invoices->splitInclusive($gross, $vatRate),
            ['vat_mode' => 'including']
        );
    }

    private function resolveVatMode(OnamEntry $entry): string
    {
        if ($entry->type !== 'income') {
            return 'none';
        }

        $mode = (string) ($entry->vat_mode ?? '');
        if ($mode === 'including' || $mode === 'none') {
            return $mode;
        }

        return ((float) ($entry->vat_amount ?? 0)) > 0 ? 'including' : 'none';
    }

    /** @param  Collection<int, OnamPartner>  $partners */
    private function resolveShares($partners): array
    {
        if ($partners->isEmpty()) {
            return [];
        }

        $defined = $partners->filter(fn ($p) => $p->share_percent !== null && (float) $p->share_percent > 0);
        if ($defined->count() === $partners->count()) {
            $map = [];
            foreach ($partners as $p) {
                $map[$p->id] = (float) $p->share_percent;
            }

            return $map;
        }

        $equal = round(100 / $partners->count(), 2);
        $map = [];
        $assigned = 0.0;
        $list = $partners->values();
        foreach ($list as $i => $p) {
            if ($i === $list->count() - 1) {
                $map[$p->id] = round(100 - $assigned, 2);
            } else {
                $map[$p->id] = $equal;
                $assigned += $equal;
            }
        }

        return $map;
    }
}
