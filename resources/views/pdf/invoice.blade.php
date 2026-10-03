<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .muted { color: #777; }
        .sub { display: block; font-size: 9px; }
        .preview-notice { border: 1px solid #c8503f; background: #fdf3f1; color: #c8503f;
            padding: 8px 10px; border-radius: 4px; margin-bottom: 12px; width: 60%; }
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
    // Flattened: legacy-issued invoices nest address as {street, city, state,
    // zip}, and echoing that array was a fatal htmlspecialchars() error.
    $summary = \App\Domain\Billing\Support\InvoicePositionSummary::for($invoice);
    $notice = config('qcp.invoice.preview_notice') ?? [];
    $p = $invoice->propertySnapshotFlat();
    $inv = $invoice->invoicerSnapshotFlat();
@endphp
<body>
    <div class="row">
        <div class="col">
            <h1>{{ $inv['name'] ?? 'Quality Cleaning Plus' }}</h1>
            @php
                // Only the lines this invoice's snapshot has — one frozen before
                // the company details were filled in would otherwise print as
                // a stack of blank lines under the name.
                $cityLine = trim(implode(' ', array_filter([
                    trim(($inv['city'] ?? '').(filled($inv['state'] ?? null) ? ', '.$inv['state'] : ''), ', '),
                    $inv['zip'] ?? null,
                ])));
                $companyLines = array_values(array_filter([
                    $inv['address'] ?? null,
                    $cityLine,
                    implode(' · ', array_filter([$inv['phone'] ?? null, $inv['email'] ?? null])),
                ], 'filled'));
            @endphp
            @if ($companyLines)
                <div class="muted">
                    @foreach ($companyLines as $line)
                        {{ $line }}@if (! $loop->last)<br>@endif
                    @endforeach
                </div>
            @endif
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
                <th>Job Code</th>
                <th class="num">Reg Hrs | Rate</th>
                <th class="num">OT Hrs | Rate</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>
                        {{ $item->contractor_name }}
                        <span class="muted sub">{{ $item->position_name }}</span>
                    </td>
                    <td>{{ $item->job_code ?? '—' }}</td>
                    <td class="num">{{ $hrs($item->regular_minutes) }} <span class="muted">| {{ $money($item->bill_rate) }}</span></td>
                    <td class="num">{{ $hrs($item->overtime_minutes) }}@if ($item->overtime_minutes > 0) <span class="muted">| {{ $money($item->ot_bill_rate) }}</span>@endif</td>
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
                <th class="num">Rate</th>
                <th class="num">Reg Hrs</th>
                <th class="num">OT Hrs</th>
                <th class="num">HLD Hrs</th>
                <th class="num">Total Hrs</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($summary as $row)
                <tr>
                    <td>{{ $row['position'] }}</td>
                    <td>{{ $row['job_code'] ?? '—' }}</td>
                    {{-- null = this position's contractors were billed at different rates --}}
                    <td class="num">{{ $row['bill_rate'] === null ? 'Mixed' : $money($row['bill_rate']) }}</td>
                    <td class="num">{{ $hrs($row['regular_minutes']) }}</td>
                    <td class="num">{{ $hrs($row['overtime_minutes']) }}</td>
                    <td class="num">{{ $hrs($row['holiday_minutes']) }}</td>
                    <td class="num">{{ $hrs($row['total_minutes']) }}</td>
                    <td class="num">{{ $money($row['total_bill']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($notice['heading'] ?? null)
        {{-- The billable invoice is issued elsewhere; the PDF is the only place
             a client reads that, so it travels with the document. --}}
        <div class="preview-notice">
            <strong>{{ $notice['heading'] }}</strong><br>
            {{ $notice['body'] ?? '' }}
        </div>
    @endif

    <table class="totals">
        <tr><td>Work Subtotal</td><td class="num">{{ $money($invoice->work_subtotal) }}</td></tr>
        <tr><td>Adjustments</td><td class="num">{{ $invoice->adjustment_total < 0 ? '-' : '+' }}{{ $money(abs($invoice->adjustment_total)) }}</td></tr>
        <tr><td>Subtotal</td><td class="num">{{ $money($invoice->subtotal) }}</td></tr>
        <tr><td>Tax Rate</td><td class="num muted">{{ rtrim(rtrim(number_format((float) $invoice->tax_rate * 100, 2), '0'), '.') }}%</td></tr>
        <tr><td>Total Tax</td><td class="num">{{ $money($invoice->tax_amount) }}</td></tr>
        <tr class="grand"><td>Total</td><td class="num">{{ $money($invoice->total) }}</td></tr>
    </table>
</body>
</html>
