<?php

namespace App\Http\Requests\Property;

use App\Concerns\PropertyValidationRules;
use App\Domain\PropertyBible\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
     * Each Settings card saves only its own fields, so every rule applies only
     * when its field is sent — a sent field is still checked in full (a blank
     * name or tax rate is refused).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_map(fn (array $rules): array => ['sometimes', ...$rules], $this->propertyRules());
    }

    /**
     * Clearing the location while QR clock-in stays on would leave a live
     * poster with no fence to judge punches against.
     *
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $property = $this->route('property');
                $clearsLocation = $this->exists('latitude') && blank($this->input('latitude'));
                $qrOn = $this->exists('qr_clock_enabled')
                    ? $this->boolean('qr_clock_enabled')
                    : ($property instanceof Property && $property->qr_clock_enabled);

                if ($clearsLocation && $qrOn) {
                    $validator->errors()->add('latitude', 'Turn off QR clock-in before clearing the location.');
                }
            },
        ];
    }
}
