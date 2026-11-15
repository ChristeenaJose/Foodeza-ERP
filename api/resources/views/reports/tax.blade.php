<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Buchhaltung {{ $report['meta']['tax_year'] }} — {{ $company['company'] }} (Kleinunternehmer)</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #1a1a2e; line-height: 1.4; }
        .header { background: #1e3a5f; color: #fff; padding: 18px 24px; margin-bottom: 14px; }
        .header h1 { font-size: 16px; margin-bottom: 4px; }
        .header p { font-size: 8px; opacity: 0.92; }
        h2 { font-size: 11px; color: #1e3a5f; margin: 12px 0 5px; border-bottom: 1.5px solid #1e3a5f; padding-bottom: 2px; page-break-after: avoid; }
        h3 { font-size: 10px; color: #334155; margin: 10px 0 4px; page-break-after: avoid; }
        .section { padding: 0 24px 8px; }
        .note { background: #f0fdf4; border: 1px solid #bbf7d0; padding: 7px 9px; margin: 6px 0 10px; font-size: 8px; color: #166534; }
        .warn { background: #fffbeb; border: 1px solid #fde68a; padding: 7px 9px; margin: 8px 0 10px; font-size: 8px; color: #92400e; }
        table.data { width: 100%; border-collapse: collapse; margin: 3px 0 8px; }
        table.data th { background: #f1f5f9; text-align: left; padding: 3px 4px; font-size: 7px; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #cbd5e1; }
        table.data td { padding: 3px 4px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        table.data .num { text-align: right; white-space: nowrap; }
        table.data tr.total td { font-weight: bold; border-top: 1.5px solid #94a3b8; background: #f8fafc; }
        table.detail td { font-size: 8px; }
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .summary td { padding: 4px 0; border-bottom: 1px solid #e2e8f0; }
        .summary .label { color: #64748b; }
        .summary .value { text-align: right; font-weight: bold; }
        .result { font-size: 12px; font-weight: bold; }
        .positive { color: #047857; }
        .negative { color: #b91c1c; }
        .footer { margin: 14px 24px; padding-top: 6px; border-top: 1px solid #e2e8f0; color: #94a3b8; font-size: 7px; }
        ul { margin: 3px 0 0 14px; }
        li { margin-bottom: 2px; }
        .page-break { page-break-before: always; }
        .muted { color: #64748b; font-size: 7px; }
    </style>
</head>
<body>
@php
    $sym = $report['meta']['currency_symbol'];
    $fmt = fn($n) => $sym . number_format((float)$n, 2, ',', '.');
    $es = $report['executive_summary'];
    $sales = $es['total_sales_received'] ?? $es['total_revenue_gross'];
    $ops = $es['total_operating_paid'] ?? $report['operating_expenses']['total_gross'];
    $incomeItems = $report['income_summary']['items'] ?? [];
    $expenseItems = $report['operating_expenses']['items'] ?? [];
    $assetItems = $report['fixed_assets']['items'] ?? [];
    $startupItems = $report['startup_costs']['items'] ?? [];
    $proofLabels = $company['expense_proof_types'] ?? [];
@endphp

{{-- ========== COVER / SUMMARY ========== --}}
<div class="header">
    <h1>Buchhaltung {{ $report['meta']['tax_year'] }} — {{ $company['company'] }}</h1>
    <p>Für den Steuerberater · Einnahmen-Überschuss-Rechnung (EÜR) · Vollständige Buchungsliste</p>
    <p>Keine Umsatzsteuer · Kleinunternehmer · Keine offizielle Steuererklärung</p>
</div>

<div class="section">
    <div class="note">
        <strong>Kleinunternehmerregelung (§ 19 UStG)</strong><br>
        • Verkäufe an Kunden: ohne MwSt.<br>
        • Einkäufe: gezahlter Bruttobetrag (MwSt. auf Lieferantenrechnungen nicht als Vorsteuer geltend gemacht)<br>
        • Rechtsform: {{ $es['legal_form'] }} · Methode: {{ $es['accounting_method'] }} · Basis: {{ $es['accounting_basis'] }}
    </div>

    <h2>Übersicht {{ $report['meta']['tax_year'] }}</h2>
    <table class="summary">
        <tr><td class="label">Einnahmen ({{ count($incomeItems) }} Buchungen)</td><td class="value">{{ $fmt($sales) }}</td></tr>
        <tr><td class="label">Betriebsausgaben ({{ count($expenseItems) }} Buchungen)</td><td class="value">{{ $fmt($ops) }}</td></tr>
        <tr><td class="label">Anlagegüter ({{ count($assetItems) }})</td><td class="value">{{ $fmt($es['total_fixed_assets_paid'] ?? $report['fixed_assets']['total_gross']) }}</td></tr>
        <tr><td class="label">Gründungs-/Anlaufkosten ({{ count($startupItems) }})</td><td class="value">{{ $fmt($es['total_startup_paid'] ?? $report['startup_costs']['total_gross']) }}</td></tr>
        <tr>
            <td class="label result">Vorläufiger Gewinn / Verlust (Einnahmen − Betriebsausgaben)</td>
            <td class="value result {{ $es['net_profit_or_loss'] >= 0 ? 'positive' : 'negative' }}">{{ $fmt($es['net_profit_or_loss']) }}</td>
        </tr>
    </table>
    <p class="muted">Große Anschaffungen separat — AfA mit Steuerberater klären.</p>

    <h2>Summen nach Art / Kategorie</h2>
    <h3>Einnahmen</h3>
    <table class="data">
        <thead><tr><th>Art</th><th class="num">Anzahl</th><th class="num">Erhalten</th></tr></thead>
        <tbody>
        @foreach($report['income_summary']['annual'] as $label => $row)
            @if($row['count'] > 0)
            <tr>
                <td>{{ $label }}</td>
                <td class="num">{{ $row['count'] }}</td>
                <td class="num">{{ $fmt($row['gross']) }}</td>
            </tr>
            @endif
        @endforeach
        <tr class="total">
            <td>Summe Einnahmen</td>
            <td class="num">{{ count($incomeItems) }}</td>
            <td class="num">{{ $fmt($report['income_summary']['total_gross']) }}</td>
        </tr>
        </tbody>
    </table>

    <h3>Betriebsausgaben</h3>
    <table class="data">
        <thead><tr><th>Kategorie</th><th class="num">Anzahl</th><th class="num">Gezahlt</th></tr></thead>
        <tbody>
        @foreach($report['operating_expenses']['annual'] as $label => $row)
            @if($row['count'] > 0)
            <tr>
                <td>{{ $label }}</td>
                <td class="num">{{ $row['count'] }}</td>
                <td class="num">{{ $fmt($row['gross']) }}</td>
            </tr>
            @endif
        @endforeach
        <tr class="total">
            <td>Summe Betriebsausgaben</td>
            <td class="num">{{ count($expenseItems) }}</td>
            <td class="num">{{ $fmt($report['operating_expenses']['total_gross']) }}</td>
        </tr>
        </tbody>
    </table>
</div>

{{-- ========== ALL INCOME ENTRIES ========== --}}
<div class="section page-break">
    <h2>1. Alle Einnahmen — Einzelliste</h2>
    <p class="muted" style="margin-bottom:6px;">Jede Zahlung vom Kunden · ohne MwSt. · {{ count($incomeItems) }} Einträge</p>
    <table class="data detail">
        <thead>
            <tr>
                <th style="width:9%">Datum</th>
                <th style="width:18%">Kunde</th>
                <th style="width:14%">Tätigkeit</th>
                <th style="width:22%">Notiz</th>
                <th style="width:12%">Rechnung</th>
                <th style="width:12%">Zahlung</th>
                <th class="num" style="width:13%">Erhalten</th>
            </tr>
        </thead>
        <tbody>
        @forelse($incomeItems as $item)
            <tr>
                <td>{{ $item['date'] }}</td>
                <td>{{ $item['customer'] }}</td>
                <td>{{ $item['service_type'] }}</td>
                <td>{{ $item['description'] }}</td>
                <td>{{ $item['invoice_number'] }}</td>
                <td>{{ $item['payment_method'] }}</td>
                <td class="num">{{ $fmt($item['amount']) }}</td>
            </tr>
        @empty
            <tr><td colspan="7">Keine Einnahmen</td></tr>
        @endforelse
        <tr class="total">
            <td colspan="6">Summe Einnahmen</td>
            <td class="num">{{ $fmt($report['income_summary']['total_gross']) }}</td>
        </tr>
        </tbody>
    </table>
</div>

{{-- ========== ALL OPERATING EXPENSES ========== --}}
<div class="section page-break">
    <h2>2. Alle Betriebsausgaben — Einzelliste</h2>
    <p class="muted" style="margin-bottom:6px;">Gezahlter Betrag inkl. MwSt. auf Einkäufen · {{ count($expenseItems) }} Einträge</p>
    <table class="data detail">
        <thead>
            <tr>
                <th style="width:9%">Datum</th>
                <th style="width:16%">Lieferant</th>
                <th style="width:14%">Kategorie</th>
                <th style="width:28%">Beschreibung</th>
                <th style="width:14%">Beleg</th>
                <th class="num" style="width:13%">Gezahlt</th>
            </tr>
        </thead>
        <tbody>
        @forelse($expenseItems as $item)
            <tr>
                <td>{{ $item['date'] }}</td>
                <td>{{ $item['supplier'] }}</td>
                <td>{{ $item['category'] }}</td>
                <td>{{ $item['description'] }}</td>
                <td>{{ $proofLabels[$item['proof_type'] ?? 'none'] ?? ($item['proof_type'] ?? '—') }}{{ !empty($item['has_receipt']) ? ' ✓' : '' }}</td>
                <td class="num">{{ $fmt($item['amount_gross']) }}</td>
            </tr>
        @empty
            <tr><td colspan="6">Keine Betriebsausgaben</td></tr>
        @endforelse
        <tr class="total">
            <td colspan="5">Summe Betriebsausgaben</td>
            <td class="num">{{ $fmt($report['operating_expenses']['total_gross']) }}</td>
        </tr>
        </tbody>
    </table>
</div>

{{-- ========== FIXED ASSETS ========== --}}
<div class="section page-break">
    <h2>3. Anlagegüter — Einzelliste (separat / AfA)</h2>
    <p class="muted" style="margin-bottom:6px;">{{ count($assetItems) }} Einträge · nicht in Betriebsausgaben enthalten</p>
    <table class="data detail">
        <thead>
            <tr>
                <th style="width:9%">Datum</th>
                <th style="width:16%">Lieferant</th>
                <th style="width:16%">Art</th>
                <th style="width:28%">Beschreibung</th>
                <th style="width:14%">AfA-Hinweis</th>
                <th class="num" style="width:13%">Gezahlt</th>
            </tr>
        </thead>
        <tbody>
        @forelse($assetItems as $item)
            <tr>
                <td>{{ $item['date'] }}</td>
                <td>{{ $item['supplier'] }}</td>
                <td>{{ $item['asset_category'] ?? 'Anlagegut' }}</td>
                <td>{{ $item['description'] }}</td>
                <td>{{ $item['afa_suggestion'] ?? '—' }}</td>
                <td class="num">{{ $fmt($item['amount_gross']) }}</td>
            </tr>
        @empty
            <tr><td colspan="6">Keine Anlagegüter</td></tr>
        @endforelse
        <tr class="total">
            <td colspan="5">Summe Anlagegüter</td>
            <td class="num">{{ $fmt($report['fixed_assets']['total_gross']) }}</td>
        </tr>
        </tbody>
    </table>

    <h2>4. Gründungs-/Anlaufkosten — Einzelliste</h2>
    <p class="muted" style="margin-bottom:6px;">{{ count($startupItems) }} Einträge · nicht in Betriebsausgaben enthalten</p>
    <table class="data detail">
        <thead>
            <tr>
                <th style="width:9%">Datum</th>
                <th style="width:18%">Lieferant</th>
                <th style="width:18%">Art</th>
                <th style="width:36%">Beschreibung</th>
                <th class="num" style="width:13%">Gezahlt</th>
            </tr>
        </thead>
        <tbody>
        @forelse($startupItems as $item)
            <tr>
                <td>{{ $item['date'] }}</td>
                <td>{{ $item['supplier'] }}</td>
                <td>{{ $item['startup_label'] ?? 'Startup' }}</td>
                <td>{{ $item['description'] }}</td>
                <td class="num">{{ $fmt($item['amount_gross']) }}</td>
            </tr>
        @empty
            <tr><td colspan="5">Keine Gründungskosten</td></tr>
        @endforelse
        <tr class="total">
            <td colspan="4">Summe Gründungskosten</td>
            <td class="num">{{ $fmt($report['startup_costs']['total_gross']) }}</td>
        </tr>
        </tbody>
    </table>
</div>

{{-- ========== RESULT + NOTES ========== --}}
<div class="section">
    <h2>5. Ergebnis (EÜR)</h2>
    <table class="summary">
        <tr><td class="label">Einnahmen erhalten</td><td class="value">{{ $fmt($sales) }}</td></tr>
        <tr><td class="label">Betriebsausgaben gezahlt</td><td class="value">− {{ $fmt($ops) }}</td></tr>
        <tr>
            <td class="label result">Gewinn / Verlust</td>
            <td class="value result {{ $es['net_profit_or_loss'] >= 0 ? 'positive' : 'negative' }}">{{ $fmt($es['net_profit_or_loss']) }}</td>
        </tr>
    </table>

    <div class="warn">
        <strong>Hinweise für den Steuerberater</strong>
        <ul>
            <li>Vollständige Buchungsliste aus Foodeza — keine Steuererklärung.</li>
            <li>Keine Umsatzsteuer-Voranmeldung (Kleinunternehmer).</li>
        </ul>
    </div>
</div>

<div class="footer">
    Erstellt {{ $report['meta']['generated_at'] }} · {{ $company['company'] }} · Steuerjahr {{ $report['meta']['tax_year'] }}
    · {{ count($incomeItems) }} Einnahmen · {{ count($expenseItems) }} Betriebsausgaben · {{ count($assetItems) }} Anlagegüter · {{ count($startupItems) }} Gründung
</div>
</body>
</html>
