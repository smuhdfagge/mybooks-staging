<?php

namespace App\Console\Commands;

use App\Models\Bill;
use Illuminate\Console\Command;

/**
 * Bills posted before stock was received on posting (A21) only put stock in
 * when fully paid. Run once after deploying to bring in the stock of posted
 * bills that are still unpaid, so stock matches the Inventory account.
 */
class ReceivePendingBillStock extends Command
{
    protected $signature = 'bills:receive-pending-stock {--dry-run : List the bills without changing anything}';

    protected $description = 'Receive stock for posted bills whose stock was waiting for payment';

    public function handle(): int
    {
        $bills = Bill::withoutGlobalScopes()
            ->whereNull('inventory_updated_at')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereHas('items', fn ($q) => $q->whereNotNull('item_id'))
            ->orderBy('id')
            ->get();

        foreach ($bills as $bill) {
            $this->line("Bill {$bill->bill_number} (business {$bill->tenant_id}, {$bill->status})");
            if (! $this->option('dry-run')) {
                $bill->updateInventory();
            }
        }

        $this->info(($this->option('dry-run') ? 'Would receive' : 'Received')." stock for {$bills->count()} bill(s).");

        return self::SUCCESS;
    }
}
