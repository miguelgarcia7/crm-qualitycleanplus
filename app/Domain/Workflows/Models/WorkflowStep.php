<?php

namespace App\Domain\Workflows\Models;

use App\Domain\People\Models\Person;
use App\Domain\Workflows\Enums\StepActor;
use App\Domain\Workflows\Enums\StepStatus;
use App\Domain\Workflows\Enums\StepType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One materialized step of a workflow.
 *
 * @property StepType $step_type
 * @property StepActor $actor
 * @property StepStatus $status
 * @property CarbonImmutable|null $completed_at
 */
class WorkflowStep extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'workflow_id',
        'step_index',
        'step_key',
        'name',
        'step_type',
        'actor',
        'assigned_to',
        'assigned_role',
        'required_permission',
        'status',
        'notes',
        'completed_at',
        'completed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step_index' => 'integer',
            'step_type' => StepType::class,
            'actor' => StepActor::class,
            'status' => StepStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'assigned_to');
    }

    /**
     * Pending steps that are the current step of their workflow and assigned to
     * this person (directly or via one of their roles) — the My Tasks inbox.
     *
     * @param  Builder<WorkflowStep>  $query
     */
    public function scopeOpenForPerson(Builder $query, Person $person): void
    {
        $roles = $person->getRoleNames()->all();

        $query->where('status', StepStatus::Pending)
            ->whereHas('workflow', fn (Builder $w) => $w->whereColumn('workflows.current_step_index', 'workflow_steps.step_index'))
            ->where(function (Builder $q) use ($person, $roles): void {
                $q->where('assigned_to', $person->id);

                if ($roles !== []) {
                    $q->orWhereIn('assigned_role', $roles);
                }
            });
    }
}
