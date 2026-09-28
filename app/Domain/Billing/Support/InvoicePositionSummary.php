<?php

namespace App\Domain\Billing\Support;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\InvoiceItem;
use Illuminate\Support\Collection;

/**
 * The per-position rollup under an invoice's line items, in the client's own
 * chart of accounts.
 *
 * Built here rather than in each controller: the back office and QC Minute both
 * render it, and they had drifted already — one carried payout, the other did
 * not. Neither does now, so a payout figure cannot reach either surface by
 * accident rather than being stripped by whichever caller remembers to.
 */
final class InvoicePositionSummary
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function for(Invoice $invoice): array
    {
        return $invoice->items
            ->groupBy('position_name')
            ->map(fn (Collection $items, string $position): array => [
                'position' => $position,
                'job_code' => $items->first()?->job_code,
                // One rate for the position, or null when its contractors were
                // billed at different rates — the view says "Mixed" rather than
                // picking one and misrepresenting the rest.
                'bill_rate' => self::singleRate($items),
                'regular_minutes' => (int) $items->sum('regular_minutes'),
                'overtime_minutes' => (int) $items->sum('overtime_minutes'),
                'holiday_minutes' => (int) $items->sum('holiday_minutes'),
                'training_minutes' => (int) $items->sum('training_minutes'),
                'total_minutes' => (int) $items->sum(fn (InvoiceItem $i): int => $i->regular_minutes
                    + $i->overtime_minutes
                    + $i->holiday_minutes
                    + $i->training_minutes),
                // No payout here. An invoice is a billing document; what QCP pays
                // its contractors is margin, and it belongs in the reports that
                // exist for it, not on the artefact a client may be looking at.
                'total_bill' => (int) $items->sum('total_bill'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, InvoiceItem>  $items
     */
    private static function singleRate(Collection $items): ?int
    {
        $rates = $items->pluck('bill_rate')->unique();

        return $rates->count() === 1 ? (int) $rates->first() : null;
    }
}
