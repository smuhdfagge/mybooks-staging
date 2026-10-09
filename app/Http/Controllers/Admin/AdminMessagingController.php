<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Models\CustomerMessage;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\AdminAuditService;
use App\Services\Messaging\MessageAllowance;
use App\Services\Messaging\MessagingDrivers;
use App\Services\Messaging\TermiiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Platform admin: SMS and WhatsApp (session 16). The monthly allowance per
 * plan, this month's use by each business, and the provider set-up.
 */
class AdminMessagingController extends Controller
{
    public function index(MessageAllowance $allowance, MessagingDrivers $drivers)
    {
        [$from, $to] = $allowance->monthRange();

        $rows = CustomerMessage::withoutGlobalScopes()
            ->selectRaw('tenant_id, channel, SUM(segments) as used, COUNT(*) as messages, SUM(COALESCE(cost, 0)) as cost')
            ->whereIn('status', MessageStatus::counted())
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('tenant_id', 'channel')
            ->get();

        $tenants = Tenant::whereIn('id', $rows->pluck('tenant_id')->unique())->with('activeSubscription.plan')->get()->keyBy('id');
        $usage = $rows->groupBy('tenant_id')->map(function ($channels, $tenantId) use ($tenants) {
            $tenant = $tenants[$tenantId] ?? null;
            $plan = $tenant?->activeSubscription?->plan;
            $line = ['tenant' => $tenant, 'plan' => $plan];
            foreach (['sms', 'whatsapp'] as $channel) {
                $row = $channels->firstWhere('channel', $channel);
                $line[$channel] = ['used' => (int) ($row->used ?? 0), 'limit' => (int) ($plan?->getAttribute($channel.'_monthly_limit') ?? 0), 'cost' => (float) ($row->cost ?? 0)];
            }

            return $line;
        })->sortByDesc(fn ($line) => $line['sms']['used'] + $line['whatsapp']['used'])->values();

        $balance = null;
        if ($drivers->smsDriverName() === 'termii') {
            $balance = Cache::remember('messaging.termii_balance', 600, fn () => app(TermiiClient::class)->balance());
        }

        return view('admin.messaging.index', [
            'plans' => Plan::orderBy('sort_order')->get(),
            'usage' => $usage,
            'month' => $from->setTimezone(config('mybooks.messaging.timezone'))->format('F Y'),
            'smsDriver' => $drivers->smsDriverName(),
            'whatsappDriver' => $drivers->whatsappDriverName(),
            'balance' => $balance,
            'webhookSet' => filled(config('mybooks.messaging.webhook_token')),
        ]);
    }

    public function updatePlans(Request $request)
    {
        $validated = $request->validate([
            'plans' => ['required', 'array'],
            'plans.*.sms_monthly_limit' => ['required', 'integer', 'min:0', 'max:1000000'],
            'plans.*.whatsapp_monthly_limit' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        foreach ($validated['plans'] as $id => $limits) {
            $plan = Plan::find($id);
            if (! $plan) {
                continue;
            }
            $before = $plan->getAttributes();
            $plan->update($limits);
            AdminAuditService::logChange("changed the SMS / WhatsApp allowance of plan '{$plan->name}'", $plan, $before);
        }

        return redirect()->route('admin.messaging.index')->with('success', 'Message allowances saved.');
    }
}
