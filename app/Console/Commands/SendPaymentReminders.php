<?php

namespace App\Console\Commands;

use App\Models\NotificationSetting;
use App\Models\Tenant;
use App\Services\Messaging\CustomerMessenger;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendPaymentReminders extends Command
{
    protected $signature = 'notifications:send-payment-reminders 
                            {--tenant= : Specific tenant ID to process}';

    protected $description = 'Send payment reminders for upcoming and overdue invoices';

    public function __construct(
        protected NotificationService $notificationService,
        protected CustomerMessenger $messenger,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::where('is_active', true)->get();

        $totalUpcoming = 0;
        $totalOverdue = 0;
        $totalMessages = 0;

        foreach ($tenants as $tenant) {
            $settings = NotificationSetting::getForTenant($tenant->id);

            // Send upcoming payment reminders
            if ($settings->send_payment_reminders) {
                $count = $this->notificationService->sendUpcomingPaymentReminders(
                    $tenant->id,
                    $settings->payment_reminder_days_before
                );
                $totalUpcoming += $count;
                $this->info("Tenant {$tenant->name}: {$count} upcoming payment reminders sent");
            }

            // Send overdue reminders
            if ($settings->send_overdue_reminders) {
                $count = $this->notificationService->sendOverdueReminders($tenant->id);
                $totalOverdue += $count;
                $this->info("Tenant {$tenant->name}: {$count} overdue reminders sent");
            }

            // SMS / WhatsApp reminders (session 16): their own on/off switches,
            // the same timing as the emails.
            $count = $this->messenger->runScheduledReminders($tenant);
            $totalMessages += $count;
            if ($count) {
                $this->info("Tenant {$tenant->name}: {$count} SMS / WhatsApp reminders queued");
            }
        }

        $this->info("Total: {$totalUpcoming} upcoming, {$totalOverdue} overdue reminders sent, {$totalMessages} SMS / WhatsApp queued");

        return Command::SUCCESS;
    }
}
