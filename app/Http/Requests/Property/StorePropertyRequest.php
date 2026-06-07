<?php

namespace App\Http\Requests\Property;

use App\Concerns\PropertyValidationRules;
use App\Domain\PropertyBible\Models\Property;
use Illuminate\Foundation\Http\FormRequest;

class StorePropertyRequest extends FormRequest
{
    use PropertyValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Property::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->propertyRules();
    }
}
