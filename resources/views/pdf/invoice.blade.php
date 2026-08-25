<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 36px 42px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #1c1b19; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.55; }
        .top { border-bottom: 2px solid #f26745; padding-bottom: 20px; }
        .brand { color: #171715; font-size: 20px; font-weight: 700; letter-spacing: -1px; }
        .brand-mark { color: #f26745; }
        .muted { color: #77746e; }
        .right { text-align: right; }
        .title { font-size: 27px; font-weight: 700; letter-spacing: -1.3px; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .meta { margin-top: 24px; }
        .meta td { vertical-align: top; width: 50%; }
        .label { color: #8a8780; font-size: 8px; font-weight: 700; letter-spacing: .7px; text-transform: uppercase; }
        .value { font-size: 11px; font-weight: 700; margin-top: 3px; }
        .box { background: #f7f6f2; border: 1px solid #ebe9e2; border-radius: 10px; padding: 15px; }
        .line-items { margin-top: 28px; }
        .line-items th { background: #171715; color: white; font-size: 8px; letter-spacing: .7px; padding: 10px 12px; text-align: left; text-transform: uppercase; }
        .line-items th:last-child, .line-items td:last-child { text-align: right; }
        .line-items td { border-bottom: 1px solid #e8e6df; padding: 13px 12px; }
        .totals { margin-left: 52%; margin-top: 16px; width: 48%; }
        .totals td { padding: 6px 10px; }
        .totals .grand td { border-top: 1px solid #d9d6ce; color: #f26745; font-size: 14px; font-weight: 700; padding-top: 10px; }
        .payment { margin-top: 30px; background: #171715; color: white; padding: 18px; }
        .payment .label { color: #a9a79f; }
        .payment .accent { color: #ff8060; }
        .status { display: inline-block; background: #fff0eb; color: #c94f31; border-radius: 12px; font-size: 8px; font-weight: 700; padding: 5px 9px; text-transform: uppercase; }
        .footer { border-top: 1px solid #e4e1da; color: #8a8780; font-size: 8px; margin-top: 28px; padding-top: 12px; }
    </style>
</head>
<body>
    <table class="top">
        <tr>
            <td>
                <div class="brand"><span class="brand-mark">K&amp;R</span> Estates</div>
                <div class="muted">Property management &amp; resident services</div>
            </td>
            <td class="right">
                <h1 class="title">Rent invoice</h1>
                <div style="margin-top: 6px"><span class="status">{{ str_replace('_', ' ', $invoice->effectiveStatus()) }}</span></div>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td style="padding-right: 10px">
                <div class="box">
                    <div class="label">Billed to</div>
                    <div class="value">{{ $invoice->tenant->full_name }}</div>
                    <div class="muted">{{ $invoice->tenant->email ?: 'Resident account' }}</div>
                    <div class="muted">{{ $invoice->tenant->phone ?: '' }}</div>
                    <div style="margin-top: 10px" class="label">Apartment</div>
                    <div class="value">{{ $invoice->unit->section->estate->name }} · Unit {{ $invoice->unit->number }}</div>
                    <div class="muted">{{ $invoice->unit->section->name }} · {{ $invoice->unit->code }}</div>
                </div>
            </td>
            <td style="padding-left: 10px">
                <table class="box">
                    <tr><td class="label">Invoice number</td><td class="right value">{{ $invoice->invoice_number }}</td></tr>
                    <tr><td class="label" style="padding-top: 9px">Issue date</td><td class="right value" style="padding-top: 9px">{{ $invoice->issue_date->format('d M Y') }}</td></tr>
                    <tr><td class="label" style="padding-top: 9px">Due date</td><td class="right value" style="padding-top: 9px">{{ $invoice->due_date->format('d M Y') }}</td></tr>
                    <tr><td class="label" style="padding-top: 9px">Lease</td><td class="right value" style="padding-top: 9px">{{ $invoice->lease->lease_number }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="line-items">
        <thead><tr><th>Description</th><th>Billing period</th><th>Amount</th></tr></thead>
        <tbody>
            <tr>
                <td><strong>{{ $invoice->description }}</strong><br><span class="muted">Monthly rent for {{ $invoice->unit->code }}</span></td>
                <td>{{ $invoice->billing_period_start->format('d M') }} – {{ $invoice->billing_period_end->format('d M Y') }}</td>
                <td>{{ $invoice->currency }} {{ number_format($invoice->total_amount) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="muted">Invoice total</td><td class="right">{{ $invoice->currency }} {{ number_format($invoice->total_amount) }}</td></tr>
        <tr><td class="muted">Payments received</td><td class="right">− {{ $invoice->currency }} {{ number_format($invoice->amount_paid) }}</td></tr>
        <tr class="grand"><td>Balance due</td><td class="right">{{ $invoice->currency }} {{ number_format($invoice->balance_due) }}</td></tr>
    </table>

    <div class="payment">
        <table>
            <tr>
                <td>
                    <div class="label">Payment destination</div>
                    <div style="font-size: 12px; font-weight: 700; margin-top: 4px">{{ $invoice->settlementAccount->owner_name }}</div>
                    <div style="color: #c5c2ba">{{ $invoice->settlementAccount->bank_name }} · account ending <span class="accent">{{ $invoice->settlementAccount->account_last_four }}</span></div>
                </td>
                <td class="right">
                    <div class="label">Use this reference</div>
                    <div class="accent" style="font-size: 13px; font-weight: 700; margin-top: 4px">{{ $invoice->invoice_number }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        This computer-generated invoice was produced on {{ $generatedAt->format('d M Y, g:i A') }} EAT. Payment is confirmed only after it appears on an official K&amp;R Estates receipt.
    </div>
</body>
</html>
