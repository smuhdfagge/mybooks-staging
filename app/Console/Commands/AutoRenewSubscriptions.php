<?php

namespace App\Console\Commands;

use App\Services\Billing\AutoRenewal;
use Illuminate\Console\Command;

/**
 * Charges the saved card of businesses with auto-renewal on whose
 * subscription is about to end, retries failed charges, and warns about
 * cards that expire before the next renewal (session 15).
 */
class AutoRenewSubscriptions extends Command
{
    protected $signature = 'subscriptions:auto-renew';

    protected $description = 'Renew subscriptions with the saved card, retry failed charges and warn about expiring cards';

    public function handle(AutoRenewal $renewal): int
    {
        if (! $renewal->enabled()) {
            $this->info('Auto-renewal is switched off (mybooks.features.auto_renewal).');

            return self::SUCCESS;
        }

        $stats = $renewal->run();

        $this->info("Renewed: {$stats['charged']}. Failed: {$stats['failed']}. Waiting for Paystack: {$stats['pending']}. Card warnings: {$stats['warned']}.");

        return self::SUCCESS;
    }
}
