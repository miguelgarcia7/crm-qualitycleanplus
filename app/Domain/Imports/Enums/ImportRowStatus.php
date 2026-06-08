<?php

namespace App\Domain\Imports\Enums;

/**
 * Per-row state within an import batch (40-flows/import-hours.md). After parsing,
 * each row is one of: `matched` (contractor + active WO with matching rates),
 * `rate_conflict` (file rate differs from the active WO), `needs_wo_creation`
 * (matched contractor, no active WO), or `unmatched` (no external_id match).
 * Resolution moves rows toward a committable state; `skipped` rows are dropped;
 * `applied` rows produced a time entry; `error` captures a parse-level problem.
 */
enum ImportRowStatus: string
{
    case Matched = 'matched';
    case RateConflict = 'rate_conflict';
    case NeedsWoCreation = 'needs_wo_creation';
    case Unmatched = 'unmatched';
    case Skipped = 'skipped';
    case Applied = 'applied';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Matched => 'Matched',
            self::RateConflict => 'Rate Conflict',
            self::NeedsWoCreation => 'New Work Order',
            self::Unmatched => 'Unmatched',
            self::Skipped => 'Skipped',
            self::Applied => 'Applied',
            self::Error => 'Error',
        };
    }

    /** Still needs a human decision before the batch can commit. */
    public function needsResolution(): bool
    {
        return in_array($this, [self::RateConflict, self::Unmatched], true);
    }

    /** Will produce a time entry on commit. */
    public function isCommittable(): bool
    {
        return in_array($this, [self::Matched, self::RateConflict, self::NeedsWoCreation], true);
    }
}
