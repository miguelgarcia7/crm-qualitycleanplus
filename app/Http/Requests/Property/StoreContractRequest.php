<?php

namespace App\Http\Requests\Property;

use App\Domain\PropertyBible\Enums\ContractType;
use App\Domain\PropertyBible\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Contract::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ContractType::class)],
            'effective_date' => ['nullable', 'date'],
            'expiration_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'document' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:20480'], // 20 MB
        ];
    }
}
