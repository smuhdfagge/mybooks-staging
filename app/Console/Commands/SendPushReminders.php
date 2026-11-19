<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Push\PushNotifier;
use Illuminate\Console\Command;

/**
 * Daily push reminders to phones (mobile plan A2): invoices that became
 * overdue today and bills due tomorrow.
 */
class SendPushReminders extends Command
{
    protected $signature = 'notifications:send-push-reminders
                            {--tenant= : Only this tenant ID}';

    protected $description = 'Push today\'s overdue invoices and tomorrow\'s bills to registered phones';

    public function handle(PushNotifier $notifier): int
    {
        $tenantId = $this->option('tenant');
        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::where('is_active', true)->get();

        $overdue = 0;
        $bills = 0;
        foreach ($tenants as $tenant) {
            Tenant::run($tenant, function () use ($tenant, $notifier, &$overdue, &$bills) {
                $counts = $notifier->dailyReminders((int) $tenant->id);
                $overdue += $counts['overdue'];
                $bills += $counts['bills'];
            });
        }

        $this->info("Pushed {$overdue} overdue invoices and {$bills} bills due tomorrow.");

        return self::SUCCESS;
    }
}
