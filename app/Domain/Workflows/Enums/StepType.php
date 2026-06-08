<?php

namespace App\Domain\Workflows\Enums;

/**
 * The kind of a workflow step (20-domain/workflows.md).
 */
enum StepType: string
{
    /** Assignee approves or rejects (rejection terminates the workflow). */
    case Approval = 'approval';
    /** A side-effect: run automatically (system) or marked done by a human. */
    case Action = 'action';
    /** Informational; surfaced to recipients, no decision required. */
    case Notification = 'notification';

    public function label(): string
    {
        return match ($this) {
            self::Approval => 'Approval',
            self::Action => 'Action',
            self::Notification => 'Notification',
        };
    }
}
