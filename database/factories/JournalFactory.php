<?php

namespace Database\Factories;

use App\Models\Journal;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class JournalFactory extends Factory
{
    protected $model = Journal::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'journal_number' => 'JE-' . str_pad(fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'journal_date' => now(),
            'reference' => fake()->optional()->bothify('REF-####'),
            'description' => fake()->sentence(),
            'total_debit' => 1000.00,
            'total_credit' => 1000.00,
            'status' => 'draft',
            'is_posted' => false,
        ];
    }

    public function posted(): static
    {
        return $this->state(fn () => [
            'status' => 'posted',
            'is_posted' => true,
            'posted_at' => now(),
        ]);
    }

    public function unbalanced(): static
    {
        return $this->state(fn () => [
            'total_debit' => 1000.00,
            'total_credit' => 500.00,
        ]);
    }
}
