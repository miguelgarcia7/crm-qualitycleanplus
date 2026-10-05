<?php

namespace App\Domain\Marketing\Policies;

use App\Domain\Marketing\Models\Testimonial;
use App\Domain\People\Models\Person;

/**
 * Marketing-site testimonials — one `marketing.testimonials.manage` permission
 * covers the whole lifecycle (create/edit/hide/delete).
 */
class TestimonialPolicy
{
    public function viewAny(Person $user): bool
    {
        return $user->can('marketing.testimonials.manage');
    }

    public function create(Person $user): bool
    {
        return $user->can('marketing.testimonials.manage');
    }

    public function update(Person $user, Testimonial $testimonial): bool
    {
        return $user->can('marketing.testimonials.manage');
    }

    public function delete(Person $user, Testimonial $testimonial): bool
    {
        return $user->can('marketing.testimonials.manage');
    }
}
