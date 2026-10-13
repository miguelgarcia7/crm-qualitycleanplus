<?php

use App\Domain\WorkOrders\Models\MoreStaffRequest;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('shows office timestamps on the company clock, not UTC', function () {
    // 23:30 UTC is 6:30 PM Central the same day.
    $this->travelTo(CarbonImmutable::parse('2026-10-12 23:30', 'UTC'));
    activity('testing')->log('Evening approval');

    $this->actingAs(person('admin'))
        ->get(main('/admin/audit?q=Evening'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('entries.0.description', 'Evening approval')
            ->where('entries.0.created_at', 'Mon, Oct 12, 2026 6:30 PM'));
});

it('dates an evening request by the company day, not the UTC day', function () {
    // 03:00 UTC on the 13th is 10 PM Central on the 12th.
    $this->travelTo(CarbonImmutable::parse('2026-10-13 03:00', 'UTC'));
    MoreStaffRequest::factory()->create(['by_date' => '2026-10-20']);

    $this->actingAs(person('admin'))
        ->get(main('/admin/staffing-requests'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('requests.0.requested_at', 'Oct 12')
            // Days left count from the company's today (the 12th), not UTC's.
            ->where('requests.0.days_left', 8));
});
