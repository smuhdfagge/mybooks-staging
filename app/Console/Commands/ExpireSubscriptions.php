<?php

namespace App\Console\Commands;

use App\Http\Middleware\EnsureFeatureEnabled;
use App\Models\BillingCard;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionExpiringNotification;
use Illuminate\Console\Command;

/**
 * Daily billing housekeeping (finding C1):
 *  - marks subscriptions whose paid period has ended as expired;
 *  - reminds tenant admins 7 days and 1 day before the end.
 *
 * Each reminder is sent once per end date, so running the command twice in
 * a day, or after a renewal, doesn't send duplicates.
 */
class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Expire subscriptions that have ended and send renewal reminders';

    /** Days before the end date on which to remind. */
    public const REMINDER_DAYS = [7, 1];

    public function handle(): int
    {
        $expired = 0;
        $reminded = 0;

        $ended = Subscription::withoutGlobalScopes()
            ->with('plan')
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->get();

        foreach ($ended as $subscription) {
            $subscription->forceFill(['status' => Subscription::STATUS_EXPIRED])->save();
            $expired++;

            if (! $subscription->cancelled_at) {
                $this->notifyAdmins($subscription, 0);
            }
        }

        $endingSoon = Subscription::withoutGlobalScopes()
            ->with('plan')
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereNull('cancelled_at')
            ->where('ends_at', '>', now())
            ->where('ends_at', '<=', now()->addDays(max(self::REMINDER_DAYS) + 1))
            ->get();

        foreach ($endingSoon as $subscription) {
            $daysLeft = (int) now()->startOfDay()->diffInDays($subscription->ends_at->copy()->startOfDay());
            if (! in_array($daysLeft, self::REMINDER_DAYS, true)) {
                continue;
            }

            // The saved card will be charged instead (session 15); a failed
            // charge sends its own email with a Pay now link.
            if ($this->willAutoRenew($subscription)) {
                continue;
            }

            $key = "reminded_{$daysLeft}";
            $metadata = $subscription->metadata ?? [];
            if (($metadata[$key] ?? null) === $subscription->ends_at->toDateString()) {
                continue;
            }

            $this->notifyAdmins($subscription, $daysLeft);
            $metadata[$key] = $subscription->ends_at->toDateString();
            $subscription->forceFill(['metadata' => $metadata])->save();
            $reminded++;
        }

        $this->info("Expired: {$expired}. Reminders sent: {$reminded}.");

        return self::SUCCESS;
    }

    private function willAutoRenew(Subscription $subscription): bool
    {
        if (! EnsureFeatureEnabled::enabled('auto_renewal')) {
            return false;
        }

        $card = BillingCard::withoutGlobalScopes()->where('tenant_id', $subscription->tenant_id)->first();

        return $card !== null && $card->auto_renew && $subscription->ends_at && ! $card->hasExpiredBy($subscription->ends_at);
    }

    private function notifyAdmins(Subscription $subscription, int $daysLeft): void
    {
        $admins = User::withoutGlobalScopes()
            ->where('tenant_id', $subscription->tenant_id)
            ->where('is_active', true)
            ->role('admin')
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new SubscriptionExpiringNotification($subscription, $daysLeft));
        }
    }
}
