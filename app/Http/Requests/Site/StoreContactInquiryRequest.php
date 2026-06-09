<?php

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a public marketing contact submission (Job Seekers or Business).
 * Public endpoint — anyone may submit.
 */
class StoreContactInquiryRequest extends FormRequest
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
            'contact_first_name' => ['required', 'string', 'max:255'],
            'contact_last_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'contact_company' => ['nullable', 'string', 'max:255'],
            'contact_address' => ['nullable', 'string', 'max:255'],
            'contact_city' => ['nullable', 'string', 'max:255'],
            'contact_state' => ['nullable', 'string', 'max:255'],
            'contact_zip' => ['nullable', 'string', 'max:10'],
            'contact_inquiry_type' => ['nullable', 'string', 'max:255'],
            'contact_call_back_time' => ['nullable', 'string', 'max:255'],
            'contact_message' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
