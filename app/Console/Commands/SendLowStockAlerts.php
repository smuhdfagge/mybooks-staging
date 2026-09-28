<?php

namespace App\Console\Commands;

use App\Models\NotificationSetting;
use App\Models\Tenant;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendLowStockAlerts extends Command
{
    protected $signature = 'notifications:send-low-stock-alerts 
                            {--tenant= : Specific tenant ID to process}';

    protected $description = 'Send low stock alerts for inventory items';

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

        $alertsSent = 0;

        foreach ($tenants as $tenant) {
            $settings = NotificationSetting::getForTenant($tenant->id);

            if ($settings->send_low_stock_alerts) {
                $sent = $this->notificationService->sendLowStockAlerts($tenant->id);
                if ($sent) {
                    $alertsSent++;
                    $this->info("Tenant {$tenant->name}: Low stock alert sent");
                } else {
                    $this->line("Tenant {$tenant->name}: No low stock items");
                }
            }
        }

        $this->info("Total: {$alertsSent} tenants alerted");

        return Command::SUCCESS;
    }
}
