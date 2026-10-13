<?php

namespace App\Http\Controllers\Minute;

use App\Domain\People\Models\Person;
use App\Domain\Time\Support\MyHours;
use App\Domain\Workflows\Actions\StartWorkflow;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Http\Controllers\Controller;
use App\Support\CompanyTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * QC Minute "My hours": a contractor's own weeks, hours and pay before
 * deductions, each week's punches day by day, and a way to report a week that
 * looks wrong (it goes to their recruiter as an Hours problem task).
 *
 * Gated on time_entries.view_own, which only contractors hold.
 */
class MyHoursController extends Controller
{
    private const RANGES = ['3m', 'year', 'all'];

    public function index(Request $request, MyHours $hours): Response
    {
        $person = $this->contractor($request);

        $validated = $request->validate([
            'range' => ['nullable', Rule::in(self::RANGES)],
            'job' => ['nullable', 'integer'],
        ]);
        $range = $validated['range'] ?? '3m';
        $jobs = $hours->jobs($person);
        // Only a job of theirs filters; anything else shows every job.
        $job = collect($jobs)->firstWhere('id', (int) ($validated['job'] ?? 0))['id'] ?? null;

        $from = match ($range) {
            '3m' => CompanyTime::now()->subMonths(3)->toDateString(),
            'year' => CompanyTime::now()->startOfYear()->toDateString(),
            default => null,
        };

        $weeks = $hours->weeks($person, $from, job: $job);
        $otRates = WorkOrder::query()->whereIn('id', collect($weeks)->pluck('jobs.*.work_order_id')->flatten()->unique())
            ->distinct()->pluck('ot_pay_rate')->filter()->values();

        return Inertia::render('minute/my-hours/index', [
            'weeks' => $weeks,
            'summary' => [
                'minutes' => (int) collect($weeks)->sum('minutes'),
                'overtime_minutes' => (int) collect($weeks)->sum('overtime_minutes'),
                'gross' => (int) collect($weeks)->sum('gross'),
                'weeks' => count($weeks),
                // One overtime rate is worth naming; several aren't.
                'ot_rate' => $otRates->count() === 1 ? $otRates->first() : null,
            ],
            'filters' => ['range' => $range, 'job' => $job],
            'jobs' => $jobs,
        ]);
    }

    public function show(Request $request, string $week, MyHours $hours): Response
    {
        $person = $this->contractor($request);

        $summary = collect($hours->weeks($person, $week))->firstWhere('start', $week);
        abort_if($summary === null, 404);

        $reports = Workflow::query()
            ->where('type', WorkflowType::HoursProblem->value)
            ->where('initiator_id', $person->id)
            ->where('data->week_start', $week)
            ->with('steps:id,workflow_id,notes')
            ->latest('id')
            ->get()
            ->map(fn (Workflow $wf): array => [
                'id' => $wf->id,
                'status' => $wf->status->value,
                'message' => $wf->data['message'] ?? '',
                'reply' => $wf->status === WorkflowStatus::Rejected ? $wf->cancel_reason : $wf->steps->first()?->notes,
                'sent_at' => CompanyTime::of($wf->created_at)?->toDateString(),
            ]);

        return Inertia::render('minute/my-hours/show', [
            'week' => $summary,
            'days' => $hours->days($person, $summary['start'], $summary['end']),
            'reports' => $reports,
            'recruiter' => $person->primaryRecruiter?->name,
            'can_report' => ! $reports->contains(fn (array $r): bool => in_array($r['status'], [WorkflowStatus::Pending->value, WorkflowStatus::InProgress->value], true)),
        ]);
    }

    public function report(Request $request, string $week, MyHours $hours, StartWorkflow $start): RedirectResponse
    {
        $person = $this->contractor($request);

        $summary = collect($hours->weeks($person, $week))->firstWhere('start', $week);
        abort_if($summary === null, 404);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'work_order_id' => ['nullable', 'integer', Rule::in(collect($summary['jobs'])->pluck('work_order_id')->all())],
        ]);

        $open = Workflow::query()
            ->where('type', WorkflowType::HoursProblem->value)
            ->where('initiator_id', $person->id)
            ->where('data->week_start', $week)
            ->whereIn('status', [WorkflowStatus::Pending->value, WorkflowStatus::InProgress->value])
            ->exists();
        if ($open) {
            throw ValidationException::withMessages(['message' => 'You already reported this week. Your recruiter is looking into it.']);
        }

        $workOrderId = $validated['work_order_id'] ?? (count($summary['jobs']) === 1 ? $summary['jobs'][0]['work_order_id'] : null);

        $start->handle(WorkflowType::HoursProblem, $person, $person, [
            'week_start' => $summary['start'],
            'week_end' => $summary['end'],
            'message' => $validated['message'],
            'work_order_id' => $workOrderId,
            'property_id' => $workOrderId !== null ? WorkOrder::find($workOrderId)?->property_id : null,
        ]);

        return back()->with('success', 'Sent to '.($person->primaryRecruiter->name ?? 'the office').'. You’ll get a notification when it’s been looked into.');
    }

    private function contractor(Request $request): Person
    {
        $person = $request->user();
        abort_unless($person instanceof Person && $person->can('time_entries.view_own'), 403);

        return $person;
    }
}
