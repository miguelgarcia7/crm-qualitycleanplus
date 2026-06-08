<?php

namespace App\Domain\Workflows\Models;

use App\Domain\People\Models\Person;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use Carbon\CarbonImmutable;
use Database\Factories\WorkflowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A workflow instance (ADR-0026). Definitions are code; this row + its steps are
 * the generic persistence and audit trail.
 *
 * @property WorkflowStatus $status
 * @property array<string, mixed>|null $data
 * @property CarbonImmutable|null $completed_at
 */
class Workflow extends Model
{
    /** @use HasFactory<WorkflowFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'subject_type',
        'subject_id',
        'initiator_id',
        'status',
        'current_step_index',
        'data',
        'completed_at',
        'completed_by',
        'cancel_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkflowStatus::class,
            'current_step_index' => 'integer',
            'data' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function workflowType(): WorkflowType
    {
        return WorkflowType::from($this->type);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'initiator_id');
    }

    /**
     * @return HasMany<WorkflowStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('step_index');
    }

    public function currentStep(): ?WorkflowStep
    {
        return $this->steps()->where('step_index', $this->current_step_index)->first();
    }
}
