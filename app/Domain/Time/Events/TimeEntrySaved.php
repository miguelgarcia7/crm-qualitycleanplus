<?php

namespace App\Domain\Time\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a property's time entries change, so the live weekly grid can
 * refresh (Phase 03). Fired from the HTTP path only — the seeder writes entries
 * directly without broadcasting.
 */
class TimeEntrySaved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $propertyId,
        public string $weekStart,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("property.{$this->propertyId}")];
    }

    public function broadcastAs(): string
    {
        return 'time-entry.saved';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['property_id' => $this->propertyId, 'week_start' => $this->weekStart];
    }
}
