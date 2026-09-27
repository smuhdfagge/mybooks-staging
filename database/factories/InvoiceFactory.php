<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'invoice_number' => 'INV-' . str_pad(fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'draft',
            'subtotal' => 1000.00,
            'tax_amount' => 75.00,
            'discount_amount' => 0,
            'total' => 1075.00,
            'amount_paid' => 0,
            'balance_due' => 1075.00,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => 'sent']);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => 'paid',
            'amount_paid' => 1075.00,
            'balance_due' => 0,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => 'overdue',
            'due_date' => now()->subDays(7),
        ]);
    }
}
