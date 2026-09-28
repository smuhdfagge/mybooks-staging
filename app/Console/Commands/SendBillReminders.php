<?php

namespace App\Console\Commands;

use App\Models\NotificationSetting;
use App\Models\Tenant;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendBillReminders extends Command
{
    protected $signature = 'notifications:send-bill-reminders 
                            {--tenant= : Specific tenant ID to process}';

    protected $description = 'Send reminders for upcoming bill payments';

    public function __construct(
        protected NotificationService $notificationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::where('is_active', true)->get();

        $total = 0;

        foreach ($tenants as $tenant) {
            $settings = NotificationSetting::getForTenant($tenant->id);

            if ($settings->send_bill_due_reminders) {
                $count = $this->notificationService->sendBillDueReminders(
                    $tenant->id,
                    $settings->bill_reminder_days_before
                );
                $total += $count;
                $this->info("Tenant {$tenant->name}: {$count} bill reminders sent");
            }
        }

        $this->info("Total: {$total} bill reminders sent");

        return Command::SUCCESS;
    }
}
