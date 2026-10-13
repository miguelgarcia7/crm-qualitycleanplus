<?php

namespace App\Domain\Time\Definitions;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Workflows\Definitions\StepBlueprint;
use App\Domain\Workflows\Definitions\WorkflowDefinition;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowStep;
use App\Notifications\WorkflowNotice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Hours problem: a contractor reports that a week on My hours looks wrong.
 * It goes to their recruiter, who checks it against the timesheet, fixes the
 * punches there if needed, and completes the task ("resolved") or rejects it
 * with a reason ("no change needed"). The contractor is told either way.
 *
 * Subject = the contractor. `data`: week_start, week_end, message, and the
 * property_id and work_order_id of the job they picked, when they picked one.
 * Without a primary recruiter, the office managers get it.
 */
class HoursProblemDefinition extends WorkflowDefinition
{
    public function type(): WorkflowType
    {
        return WorkflowType::HoursProblem;
    }

    /**
     * @return list<StepBlueprint>
     */
    public function steps(Workflow $workflow): array
    {
        $recruiter = $this->recruiter($workflow);

        return [$recruiter !== null
            ? StepBlueprint::humanPerson('look_into_hours', 'Look into reported hours', $recruiter->id, 'time_entries.edit')
            : StepBlueprint::humanRole('look_into_hours', 'Look into reported hours', 'office_manager', 'time_entries.edit')];
    }

    public function onStart(Workflow $workflow): void
    {
        $this->recruiter($workflow)?->notify(new WorkflowNotice(
            $workflow,
            $this->contractor($workflow)?->name.' reported a problem with their hours for '.$this->week($workflow).'.',
            ['week_start' => $workflow->data['week_start'] ?? null],
        ));
    }

    public function onStepCompleted(Workflow $workflow, WorkflowStep $step): void
    {
        $note = trim((string) $step->notes);

        $this->contractor($workflow)?->notify(new WorkflowNotice(
            $workflow,
            'Your hours for '.$this->week($workflow).' were looked into and marked resolved.'.($note !== '' ? " {$note}" : ''),
            ['week_start' => $workflow->data['week_start'] ?? null],
        ));
    }

    public function onStepRejected(Workflow $workflow, WorkflowStep $step): void
    {
        $this->contractor($workflow)?->notify(new WorkflowNotice(
            $workflow,
            'Your hours for '.$this->week($workflow).' were checked and no change was needed: '.($workflow->cancel_reason ?? ''),
            ['week_start' => $workflow->data['week_start'] ?? null],
        ));
    }

    public function taskSummary(Workflow $workflow): ?string
    {
        $property = Property::find($workflow->data['property_id'] ?? 0)?->name;

        return sprintf(
            '%s · %s%s: “%s”',
            $this->contractor($workflow)->name ?? 'Contractor',
            $this->week($workflow),
            $property !== null ? " at {$property}" : '',
            $workflow->data['message'] ?? '',
        );
    }

    /** That week on the property's timesheet grid, where the punches are fixed. */
    public function contextLink(Workflow $workflow, WorkflowStep $step): ?array
    {
        // The job they picked; otherwise the property they worked most that week.
        $propertyId = $workflow->data['property_id'] ?? TimeEntry::query()
            ->where('time_entries.person_id', (int) $workflow->subject_id)
            ->join('payroll_periods', 'payroll_periods.id', '=', 'time_entries.payroll_period_id')
            ->whereDate('payroll_periods.week_start', $workflow->data['week_start'] ?? '')
            ->groupBy('time_entries.property_id')
            ->orderByRaw('SUM(time_entries.duration_minutes) DESC')
            ->value('time_entries.property_id');

        return $propertyId === null ? null : [
            'label' => 'Open the timesheet',
            'url' => "/admin/properties/{$propertyId}/grid?week=".($workflow->data['week_start'] ?? ''),
        ];
    }

    public function completionNoteLabel(Workflow $workflow, WorkflowStep $step): ?string
    {
        return 'What you changed, for '.(Str::before((string) $this->contractor($workflow)?->name, ' ') ?: 'the contractor').' (optional)';
    }

    /** "Oct 5 – 11", as the contractor saw it on My hours. */
    public function week(Workflow $workflow): string
    {
        $start = CarbonImmutable::parse($workflow->data['week_start'] ?? 'today');
        $end = CarbonImmutable::parse($workflow->data['week_end'] ?? $start->addDays(6)->toDateString());

        return $start->format('M j').' – '.($start->month === $end->month ? $end->format('j') : $end->format('M j'));
    }

    private function contractor(Workflow $workflow): ?Person
    {
        return Person::find((int) $workflow->subject_id);
    }

    /** The contractor's recruiter, while they can still fix hours. */
    private function recruiter(Workflow $workflow): ?Person
    {
        $recruiter = $this->contractor($workflow)?->primaryRecruiter;

        return $recruiter !== null && $recruiter->can('time_entries.edit') ? $recruiter : null;
    }
}
