<?php

namespace App\Domain\Pto\Actions;

use App\Domain\People\Models\Person;
use App\Domain\Pto\Enums\PtoBucket;
use App\Domain\Pto\Enums\PtoRequestStatus;
use App\Domain\Pto\Models\PtoRequest;
use Illuminate\Validation\ValidationException;

/**
 * Submits a PTO request (ADR-0016). W-2 staff only. Reserves hours on submission —
 * blocks if the request exceeds the bucket's available balance (allotment minus
 * pending + approved). Creates a `pending` request.
 *
 * @phpstan-type SubmitData array{bucket:string, start_date:string, end_date:string, hours:float, reason?:string|null, notice_period_warning_acknowledged?:bool}
 */
class SubmitPtoRequest
{
    public function __construct(private readonly EnsurePtoYear $ensureYear) {}

    /**
     * @param  SubmitData  $data
     */
    public function handle(Person $person, array $data): PtoRequest
    {
        if (! $person->status->isStaff()) {
            throw ValidationException::withMessages(['pto' => 'PTO is only available to W-2 staff.']);
        }

        $bucket = PtoBucket::from($data['bucket']);
        $hours = (float) $data['hours'];

        if ($hours <= 0) {
            throw ValidationException::withMessages(['hours' => 'Enter a number of hours greater than zero.']);
        }

        $allotment = $this->ensureYear->handle($person);
        $available = $allotment->availableFor($bucket);

        if ($hours > $available) {
            throw ValidationException::withMessages([
                'hours' => "Only {$available} {$bucket->label()} hours are available.",
            ]);
        }

        return PtoRequest::create([
            'person_id' => $person->id,
            'year_allotment_id' => $allotment->id,
            'bucket' => $bucket,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'hours' => $hours,
            'reason' => $data['reason'] ?? null,
            'status' => PtoRequestStatus::Pending,
            'submitted_at' => now(),
            'notice_period_warning_acknowledged' => (bool) ($data['notice_period_warning_acknowledged'] ?? false),
        ]);
    }
}
