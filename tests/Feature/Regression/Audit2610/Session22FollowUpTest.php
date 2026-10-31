<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Actions\Inventory\AdjustStock;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciation;
use App\Models\Inventory;
use App\Models\InventoryLayer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Journal;
use App\Notifications\InvoiceSentNotification;
use App\Services\DepreciationService;
use App\Services\StockValuationService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 22 follow-up (review of PR #135):
 * - MB-31: stock:reconcile-layers took stock sold on an invoice not yet
 *   released (out of the layers, still on hand) for stock with no cost;
 *   one failed row stopped the run; a count-down of stock with no cost was
 *   refused; rounding the weighted-average shares could refuse a sale;
 *   sending an invoice whose posting was refused left it sent, unposted;
 * - MB-30: a document's stock was taken before the document was locked,
 *   its old lines were read without a lock, and first postings of two
 *   documents at once could deadlock on the journal look-up;
 * - MB-32: a reversal read the asset without locking it.
 */
class Session22FollowUpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser(['view invoices', 'send invoices']);
        $this->subscription->update(['ends_at' => '2030-12-31']);
    }

    /** Statements run inside $run, for checking which take a row lock. */
    private function sqlDuring(callable $run): array
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite has no row locks; CI runs this on MariaDB.');
        }
        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = strtolower($query->sql);
        });
        $run();

        return $sql;
    }

    /** $onHand in stock, $layered of it with a cost layer at $cost. */
    private function item(float $onHand, float $layered, float $cost = 100, string $method = 'fifo'): Item
    {
        $item = Item::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Groundnut oil', 'type' => 'product',
            'track_inventory' => true, 'valuation_method' => $method, 'cost_price' => $cost]);
        Inventory::create(['tenant_id' => $this->tenant->id, 'item_id' => $item->id, 'quantity' => $onHand, 'reserved_quantity' => 0, 'unit_cost' => $cost]);
        if ($layered > 0) {
            app(StockValuationService::class)->addLayer($this->tenant->id, $item->id, $layered, $cost);
        }

        return $item;
    }

    private function invoice(Item $item, float $quantity): Invoice
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'buyer@example.com']);
        $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'status' => 'draft']);
        $invoice->items()->create(['item_id' => $item->id, 'description' => 'Oil', 'quantity' => $quantity, 'unit_price' => 500,
            'tax_rate' => 0, 'tax_amount' => 0, 'total' => 500 * $quantity]);

        return $invoice->fresh();
    }

    // ---- stock:reconcile-layers ----------------------------------------------

    public function test_stock_sold_and_not_yet_released_is_not_taken_for_stock_with_no_cost(): void
    {
        $item = $this->item(10, 10);
        $invoice = $this->invoice($item, 4);
        $invoice->update(['status' => 'sent']); // 4 out of the layers, still on hand until released

        $this->artisan('stock:reconcile-layers', ['--apply' => true])
            ->expectsOutputToContain('0 cost layer(s) added, 0 difference(s) found')->assertSuccessful();

        $invoice->fresh()->releaseStock();
        $this->artisan('stock:reconcile-layers')->expectsOutputToContain('0 difference(s) found')->assertSuccessful();
        $this->assertSame(0, InventoryLayer::where('reference_type', 'opening_stock')->count());
    }

    public function test_a_row_that_cannot_be_recorded_is_listed_and_the_run_carries_on(): void
    {
        $this->item(5, 2);
        ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '3900')->delete();

        $this->artisan('stock:reconcile-layers', ['--apply' => true])
            ->expectsOutputToContain('| failed: ')
            ->expectsOutputToContain('0 cost layer(s) added, 1 difference(s) found. 1 failed')
            ->assertFailed();
        $this->assertSame(0, InventoryLayer::where('reference_type', 'opening_stock')->count());
    }

    // ---- issuing stock -------------------------------------------------------

    public function test_a_stock_count_takes_stock_with_no_cost_out_at_no_cost(): void
    {
        $item = $this->item(5, 2, 350);

        app(AdjustStock::class)->handle($item, AdjustStock::OUT, 4, reason: 'Counted');

        $this->assertEqualsWithDelta(1.0, (float) Inventory::where('item_id', $item->id)->value('quantity'), 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) InventoryLayer::where('item_id', $item->id)->sum('remaining_quantity'), 0.0001);
        // Only the 2 with a cost leave the Inventory account: 2 @ 350.
        $journal = Journal::where('journal_type', 'stock_adjustment')->sole();
        $this->assertEqualsWithDelta(700.0, (float) $journal->total_debit, 0.001);
    }

    public function test_weighted_average_rounding_does_not_refuse_stock_that_is_there(): void
    {
        $item = $this->item(13.7777, 0, 10, 'weighted_average');
        foreach ([0.1352, 1.6536, 11.9886, 0.0003] as $quantity) {
            app(StockValuationService::class)->addLayer($this->tenant->id, $item->id, $quantity, 10);
        }

        $this->assertSame(135.85, app(StockValuationService::class)->issue($item, 13.5848, Invoice::class, 1));
        $this->assertEqualsWithDelta(0.1929, (float) InventoryLayer::where('item_id', $item->id)->sum('remaining_quantity'), 0.00001);
    }

    public function test_an_invoice_whose_posting_is_refused_is_neither_sent_nor_marked_sent(): void
    {
        Notification::fake();
        $item = $this->item(5, 2);
        $invoice = $this->invoice($item, 4);

        $this->post(route('invoices.send', $invoice))->assertSessionHasErrors('items');
        $this->postJson(route('api.invoices.send', $invoice))->assertStatus(422);

        $this->assertSame('draft', $invoice->fresh()->status);
        $this->assertSame(0, Journal::where('reference_type', Invoice::class)->where('reference_id', $invoice->id)->count());
        Notification::assertNothingSent();

        // With the stock there, it is posted and sent.
        app(StockValuationService::class)->addLayer($this->tenant->id, $item->id, 3, 100);
        $this->post(route('invoices.send', $invoice))->assertSessionHas('success');
        $this->assertSame('sent', $invoice->fresh()->status);
        Notification::assertSentTo($invoice->customer, InvoiceSentNotification::class);
    }

    // ---- locks (MariaDB) -------------------------------------------------------

    public function test_a_document_is_locked_before_its_stock_and_numbering_before_the_journal_look_up(): void
    {
        $invoice = $this->invoice($this->item(10, 10), 4);
        $sql = $this->sqlDuring(fn () => $invoice->update(['status' => 'sent']));

        $find = fn (string ...$parts) => collect($sql)->search(fn ($q) => collect($parts)->every(fn ($p) => str_contains($q, $p)));
        $document = $find('from `invoices`', 'for update');
        $layers = $find('from `inventory_layers`', 'for update');
        $numbering = $find('from `document_sequences`', 'for update');
        $lookup = $find('from `journals`', '`reference_type`', 'for update');
        $this->assertNotFalse($layers, 'the layers are locked');
        $this->assertLessThan($layers, $document, 'the invoice before its layers');
        $this->assertLessThan($lookup, $numbering, 'the numbering before the journal look-up');
        $this->assertStringContainsString('`tenant_id`', $sql[$lookup]);
    }

    public function test_the_journal_numbering_row_is_made_before_it_is_locked(): void
    {
        DB::table('document_sequences')->where('tenant_id', $this->tenant->id)->where('type', 'Journal.journal_number')->delete();
        $invoice = $this->invoice($this->item(10, 10), 4);

        $invoice->update(['status' => 'sent']);

        $this->assertSame(1, DB::table('document_sequences')->where('tenant_id', $this->tenant->id)->where('type', 'Journal.journal_number')->count());
        $this->assertNotNull(Journal::where('reference_type', Invoice::class)->where('reference_id', $invoice->id)->value('journal_number'));
    }

    // ---- depreciation ------------------------------------------------------------

    public function test_a_depreciation_reversed_twice_is_reversed_once(): void
    {
        $asset = FixedAsset::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Delivery van', 'purchase_date' => '2026-01-31', 'in_service_date' => '2026-01-31',
            'purchase_cost' => 60000, 'salvage_value' => 0, 'depreciable_amount' => 60000, 'useful_life' => 5,
            'depreciation_method' => 'straight_line', 'accumulated_depreciation' => 0, 'book_value' => 60000, 'status' => 'active',
        ]);
        $service = app(DepreciationService::class);
        $service->recordDepreciation($asset, Carbon::parse('2026-01-31'));
        $first = FixedAssetDepreciation::sole();
        $second = FixedAssetDepreciation::sole(); // a second copy, read before the first reversal

        $this->assertTrue($service->reverseDepreciation($first));
        $this->assertSame(FixedAssetDepreciation::STATUS_REVERSED, $first->status);
        try {
            $service->reverseDepreciation($second);
            $this->fail('the month was reversed twice');
        } catch (Exception $e) {
            $this->assertSame('This depreciation cannot be reversed.', $e->getMessage());
        }
        $this->assertEqualsWithDelta(60000.0, (float) $asset->fresh()->book_value, 0.001);
    }
}
