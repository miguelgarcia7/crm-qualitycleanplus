<?php

use App\Enums\PersonStatus;
use App\Models\Person;

it('casts status to the PersonStatus enum', function () {
    $person = Person::factory()->create(['status' => PersonStatus::ContractorActive]);

    expect($person->refresh()->status)->toBe(PersonStatus::ContractorActive)
        ->and($person->status->isContractor())->toBeTrue();
});

it('soft deletes', function () {
    $person = Person::factory()->create();
    $person->delete();

    expect(Person::count())->toBe(0)
        ->and(Person::withTrashed()->count())->toBe(1);
});

it('scopes by legal hold', function () {
    $held = Person::factory()->create();
    Person::factory()->create();

    $held->setLegalHold('litigation');

    expect(Person::onLegalHold()->count())->toBe(1)
        ->and(Person::notOnLegalHold()->count())->toBe(1);
});

it('forbids changing application_date once set', function () {
    $person = Person::factory()->create(['application_date' => now()->subYear()]);

    $person->update(['application_date' => now()]);
})->throws(RuntimeException::class);

it('allows setting application_date when previously null', function () {
    $person = Person::factory()->create(['application_date' => null]);

    $person->update(['application_date' => now()]);

    expect($person->refresh()->application_date)->not->toBeNull();
});
