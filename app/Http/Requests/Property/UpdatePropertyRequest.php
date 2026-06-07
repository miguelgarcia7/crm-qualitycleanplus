<?php

namespace App\Http\Requests\Property;

use App\Concerns\PropertyValidationRules;
use App\Domain\PropertyBible\Models\Property;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePropertyRequest extends FormRequest
{
    use PropertyValidationRules;

    public function authorize(): bool
    {
        $property = $this->route('property');

        return $property instanceof Property
            && ($this->user()?->can('update', $property) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->propertyRules();
    }
}
