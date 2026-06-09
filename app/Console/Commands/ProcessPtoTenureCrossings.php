<?php

namespace App\Console\Commands;

use App\Domain\Pto\Actions\ProcessPtoTenureCrossings as ProcessAction;
use Illuminate\Console\Command;

/**
 * Daily PTO accrual (ADR-0016): tier-crossing top-ups + hire-anniversary
 * forfeit/refresh for every active W-2 employee. Idempotent.
 */
class ProcessPtoTenureCrossings extends Command
{
    protected $signature = 'pto:process-crossings';

    protected $description = 'Apply PTO tier crossings and anniversary refreshes for active staff';

    public function handle(ProcessAction $action): int
    {
        $stats = $action->handle();

        $this->info("PTO processed: {$stats['processed']} staff, {$stats['anniversaries']} anniversaries, {$stats['crossings']} tier crossings.");

        return self::SUCCESS;
    }
}
