<?php

namespace App\Notifications;

use App\Domain\Workflows\Models\Workflow;
use Illuminate\Bus\Queueable;

/**
 * Generic in-app notice for a workflow event (e.g. a transfer applied, a temp
 * assignment opened, a pay increase approved/declined). Database channel.
 */
class WorkflowNotice extends AppNotification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public Workflow $workflow,
        public string $message,
        public array $data = [],
    ) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Workflows;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'workflow_'.$this->workflow->type,
            'category' => $this->category()->value,
            'workflow_id' => $this->workflow->id,
            'message' => $this->message,
            ...$this->data,
        ];
    }
}
