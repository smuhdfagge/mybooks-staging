<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(3, true),
            'sku' => strtoupper(fake()->unique()->bothify('???-####')),
            'description' => fake()->sentence(),
            'type' => fake()->randomElement(['product', 'service']),
            'unit' => fake()->randomElement(['pcs', 'kg', 'hrs', 'box']),
            'selling_price' => fake()->randomFloat(2, 10, 1000),
            'cost_price' => fake()->randomFloat(2, 5, 500),
            'tax_rate' => 7.5,
            'is_taxable' => true,
            'track_inventory' => false,
            'is_active' => true,
        ];
    }

    public function product(): static
    {
        return $this->state(fn () => [
            'type' => 'product',
            'track_inventory' => true,
        ]);
    }

    public function service(): static
    {
        return $this->state(fn () => [
            'type' => 'service',
            'track_inventory' => false,
        ]);
    }
}
