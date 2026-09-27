<?php

namespace Database\Factories;

use App\Models\JournalEntry;
use App\Models\Journal;
use App\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    public function definition(): array
    {
        return [
            'journal_id' => Journal::factory(),
            'account_id' => ChartOfAccount::factory(),
            'description' => fake()->sentence(),
            'debit' => 0,
            'credit' => 0,
        ];
    }

    public function debit(float $amount = 1000.00): static
    {
        return $this->state(fn () => [
            'debit' => $amount,
            'credit' => 0,
        ]);
    }

    public function credit(float $amount = 1000.00): static
    {
        return $this->state(fn () => [
            'debit' => 0,
            'credit' => $amount,
        ]);
    }
}
