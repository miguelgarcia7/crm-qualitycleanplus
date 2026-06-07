<?php

namespace App\Domain\PropertyBible\Actions;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\PropertyPositionRate;
use Illuminate\Support\Carbon;

class AddPropertyPositionRate
{
    use LogsPropertyActivity;

    /**
     * Rates are append-only: a change adds a new effective-dated row and closes
     * out the prior open row. Rates arrive as dollars and are stored as cents.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Property $property, array $data, ?Person $creator): PropertyPositionRate
    {
        $effectiveDate = Carbon::parse($data['effective_date']);

        $property->positionRates()
            ->where('position_id', $data['position_id'])
            ->whereNull('end_date')
            ->where('effective_date', '<', $effectiveDate)
            ->update(['end_date' => $effectiveDate->copy()->subDay()->toDateString()]);

        $rate = $property->positionRates()->create([
            'position_id' => $data['position_id'],
            'pay_rate' => $this->toCents($data['pay_rate']),
            'bill_rate' => $this->toCents($data['bill_rate']),
            'ot_pay_rate' => $this->toCents($data['ot_pay_rate']),
            'ot_bill_rate' => $this->toCents($data['ot_bill_rate']),
            'effective_date' => $effectiveDate->toDateString(),
            'is_active' => true,
            'notes' => $data['notes'] ?? null,
            'created_by' => $creator?->id,
        ]);

        $position = Position::findOrFail($data['position_id'])->name;
        $this->logProperty($property, 'updated', "Added a rate for \"{$position}\"");

        return $rate;
    }

    private function toCents(float|int|string $dollars): int
    {
        return (int) round(((float) $dollars) * 100);
    }
}
