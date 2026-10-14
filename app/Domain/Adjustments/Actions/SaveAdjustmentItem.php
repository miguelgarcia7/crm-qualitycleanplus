<?php

namespace App\Domain\Adjustments\Actions;

use App\Domain\Adjustments\Enums\AdjustmentType;
use App\Domain\Adjustments\Models\AdjustmentItem;
use App\Domain\People\Models\Person;

/**
 * Adds or changes an item on the adjustment list recruiters pick from
 * (Billing → Adjustment Items). Each change is written to the item's History
 * in plain words. Adjustments already added keep their own type, amount and
 * billing, so editing an item only changes new ones.
 */
class SaveAdjustmentItem
{
    /**
     * @param  array{name: string, type: string, default_value: int|null, is_billable: bool, active: bool}  $data
     */
    public function handle(?AdjustmentItem $item, array $data, Person $by): AdjustmentItem
    {
        $type = AdjustmentType::from($data['type']);
        $attributes = [
            'name' => $data['name'],
            'type' => $type,
            'default_value' => $data['default_value'],
            'is_billable' => $type === AdjustmentType::Incentive && $data['is_billable'],
            'active' => $data['active'],
        ];

        if ($item === null) {
            $item = AdjustmentItem::create($attributes);
            activity()->performedOn($item)->causedBy($by)->event('created')->log('Added');

            return $item;
        }

        $before = $item->replicate();
        $item->update($attributes);

        $changes = $this->describe($before, $item);
        if ($changes !== []) {
            activity()->performedOn($item)->causedBy($by)->event('updated')->log(implode('; ', $changes));
        }

        return $item;
    }

    /**
     * @return list<string>
     */
    private function describe(AdjustmentItem $before, AdjustmentItem $after): array
    {
        $out = [];
        if ($before->name !== $after->name) {
            $out[] = "Name {$before->name} → {$after->name}";
        }
        if ($before->type !== $after->type) {
            $out[] = 'Type '.$before->type->value.' → '.$after->type->value;
        }
        if ($before->default_value !== $after->default_value) {
            $out[] = 'Default amount '.$this->money($before->default_value).' → '.$this->money($after->default_value);
        }
        if ($before->is_billable !== $after->is_billable) {
            $out[] = $after->is_billable ? 'Now billed to the hotel' : 'No longer billed to the hotel';
        }
        if ($before->active !== $after->active) {
            $out[] = $after->active ? 'Made active again' : 'Made inactive';
        }

        return $out;
    }

    private function money(?int $cents): string
    {
        return $cents === null ? 'none' : '$'.number_format($cents / 100, 2);
    }
}
