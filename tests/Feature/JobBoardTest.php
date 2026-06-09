<?php

use App\Domain\PropertyBible\Models\Property;
use App\Domain\Recruiting\Models\JobPosting;

it('lists only published postings on the job board', function () {
    $published = JobPosting::factory()->published()->create([
        'title' => 'Night Auditor',
        'location_label' => 'Dallas, TX',
    ]);
    JobPosting::factory()->create(['title' => 'Draft Role']);          // draft
    JobPosting::factory()->closed()->create(['title' => 'Closed Role']);

    $this->get(main('/job-openings'))->assertOk()
        ->assertSee('Night Auditor')
        ->assertSee('Dallas, TX')
        ->assertSee('/application/'.$published->slug, false)
        ->assertDontSee('Draft Role')
        ->assertDontSee('Closed Role');
});

it('shows the linked property name as the location when set', function () {
    $property = Property::factory()->create(['name' => 'Marriott Downtown']);
    JobPosting::factory()->published()->create(['title' => 'Houseman', 'property_id' => $property->id, 'location_label' => null]);

    $this->get(main('/job-openings'))->assertOk()->assertSee('Marriott Downtown');
});

it('shows an empty state when there are no published postings', function () {
    $this->get(main('/job-openings'))->assertOk()->assertSee('no open positions');
});
