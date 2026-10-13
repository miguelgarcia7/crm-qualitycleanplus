<?php

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\PropertyBible\Models\Property;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

afterEach(fn () => CarbonImmutable::setTestNow());

it('judges overdue by the property\'s own date, in the model and in SQL alike', function () {
    // 11 pm Monday in Los Angeles is already Tuesday in Chicago and in UTC.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-12 23:00', 'America/Los_Angeles'));
    Carbon::setTestNow(CarbonImmutable::now());

    $west = Property::factory()->create(['timezone' => 'America/Los_Angeles']);
    $central = Property::factory()->create(['timezone' => 'America/Chicago']);

    // Both due Monday the 12th.
    $westInvoice = Invoice::factory()->create(['property_id' => $west->id, 'status' => InvoiceStatus::InvoiceSent, 'due_date' => '2026-10-12']);
    $centralInvoice = Invoice::factory()->create(['property_id' => $central->id, 'status' => InvoiceStatus::InvoiceSent, 'due_date' => '2026-10-12']);

    // Still Monday in LA: due today, so on time. Tuesday in Chicago: a day late.
    expect($westInvoice->fresh()->isOverdue())->toBeFalse()
        ->and($westInvoice->fresh()->daysPastDue())->toBe(0)
        ->and($centralInvoice->fresh()->isOverdue())->toBeTrue()
        ->and($centralInvoice->fresh()->daysOverdue())->toBe(1)
        ->and(Invoice::query()->overdue()->pluck('id')->all())->toBe([$centralInvoice->id]);

    Carbon::setTestNow();
});

it('never calls a paid or voided invoice overdue', function () {
    $property = Property::factory()->create(['timezone' => 'America/Chicago']);
    Invoice::factory()->create(['property_id' => $property->id, 'status' => InvoiceStatus::InvoiceSent, 'due_date' => now()->subDays(10), 'paid_at' => now()->subDays(12)]);
    Invoice::factory()->create(['property_id' => $property->id, 'status' => InvoiceStatus::Voided, 'due_date' => now()->subDays(10)]);

    expect(Invoice::query()->overdue()->count())->toBe(0);
});
