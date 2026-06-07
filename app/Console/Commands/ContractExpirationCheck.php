<?php

namespace App\Console\Commands;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Contract;
use App\Notifications\ContractExpiringNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Daily check that alerts ownership + payroll about contracts approaching
 * expiration at the 30-day and 14-day marks (20-domain/property-bible.md §4).
 */
class ContractExpirationCheck extends Command
{
    protected $signature = 'contracts:expiration-check';

    protected $description = 'Notify ownership + payroll about contracts expiring in 30 or 14 days';

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
            $target = now()->addDays($days)->toDateString();

            $contracts = Contract::query()
                ->where('is_active', true)
                ->whereDate('expiration_date', $target)
                ->get();

            foreach ($contracts as $contract) {
                Notification::send($recipients, new ContractExpiringNotification($contract, $days));
                $sent++;
            }
        }

        $this->info("Contract expiration check complete: {$sent} contract alert(s) dispatched.");

        return self::SUCCESS;
    }
}
