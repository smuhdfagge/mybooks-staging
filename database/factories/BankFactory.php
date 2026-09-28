<?php

namespace Database\Factories;

use App\Models\Bank;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankFactory extends Factory
{
    protected $model = Bank::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company().' Account',
            'bank_name' => fake()->company().' Bank',
            'account_number' => fake()->numerify('##########'),
            'account_type' => Bank::TYPE_CHECKING,
            'currency' => 'NGN',
            'opening_balance' => fake()->randomFloat(2, 1000, 100000),
            'current_balance' => fake()->randomFloat(2, 1000, 100000),
            'is_primary' => false,
            'is_active' => true,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function savings(): static
    {
        return $this->state(fn () => ['account_type' => Bank::TYPE_SAVINGS]);
    }
}
