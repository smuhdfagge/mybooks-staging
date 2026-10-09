<?php

namespace App\Services\Messaging;

use App\Enums\MessageStatus;
use App\Models\CustomerMessage;
use App\Models\MessageSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\MessageAllowanceUsedNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Each plan includes so many SMS and WhatsApp messages a calendar month
 * (Lagos time); MyBooks pays the provider (session 16). A long SMS counts
 * once per page. Failed messages don't count.
 */
class MessageAllowance
{
    public function limit(Tenant $tenant, string $channel): int
    {
        $plan = $tenant->currentPlan();

        return $plan ? (int) $plan->getAttribute($channel.'_monthly_limit') : 0;
    }

    public function used(int $tenantId, string $channel, ?CarbonImmutable $month = null): int
    {
        [$from, $to] = $this->monthRange($month);

        return (int) CustomerMessage::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('channel', $channel)
            ->whereIn('status', MessageStatus::counted())
            ->whereBetween('created_at', [$from, $to])
            ->sum('segments');
    }

    public function remaining(Tenant $tenant, string $channel): int
    {
        return max(0, $this->limit($tenant, $channel) - $this->used($tenant->id, $channel));
    }

    /** "You've used 200 of 200 SMS this month." */
    public function summary(Tenant $tenant, string $channel): string
    {
        $label = $channel === 'whatsapp' ? 'WhatsApp messages' : 'SMS';

        return "You've used ".number_format($this->used($tenant->id, $channel)).' of '.number_format($this->limit($tenant, $channel))." {$label} this month.";
    }

    /** Start and end of the month (Lagos), in the app's time zone. @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function monthRange(?CarbonImmutable $month = null): array
    {
        $tz = (string) config('mybooks.messaging.timezone', 'Africa/Lagos');
        $local = ($month ?? CarbonImmutable::now())->setTimezone($tz);

        return [
            $local->startOfMonth()->setTimezone(config('app.timezone')),
            $local->endOfMonth()->setTimezone(config('app.timezone')),
        ];
    }

    public function monthKey(): string
    {
        return CarbonImmutable::now()->setTimezone((string) config('mybooks.messaging.timezone', 'Africa/Lagos'))->format('Y-m');
    }

    /** Tell the business once a month that a channel's allowance has run out. */
    public function noticeUsedUp(Tenant $tenant, string $channel): void
    {
        $settings = MessageSetting::forTenant($tenant->id);
        $notices = $settings->limit_notices ?? [];
        $month = $this->monthKey();
        if (($notices[$channel] ?? null) === $month) {
            return;
        }
        $notices[$channel] = $month;
        $settings->forceFill(['limit_notices' => $notices])->save();

        try {
            $users = User::where('tenant_id', $tenant->id)->where('is_active', true)->permission('edit settings')->get();
        } catch (PermissionDoesNotExist) {
            return;
        }
        Notification::send($users, new MessageAllowanceUsedNotification($channel, $this->summary($tenant, $channel)));
    }
}
