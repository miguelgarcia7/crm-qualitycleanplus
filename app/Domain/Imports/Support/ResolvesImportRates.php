<?php

namespace App\Domain\Imports\Support;

use App\Domain\Imports\Enums\ImportRowStatus;
use App\Domain\Imports\Models\ImportBatchRow;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\WorkOrders\Models\WorkOrder;

/**
 * Shared logic for turning an import row's file values into the four work-order
 * rates and a position id (40-flows/import-hours.md, 20-domain/work-orders.md):
 * the file pay rate is authoritative; the bill rate comes from the file or the
 * property's contract rate; OT defaults to 1.5× when no contract OT rate exists.
 * Used by both the preview summary and the commit so they always agree.
 */
trait ResolvesImportRates
{
    /**
     * Resolve a position id for the row: an explicit pick (set during resolve),
     * else a case-insensitive match of the file's position name against the global
     * catalog. Null when neither resolves.
     *
     * @param  array<string, mixed>  $raw
     */
    protected function resolvePositionId(array $raw, Property $property): ?int
    {
        if (! empty($raw['position_id'])) {
            return (int) $raw['position_id'];
        }

        $name = isset($raw['position']) ? trim((string) $raw['position']) : '';
        if ($name === '') {
            return null;
        }

        $id = Position::query()->whereRaw('LOWER(name) = ?', [strtolower($name)])->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * The rate source a row will use on commit: matched rows reuse the active WO;
     * needs-WO rows create a new one from the file; rate-conflict rows follow the
     * recruiter's `use_file` / `use_existing` choice (null until they pick).
     */
    protected function effectiveResolution(ImportBatchRow $row): ?string
    {
        return match ($row->status) {
            ImportRowStatus::Matched => 'use_existing',
            ImportRowStatus::NeedsWoCreation => 'use_file',
            ImportRowStatus::RateConflict => $row->resolution,
            default => null,
        };
    }

    /** Whether this row will create a new work order on commit. */
    protected function willCreateWorkOrder(ImportBatchRow $row): bool
    {
        return $row->status === ImportRowStatus::NeedsWoCreation
            || ($row->status === ImportRowStatus::RateConflict && $row->resolution === 'use_file');
    }

    /**
     * Effective work-order rates (cents) for a row. `use_existing` reuses the
     * active WO's rates verbatim; otherwise the file pay rate is authoritative,
     * the bill rate falls back to the property's contract rate, and OT to 1.5×.
     * Returns null bill when neither the file nor a contract rate supplies one.
     *
     * @param  array<string, mixed>  $raw
     * @return array{pay:int, bill:int|null, ot_pay:int, ot_bill:int|null, position_id:int|null}
     */
    protected function effectiveRates(array $raw, Property $property, ?WorkOrder $existing, ?string $resolution): array
    {
        if ($resolution === 'use_existing' && $existing !== null) {
            return [
                'pay' => $existing->pay_rate,
                'bill' => $existing->bill_rate,
                'ot_pay' => $existing->ot_pay_rate,
                'ot_bill' => $existing->ot_bill_rate,
                'position_id' => $existing->position_id,
            ];
        }

        $positionId = $this->resolvePositionId($raw, $property);
        $contractRate = $positionId !== null ? $property->currentRateFor($positionId) : null;

        $pay = (int) $raw['pay_rate_cents'];
        $bill = $raw['bill_rate_cents'] ?? $contractRate?->bill_rate;

        $contractOtPay = $contractRate?->ot_pay_rate;
        $contractOtBill = $contractRate?->ot_bill_rate;

        return [
            'pay' => $pay,
            'bill' => $bill,
            'ot_pay' => $contractOtPay ?? (int) round($pay * 1.5),
            'ot_bill' => $bill === null ? null : ($contractOtBill ?? (int) round($bill * 1.5)),
            'position_id' => $positionId,
        ];
    }
}
