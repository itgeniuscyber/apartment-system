<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $payment->receipt_number }}</title>
    <style>
        @page { margin: 36px 42px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #1c1b19; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.55; }
        table { border-collapse: collapse; width: 100%; }
        .brand { color: #171715; font-size: 20px; font-weight: 700; letter-spacing: -1px; }
        .accent { color: #f26745; }
        .muted { color: #77746e; }
        .right { text-align: right; }
        .top { border-bottom: 2px solid #f26745; padding-bottom: 20px; }
        .top td { vertical-align: top; width: 50%; }
        .title { font-size: 24px; font-weight: 700; letter-spacing: -1.1px; margin: 0; }
        .paid { display: inline-block; background: #e9f9ef; color: #16864b; border-radius: 12px; font-size: 8px; font-weight: 700; padding: 5px 9px; text-transform: uppercase; }
        .hero { background: #171715; color: white; margin-top: 26px; padding: 24px; text-align: center; }
        .hero .small { color: #aaa79f; font-size: 8px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; }
        .hero .amount { color: #ff8060; font-size: 30px; font-weight: 700; letter-spacing: -1.5px; margin: 7px 0 2px; }
        .details { margin-top: 24px; }
        .details td { border-bottom: 1px solid #e8e6df; padding: 10px 8px; }
        .details td:first-child { color: #77746e; width: 36%; }
        .details td:last-child { font-weight: 700; text-align: right; }
        .allocation { background: #f7f6f2; border: 1px solid #ebe9e2; margin-top: 26px; padding: 17px; }
        .label { color: #8a8780; font-size: 8px; font-weight: 700; letter-spacing: .7px; text-transform: uppercase; }
        .footer { border-top: 1px solid #e4e1da; color: #8a8780; font-size: 8px; margin-top: 30px; padding-top: 12px; }
    </style>
</head>
<body>
    @php($allocation = $payment->allocations->first())
    @php($invoice = $allocation?->invoice)
    <table class="top">
        <tr>
            <td><div class="brand"><span class="accent">K&amp;R</span> Estates</div><div class="muted">Property management &amp; resident services</div></td>
            <td class="right"><h1 class="title">Payment receipt</h1><div style="margin-top: 6px"><span class="paid">Payment confirmed</span></div></td>
        </tr>
    </table>

    <div class="hero">
        <div class="small">Amount received</div>
        <div class="amount">{{ $payment->currency }} {{ number_format($payment->amount) }}</div>
        <div style="color: #c8c5be">Received from {{ $payment->tenant->full_name }}</div>
    </div>

    <table class="details">
        <tr><td>Receipt number</td><td>{{ $payment->receipt_number }}</td></tr>
        <tr><td>Payment number</td><td>{{ $payment->payment_number }}</td></tr>
        <tr><td>Payment date</td><td>{{ $payment->paid_at->format('d M Y, g:i A') }}</td></tr>
        <tr><td>Payment method</td><td>{{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}{{ $payment->card_brand ? ' · '.ucfirst($payment->card_brand) : '' }}</td></tr>
        <tr><td>Provider reference</td><td>{{ $payment->provider_reference ?: 'Recorded by management' }}</td></tr>
        <tr><td>Destination account</td><td>{{ $payment->settlementAccount->owner_name }} · ••{{ $payment->settlementAccount->account_last_four }}</td></tr>
    </table>

    @if($invoice)
        <div class="allocation">
            <table>
                <tr>
                    <td>
                        <div class="label">Allocated to invoice</div>
                        <div style="font-size: 12px; font-weight: 700; margin-top: 4px">{{ $invoice->invoice_number }} · {{ $invoice->description }}</div>
                        <div class="muted">{{ $invoice->unit->section->estate->name }} · Unit {{ $invoice->unit->number }} · {{ $invoice->unit->code }}</div>
                    </td>
                    <td class="right">
                        <div class="label">Allocated amount</div>
                        <div class="accent" style="font-size: 13px; font-weight: 700; margin-top: 4px">{{ $payment->currency }} {{ number_format($allocation->amount) }}</div>
                    </td>
                </tr>
            </table>
        </div>
    @endif

    <div class="footer">
        This is an official computer-generated K&amp;R Estates receipt produced on {{ $generatedAt->format('d M Y, g:i A') }} EAT. Keep the receipt number for future enquiries.
    </div>
</body>
</html>
