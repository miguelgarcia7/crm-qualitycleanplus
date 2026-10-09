<?php

namespace App\Domain\Recruiting\Actions;

use App\Domain\People\Models\Person;
use App\Domain\Recruiting\Models\JobApplication;
use App\Notifications\ApplicationReceived;
use Illuminate\Support\Facades\Notification;

/**
 * Tells every recruiter that an application came in through the public site.
 * Called by the site's application form only, never by imports, so loading
 * historical applications doesn't flood anyone's bell.
 */
class NotifyRecruitersOfApplication
{
    public function handle(JobApplication $application): void
    {
        // Not Person::role(): it throws when the role doesn't exist, and a missing
        // role must never fail the applicant's submission.
        $recruiters = Person::query()->whereHas('roles', fn ($q) => $q->where('name', 'recruiter'))->get();

        if ($recruiters->isNotEmpty()) {
            Notification::send($recruiters, new ApplicationReceived($application));
        }
    }
}
