<?php

namespace App\Domain\Imports\Enums;

/**
 * Lifecycle of an hour-import batch (40-flows/import-hours.md). `parsing` while the
 * file is read; `preview` once rows are matched and awaiting resolution/commit;
 * `applied` after the commit transaction (time entries + invoice created);
 * `rolled_back` after a void/rollback. `applied` and `rolled_back` are terminal.
 */
enum ImportBatchStatus: string
{
    case Parsing = 'parsing';
    case Preview = 'preview';
    case Applied = 'applied';
    case RolledBack = 'rolled_back';

    public function label(): string
    {
        return match ($this) {
            self::Parsing => 'Parsing',
            self::Preview => 'Preview',
            self::Applied => 'Applied',
            self::RolledBack => 'Rolled Back',
        };
    }

    /** Still in the wizard — rows can be resolved and the batch committed. */
    public function isEditable(): bool
    {
        return $this === self::Preview;
    }
}
