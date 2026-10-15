<?php

namespace App\Console\Commands;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Contract;
use App\Notifications\ContractExpiringNotification;
use App\Notifications\ContractThreeYearsOldNotification;
use App\Support\CompanyTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Daily check that alerts ownership + payroll about contracts approaching
 * expiration at the 30-day and 14-day marks (20-domain/property-bible.md §4),
 * and tells Admin when a contract turns three years old — every contract,
 * active or replaced, counted from its effective date (or its upload day when
 * it has none). Contracts are kept, never deleted; that notice is a nudge to
 * review one, sent once. A run missed by up to a week still sends it; older
 * contracts that were already past three years aren't announced in a burst.
 */
class ContractExpirationCheck extends Command
{
    protected $signature = 'contracts:expiration-check';

    protected $description = 'Notify ownership + payroll about contracts expiring in 30 or 14 days, and Admin about contracts turning 3 years old';

    /** Days-out thresholds that trigger an alert. */
    private const THRESHOLDS = [30, 14];

    public function handle(): int
    {
        $recipients = Person::query()->permission('bible.contracts.view')->get();

        if ($recipients->isEmpty()) {
            $this->warn('No recipients hold bible.contracts.view; nothing sent.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach (self::THRESHOLDS as $days) {
            $target = CompanyTime::now()->addDays($days)->toDateString();

            $contracts = Contract::query()
                ->where('is_active', true)
                ->whereDate('expiration_date', $target)
                ->get();

            foreach ($contracts as $contract) {
                Notification::send($recipients, new ContractExpiringNotification($contract, $days));
                $sent++;
            }
        }

        $sent += $this->threeYearsOld();

        $this->info("Contract expiration check complete: {$sent} contract alert(s) dispatched.");

        return self::SUCCESS;
    }

    /** Contracts that turned three years old in the last week and haven't been announced. */
    private function threeYearsOld(): int
    {
        $admins = Person::query()->role(['admin', 'super_admin'])->get();
        if ($admins->isEmpty()) {
            return 0;
        }

        $latest = CompanyTime::now()->subYears(3);
        $earliest = $latest->subDays(7)->startOfDay();

        $contracts = Contract::query()
            ->with('property:id,name')
            ->whereNull('three_year_notice_at')
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->whereNotNull('effective_date')
                    ->whereDate('effective_date', '>=', $earliest->toDateString())
                    ->whereDate('effective_date', '<=', $latest->toDateString()))
                ->orWhere(fn ($q) => $q->whereNull('effective_date')
                    ->whereBetween('created_at', [$earliest->utc(), $latest->endOfDay()->utc()])))
            ->get();

        foreach ($contracts as $contract) {
            Notification::send($admins, new ContractThreeYearsOldNotification($contract));
            $contract->forceFill(['three_year_notice_at' => now()])->save();
        }

        return $contracts->count();
    }
}
