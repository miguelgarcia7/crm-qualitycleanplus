<?php

namespace App\Http\Requests\Property;

use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $property = $this->route('property');

        return $property instanceof Property
            && ($this->user()?->can('manageAssignments', $property) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $property = $this->route('property');
        $propertyId = $property instanceof Property ? $property->getKey() : null;

        return [
            'person_id' => ['required', 'integer', Rule::exists('people', 'id')->whereNull('deleted_at')],
            'role' => [
                'required',
                Rule::enum(PropertyAssignmentRole::class),
                Rule::unique('property_assignments', 'role')
                    ->where('property_id', $propertyId)
                    ->where('person_id', $this->integer('person_id'))
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
