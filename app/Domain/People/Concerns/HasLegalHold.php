<?php

namespace App\Domain\People\Concerns;

use App\Domain\People\Models\Person;
use Illuminate\Database\Eloquent\Builder;

/**
 * Legal hold on PII-bearing models (ADR-0010, 20-domain/audit-and-pii.md).
 * A record under legal hold is exempt from RetentionPurge hard-deletes.
 *
 * Requires columns: legal_hold, legal_hold_reason, legal_hold_set_at,
 * legal_hold_set_by, is_anonymized, anonymized_at, anonymized_by.
 */
trait HasLegalHold
{
    public function isOnLegalHold(): bool
    {
        return (bool) $this->legal_hold;
    }

    public function setLegalHold(string $reason, ?Person $by = null): static
    {
        $this->forceFill([
            'legal_hold' => true,
            'legal_hold_reason' => $reason,
            'legal_hold_set_at' => now(),
            'legal_hold_set_by' => $by?->getKey(),
        ])->save();

        return $this;
    }

    public function clearLegalHold(): static
    {
        $this->forceFill([
            'legal_hold' => false,
            'legal_hold_reason' => null,
            'legal_hold_set_at' => null,
            'legal_hold_set_by' => null,
        ])->save();

        return $this;
    }

    /** @param  Builder<static>  $query */
    public function scopeOnLegalHold(Builder $query): void
    {
        $query->where('legal_hold', true);
    }

    /** @param  Builder<static>  $query */
    public function scopeNotOnLegalHold(Builder $query): void
    {
        $query->where('legal_hold', false);
    }
}
