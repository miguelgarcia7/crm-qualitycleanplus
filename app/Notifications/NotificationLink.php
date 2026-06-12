<?php

namespace App\Notifications;

/**
 * Maps a stored notification's data payload to a deep link on the surface the
 * recipient is currently using. The same notification can land differently —
 * a PM opens timesheets on QC Minute, a recruiter on the back office — so the
 * URL is resolved at render time, not at send time. Null = nothing sensible
 * to open; the click just marks it read.
 */
class NotificationLink
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function resolve(array $data, bool $onMinute): ?string
    {
        $type = $data['type'] ?? '';

        if ($onMinute) {
            return match (true) {
                str_starts_with($type, 'timesheet_') && isset($data['timesheet_id']) => "/timesheets/{$data['timesheet_id']}",
                $type === 'workflow_pay_increase' => '/pay-increases',
                $type === 'workflow_more_staff' => '/staffing-requests',
                $type === 'workflow_change_personal_info' => '/my-info',
                default => null,
            };
        }

        return match (true) {
            str_starts_with($type, 'timesheet_') => '/admin/timesheets',
            $type === 'workflow_pay_increase' => '/admin/pay-increases',
            $type === 'workflow_more_staff' => '/admin/staffing-requests',
            $type === 'workflow_change_personal_info' => '/admin/info-changes',
            $type === 'workflow_transfer', $type === 'workflow_temporary_assignment' => '/admin/work-orders',
            $type === 'contract_expiring' && isset($data['property_id']) => "/admin/properties/{$data['property_id']}",
            default => null,
        };
    }
}
