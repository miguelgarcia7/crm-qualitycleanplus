<?php

namespace App\Http\Controllers;

use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;
use App\Domain\PropertyBible\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Per-property job codes (the client's GL code per position), edited on the
 * Bible's Rates tab. One transactional save: each submitted row upserts its
 * code or, when blank, removes it — rows not submitted are untouched (the
 * legacy app's destructive sync() silently dropped codes for positions whose
 * work orders had gone). Codes only affect invoices generated AFTER the
 * change; issued invoices carry their own frozen snapshot (ADR-0006).
 */
class PropertyJobCodeController extends Controller
{
    use LogsPropertyActivity;

    public function sync(Request $request, Property $property): RedirectResponse
    {
        $this->authorize('editRates', $property);

        /** @var array{codes: list<array{position_id: int, job_code: string|null}>} $validated */
        $validated = $request->validate([
            'codes' => ['present', 'array'],
            'codes.*.position_id' => ['required', 'integer', Rule::exists('positions', 'id')],
            'codes.*.job_code' => ['nullable', 'string', 'max:64'],
        ]);

        DB::transaction(function () use ($property, $validated): void {
            foreach ($validated['codes'] as $row) {
                $code = trim((string) ($row['job_code'] ?? ''));

                if ($code === '') {
                    $property->positionCodes()->where('position_id', $row['position_id'])->delete();
                } else {
                    $property->positionCodes()->updateOrCreate(
                        ['position_id' => $row['position_id']],
                        ['job_code' => $code],
                    );
                }
            }
        });

        $this->logProperty($property, 'updated', 'Updated job codes');

        return back()->with('success', 'Job codes updated.');
    }
}
