<?php

namespace Tests\Feature\Regression;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Inventory;
use App\Models\InventoryHistory;
use App\Models\InventoryLayer;
use App\Models\Item;
use App\Models\RecurrentBill;
use App\Models\RecurrentBillItem;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Round 3, Phase C, R6: transactions are closures, not begin/commit/rollBack.
 *
 * The old blocks only rolled back on \Exception. A PHP \Error (TypeError,
 * DivisionByZeroError, a bad method call...) skipped the catch, so the
 * transaction was left open with the half-done writes still in it.
 * DB::transaction() rolls back on any Throwable.
 */
class PhaseCR6Test extends TestCase
{
    public function test_r6_an_error_midway_through_a_recurrent_bill_leaves_nothing_behind(): void
    {
        $this->createAuthenticatedUser(['create recurrent-bills', 'view recurrent-bills']);
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $levelBefore = DB::transactionLevel();

        // The profile row is written first; then the first line fails with an \Error.
        RecurrentBillItem::creating(function () {
            throw new \Error('boom');
        });

        $this->post(route('recurrent-bills.store'), [
            'vendor_id' => $vendor->id,
            'profile_name' => 'Office rent',
            'frequency' => 'monthly',
            'start_date' => '2026-10-01',
            'items' => [
                ['description' => 'Rent', 'quantity' => 1, 'unit_price' => 50000],
            ],
        ])->assertStatus(500);

        $this->assertSame($levelBefore, DB::transactionLevel(), 'The transaction was left open.');
        $this->assertSame(0, RecurrentBill::withoutGlobalScopes()->count(), 'A profile without lines was left behind.');
    }

    public function test_r6_an_error_while_receiving_bill_stock_rolls_the_stock_back(): void
    {
        $this->createAuthenticatedUser();
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $item = Item::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'product', 'track_inventory' => true]);

        $bill = Bill::withoutEvents(fn () => Bill::factory()->create([
            'tenant_id' => $this->tenant->id, 'vendor_id' => $vendor->id, 'status' => 'unpaid',
        ]));
        BillItem::withoutEvents(fn () => BillItem::create([
            'bill_id' => $bill->id, 'item_id' => $item->id, 'description' => 'Cement',
            'quantity' => 10, 'unit_price' => 5000, 'tax_rate' => 0, 'tax_amount' => 0, 'total' => 50000,
        ]));

        $levelBefore = DB::transactionLevel();

        // Stock and the FIFO layer are written first; the history row then fails.
        InventoryHistory::creating(function () {
            throw new \Error('boom');
        });

        try {
            $bill->updateInventory();
            $this->fail('The error was swallowed.');
        } catch (\Error $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame($levelBefore, DB::transactionLevel(), 'The transaction was left open.');
        $this->assertSame(0, InventoryLayer::withoutGlobalScopes()->where('reference_id', $bill->id)->count());
        $this->assertEqualsWithDelta(0, (float) Inventory::withoutGlobalScopes()->where('item_id', $item->id)->value('quantity'), 0.001);
        $this->assertNull($bill->fresh()->inventory_updated_at);
    }
}
