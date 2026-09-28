<?php

namespace App\Notifications;

use App\Models\Payroll;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayrollApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Payroll $payroll
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $employeeName = $this->payroll->employee?->full_name ?? $notifiable->name;

        return (new MailMessage)
            ->subject("Payroll Approved - {$this->payroll->payroll_number}")
            ->greeting("Hello {$employeeName},")
            ->line('Your payroll has been approved and processed.')
            ->line("**Payroll Number:** {$this->payroll->payroll_number}")
            ->line("**Pay Period:** {$this->payroll->pay_period_start->format('M d')} - {$this->payroll->pay_period_end->format('M d, Y')}")
            ->line("**Pay Date:** {$this->payroll->pay_date->format('M d, Y')}")
            ->line('---')
            ->line('**Gross Salary:** '.number_format($this->payroll->gross_salary, 2))
            ->line('**Total Deductions:** '.number_format($this->payroll->total_deductions, 2))
            ->line('**Net Salary:** '.number_format($this->payroll->net_salary, 2))
            ->line('---')
            ->line('If you have any questions about your pay, please contact HR.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payroll_approved',
            'payroll_id' => $this->payroll->id,
            'payroll_number' => $this->payroll->payroll_number,
            'net_salary' => $this->payroll->net_salary,
            'message' => "Payroll #{$this->payroll->payroll_number} has been approved",
        ];
    }
}
