<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChartOfAccountFactory extends Factory
{
    protected $model = ChartOfAccount::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'account_code' => fake()->unique()->numberBetween(90000, 99999),
            'name' => fake()->words(3, true),
            'type' => fake()->randomElement(['asset', 'liability', 'equity', 'income', 'expense']),
            'description' => fake()->sentence(),
            'is_system' => false,
            'is_active' => true,
            'opening_balance' => 0,
            'current_balance' => 0,
        ];
    }

    public function asset(): static
    {
        return $this->state(fn () => [
            'type' => 'asset',
            'account_code' => fake()->unique()->numberBetween(18000, 19999),
        ]);
    }

    public function liability(): static
    {
        return $this->state(fn () => [
            'type' => 'liability',
            'account_code' => fake()->unique()->numberBetween(28000, 29999),
        ]);
    }

    public function equity(): static
    {
        return $this->state(fn () => [
            'type' => 'equity',
            'account_code' => fake()->unique()->numberBetween(38000, 39999),
        ]);
    }

    public function income(): static
    {
        return $this->state(fn () => [
            'type' => 'income',
            'account_code' => fake()->unique()->numberBetween(48000, 49999),
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn () => [
            'type' => 'expense',
            'account_code' => fake()->unique()->numberBetween(58000, 59999),
        ]);
    }

    public function system(): static
    {
        return $this->state(fn () => ['is_system' => true]);
    }
}
