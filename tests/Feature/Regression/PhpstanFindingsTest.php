<?php

namespace Tests\Feature\Regression;

use Tests\TestCase;

/**
 * Bugs that PHPStan found once model relationships had types (finding L5).
 */
class PhpstanFindingsTest extends TestCase
{
    public function test_bill_reference_typed_on_the_form_is_saved(): void
    {
        $this->createAuthenticatedUser(['create bills', 'edit bills']);
        $vendor = \App\Models\Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $form = [
            'vendor_id' => $vendor->id, 'bill_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'reference' => 'INV-7781',
            'items' => [['description' => 'Feed', 'quantity' => 1, 'unit_price' => 100]],
        ];

        $this->post(route('bills.store'), $form)->assertSessionHasNoErrors();
        $bill = \App\Models\Bill::sole();
        $this->assertSame('INV-7781', $bill->vendor_bill_number);

        $this->put(route('bills.update', $bill), array_merge($form, ['reference' => 'INV-7782']))->assertSessionHasNoErrors();
        $this->assertSame('INV-7782', $bill->fresh()->vendor_bill_number);
    }

    public function test_api_stock_adjustment_works_and_keeps_cost_layers(): void
    {
        $this->createAuthenticatedUser(['view inventory', 'adjust inventory']);
        $item = \App\Models\Item::factory()->create(['tenant_id' => $this->tenant->id, 'track_inventory' => true, 'cost_price' => 250]);
        $inventory = \App\Models\Inventory::create(['tenant_id' => $this->tenant->id, 'item_id' => $item->id, 'quantity' => 0, 'reserved_quantity' => 0]);
        $api = $this->actingAs($this->user, 'sanctum');

        $api->postJson("/api/v1/inventory/{$inventory->id}/adjust", ['type' => 'add', 'quantity' => 10, 'reason' => 'Opening count'])->assertOk();
        $api->postJson("/api/v1/inventory/{$inventory->id}/adjust", ['type' => 'subtract', 'quantity' => 3, 'reason' => 'Damaged'])->assertOk();
        $api->postJson("/api/v1/inventory/{$inventory->id}/adjust", ['type' => 'subtract', 'quantity' => 30, 'reason' => 'Too many'])
            ->assertStatus(422);

        $this->assertSame(7.0, (float) $inventory->fresh()->quantity);
        $this->assertSame(7.0, (float) \App\Models\InventoryLayer::where('item_id', $item->id)->sum('remaining_quantity'));
        $this->assertSame([10.0, -3.0], \App\Models\InventoryHistory::where('item_id', $item->id)->orderBy('id')->pluck('quantity')->map(fn ($q) => (float) $q)->all());

        $api->getJson("/api/v1/inventory/{$inventory->id}/history")->assertOk()->assertJsonCount(2, 'data');
    }
}
