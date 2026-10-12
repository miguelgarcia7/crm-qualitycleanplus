<?php

namespace App\Domain\Dashboards\Support;

use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\PropertyAssignment;
use App\Domain\Pto\Enums\PtoRequestStatus;
use App\Domain\Pto\Models\PtoRequest;
use Carbon\CarbonImmutable;

/**
 * Approved staff time off coming up, soonest first, saying who is away now
 * and how many properties a recruiter leaves without cover.
 */
class StaffAway
{
    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function upcoming(CarbonImmutable $today, int $days, int $limit): array
    {
        $requests = PtoRequest::query()
            ->where('status', PtoRequestStatus::Approved->value)
            ->whereDate('end_date', '>=', $today->toDateString())
            ->whereDate('start_date', '<=', $today->addDays($days)->toDateString())
            ->with('person:id,name')
            ->orderBy('start_date')
            ->get();

        $shown = $requests->take($limit);

        $covers = PropertyAssignment::query()
            ->where('role', PropertyAssignmentRole::Recruiter->value)
            ->whereIn('person_id', $shown->pluck('person_id'))
            ->selectRaw('person_id, COUNT(DISTINCT property_id) as n')
            ->groupBy('person_id')
            ->pluck('n', 'person_id');

        return [
            'rows' => $shown->map(fn (PtoRequest $request): array => [
                'id' => $request->id,
                'name' => $request->person?->name,
                'dates' => DashboardFormat::range($request->start_date, $request->end_date),
                'kind' => strtolower($request->bucket->label()),
                'now' => $request->start_date->toDateString() <= $today->toDateString(),
                'covers' => (int) ($covers[$request->person_id] ?? 0),
            ])->values()->all(),
            'total' => $requests->count(),
        ];
    }
}
