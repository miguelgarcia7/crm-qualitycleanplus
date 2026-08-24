<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payouts by Contractor</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; margin: 24px; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .meta { color: #6b7280; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { background: #f3f4f6; text-transform: uppercase; font-size: 9px; letter-spacing: .03em; }
        td.num, th.num { text-align: right; }
        tfoot td { font-weight: bold; border-top: 2px solid #9ca3af; }
    </style>
</head>
<body>
    @php
        $hours = fn (int $minutes): string => number_format($minutes / 60, 1);
        $money = fn (int $cents): string => '$'.number_format($cents / 100, 2);
        $weekEnd = $week !== null ? \Carbon\CarbonImmutable::parse($week)->addDays(6) : null;
    @endphp

    <h1>Payouts by Contractor</h1>
    <p class="meta">
        Payroll week: {{ $week !== null ? \Carbon\CarbonImmutable::parse($week)->format('M j').' – '.$weekEnd->format('M j, Y') : '—' }}
        &middot; Generated {{ $generatedAt->format('M j, Y g:i A') }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Contractor</th>
                <th>Property</th>
                <th>Position</th>
                <th>Job Code</th>
                <th class="num">Regular (h)</th>
                <th class="num">Overtime (h)</th>
                <th class="num">Training (h)</th>
                <th class="num">Total Pay</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['contractor'] }}</td>
                    <td>{{ $row['property'] }}</td>
                    <td>{{ $row['position'] }}</td>
                    <td>{{ $row['job_code'] ?? '—' }}</td>
                    <td class="num">{{ $hours($row['regular_minutes']) }}</td>
                    <td class="num">{{ $hours($row['overtime_minutes']) }}</td>
                    <td class="num">{{ $hours($row['training_minutes']) }}</td>
                    <td class="num">{{ $money($row['total_pay']) }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No hours recorded for this week.</td></tr>
            @endforelse
        </tbody>
        @if (count($rows) > 0)
            <tfoot>
                <tr>
                    <td colspan="3">Total</td>
                    <td class="num">{{ $hours($totals['regular_minutes']) }}</td>
                    <td class="num">{{ $hours($totals['overtime_minutes']) }}</td>
                    <td class="num">{{ $hours($totals['training_minutes']) }}</td>
                    <td class="num">{{ $money($totals['total_pay']) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
