<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'expense_number' => 'EXP-'.str_pad(fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'name' => fake()->words(3, true),
            'expense_date' => now(),
            'amount' => fake()->randomFloat(2, 50, 5000),
            'tax_amount' => 0,
            'total' => fake()->randomFloat(2, 50, 5000),
            'status' => Expense::STATUS_DRAFT,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => Expense::STATUS_APPROVED]);
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => Expense::STATUS_PAID]);
    }
}
