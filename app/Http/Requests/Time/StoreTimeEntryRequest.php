<?php

namespace App\Http\Requests\Time;

use App\Domain\Time\Enums\TimeEntryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller authorizes via property scoping
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'entry_type' => ['required', Rule::enum(TimeEntryType::class)],
        ];
    }
}
