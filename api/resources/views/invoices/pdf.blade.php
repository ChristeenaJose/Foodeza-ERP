<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #1a1a2e; line-height: 1.45; }
        .header { background: #dc2626; color: #fff; padding: 28px 36px; }
        .header h1 { font-size: 28px; font-weight: bold; margin-bottom: 4px; }
        .header p { font-size: 10px; opacity: 0.9; }
        .meta { display: table; width: 100%; padding: 28px 36px 8px; }
        .meta-col { display: table-cell; width: 50%; vertical-align: top; }
        .meta-col.right { text-align: right; }
        .label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; margin-bottom: 4px; }
        .value { font-size: 12px; margin-bottom: 14px; }
        .invoice-title { font-size: 22px; font-weight: bold; color: #dc2626; margin-bottom: 6px; }
        table.items { width: calc(100% - 72px); margin: 16px 36px; border-collapse: collapse; }
        table.items th { background: #f4f1ec; text-align: left; padding: 10px 12px; font-size: 9px; text-transform: uppercase; color: #6b7280; border-bottom: 2px solid #dc2626; }
        table.items td { padding: 10px 12px; border-bottom: 1px solid #e5e2dc; vertical-align: top; }
        table.items .num { text-align: right; white-space: nowrap; }
        .totals { width: calc(100% - 72px); margin: 8px 36px 24px; }
        .totals table { width: 280px; margin-left: auto; border-collapse: collapse; }
        .totals td { padding: 6px 0; }
        .totals .num { text-align: right; }
        .totals .grand td { font-size: 14px; font-weight: bold; color: #dc2626; border-top: 2px solid #dc2626; padding-top: 10px; }
        .footer { margin: 0 36px 36px; padding-top: 16px; border-top: 1px solid #e5e2dc; color: #6b7280; font-size: 10px; }
        .footer strong { color: #1a1a2e; }
        .notes { margin: 0 36px 20px; padding: 12px 14px; background: #f9fafb; border-left: 3px solid #dc2626; font-size: 10px; }
        .badge { display: inline-block; background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 10px; font-size: 9px; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $company['company'] }}</h1>
        <p>{{ $company['tagline'] ?? '' }}</p>
    </div>

    <div class="meta">
        <div class="meta-col">
            <div class="label">Bill to</div>
            <div class="value">
                <strong>{{ $invoice->customer->name }}</strong><br>
                @if($invoice->customer->address){!! nl2br(e($invoice->customer->address)) !!}<br>@endif
                @if($invoice->customer->email){{ $invoice->customer->email }}<br>@endif
                @if($invoice->customer->phone){{ $invoice->customer->phone }}@endif
            </div>
            <div class="label">Service</div>
            <div class="value">{{ $invoice->service_type }}</div>
        </div>
        <div class="meta-col right">
            <div class="invoice-title">INVOICE</div>
            <div class="value"><strong>{{ $invoice->invoice_number }}</strong></div>
            <div class="label">Invoice date</div>
            <div class="value">{{ $invoice->date->format('d M Y') }}</div>
            @if($invoice->due_date)
                <div class="label">Due date</div>
                <div class="value">{{ $invoice->due_date->format('d M Y') }}</div>
            @endif
            <div class="label">Status</div>
            <div class="value"><span class="badge">{{ $invoice->status }}</span></div>
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width:50%">Description</th>
                <th class="num" style="width:12%">Qty</th>
                <th class="num" style="width:19%">Unit price</th>
                <th class="num" style="width:19%">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ number_format($item->quantity, 2) }}</td>
                    <td class="num">{{ $company['currency_symbol'] }}{{ number_format($item->unit_price, 2) }}</td>
                    <td class="num">{{ $company['currency_symbol'] }}{{ number_format($item->amount, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td>{{ $invoice->service_type }}</td>
                    <td class="num">1.00</td>
                    <td class="num">{{ $company['currency_symbol'] }}{{ number_format($invoice->subtotal, 2) }}</td>
                    <td class="num">{{ $company['currency_symbol'] }}{{ number_format($invoice->subtotal, 2) }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        <table>
            @if(!empty($company['kleinunternehmer']) || (float) $invoice->vat_amount == 0)
            <tr class="grand">
                <td>Total due (no VAT — Kleinunternehmer)</td>
                <td class="num">{{ $company['currency_symbol'] }}{{ number_format($invoice->total, 2) }}</td>
            </tr>
            @else
            <tr>
                <td>Subtotal (excl. VAT)</td>
                <td class="num">{{ $company['currency_symbol'] }}{{ number_format($invoice->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td>VAT ({{ number_format($invoice->vat_rate * 100, 0) }}%)</td>
                <td class="num">{{ $company['currency_symbol'] }}{{ number_format($invoice->vat_amount, 2) }}</td>
            </tr>
            <tr class="grand">
                <td>Total due</td>
                <td class="num">{{ $company['currency_symbol'] }}{{ number_format($invoice->total, 2) }}</td>
            </tr>
            @endif
        </table>
    </div>

    @if($invoice->notes)
        <div class="notes"><strong>Notes:</strong> {{ $invoice->notes }}</div>
    @endif

    <div class="footer">
        <strong>{{ $company['company'] }}</strong><br>
        {{ $company['company_details']['address'] ?? '' }},
        {{ $company['company_details']['postcode'] ?? '' }},
        {{ $company['company_details']['country'] ?? '' }}<br>
        @if(!empty($company['company_details']['email']))Email: {{ $company['company_details']['email'] }} · @endif
        @if(!empty($company['company_details']['phone']))Tel: {{ $company['company_details']['phone'] }}<br>@endif
        @if(!empty($company['company_details']['kvk']))KVK: {{ $company['company_details']['kvk'] }} · @endif
        @if(!empty($company['company_details']['vat_number']))BTW: {{ $company['company_details']['vat_number'] }}<br>@endif
        @if(!empty($company['company_details']['iban']))IBAN: {{ $company['company_details']['iban'] }}
        @if(!empty($company['company_details']['bic'])) · BIC: {{ $company['company_details']['bic'] }}@endif
        @endif
    </div>
</body>
</html>
