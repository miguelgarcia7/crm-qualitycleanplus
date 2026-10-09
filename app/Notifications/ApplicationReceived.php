<?php

namespace App\Notifications;

use App\Domain\Recruiting\Models\JobApplication;
use Illuminate\Bus\Queueable;

/**
 * In-app notice to recruiters and office managers that someone applied through
 * the public website.
 * Applications otherwise only appear in Applicants, which nobody watches all day.
 * Mutable: the "New applications" category can be switched off per person.
 */
class ApplicationReceived extends AppNotification
{
    use Queueable;

    public function __construct(public JobApplication $application) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Applications;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $application = $this->application;
        $name = trim($application->first_name.' '.$application->last_name);
        $message = filled($application->desired_position)
            ? "New application from {$name} for {$application->desired_position}."
            : "New application from {$name}.";

        // The applicant's email belongs to someone already on file; a recruiter
        // has to link or dismiss before review can start.
        if ($application->matched_person_id !== null) {
            $message .= ' Their email matches an existing person — link or dismiss before reviewing.';
        }

        return [
            'type' => 'application_received',
            'category' => $this->category()->value,
            'application_id' => $application->id,
            'person_id' => $application->person_id,
            'message' => $message,
        ];
    }
}
