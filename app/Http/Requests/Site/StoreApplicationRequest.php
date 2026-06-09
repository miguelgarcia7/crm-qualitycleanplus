<?php

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a public employment application (Phase 08b-i). Mirrors the legacy
 * intake form's required set. Public endpoint — anyone may submit.
 */
class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'second_last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'address' => ['required', 'string', 'max:255'],
            'apartment_number' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:255'],
            'zip' => ['required', 'string', 'max:10'],

            'position' => ['required', 'string', 'max:255'],
            'desired_salary' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'dob' => ['required', 'date'],

            'transportation' => ['required', 'in:0,1'],
            'work_at_qcp' => ['required', 'in:0,1'],
            'work_at_qcp_explain' => ['nullable', 'string', 'max:255'],
            'usa_citizen' => ['required', 'in:0,1'],
            'eligible_to_work' => ['nullable', 'in:0,1'],
            'another_staff_agency' => ['required', 'in:0,1'],
            'non_complete' => ['nullable', 'string', 'max:255'],
            'convicted_felon' => ['required', 'in:0,1'],
            'felony_conviction' => ['nullable', 'string', 'max:5000'],

            'full_name' => ['nullable', 'string', 'max:255'],
            'emergency_phone' => ['nullable', 'string', 'max:32'],
            'relationship' => ['nullable', 'string', 'max:255'],
            'full_address' => ['nullable', 'string', 'max:255'],

            'acknowledgement' => ['accepted'],
            'job_id' => ['nullable', 'integer', 'exists:job_postings,id'],
        ];
    }
}
