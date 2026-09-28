<?php

namespace App\Notifications;

use App\Models\Leave;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Leave $leave,
        public string $action = 'submitted' // submitted, approved, rejected
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $employeeName = $this->leave->employee?->full_name ?? 'Employee';
        $leaveType = $this->leave->leaveType?->name ?? 'Leave';

        if ($this->action === 'submitted') {
            return $this->toMailForManager($notifiable, $employeeName, $leaveType);
        }

        return $this->toMailForEmployee($notifiable, $leaveType);
    }

    protected function toMailForManager(object $notifiable, string $employeeName, string $leaveType): MailMessage
    {
        return (new MailMessage)
            ->subject("Leave Request from {$employeeName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$employeeName} has submitted a leave request that requires your attention.")
            ->line("**Leave Type:** {$leaveType}")
            ->line("**From:** {$this->leave->start_date->format('M d, Y')}")
            ->line("**To:** {$this->leave->end_date->format('M d, Y')}")
            ->line("**Days:** {$this->leave->days}")
            ->line('**Reason:** '.($this->leave->reason ?? 'Not specified'))
            ->action('Review Request', route('leaves.show', $this->leave))
            ->line('Please review and approve/reject this request.');
    }

    protected function toMailForEmployee(object $notifiable, string $leaveType): MailMessage
    {
        $statusColor = $this->action === 'approved' ? 'green' : 'red';
        $statusText = ucfirst($this->action);

        return (new MailMessage)
            ->subject("Leave Request {$statusText}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your leave request has been **{$statusText}**.")
            ->line("**Leave Type:** {$leaveType}")
            ->line("**From:** {$this->leave->start_date->format('M d, Y')}")
            ->line("**To:** {$this->leave->end_date->format('M d, Y')}")
            ->line("**Days:** {$this->leave->days}")
            ->when($this->leave->rejection_reason && $this->action === 'rejected', function ($message) {
                return $message->line("**Reason:** {$this->leave->rejection_reason}");
            })
            ->action('View Details', route('leaves.show', $this->leave));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'leave_request',
            'leave_id' => $this->leave->id,
            'action' => $this->action,
            'employee_name' => $this->leave->employee?->full_name,
            'message' => "Leave request {$this->action}",
        ];
    }
}
