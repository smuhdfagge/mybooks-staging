<?php

namespace App\Support\PublicSite;

use App\Models\Plan;
use Illuminate\Support\Collection;

/**
 * The plans as the public pages show them (home page plan, decision 7).
 * Everything comes from the plans table: names, prices, limits and the
 * feature lists. Nothing here is typed in.
 *
 * @phpstan-type PublicPlan array{
 *     name: string, slug: string, description: ?string,
 *     monthly: ?float, annual: ?float, annual_per_month: ?float,
 *     savings_percent: ?int, monthly_only: bool, annual_only: bool,
 *     users: int, sms: int, whatsapp: int, bank_accounts: int,
 *     features: list<string>, monthly_url: ?string, annual_url: ?string
 * }
 */
class PublicPlans
{
    /** @return Collection<int, PublicPlan> */
    public function all(): Collection
    {
        return Plan::query()->active()->ordered()->get()->map(fn (Plan $plan) => $this->present($plan))->values();
    }

    /** True when at least one plan can be paid monthly (the switch is shown only then). */
    /** @param Collection<int, PublicPlan> $plans */
    public function anyMonthly(Collection $plans): bool
    {
        return $plans->contains(fn (array $p) => $p['monthly'] !== null);
    }

    /** True when at least one plan can be paid yearly. */
    /** @param Collection<int, PublicPlan> $plans */
    public function anyAnnual(Collection $plans): bool
    {
        return $plans->contains(fn (array $p) => $p['annual'] !== null);
    }

    /** @return PublicPlan */
    private function present(Plan $plan): array
    {
        $monthly = $plan->allowsBillingCycle('monthly') && (float) $plan->monthly_price > 0 ? (float) $plan->monthly_price : null;
        $annual = $plan->allowsBillingCycle('annual') && (float) $plan->annual_price > 0 ? (float) $plan->annual_price : null;

        $savings = null;
        if ($monthly !== null && $annual !== null && $monthly * 12 > $annual) {
            $savings = (int) round((1 - $annual / ($monthly * 12)) * 100);
        }

        return [
            'name' => (string) $plan->name,
            'slug' => (string) $plan->slug,
            'description' => $plan->description,
            'monthly' => $monthly,
            'annual' => $annual,
            'annual_per_month' => $annual !== null ? round($annual / 12, 2) : null,
            'savings_percent' => $savings ?: null,
            'monthly_only' => $monthly !== null && $annual === null,
            'annual_only' => $annual !== null && $monthly === null,
            'users' => (int) $plan->max_users,
            'sms' => (int) $plan->sms_monthly_limit,
            'whatsapp' => (int) $plan->whatsapp_monthly_limit,
            'bank_accounts' => (int) $plan->bank_feed_accounts_limit,
            'features' => array_values(array_filter(array_map('strval', (array) $plan->features))),
            'monthly_url' => $monthly !== null ? route('register', ['plan' => $plan->slug, 'cycle' => 'monthly']) : null,
            'annual_url' => $annual !== null ? route('register', ['plan' => $plan->slug, 'cycle' => 'annual']) : null,
        ];
    }

    /** "₦25,000", or "₦12,500.50" when there are kobo. */
    public static function naira(float $amount): string
    {
        $decimals = fmod($amount, 1.0) === 0.0 ? 0 : 2;

        return '₦'.number_format($amount, $decimals);
    }
}
