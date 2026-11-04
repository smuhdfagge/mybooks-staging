<?php

namespace Tests\Feature;

use App\Livewire\Invoices\InvoicesTable;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\InventoryLayer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Journal;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\StockValuationService;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Missing Stock Costs (audit MB-31): the page that records the cost of
 * stock on hand with none, so invoices can take it out.
 */
class StockCostsTest extends TestCase
{
    /** 5 on hand, only 2 with a recorded cost. */
    private function driftedItem(float $costPrice = 400, string $name = 'Kaka Walnut 15mm'): Item
    {
        $item = Item::factory()->create(['tenant_id' => $this->tenant->id, 'name' => $name, 'type' => 'product',
            'track_inventory' => true, 'valuation_method' => 'fifo', 'cost_price' => $costPrice]);
        Inventory::create(['tenant_id' => $this->tenant->id, 'item_id' => $item->id, 'quantity' => 5, 'reserved_quantity' => 0, 'unit_cost' => 0]);
        app(StockValuationService::class)->addLayer($this->tenant->id, $item->id, 2, 350);

        return $item;
    }

    private function warehouseId(): int
    {
        return Warehouse::defaultIdFor($this->tenant->id);
    }

    public function test_the_page_lists_stock_with_no_recorded_cost(): void
    {
        $this->createAuthenticatedUser(['view inventory', 'adjust inventory', 'post journals']);
        $this->driftedItem();

        $this->get(route('stock-costs.index'))
            ->assertOk()
            ->assertSee('Kaka Walnut 15mm')
            ->assertSee('Record cost')
            ->assertSee('value="400"', false);
    }

    public function test_the_page_needs_adjust_inventory(): void
    {
        $this->createAuthenticatedUser(['view inventory']);

        $this->get(route('stock-costs.index'))->assertForbidden();
    }

    public function test_without_post_journals_the_page_shows_but_cannot_record(): void
    {
        $this->createAuthenticatedUser(['view inventory', 'adjust inventory']);
        $item = $this->driftedItem();

        $this->get(route('stock-costs.index'))->assertOk()->assertSee('Kaka Walnut 15mm')->assertDontSee('Record cost');
        $this->post(route('stock-costs.store'), ['item_id' => $item->id, 'warehouse_id' => $this->warehouseId(), 'unit_cost' => 400])
            ->assertForbidden();
        $this->assertSame(1, InventoryLayer::count());
    }

    public function test_recording_the_cost_lets_the_invoice_be_sent(): void
    {
        $this->createAuthenticatedUser(['view inventory', 'adjust inventory', 'post journals', 'send invoices']);
        $item = $this->driftedItem(costPrice: 0);
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id, 'customer_id' => Customer::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'invoice_number' => 'INV-004169', 'subtotal' => 2500, 'tax_amount' => 0, 'discount_amount' => 0, 'total' => 2500, 'balance_due' => 2500,
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'item_id' => $item->id, 'description' => $item->name,
            'quantity' => 5, 'unit_price' => 500, 'tax_rate' => 0, 'tax_amount' => 0, 'total' => 2500]);

        // Refused first: 3 of the 5 have no cost.
        Livewire::test(InvoicesTable::class)->set('selectedItems', [(string) $invoice->id])->set('bulkAction', 'mark_sent')->call('applyBulkAction');
        $this->assertSame('draft', $invoice->fresh()->status);

        // No cost price, so the person enters one.
        $this->from(route('stock-costs.index'))
            ->post(route('stock-costs.store'), ['item_id' => $item->id, 'warehouse_id' => $this->warehouseId(), 'unit_cost' => 380])
            ->assertRedirect(route('stock-costs.index'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Recorded the cost of 3 Kaka Walnut 15mm'));

        $layer = InventoryLayer::where('item_id', $item->id)->where('reference_type', 'opening_stock')->sole();
        $this->assertEqualsWithDelta(3.0, (float) $layer->remaining_quantity, 0.0001);
        $this->assertEqualsWithDelta(380.0, (float) $layer->unit_cost, 0.0001);
        // Dr Inventory 1,140, Cr Opening Balance Equity 1,140.
        $journal = Journal::where('reference_type', InventoryLayer::class)->where('reference_id', $layer->id)->sole();
        $lines = $journal->entries()->get()->mapWithKeys(fn ($e) => [ChartOfAccount::find($e->account_id)->account_code => [(float) $e->debit, (float) $e->credit]])->all();
        $this->assertSame(['1300' => [1140.0, 0.0], '3900' => [0.0, 1140.0]], $lines);

        $this->get(route('stock-costs.index'))->assertOk()->assertSee('No stock is missing a cost');

        // Now the invoice goes out.
        Livewire::test(InvoicesTable::class)->set('selectedItems', [(string) $invoice->id])->set('bulkAction', 'mark_sent')->call('applyBulkAction')
            ->assertSet('errorMessage', '');
        $this->assertSame('sent', $invoice->fresh()->status);
    }

    public function test_recording_twice_adds_the_cost_once(): void
    {
        $this->createAuthenticatedUser(['view inventory', 'adjust inventory', 'post journals']);
        $item = $this->driftedItem();
        $payload = ['item_id' => $item->id, 'warehouse_id' => $this->warehouseId(), 'unit_cost' => 400];

        $this->post(route('stock-costs.store'), $payload)->assertSessionHas('success');
        $this->post(route('stock-costs.store'), $payload)->assertSessionHas('error', 'This stock already has a recorded cost.');

        $this->assertSame(1, InventoryLayer::where('reference_type', 'opening_stock')->count());
    }

    public function test_a_unit_cost_is_required_and_must_be_above_zero(): void
    {
        $this->createAuthenticatedUser(['view inventory', 'adjust inventory', 'post journals']);
        $item = $this->driftedItem();

        $this->post(route('stock-costs.store'), ['item_id' => $item->id, 'warehouse_id' => $this->warehouseId(), 'unit_cost' => 0])
            ->assertSessionHasErrors('unit_cost');
        $this->assertSame(0, InventoryLayer::where('reference_type', 'opening_stock')->count());
    }

    public function test_another_business_items_cannot_be_recorded(): void
    {
        // Made before signing in: once signed in, new records belong to that business.
        $other = Tenant::factory()->create();
        $theirs = Item::factory()->create(['tenant_id' => $other->id, 'track_inventory' => true]);
        $this->createAuthenticatedUser(['view inventory', 'adjust inventory', 'post journals']);

        $this->post(route('stock-costs.store'), ['item_id' => $theirs->id, 'warehouse_id' => $this->warehouseId(), 'unit_cost' => 400])
            ->assertSessionHasErrors('item_id');
        $this->assertSame(0, InventoryLayer::where('reference_type', 'opening_stock')->count());
    }
}
