<?php

use App\Domain\Inventory\Actions\CreatePurchaseOrder;
use App\Domain\Inventory\Actions\ManualStockOut;
use App\Domain\Inventory\Actions\ReceivePurchaseOrder;
use App\Domain\Inventory\Actions\RecordStockMovement;
use App\Domain\Inventory\Actions\ReturnEquipment;
use App\Domain\Inventory\Actions\ReturnToStock;
use App\Domain\Inventory\Enums\EquipmentAssignmentStatus;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use App\Domain\Inventory\Models\ContractorChargeSchedule;
use App\Domain\Inventory\Models\EquipmentAssignment;
use App\Domain\Inventory\Models\ItemVariant;
use Illuminate\Validation\ValidationException;

it('keeps current_stock in sync across inflow and outflow movements', function () {
    $variant = ItemVariant::factory()->create(['current_stock' => 0]);
    $record = app(RecordStockMovement::class);

    $record->handle($variant, MovementType::DirectReceipt, 10, ['reason' => 'init']);
    expect($variant->fresh()->current_stock)->toBe(10);

    $record->handle($variant, MovementType::Issuance, -3);
    expect($variant->fresh()->current_stock)->toBe(7);
});

it('prevents negative stock on an outflow', function () {
    $variant = ItemVariant::factory()->create(['current_stock' => 2]);

    expect(fn () => app(RecordStockMovement::class)->handle($variant, MovementType::Issuance, -5))
        ->toThrow(ValidationException::class);

    expect($variant->fresh()->current_stock)->toBe(2);
});

it('rejects a movement whose sign does not match its type', function () {
    $variant = ItemVariant::factory()->create(['current_stock' => 5]);

    expect(fn () => app(RecordStockMovement::class)->handle($variant, MovementType::DirectReceipt, -1))
        ->toThrow(ValidationException::class);
});

it('manual stock out reduces stock and creates no charge (ADR-0015)', function () {
    $variant = ItemVariant::factory()->create(['current_stock' => 10]);

    app(ManualStockOut::class)->handle($variant, 3, 'Admin grabbed a few', [], null);

    expect($variant->fresh()->current_stock)->toBe(7)
        ->and(ContractorChargeSchedule::count())->toBe(0);
});

it('returns stock to inventory', function () {
    $variant = ItemVariant::factory()->create(['current_stock' => 4]);

    app(ReturnToStock::class)->handle($variant, 2, 'Unused', null, null);

    expect($variant->fresh()->current_stock)->toBe(6);
});

it('receives a purchase order and increments stock', function () {
    $variant = ItemVariant::factory()->create(['current_stock' => 0]);

    $po = app(CreatePurchaseOrder::class)->handle([
        'items' => [['item_variant_id' => $variant->id, 'quantity' => 8]],
    ], null);

    app(ReceivePurchaseOrder::class)->handle($po, null);

    expect($variant->fresh()->current_stock)->toBe(8)
        ->and($po->fresh()->status)->toBe(PurchaseOrderStatus::Received);
});

it('returns equipment back into stock', function () {
    $variant = ItemVariant::factory()->create(['current_stock' => 0]);
    $assignment = EquipmentAssignment::factory()->create(['item_variant_id' => $variant->id, 'quantity' => 1]);

    app(ReturnEquipment::class)->handle($assignment, true, 'Returned in good condition', null);

    expect($assignment->fresh()->status)->toBe(EquipmentAssignmentStatus::Returned)
        ->and($variant->fresh()->current_stock)->toBe(1);
});

it('marks unreturned equipment as lost without restocking', function () {
    $variant = ItemVariant::factory()->create(['current_stock' => 0]);
    $assignment = EquipmentAssignment::factory()->create(['item_variant_id' => $variant->id, 'quantity' => 1]);

    app(ReturnEquipment::class)->handle($assignment, false, 'Never returned', null);

    expect($assignment->fresh()->status)->toBe(EquipmentAssignmentStatus::Lost)
        ->and($variant->fresh()->current_stock)->toBe(0);
});
