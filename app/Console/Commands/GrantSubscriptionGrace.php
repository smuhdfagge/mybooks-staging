<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

/**
 * One-off helper for switching billing on (finding C1).
 *
 * Before billing was enforced, sign-up gave every tenant an "active"
 * subscription with an end date nobody checked, so many existing tenants are
 * already past their end date. Run this once when deploying so they get a
 * few days' notice to pay instead of being locked out at once.
 */
class GrantSubscriptionGrace extends Command
{
    protected $signature = 'subscriptions:grace
                            {--days=14 : Give active subscriptions at least this many days}
                            {--dry-run : Only show how many would change}';

    protected $description = 'Give existing active subscriptions time to renew before billing is enforced';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $until = now()->addDays($days)->endOfDay();

        $query = Subscription::withoutGlobalScopes()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $until);

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("{$count} active subscription(s) would be given until {$until->toDateString()}.");

            return self::SUCCESS;
        }

        foreach ($query->get() as $subscription) {
            $metadata = $subscription->metadata ?? [];
            $metadata['grace_from'] = $subscription->ends_at->toDateTimeString();
            $subscription->forceFill(['ends_at' => $until, 'metadata' => $metadata])->save();
        }

        $this->info("{$count} active subscription(s) now run until {$until->toDateString()}.");

        return self::SUCCESS;
    }
}
