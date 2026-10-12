<?php

namespace App\Domain\Dashboards\Support;

use App\Domain\People\Models\Person;

/**
 * Banners a dashboard shows only when something is broken, and only to people
 * whose work it affects.
 */
class SystemAlerts
{
    /**
     * @return list<array<string, string>>
     */
    public function for(Person $user): array
    {
        $alerts = [];
        $mailer = (string) config('mail.default');

        // Invoices record a recipient and flip to sent, but a log/array mailer
        // means nothing left the building.
        if (in_array($mailer, ['log', 'array'], true) && $user->can('invoices.view')) {
            $alerts[] = [
                'key' => 'mail-undelivered',
                'title' => "Invoice email isn't being delivered",
                'body' => "The mailer is set to {$mailer}, so invoices marked sent never reach a client.",
                'actionLabel' => 'View invoices',
                'actionHref' => '/admin/invoices',
            ];
        }

        return $alerts;
    }
}
