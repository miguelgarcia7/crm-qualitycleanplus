<?php

use App\Domain\Dashboards\Services\FrontDeskHome;
use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use App\Domain\Inventory\Models\EquipmentAssignment;
use App\Domain\Inventory\Models\Item;
use App\Domain\Inventory\Models\ItemVariant;
use App\Domain\Inventory\Models\PurchaseOrder;
use App\Domain\Inventory\Models\SupplyRequest;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\Recruiting\Models\JobApplication;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Notification::fake();
    $this->seed(RolePermissionSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-12 14:00:00', 'UTC'));
});

function frontDesk(?Person $user = null): array
{
    return app(FrontDeskHome::class)->build($user ?? person('front_desk'));
}

it('gives the front desk its own dashboard', function () {
    $this->actingAs(person('front_desk'))->get(main('/admin/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/dashboard/front-desk')
            ->where('home.actions', ['searchPeople' => true, 'searchApplicants' => true, 'receive' => true]));
});

it('lists supply requests to fill and says when stock is short', function () {
    $polo = ItemVariant::factory()->create(['item_id' => Item::factory()->create(['name' => 'Polo shirt'])->id, 'size' => 'M', 'current_stock' => 1]);
    SupplyRequest::factory()->create(['item_variant_id' => $polo->id, 'quantity' => 2]);
    SupplyRequest::factory()->create(['item_variant_id' => ItemVariant::factory()->create(['current_stock' => 10])->id]);

    $rows = frontDesk()['supplies']['rows'];

    expect($rows[0]['title'])->toContain('Polo shirt (M) × 2')
        ->and($rows[0]['stock'])->toBe(['text' => 'only 1 in stock', 'short' => true])
        ->and($rows[1]['stock'])->toBe(['text' => 'in stock', 'short' => false]);
});

it('lists purchase orders on the way', function () {
    $order = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::Ordered, 'ordered_at' => '2026-10-06 15:00:00']);
    $order->items()->create(['item_variant_id' => ItemVariant::factory()->create(['item_id' => Item::factory()->create(['name' => 'Polo shirt'])->id])->id, 'quantity' => 24]);
    PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::Received]);

    $deliveries = frontDesk()['deliveries'];

    expect($deliveries['total'])->toBe(1)
        ->and($deliveries['rows'][0]['detail'])->toBe('Polo shirt · 24 units · ordered Oct 6');
});

it("shows the front desk's termination steps with the equipment to get back", function () {
    $contractor = Person::factory()->create(['name' => 'Tom Nguyen', 'status' => PersonStatus::ContractorActive]);
    WorkOrder::factory()->create(['person_id' => $contractor->id]);
    EquipmentAssignment::factory()->create(['assigned_to_person_id' => $contractor->id, 'quantity' => 2, 'item_variant_id' => ItemVariant::factory()->create(['item_id' => Item::factory()->create(['name' => 'Polo shirt'])->id])->id]);

    $this->actingAs(person('hr'))->post(main('/admin/terminations'), [
        'person_id' => $contractor->id,
        'effective_date' => now()->toDateString(),
        'termination_type' => 'voluntary',
        'reason_category' => 'resignation',
    ])->assertRedirect();

    $steps = frontDesk()['terminations'];

    expect($steps['total'])->toBe(1)
        ->and($steps['rows'][0])->toMatchArray(['title' => 'Tom Nguyen · recover equipment', 'items' => ['2 × Polo shirt']]);
});

it('lists applicants missing documents, each opening their checklist', function () {
    $applicant = Person::factory()->create(['name' => 'Marcus Hill', 'status' => PersonStatus::Applicant]);
    $application = JobApplication::factory()->reviewing()->create(['person_id' => $applicant->id]);

    $documents = frontDesk()['documents'];

    expect($documents['total'])->toBe(1)
        ->and($documents['rows'][0]['name'])->toBe('Marcus Hill')
        ->and($documents['rows'][0]['missing'])->toContain('I-9', 'W-9')
        ->and($documents['rows'][0]['href'])->toBe("/admin/applicants/{$application->id}#onboarding");
});

it('hides what the role cannot act on', function () {
    // HR can't fill supply requests or receive deliveries.
    $home = frontDesk(person('hr'));

    expect($home['supplies'])->toBeNull()
        ->and($home['deliveries'])->toBeNull()
        ->and($home['actions']['receive'])->toBeFalse();
});

it('opens the applicants list already searched', function () {
    $this->actingAs(person('front_desk'))->get(main('/admin/applicants?status=all&search=Marcus'))->assertOk();
});
