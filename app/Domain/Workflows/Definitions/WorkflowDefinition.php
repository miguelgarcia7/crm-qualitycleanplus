<?php

namespace App\Domain\Workflows\Definitions;

use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowStep;

/**
 * Base class for a concrete workflow (ADR-0026). A definition declares its
 * type and its steps, and hooks side-effects into the lifecycle. The generic
 * engine (Start/Advance/CompleteStep/RejectStep/Cancel) drives every definition.
 *
 * Side-effects are plain PHP calling domain Actions — no JSON effect-DSL.
 */
abstract class WorkflowDefinition
{
    abstract public function type(): WorkflowType;

    /**
     * The ordered steps to materialize when the workflow starts.
     *
     * @return list<StepBlueprint>
     */
    abstract public function steps(Workflow $workflow): array;

    /** Runs once, after the workflow + its steps are created, before advancing. */
    public function onStart(Workflow $workflow): void {}

    /** Executes a `system` step's effect; the engine then marks it done. */
    public function runSystemStep(Workflow $workflow, WorkflowStep $step): void {}

    /** Runs after a human step is completed, before the engine advances. */
    public function onStepCompleted(Workflow $workflow, WorkflowStep $step): void {}

    /** Runs after a step is rejected (the workflow is already marked rejected). */
    public function onStepRejected(Workflow $workflow, WorkflowStep $step): void {}

    /** Runs after the workflow is cancelled. */
    public function onCancelled(Workflow $workflow): void {}
}
