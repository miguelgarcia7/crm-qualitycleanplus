<?php

namespace App\Domain\Marketing\Policies;

use App\Domain\Marketing\Models\ContactInquiry;
use App\Domain\People\Models\Person;

/**
 * Website leads in the back-office inbox — one `marketing.inquiries.manage`
 * permission covers reading them, marking them handled, and deleting spam.
 */
class ContactInquiryPolicy
{
    public function viewAny(Person $user): bool
    {
        return $user->can('marketing.inquiries.manage');
    }

    public function update(Person $user, ContactInquiry $inquiry): bool
    {
        return $user->can('marketing.inquiries.manage');
    }

    public function delete(Person $user, ContactInquiry $inquiry): bool
    {
        return $user->can('marketing.inquiries.manage');
    }
}
