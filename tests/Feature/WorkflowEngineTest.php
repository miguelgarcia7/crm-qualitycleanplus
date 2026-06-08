<?php

use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\Item;
use App\Domain\Inventory\Models\ItemVariant;
use App\Domain\Inventory\Models\SupplyRequest;
use App\Domain\People\Models\Person;
use App\Domain\Workflows\Actions\CancelWorkflow;
use App\Domain\Workflows\Actions\StartWorkflow;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowStep;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(InventorySeeder::class);
});

/** @return array{request: SupplyRequest, workflow: Workflow, variant: ItemVariant} */
function existingItemRequest(?Person $initiator = null, int $stock = 10): array
{
    $initiator ??= person('office_manager');
    $category = Category::query()->where('slug', 'office_supplies')->firstOrFail();
    $item = Item::factory()->create(['category_id' => $category->id]);
    $variant = ItemVariant::factory()->create(['item_id' => $item->id, 'current_stock' => $stock]);

    $request = SupplyRequest::create([
        'category_id' => $category->id,
        'item_variant_id' => $variant->id,
        'beneficiary_type' => 'self',
        'quantity' => 1,
        'requested_by' => $initiator->id,
        'status' => 'pending',
    ]);

    $workflow = app(StartWorkflow::class)->handle(WorkflowType::SupplyRequest, $request, $initiator);
    $request->update(['workflow_id' => $workflow->id]);

    return ['request' => $request, 'workflow' => $workflow, 'variant' => $variant];
}

it('starts a supply-request workflow with a pending front-desk fulfill step', function () {
    ['workflow' => $workflow] = existingItemRequest();

    expect($workflow->status)->toBe(WorkflowStatus::InProgress);
    $step = $workflow->currentStep();
    expect($step->step_key)->toBe('fulfill')
        ->and($step->assigned_role)->toBe('front_desk');
});

it('surfaces the step in the assignee role inbox but not to others', function () {
    existingItemRequest();

    expect(WorkflowStep::openForPerson(person('front_desk'))->count())->toBe(1)
        ->and(WorkflowStep::openForPerson(person('recruiter'))->count())->toBe(0);
});

it('completes the fulfill step, issuing stock and finishing the workflow', function () {
    ['request' => $request, 'workflow' => $workflow, 'variant' => $variant] = existingItemRequest(stock: 10);
    $step = $workflow->currentStep();

    $this->actingAs(person('front_desk'))
        ->post(main("/admin/workflow-steps/{$step->id}/complete"))
        ->assertRedirect();

    expect($request->fresh()->status->value)->toBe('fulfilled')
        ->and($variant->fresh()->current_stock)->toBe(9)
        ->and($workflow->fresh()->status)->toBe(WorkflowStatus::Completed);
});

it('forbids a non-assignee from acting on a step', function () {
    ['workflow' => $workflow] = existingItemRequest();
    $step = $workflow->currentStep();

    $this->actingAs(person('recruiter'))
        ->post(main("/admin/workflow-steps/{$step->id}/complete"))
        ->assertForbidden();
});

it('routes a new-item request through admin approval then fulfillment', function () {
    $category = Category::query()->where('slug', 'office_supplies')->firstOrFail();
    $request = SupplyRequest::create([
        'category_id' => $category->id,
        'item_variant_id' => null,
        'beneficiary_type' => 'self',
        'quantity' => 1,
        'proposed_item_name' => 'Ergonomic Stapler',
        'requested_by' => person('office_manager')->id,
        'status' => 'pending',
    ]);
    $workflow = app(StartWorkflow::class)->handle(WorkflowType::SupplyRequest, $request, person('office_manager'));
    $request->update(['workflow_id' => $workflow->id]);

    expect($workflow->currentStep()->step_key)->toBe('approve_new_item');

    $this->actingAs(person('admin'))
        ->post(main("/admin/workflow-steps/{$workflow->currentStep()->id}/complete"))
        ->assertRedirect();

    expect($request->fresh()->status->value)->toBe('approved')
        ->and($workflow->fresh()->currentStep()->step_key)->toBe('fulfill');
});

it('denies a new-item request when the approval step is rejected', function () {
    $category = Category::query()->where('slug', 'office_supplies')->firstOrFail();
    $request = SupplyRequest::create([
        'category_id' => $category->id,
        'beneficiary_type' => 'self',
        'quantity' => 1,
        'proposed_item_name' => 'Gold Stapler',
        'requested_by' => person('office_manager')->id,
        'status' => 'pending',
    ]);
    $workflow = app(StartWorkflow::class)->handle(WorkflowType::SupplyRequest, $request, person('office_manager'));
    $request->update(['workflow_id' => $workflow->id]);

    $this->actingAs(person('admin'))
        ->post(main("/admin/workflow-steps/{$workflow->currentStep()->id}/reject"), ['reason' => 'Too pricey'])
        ->assertRedirect();

    expect($request->fresh()->status->value)->toBe('denied')
        ->and($workflow->fresh()->status)->toBe(WorkflowStatus::Rejected);
});

it('cancels an in-flight workflow', function () {
    ['workflow' => $workflow] = existingItemRequest();

    app(CancelWorkflow::class)->handle($workflow, person('admin'), 'No longer needed');

    expect($workflow->fresh()->status)->toBe(WorkflowStatus::Cancelled);
});
