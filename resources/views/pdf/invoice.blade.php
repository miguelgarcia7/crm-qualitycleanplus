<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .muted { color: #777; }
        .row { width: 100%; }
        .col { display: inline-block; vertical-align: top; width: 48%; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #eee; }
        th { background: #f5f5f5; text-transform: uppercase; font-size: 10px; letter-spacing: .04em; }
        .num { text-align: right; }
        .totals { margin-top: 12px; width: 40%; float: right; }
        .totals td { border: none; padding: 3px 8px; }
        .grand { font-weight: bold; border-top: 1px solid #333; }
    </style>
</head>
@php
    $money = fn ($c) => '$' . number_format(($c ?? 0) / 100, 2);
    $hrs = fn ($m) => number_format(($m ?? 0) / 60, 2);
    $p = $invoice->property_snapshot;
    $inv = $invoice->invoicer_snapshot;
@endphp
<body>
    <div class="row">
        <div class="col">
            <h1>{{ $inv['name'] ?? 'Quality Cleaning Plus' }}</h1>
            <div class="muted">
                {{ $inv['address'] ?? '' }}<br>
                {{ $inv['city'] ?? '' }} {{ $inv['state'] ?? '' }} {{ $inv['zip'] ?? '' }}<br>
                {{ $inv['phone'] ?? '' }} {{ $inv['email'] ?? '' }}
            </div>
        </div>
        <div class="col" style="text-align: right;">
            <h1>INVOICE</h1>
            <div><strong>{{ $invoice->invoice_number }}</strong></div>
            <div class="muted">Issued {{ $invoice->issue_date->toFormattedDateString() }}</div>
            <div class="muted">Due {{ $invoice->due_date->toFormattedDateString() }}</div>
        </div>
    </div>

    <div style="margin-top: 16px;">
        <div class="muted">Bill To</div>
        <strong>{{ $p['name'] ?? '' }}</strong><br>
        <span class="muted">{{ $p['address'] ?? '' }}, {{ $p['city'] ?? '' }} {{ $p['state'] ?? '' }} {{ $p['zip'] ?? '' }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Contractor</th>
                <th>Position</th>
                <th>Job Code</th>
                <th class="num">Reg Hrs</th>
                <th class="num">OT Hrs</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->contractor_name }}</td>
                    <td>{{ $item->position_name }}</td>
                    <td>{{ $item->job_code ?? '—' }}</td>
                    <td class="num">{{ $hrs($item->regular_minutes) }}</td>
                    <td class="num">{{ $hrs($item->overtime_minutes) }}</td>
                    <td class="num">{{ $money($item->total_bill) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Per-position rollup in the client's own chart of accounts --}}
    <table>
        <thead>
            <tr>
                <th>Position</th>
                <th>Job Code</th>
                <th class="num">Reg Hrs</th>
                <th class="num">OT Hrs</th>
                <th class="num">HLD Hrs</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items->groupBy('position_name') as $position => $items)
                <tr>
                    <td>{{ $position }}</td>
                    <td>{{ $items->first()->job_code ?? '—' }}</td>
                    <td class="num">{{ $hrs($items->sum('regular_minutes')) }}</td>
                    <td class="num">{{ $hrs($items->sum('overtime_minutes')) }}</td>
                    <td class="num">{{ $hrs($items->sum('holiday_minutes')) }}</td>
                    <td class="num">{{ $money($items->sum('total_bill')) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="num">{{ $money($invoice->subtotal) }}</td></tr>
        <tr><td>Tax ({{ number_format((float) $invoice->tax_rate * 100, 2) }}%)</td><td class="num">{{ $money($invoice->tax_amount) }}</td></tr>
        <tr class="grand"><td>Total</td><td class="num">{{ $money($invoice->total) }}</td></tr>
    </table>
</body>
</html>
