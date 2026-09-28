<?php

namespace Database\Factories;

use App\Models\Bill;
use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillFactory extends Factory
{
    protected $model = Bill::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'vendor_id' => Vendor::factory(),
            'bill_number' => 'BIL-'.str_pad(fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'bill_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'draft',
            'subtotal' => 500.00,
            'tax_amount' => 37.50,
            'discount_amount' => 0,
            'total' => 537.50,
            'amount_paid' => 0,
            'balance_due' => 537.50,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => 'paid',
            'amount_paid' => 537.50,
            'balance_due' => 0,
        ]);
    }
}
