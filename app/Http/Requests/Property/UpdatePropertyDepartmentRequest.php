<?php

namespace App\Http\Requests\Property;

use App\Domain\PropertyBible\Models\Property;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePropertyDepartmentRequest extends FormRequest
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
        return [
            'manager_name' => ['nullable', 'string', 'max:255'],
            'manager_phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['boolean'],
        ];
    }
}
