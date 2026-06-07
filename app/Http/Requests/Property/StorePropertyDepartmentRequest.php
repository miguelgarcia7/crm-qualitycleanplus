<?php

namespace App\Http\Requests\Property;

use App\Domain\PropertyBible\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $property = $this->route('property');

        return $property instanceof Property
            && ($this->user()?->can('editDepartments', $property) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $property = $this->route('property');
        $propertyId = $property instanceof Property ? $property->getKey() : null;

        return [
            'department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id'),
                Rule::unique('property_departments', 'department_id')
                    ->where('property_id', $propertyId)
                    ->whereNull('deleted_at'),
            ],
            'manager_name' => ['nullable', 'string', 'max:255'],
            'manager_phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['boolean'],
        ];
    }
}
