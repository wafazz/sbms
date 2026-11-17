<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; margin: 0; }
        h1 { font-size: 22px; margin: 0; letter-spacing: 0.5px; }
        h2 { font-size: 11px; text-transform: uppercase; color: #64748b; margin: 0 0 6px; letter-spacing: 0.6px; }
        p { margin: 0; line-height: 1.5; }
        .muted { color: #64748b; }
        .row { width: 100%; }
        .row:after { content: ""; display: table; clear: both; }
        .col { float: left; width: 50%; }
        .col-right { float: right; width: 50%; text-align: right; }
        .logo { max-height: 56px; max-width: 180px; margin-bottom: 8px; }
        .section { margin-top: 22px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 10px; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #cbd5e1; padding: 6px 4px; }
        td { padding: 7px 4px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .totals { width: 42%; margin-left: auto; margin-top: 12px; }
        .totals td { border: none; padding: 4px 4px; }
        .totals .grand td { border-top: 1px solid #1e293b; font-weight: bold; font-size: 13px; padding-top: 8px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; background: #e2e8f0; font-size: 10px; text-transform: uppercase; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; font-size: 9px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <div class="row">
        <div class="col">
            @if ($logo)
                <img class="logo" src="{{ $logo }}" alt="Company logo">
            @endif
            <p><strong>{{ $company->name }}</strong></p>
            @if ($company->address)
                <p class="muted">{!! nl2br(e($company->address)) !!}</p>
            @endif
            @if ($company->tax_number)
                <p class="muted">Tax no: {{ $company->tax_number }}</p>
            @endif
        </div>
        <div class="col-right">
            <h1>INVOICE</h1>
            <p class="muted" style="margin-top: 4px;">{{ $invoice->invoice_number }}</p>
            <p style="margin-top: 6px;"><span class="badge">{{ $invoice->status->label() }}</span></p>
        </div>
    </div>

    <div class="row section">
        <div class="col">
            <h2>Bill to</h2>
            <p><strong>{{ $invoice->client->name }}</strong></p>
            @if ($invoice->client->company)<p>{{ $invoice->client->company }}</p>@endif
            @if ($invoice->client->address)<p class="muted">{!! nl2br(e($invoice->client->address)) !!}</p>@endif
            @if ($invoice->client->email)<p class="muted">{{ $invoice->client->email }}</p>@endif
        </div>
        <div class="col-right">
            <p><span class="muted">Issue date:</span> {{ $invoice->issue_date->format('d M Y') }}</p>
            <p><span class="muted">Due date:</span> {{ $invoice->due_date->format('d M Y') }}</p>
            <p><span class="muted">Currency:</span> {{ $invoice->currency }}</p>
            @if ($invoice->project)
                <p><span class="muted">Project:</span> {{ $invoice->project->code }} · {{ $invoice->project->name }}</p>
            @endif
        </div>
    </div>

    <div class="section">
        <table>
            <thead>
                <tr>
                    <th style="width: 50%;">Description</th>
                    <th class="num">Qty</th>
                    <th class="num">Unit price</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td class="num">{{ number_format((float) $item->quantity, 2) }}</td>
                        <td class="num">{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td class="num">{{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            <tr><td class="muted">Subtotal</td><td class="num">{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
            @if ((float) $invoice->discount > 0)
                <tr><td class="muted">Discount</td><td class="num">-{{ $invoice->currency }} {{ number_format((float) $invoice->discount, 2) }}</td></tr>
            @endif
            <tr><td class="muted">Tax ({{ number_format((float) $invoice->tax_rate, 2) }}%)</td><td class="num">{{ $invoice->currency }} {{ number_format((float) $invoice->tax_amount, 2) }}</td></tr>
            <tr class="grand"><td>Total</td><td class="num">{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</td></tr>
            @if ((float) $invoice->amount_paid > 0)
                <tr><td class="muted">Paid</td><td class="num">{{ $invoice->currency }} {{ number_format((float) $invoice->amount_paid, 2) }}</td></tr>
                <tr><td><strong>Balance due</strong></td><td class="num"><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->balance, 2) }}</strong></td></tr>
            @endif
        </table>
    </div>

    @if ($company->bank_name || $company->bank_account)
        <div class="section">
            <h2>Payment details</h2>
            @if ($company->bank_name)<p>Bank: {{ $company->bank_name }}</p>@endif
            @if ($company->bank_account)<p>Account: {{ $company->bank_account }}</p>@endif
            @if ($company->bank_swift)<p>SWIFT: {{ $company->bank_swift }}</p>@endif
            <p class="muted">Please quote {{ $invoice->invoice_number }} as the payment reference.</p>
        </div>
    @endif

    @if ($invoice->payments->count())
        <div class="section">
            <h2>Payments received</h2>
            <table>
                <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th class="num">Amount</th></tr></thead>
                <tbody>
                    @foreach ($invoice->payments->sortBy('payment_date') as $payment)
                        <tr>
                            <td>{{ $payment->payment_date->format('d M Y') }}</td>
                            <td>{{ $payment->method->label() }}</td>
                            <td>{{ $payment->reference ?? '—' }}</td>
                            <td class="num">{{ number_format((float) $payment->amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($invoice->terms || $invoice->notes)
        <div class="section">
            @if ($invoice->terms)
                <h2>Terms</h2>
                <p class="muted">{!! nl2br(e($invoice->terms)) !!}</p>
            @endif
            @if ($invoice->notes)
                <h2 style="margin-top: 12px;">Notes</h2>
                <p class="muted">{!! nl2br(e($invoice->notes)) !!}</p>
            @endif
        </div>
    @endif

    <div class="footer">{{ $company->name }} · {{ $invoice->invoice_number }}</div>
</body>
</html>
