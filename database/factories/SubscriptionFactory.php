<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => Plan::factory(),
            'billing_cycle' => 'monthly',
            'status' => Subscription::STATUS_ACTIVE,
            'amount' => fake()->randomFloat(2, 10, 200),
            'currency' => 'NGN',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => Subscription::STATUS_ACTIVE,
            'ends_at' => now()->addMonth(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => Subscription::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => Subscription::STATUS_EXPIRED,
            'ends_at' => now()->subDay(),
        ]);
    }
}
