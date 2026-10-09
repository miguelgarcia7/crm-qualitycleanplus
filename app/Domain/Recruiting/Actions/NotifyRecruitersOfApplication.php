<?php

namespace App\Domain\Recruiting\Actions;

use App\Domain\People\Models\Person;
use App\Domain\Recruiting\Models\JobApplication;
use App\Notifications\ApplicationReceived;
use Illuminate\Support\Facades\Notification;

/**
 * Tells every recruiter and office manager that an application came in through
 * the public site.
 * Called by the site's application form only, never by imports, so loading
 * historical applications doesn't flood anyone's bell.
 */
class NotifyRecruitersOfApplication
{
    /** Roles that hear about every new application. */
    private const ROLES = ['recruiter', 'office_manager'];

    public function handle(JobApplication $application): void
    {
        // Not Person::role(): it throws when a role doesn't exist, and a missing
        // role must never fail the applicant's submission.
        $recipients = Person::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', self::ROLES))
            ->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ApplicationReceived($application));
        }
    }
}
