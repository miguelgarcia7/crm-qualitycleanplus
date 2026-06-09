<?php

namespace App\Domain\Recruiting\Policies;

use App\Domain\People\Models\Person;
use App\Domain\Recruiting\Models\JobPosting;

/**
 * Back-office job-posting management (Phase 08b-ii) — one `job_postings.manage`
 * permission covers the whole lifecycle (create/edit/publish/close/delete).
 */
class JobPostingPolicy
{
    public function viewAny(Person $user): bool
    {
        return $user->can('job_postings.manage');
    }

    public function create(Person $user): bool
    {
        return $user->can('job_postings.manage');
    }

    public function update(Person $user, JobPosting $posting): bool
    {
        return $user->can('job_postings.manage');
    }

    public function delete(Person $user, JobPosting $posting): bool
    {
        return $user->can('job_postings.manage');
    }
}
