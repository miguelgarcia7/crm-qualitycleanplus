<?php

namespace App\Http\Requests\Time;

use App\Domain\Time\Enums\TimeEntryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller authorizes via property scoping
    }

    /**
     * No `date`: moving a punch to another day could land it in a different
     * payroll period, which is a delete-and-re-add rather than an edit.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'entry_type' => ['required', Rule::enum(TimeEntryType::class)],
        ];
    }
}
