<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Starter', 'Professional', 'Enterprise']),
            'slug' => fake()->unique()->slug(),
            'description' => fake()->sentence(),
            'monthly_price' => fake()->randomFloat(2, 10, 200),
            'annual_price' => fake()->randomFloat(2, 100, 2000),
            'allow_monthly_billing' => true,
            'allow_annual_billing' => true,
            'max_users' => 10,
            'features' => ['invoices', 'bills', 'reports'],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
