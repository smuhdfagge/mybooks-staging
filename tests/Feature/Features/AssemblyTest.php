<?php

namespace Tests\Feature\Features;

use App\Actions\Assembly\CancelAssemblyOrder;
use App\Actions\Assembly\CompleteAssemblyOrder;
use App\Actions\Assembly\DeleteAssemblyOrder;
use App\Actions\Assembly\SaveAssemblyOrder;
use App\Actions\Assembly\SaveBillOfMaterial;
use App\Actions\Assembly\UndoAssemblyOrder;
use App\Actions\Bills\SaveBill;
use App\Actions\Invoices\SaveInvoice;
use App\Actions\LockDates\UpdateLockDates;
use App\Livewire\Assembly\AssemblyOrdersTable;
use App\Models\AssemblyOrder;
use App\Models\AssemblyOrderItem;
use App\Models\BillOfMaterial;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\InventoryHistory;
use App\Models\InventoryLayer;
use App\Models\InventoryLayerConsumption;
use App\Models\Item;
use App\Models\Journal;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\AccountCodeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Session 14: assembly with bills of materials. A build takes the
 * components out at their FIFO / average cost and puts the finished goods
 * in as a cost layer worth exactly that plus the extra costs; only the
 * extra costs post a journal (Dr Inventory, Cr the chosen account).
 */
class AssemblyTest extends TestCase
{
    private const PERMISSIONS = [
        'view invoices', 'create invoices', 'view customers', 'view bills', 'create bills',
        'view inventory', 'adjust inventory', 'view items', 'create items', 'edit items', 'delete items',
        'view reports', 'view journals',
    ];

    private Customer $customer;

    private Vendor $vendor;

    private Warehouse $main;

    private Warehouse $kano;

    private Item $maize;

    private Item $soya;

    private Item $premix;

    private Item $feed;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->createAuthenticatedUser(self::PERMISSIONS);
        $this->subscription->update(['ends_at' => '2030-12-31']);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->main = Warehouse::getDefault($this->tenant->id);
        $this->kano = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Kano shop', 'code' => 'KANO']);

        $this->maize = $this->item('Maize', 'fifo', 'kg');
        $this->soya = $this->item('Soya', 'weighted_average', 'kg');
        $this->premix = $this->item('Premix', 'fifo', 'kg');
        $this->feed = $this->item('Feed 25kg bag', 'fifo', 'bag');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---- helpers -------------------------------------------------------

    private function item(string $name, string $method = 'fifo', string $unit = 'pcs', bool $stock = true): Item
    {
        return Item::factory()->product()->create([
            'tenant_id' => $this->tenant->id, 'name' => $name, 'unit' => $unit, 'cost_price' => 0, 'selling_price' => 15000,
            'valuation_method' => $method, 'track_inventory' => $stock,
        ]);
    }

    private function buy(Item $item, float $qty, float $cost, ?Warehouse $warehouse = null, string $date = '2026-10-01'): void
    {
        app(SaveBill::class)->create($this->tenant->id, [
            'vendor_id' => $this->vendor->id, 'bill_date' => $date, 'due_date' => '2026-11-30',
            'warehouse_id' => ($warehouse ?? $this->main)->id,
            'items' => [['item_id' => $item->id, 'description' => $item->name, 'quantity' => $qty, 'unit_price' => $cost, 'tax_rate' => 0]],
        ], $this->user->id);
    }

    /** Maize in two lots at different prices (FIFO), soya, premix. */
    private function stockUp(?Warehouse $warehouse = null): void
    {
        $this->buy($this->maize, 500, 300, $warehouse, '2026-10-01');
        $this->buy($this->maize, 500, 340, $warehouse, '2026-10-02');
        $this->buy($this->soya, 300, 600, $warehouse);
        $this->buy($this->premix, 100, 1500, $warehouse);
    }

    /**
     * @param  array<int, array{0: Item, 1: float, 2?: float}>  $parts
     * @param  array<int, array{0: string, 1: float, 2?: ?int}>  $costs
     */
    private function bom(?Item $finished = null, array $parts = [], float $output = 40, array $costs = [['Labour', 20000]]): BillOfMaterial
    {
        $parts = $parts ?: [[$this->maize, 700], [$this->soya, 250], [$this->premix, 50]];

        return app(SaveBillOfMaterial::class)->create($this->tenant->id, [
            'item_id' => ($finished ?? $this->feed)->id, 'name' => 'Layer feed', 'output_quantity' => $output,
            'components' => array_map(fn ($p) => ['item_id' => $p[0]->id, 'quantity' => $p[1], 'waste_percentage' => $p[2] ?? 0], $parts),
            'costs' => array_map(fn ($c) => ['description' => $c[0], 'amount' => $c[1], 'account_id' => $c[2] ?? null], $costs),
        ]);
    }

    private function draft(BillOfMaterial $bom, float $quantity = 40, ?Warehouse $from = null, ?Warehouse $to = null, string $date = '2026-10-10', string $kind = 'build'): AssemblyOrder
    {
        return app(SaveAssemblyOrder::class)->create($this->tenant->id, [
            'kind' => $kind, 'bill_of_materials_id' => $bom->id, 'assembly_date' => $date, 'quantity' => $quantity,
            'warehouse_id' => ($from ?? $this->main)->id, 'to_warehouse_id' => ($to ?? $from ?? $this->main)->id,
        ], $this->user->id);
    }

    /** @param array<string, mixed> $actual */
    private function build(BillOfMaterial $bom, float $quantity = 40, array $actual = [], ?Warehouse $from = null, ?Warehouse $to = null): AssemblyOrder
    {
        return app(CompleteAssemblyOrder::class)->handle($this->draft($bom, $quantity, $from, $to), $actual);
    }

    private function onHand(Item $item, ?Warehouse $warehouse = null): float
    {
        return (float) Inventory::where('item_id', $item->id)->where('warehouse_id', ($warehouse ?? $this->main)->id)->value('quantity');
    }

    /** @return array<int, array{0: float, 1: float}> [remaining, unit cost] */
    private function layers(Item $item, ?Warehouse $warehouse = null): array
    {
        return InventoryLayer::where('item_id', $item->id)->where('warehouse_id', ($warehouse ?? $this->main)->id)->where('remaining_quantity', '>', 0)
            ->orderBy('received_date')->orderBy('id')->get()
            ->map(fn ($l) => [(float) $l->remaining_quantity, (float) $l->unit_cost])->all();
    }

    private function layerValue(Item $item, ?Warehouse $warehouse = null): float
    {
        return round((float) InventoryLayer::where('item_id', $item->id)->where('warehouse_id', ($warehouse ?? $this->main)->id)
            ->selectRaw('SUM(remaining_quantity * unit_cost) as v')->value('v'), 2);
    }

    private function stockValue(): float
    {
        return round((float) InventoryLayer::where('tenant_id', $this->tenant->id)->selectRaw('SUM(remaining_quantity * unit_cost) as v')->value('v'), 2);
    }

    private function balance(string $code): float
    {
        return round((float) ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', $code)->value('current_balance'), 2);
    }

    private function sell(Item $item, float $qty, ?Warehouse $warehouse = null): void
    {
        app(SaveInvoice::class)->create($this->tenant->id, [
            'customer_id' => $this->customer->id, 'invoice_date' => '2026-10-15', 'due_date' => '2026-11-15', 'status' => 'unpaid',
            'warehouse_id' => ($warehouse ?? $this->main)->id,
            'items' => [['item_id' => $item->id, 'description' => $item->name, 'quantity' => $qty, 'unit_price' => 15000, 'tax_rate' => 0]],
        ], $this->user->id);
    }

    private function otherTenant(): Tenant
    {
        auth()->forgetGuards();
        [$other] = $this->createTenantWithSubscription();
        $this->actingAs($this->user);

        return $other;
    }

    // ---- bills of materials -----------------------------------------------

    public function test_a_bill_needs_stock_items_and_refuses_making_an_item_from_itself(): void
    {
        $labour = Item::factory()->service()->create(['tenant_id' => $this->tenant->id, 'name' => 'Labour hours']);
        $payload = fn (Item $made, array $parts) => [
            'item_id' => $made->id, 'name' => 'Feed', 'output_quantity' => 40,
            'components' => array_map(fn ($p) => ['item_id' => $p->id, 'quantity' => 1], $parts),
        ];

        $this->post(route('bill-of-materials.store'), $payload($labour, [$this->maize]))->assertSessionHasErrors('item_id');
        $this->post(route('bill-of-materials.store'), $payload($this->feed, [$this->maize, $labour]))
            ->assertSessionHasErrors(['components.1.item_id' => "Labour hours doesn't keep stock, so it can't be a component. Put costs like labour under extra costs instead."]);
        $this->post(route('bill-of-materials.store'), $payload($this->feed, [$this->feed]))
            ->assertSessionHasErrors(['components.0.item_id' => "Feed 25kg bag can't be made from itself."]);
        $this->post(route('bill-of-materials.store'), ['item_id' => $this->feed->id, 'name' => 'Feed', 'output_quantity' => 0, 'components' => [['item_id' => $this->maize->id, 'quantity' => 1]]])
            ->assertSessionHasErrors('output_quantity');
        $this->assertSame(0, BillOfMaterial::count());

        $this->post(route('bill-of-materials.store'), $payload($this->feed, [$this->maize, $this->soya]))->assertSessionHasNoErrors();
        $this->assertSame(1, BillOfMaterial::count());
    }

    public function test_sub_assemblies_are_allowed_but_loops_are_refused(): void
    {
        $mash = $this->item('Mash', 'fifo', 'kg');
        // Mash is made from maize; feed from mash (a sub-assembly): fine.
        $this->bom($mash, [[$this->maize, 10]], 10, []);
        $this->bom($this->feed, [[$mash, 25]], 1, []);

        // Maize from feed would go round: maize -> feed -> mash -> maize.
        try {
            $this->bom($this->maize, [[$this->soya, 1], [$this->feed, 1]], 1, []);
            $this->fail('a loop through other bills was accepted');
        } catch (ValidationException $e) {
            $this->assertSame(['Feed 25kg bag is itself made from Maize (on another bill of materials), so it can\'t be one of its components.'], $e->errors()['components.1.item_id']);
        }
        $this->assertSame(2, BillOfMaterial::count());
    }

    public function test_extra_costs_cannot_be_credited_to_inventory_and_default_to_production_costs_applied(): void
    {
        $inventory = ChartOfAccount::where('account_code', '1300')->first();
        try {
            $this->bom(null, [], 40, [['Labour', 20000, $inventory->id]]);
            $this->fail('crediting Inventory itself');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('costs.0.account_id', $e->errors());
        }

        $bom = $this->bom();
        $this->assertSame('5500', $bom->costs->first()->account->account_code);
        $this->assertSame('Production Costs Applied', $bom->costs->first()->account->name);
    }

    public function test_the_estimate_uses_todays_component_costs(): void
    {
        $this->stockUp();
        $bom = $this->bom(null, [[$this->maize, 700, 2], [$this->soya, 250], [$this->premix, 50]]);

        $estimate = $bom->estimate();

        // Maize at its average 320 x 714 kg (2% wastage), soya 600 x 250, premix 1,500 x 50.
        $this->assertSame(228480.0 + 150000.0 + 75000.0, $estimate['components']);
        $this->assertSame(20000.0, $estimate['extra']);
        $this->assertSame(round((453480 + 20000) / 40, 2), $estimate['per_unit']);
        // Soya runs out first: 300 kg / 6.25 kg a bag = 48 bags.
        $this->assertSame(48.0, $bom->maxBuildable($this->main->id));
    }

    // ---- completing a build -------------------------------------------------

    public function test_a_build_moves_fifo_component_cost_into_one_finished_layer_plus_extra_costs(): void
    {
        $this->stockUp();
        $bom = $this->bom();
        $value = $this->stockValue();
        $ledger = $this->balance('1300');

        $order = $this->build($bom);

        $this->assertSame(AssemblyOrder::STATUS_COMPLETED, $order->status);
        // Maize: 500 at 300 then 200 at 340 = 218,000; soya 150,000; premix 75,000.
        $this->assertSame([218000.0, 150000.0, 75000.0], $order->items->map(fn ($l) => (float) $l->cost)->all());
        $this->assertSame(443000.0, (float) $order->components_cost);
        $this->assertSame(20000.0, (float) $order->extra_cost);
        $this->assertSame(463000.0, (float) $order->total_cost);
        $this->assertSame([[40.0, 11575.0]], $this->layers($this->feed));
        $this->assertSame(463000.0, $this->layerValue($this->feed));
        $this->assertSame(40.0, $this->onHand($this->feed));
        $this->assertEquals(11575, (float) Inventory::where('item_id', $this->feed->id)->value('unit_cost'));

        $this->assertSame([[300.0, 340.0]], $this->layers($this->maize));
        $this->assertSame(300.0, $this->onHand($this->maize));
        $this->assertSame(50.0, $this->onHand($this->soya));
        $this->assertSame(50.0, $this->onHand($this->premix));
        // Two pieces of maize (one per lot), one each of soya and premix.
        $this->assertSame(4, InventoryLayerConsumption::where('source_type', AssemblyOrderItem::class)->count());

        // Components to finished goods is Inventory to Inventory: only the labour moves the ledger.
        $this->assertSame(round($value + 20000, 2), $this->stockValue());
        $this->assertSame(round($ledger + 20000, 2), $this->balance('1300'));
    }

    public function test_extra_costs_post_a_balanced_journal_to_the_chosen_account(): void
    {
        $this->stockUp();
        $wages = ChartOfAccount::where('account_code', '2210')->first();
        $bom = $this->bom(null, [], 40, [['Labour', 20000, $wages->id], ['Power', 5000]]);

        $order = $this->build($bom);

        $journal = Journal::where('reference_type', AssemblyOrder::class)->where('reference_id', $order->id)->sole();
        $this->assertSame('2026-10-10', $journal->journal_date->toDateString());
        $this->assertSame(25000.0, (float) $journal->total_debit);
        $this->assertSame(25000.0, (float) $journal->total_credit);
        $lines = $journal->entries()->with('account')->get()
            ->map(fn ($e) => [$e->account->account_code, (float) $e->debit, (float) $e->credit])->all();
        $this->assertSame([['1300', 25000.0, 0.0], ['2210', 0.0, 20000.0], ['5500', 0.0, 5000.0]], $lines);
        $this->assertSame(468000.0, (float) $order->total_cost);
    }

    public function test_a_build_with_no_extra_costs_posts_nothing(): void
    {
        $this->stockUp();
        $journals = Journal::count();

        $this->build($this->bom(null, [], 40, []));

        $this->assertSame($journals, Journal::count());
        $this->assertSame([[40.0, 11075.0]], $this->layers($this->feed));
    }

    public function test_weighted_average_components_go_in_at_their_average_cost(): void
    {
        $this->buy($this->soya, 10, 1000);
        $this->buy($this->soya, 10, 1600);
        $bag = $this->item('Soya cake', 'weighted_average', 'bag');
        $bom = $this->bom($bag, [[$this->soya, 5]], 1, []);

        $order = $this->build($bom, 1);

        $this->assertSame(6500.0, (float) $order->total_cost);
        $this->assertSame([[1.0, 6500.0]], $this->layers($bag));
        $this->assertEquals(1300, (float) Inventory::where('item_id', $this->soya->id)->value('unit_cost'));
        $this->assertSame(19500.0, $this->layerValue($this->soya));
    }

    public function test_wastage_is_taken_from_stock_and_is_part_of_the_cost(): void
    {
        $this->stockUp();
        $bom = $this->bom(null, [[$this->maize, 700, 2], [$this->soya, 250], [$this->premix, 50]]);

        $order = $this->build($bom);

        $this->assertSame(714.0, (float) $order->items->first()->planned_quantity);
        $this->assertSame(286.0, $this->onHand($this->maize));
        // 500 at 300 + 214 at 340.
        $this->assertSame(222760.0, (float) $order->items->first()->cost);
        $this->assertSame(222760.0 + 150000.0 + 75000.0 + 20000.0, (float) $order->total_cost);
    }

    public function test_actual_quantities_used_can_differ_from_the_plan(): void
    {
        $this->stockUp();
        $order = $this->draft($this->bom());
        $maizeLine = $order->items->first();
        $labour = $order->costs->first();

        $order = app(CompleteAssemblyOrder::class)->handle($order, [
            'items' => [$maizeLine->id => 720], 'costs' => [$labour->id => 18000],
        ]);

        $this->assertSame(720.0, (float) $order->items->first()->quantity);
        $this->assertSame(700.0, (float) $order->items->first()->planned_quantity);
        $this->assertSame(280.0, $this->onHand($this->maize));
        $this->assertSame(224800.0, (float) $order->items->first()->cost);
        $this->assertSame(18000.0, (float) $order->extra_cost);
        $this->assertSame(224800.0 + 150000.0 + 75000.0 + 18000.0, (float) $order->total_cost);
    }

    public function test_making_fewer_than_planned_raises_the_unit_cost_and_the_layers_add_up_to_the_kobo(): void
    {
        $this->stockUp();

        $order = $this->build($this->bom(), 40, ['quantity_made' => 39]);

        $this->assertSame(39.0, (float) $order->quantity_made);
        $this->assertSame(40.0, (float) $order->planned_quantity);
        $this->assertSame(463000.0, (float) $order->total_cost);
        $this->assertSame(11871.7949, (float) $order->unit_cost);
        $this->assertGreaterThan(11575, (float) $order->unit_cost);
        // 463,000 / 39 doesn't divide to 4 decimals: the last bag carries the difference.
        $this->assertSame([[38.0, 11871.7949], [1.0, 11871.7938]], $this->layers($this->feed));
        $this->assertSame(463000.0, $this->layerValue($this->feed));
        $this->assertSame(39.0, $this->onHand($this->feed));
    }

    public function test_components_and_finished_goods_can_use_different_warehouses(): void
    {
        $this->stockUp();

        $order = $this->build($this->bom(), 40, [], $this->main, $this->kano);

        $this->assertSame(300.0, $this->onHand($this->maize, $this->main));
        $this->assertSame(0.0, $this->onHand($this->feed, $this->main));
        $this->assertSame(40.0, $this->onHand($this->feed, $this->kano));
        $this->assertSame([[40.0, 11575.0]], $this->layers($this->feed, $this->kano));
        $this->assertSame($this->kano->id, (int) $order->to_warehouse_id);
    }

    public function test_not_enough_free_stock_is_refused_and_reserved_stock_is_not_free(): void
    {
        $this->stockUp();
        $bom = $this->bom();
        // An invoice holds 100 kg of soya: 200 free, the build needs 250.
        $this->sell($this->soya, 100);

        $this->post(route('assembly-orders.store'), [
            'bill_of_materials_id' => $bom->id, 'assembly_date' => '2026-10-10', 'quantity' => 40, 'action' => 'complete',
        ])->assertSessionHasErrors(['items' => 'Only 200 kg Soya free in '.$this->main->name.' (300 on hand, 100 reserved for invoices), but this build needs 250 kg.']);

        $this->assertSame(0, AssemblyOrder::count(), 'nothing is kept when the build fails');
        $this->assertSame(1000.0, $this->onHand($this->maize));
        $this->assertSame(0.0, $this->onHand($this->feed));

        // 32 bags need 200 kg of soya: that works.
        $this->post(route('assembly-orders.store'), [
            'bill_of_materials_id' => $bom->id, 'assembly_date' => '2026-10-10', 'quantity' => 32, 'action' => 'complete',
        ])->assertSessionHasNoErrors();
        $this->assertSame(32.0, $this->onHand($this->feed));
        $this->assertSame(100.0, $this->onHand($this->soya));
    }

    public function test_stock_history_says_used_in_and_made_in_and_links_to_the_order(): void
    {
        $this->stockUp();
        $order = $this->build($this->bom());

        $maize = InventoryHistory::where('item_id', $this->maize->id)->where('reference_type', 'assembly_order')->sole();
        $this->assertSame('assembly', $maize->type);
        $this->assertSame(-700.0, (float) $maize->quantity);
        $this->assertSame("Used in {$order->order_number}", $maize->notes);
        $feed = InventoryHistory::where('item_id', $this->feed->id)->where('reference_type', 'assembly_order')->sole();
        $this->assertSame(40.0, (float) $feed->quantity);
        $this->assertSame("Made in {$order->order_number}", $feed->notes);

        $this->get(route('inventory.history', $this->feed))->assertOk()
            ->assertSee(route('assembly-orders.show', $order), false)->assertSee("Made in {$order->order_number}")->assertSee('Assembly');
        $this->get(route('inventory.show', $this->maize))->assertOk()->assertSee("Used in {$order->order_number}");
    }

    public function test_a_later_sale_of_the_finished_goods_uses_the_assembled_cost(): void
    {
        $this->stockUp();
        $this->build($this->bom());
        $cogs = $this->balance('5000');

        $this->sell($this->feed, 10);

        $this->assertSame(round($cogs + 115750, 2), $this->balance('5000'));
        $this->assertSame([[30.0, 11575.0]], $this->layers($this->feed));
    }

    // ---- undo ------------------------------------------------------------------

    public function test_undo_build_puts_components_back_at_exactly_the_cost_they_left_at(): void
    {
        $this->stockUp();
        $before = [$this->layers($this->maize), $this->layers($this->soya), $this->layers($this->premix)];
        $value = $this->stockValue();
        $ledger = $this->balance('1300');
        $order = $this->build($this->bom(), 40, ['quantity_made' => 39]);

        $order = app(UndoAssemblyOrder::class)->handle($order);

        $this->assertSame(AssemblyOrder::STATUS_DRAFT, $order->status);
        $this->assertNull($order->quantity_made);
        $this->assertSame($before, [$this->layers($this->maize), $this->layers($this->soya), $this->layers($this->premix)]);
        $this->assertSame(1000.0, $this->onHand($this->maize));
        $this->assertSame([], $this->layers($this->feed));
        $this->assertSame(0.0, $this->onHand($this->feed));
        $this->assertSame($value, $this->stockValue());
        $this->assertSame($ledger, $this->balance('1300'), 'the labour journal is reversed');
        $this->assertSame(0, InventoryLayerConsumption::where('source_type', AssemblyOrderItem::class)->count());
        $journals = Journal::where('reference_type', AssemblyOrder::class)->where('reference_id', $order->id)->orderBy('id')->get();
        $this->assertSame(['reversed', 'posted'], $journals->pluck('status')->all());
        $this->assertStringStartsWith('REV-', (string) $journals->last()->reference);

        // It can be built again, as a draft.
        $again = app(CompleteAssemblyOrder::class)->handle($order);
        $this->assertSame(463000.0, (float) $again->total_cost);
        $this->assertSame([[40.0, 11575.0]], $this->layers($this->feed));
    }

    public function test_undo_is_refused_once_the_finished_goods_have_been_sold(): void
    {
        $this->stockUp();
        $order = $this->build($this->bom());
        $this->sell($this->feed, 10);

        try {
            app(UndoAssemblyOrder::class)->handle($order);
            $this->fail('undoing a build whose goods were sold');
        } catch (ValidationException $e) {
            $this->assertSame(["Some of the 40 Feed 25kg bag made in {$order->order_number} have been sold, used or reserved since, so this build can't be undone."], $e->errors()['order']);
        }
        $this->assertSame(AssemblyOrder::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertSame(300.0, $this->onHand($this->maize));

        $this->post(route('assembly-orders.undo', $order))->assertSessionHasErrors('order');
    }

    // ---- break-down ------------------------------------------------------------

    public function test_a_break_down_takes_kits_apart_and_splits_their_cost_by_the_bill(): void
    {
        $rice = $this->item('Rice 5kg', 'fifo');
        $oil = $this->item('Oil 1L', 'fifo');
        $hamper = $this->item('Gift hamper', 'fifo');
        $this->buy($rice, 10, 6000);
        $this->buy($oil, 10, 3000);
        $bom = $this->bom($hamper, [[$rice, 1], [$oil, 2]], 1, [['Basket and wrapping', 1000]]);
        $this->build($bom, 5);
        $this->assertSame([[5.0, 13000.0]], $this->layers($hamper));
        $value = $this->stockValue();
        $journals = Journal::count();

        $order = app(CompleteAssemblyOrder::class)->handle($this->draft($bom, 2, null, null, '2026-10-11', 'breakdown'));

        $this->assertSame(3.0, $this->onHand($hamper));
        $this->assertSame(7.0, $this->onHand($rice));
        $this->assertSame(4.0, $this->onHand($oil));
        // 26,000 shared by today's costs: rice 2 x 6,000 = 12,000, oil 4 x 3,000 (none in stock: its
        // last cost) = 12,000 -> half each.
        $this->assertSame([13000.0, 13000.0], $order->items->map(fn ($l) => (float) $l->cost)->all());
        $this->assertSame([[5.0, 6000.0], [2.0, 6500.0]], $this->layers($rice));
        $this->assertSame([[4.0, 3250.0]], $this->layers($oil));
        $this->assertSame($value, $this->stockValue(), 'a break-down moves value, it does not change it');
        $this->assertSame($journals, Journal::count());

        app(UndoAssemblyOrder::class)->handle($order);
        $this->assertSame(5.0, $this->onHand($hamper));
        $this->assertSame([[5.0, 13000.0]], $this->layers($hamper));
        $this->assertSame([[5.0, 6000.0]], $this->layers($rice));
        $this->assertSame($value, $this->stockValue());
    }

    // ---- draft, cancel, delete, lock dates --------------------------------------

    public function test_a_draft_moves_nothing_and_can_be_cancelled_or_deleted_but_a_completed_one_cannot(): void
    {
        $this->stockUp();
        $bom = $this->bom();
        $draft = $this->draft($bom);
        $this->assertSame(1000.0, $this->onHand($this->maize));
        $this->assertSame(3, $draft->items()->count());

        app(CancelAssemblyOrder::class)->handle($draft);
        $this->assertSame(AssemblyOrder::STATUS_CANCELLED, $draft->fresh()->status);
        try {
            app(CompleteAssemblyOrder::class)->handle($draft);
            $this->fail('completing a cancelled order');
        } catch (ValidationException) {
        }
        app(DeleteAssemblyOrder::class)->handle($draft->fresh());
        $this->assertNull(AssemblyOrder::find($draft->id));

        $done = $this->build($bom, 8);
        $this->delete(route('assembly-orders.destroy', $done))->assertSessionHas('error');
        $this->post(route('assembly-orders.cancel', $done))->assertSessionHas('error');
        $this->assertSame(AssemblyOrder::STATUS_COMPLETED, $done->fresh()->status);
    }

    public function test_lock_dates_apply_to_completing_undoing_and_deleting(): void
    {
        $this->stockUp();
        $bom = $this->bom();
        app(UpdateLockDates::class)->handle($this->tenant, ['staff_lock_date' => '2026-10-05', 'all_users_lock_date' => null, 'reason' => null]);

        $locked = $this->draft($bom, 40, null, null, '2026-10-03');
        try {
            app(CompleteAssemblyOrder::class)->handle($locked);
            $this->fail('completing on a locked date');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('assembly_date', $e->errors());
        }
        $this->assertSame(1000.0, $this->onHand($this->maize));
        $this->assertSame(AssemblyOrder::STATUS_DRAFT, $locked->fresh()->status);
        try {
            app(DeleteAssemblyOrder::class)->handle($locked);
            $this->fail('deleting an order dated in a locked month');
        } catch (ValidationException) {
        }

        $done = $this->build($bom, 8);
        app(UpdateLockDates::class)->handle($this->tenant, ['staff_lock_date' => '2026-10-12', 'all_users_lock_date' => null, 'reason' => null]);
        try {
            app(UndoAssemblyOrder::class)->handle($done);
            $this->fail('undoing a build in a locked month');
        } catch (ValidationException) {
        }
        $this->assertSame(AssemblyOrder::STATUS_COMPLETED, $done->fresh()->status);
    }

    // ---- screens ------------------------------------------------------------------

    public function test_the_screens_work_through_the_whole_flow(): void
    {
        $this->stockUp();

        $this->get(route('bill-of-materials.create'))->assertOk()->assertSee('Components per batch')->assertSee('Production Costs Applied');
        $this->getJson(route('bill-of-materials.items', ['q' => 'Mai']))->assertOk()
            ->assertJsonPath('data.0.name', 'Maize')->assertJsonPath('data.0.cost', 320);
        $this->post(route('bill-of-materials.store'), [
            'item_id' => $this->feed->id, 'name' => 'Layer feed', 'version' => 'v1', 'output_quantity' => 40, 'is_active' => '1',
            'components' => [
                ['item_id' => $this->maize->id, 'quantity' => 700, 'waste_percentage' => 0],
                ['item_id' => $this->soya->id, 'quantity' => 250],
                ['item_id' => $this->premix->id, 'quantity' => 50],
                ['item_id' => '', 'quantity' => ''],
            ],
            'costs' => [['description' => 'Labour', 'amount' => 20000, 'account_id' => '']],
        ])->assertSessionHasNoErrors();
        $bom = BillOfMaterial::sole();
        $this->assertSame(3, $bom->components()->count());

        $this->get(route('bill-of-materials.index'))->assertOk()->assertSee('Layer feed (v1)');
        $this->get(route('bill-of-materials.show', $bom))->assertOk()->assertSee('Estimated cost')
            ->assertSee('₦11,725.00')->assertSee(route('assembly-orders.create', ['bill' => $bom->id]), false);
        $this->get(route('bill-of-materials.edit', $bom))->assertOk()->assertSee('Maize');

        $this->get(route('assembly-orders.create', ['bill' => $bom->id]))->assertOk()->assertSee('Build now')->assertSee('ASM-000001');
        $this->getJson(route('assembly-orders.availability', ['bill_of_materials_id' => $bom->id, 'warehouse_id' => $this->main->id]))->assertOk()
            ->assertJsonPath('max', 48)->assertJsonPath('components.0.name', 'Maize')->assertJsonPath('components.0.free', 1000)
            ->assertJsonPath('unit_cost', 11725);

        $this->post(route('assembly-orders.store'), [
            'bill_of_materials_id' => $bom->id, 'assembly_date' => '2026-10-10', 'quantity' => 40, 'action' => 'draft', 'notes' => 'Morning shift',
        ])->assertSessionHasNoErrors();
        $order = AssemblyOrder::sole();
        $this->assertSame(AssemblyOrder::STATUS_DRAFT, $order->status);
        $this->get(route('assembly-orders.show', $order))->assertOk()->assertSee('Complete build')->assertSee('Free now');
        $this->get(route('assembly-orders.edit', $order))->assertOk();
        $this->put(route('assembly-orders.update', $order), [
            'bill_of_materials_id' => $bom->id, 'assembly_date' => '2026-10-10', 'quantity' => 80, 'action' => 'draft',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1400.0, (float) $order->items()->first()->planned_quantity);
        $this->put(route('assembly-orders.update', $order), [
            'bill_of_materials_id' => $bom->id, 'assembly_date' => '2026-10-10', 'quantity' => 40, 'action' => 'draft',
        ])->assertSessionHasNoErrors();

        $lines = $order->items()->get();
        $this->post(route('assembly-orders.complete', $order), [
            'quantity_made' => 39,
            'items' => $lines->map(fn ($l) => ['id' => $l->id, 'quantity' => (float) $l->planned_quantity])->all(),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $order->refresh();
        $this->assertSame(39.0, (float) $order->quantity_made);

        $this->get(route('assembly-orders.show', $order))->assertOk()->assertSee('Undo build')
            ->assertSee('39 made instead of 40')->assertSee('₦463,000.00');
        $this->get(route('assembly-orders.print', $order))->assertOk()->assertSee('PRODUCTION SHEET')->assertSee('Maize')->assertSee('Made by');
        $this->get(route('assembly-orders.index'))->assertOk()->assertSee('Assembly orders');
        $this->get(route('assembly-orders.report', ['from' => '2026-10-10', 'to' => '2026-10-10']))->assertOk()
            ->assertSee('Feed 25kg bag')->assertSee('₦463,000.00');

        Livewire::test(AssemblyOrdersTable::class)->assertSee($order->order_number)
            ->set('status', 'draft')->assertDontSee($order->order_number)
            ->set('status', '')->set('kind', 'breakdown')->assertDontSee($order->order_number)
            ->set('kind', '')->set('search', 'Feed')->assertSee($order->order_number);
    }

    public function test_numbers_follow_on(): void
    {
        $bom = $this->bom();

        $this->assertSame('ASM-000001', $this->draft($bom)->order_number);
        $this->assertSame('ASM-000002', $this->draft($bom)->order_number);
    }

    public function test_one_warehouse_hides_the_warehouse_pickers(): void
    {
        $this->kano->update(['is_active' => false]);
        $bom = $this->bom();

        $this->get(route('assembly-orders.create', ['bill' => $bom->id]))->assertOk()
            ->assertDontSee('Take components from')->assertDontSee('Put finished goods in');
        $this->post(route('assembly-orders.store'), [
            'bill_of_materials_id' => $bom->id, 'assembly_date' => '2026-10-10', 'quantity' => 40, 'action' => 'draft',
        ])->assertSessionHasNoErrors();
        $order = AssemblyOrder::sole();
        $this->assertSame($this->main->id, (int) $order->warehouse_id);
        $this->assertSame($this->main->id, (int) $order->to_warehouse_id);
    }

    // ---- isolation, permissions, switch --------------------------------------------

    public function test_another_businesss_bills_and_orders_cannot_be_seen_or_used(): void
    {
        $other = $this->otherTenant();
        [$theirBom, $theirOrder, $theirItem, $theirWarehouse] = BillOfMaterial::withoutTenantGuard(function () use ($other) {
            $item = Item::factory()->product()->create(['tenant_id' => $other->id, 'name' => 'Their feed']);
            $part = Item::factory()->product()->create(['tenant_id' => $other->id, 'name' => 'Their maize']);
            $warehouse = Warehouse::create(['tenant_id' => $other->id, 'name' => 'Theirs', 'code' => 'THR']);
            $bom = BillOfMaterial::create(['tenant_id' => $other->id, 'item_id' => $item->id, 'name' => 'Their bill', 'output_quantity' => 1]);
            $bom->components()->create(['item_id' => $part->id, 'quantity' => 1]);
            $order = AssemblyOrder::create(['tenant_id' => $other->id, 'order_number' => 'ASM-000001', 'kind' => 'build', 'assembly_date' => '2026-10-10',
                'bill_of_materials_id' => $bom->id, 'warehouse_id' => $warehouse->id, 'to_warehouse_id' => $warehouse->id,
                'quantity' => 1, 'planned_quantity' => 1, 'status' => 'draft']);

            return [$bom, $order, $item, $warehouse];
        });

        $this->get(route('bill-of-materials.show', $theirBom))->assertNotFound();
        $this->put(route('bill-of-materials.update', $theirBom), [])->assertNotFound();
        $this->get(route('assembly-orders.show', $theirOrder))->assertNotFound();
        $this->post(route('assembly-orders.complete', $theirOrder))->assertNotFound();
        $this->post(route('assembly-orders.undo', $theirOrder))->assertNotFound();
        $this->delete(route('assembly-orders.destroy', $theirOrder))->assertNotFound();
        $this->getJson(route('assembly-orders.availability', ['bill_of_materials_id' => $theirBom->id]))->assertNotFound();
        $this->getJson('/api/v1/assembly-orders/'.$theirOrder->id)->assertNotFound();
        $this->getJson('/api/v1/assembly-orders')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/bill-of-materials')->assertOk()->assertJsonCount(0, 'data');
        Livewire::test(AssemblyOrdersTable::class)->assertDontSee('ASM-000001');
        $this->get(route('bill-of-materials.index'))->assertOk()->assertDontSee('Their bill');

        // Their items, bill and warehouse can't be used on our documents.
        $this->post(route('bill-of-materials.store'), [
            'item_id' => $this->feed->id, 'name' => 'Feed', 'output_quantity' => 1, 'components' => [['item_id' => $theirItem->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('components.0.item_id');
        $this->post(route('assembly-orders.store'), [
            'bill_of_materials_id' => $theirBom->id, 'assembly_date' => '2026-10-10', 'quantity' => 1,
        ])->assertSessionHasErrors('bill_of_materials_id');
        $ours = $this->bom();
        $this->post(route('assembly-orders.store'), [
            'bill_of_materials_id' => $ours->id, 'assembly_date' => '2026-10-10', 'quantity' => 1, 'warehouse_id' => $theirWarehouse->id,
        ])->assertSessionHasErrors('warehouse_id');
        $this->postJson('/api/v1/assembly-orders', [
            'bill_of_materials_id' => $theirBom->id, 'assembly_date' => '2026-10-10', 'quantity' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('bill_of_materials_id');
        $this->assertSame(AssemblyOrder::STATUS_DRAFT, AssemblyOrder::withoutGlobalScopes()->find($theirOrder->id)->status);
    }

    public function test_bills_need_item_permissions_and_orders_need_adjust_inventory(): void
    {
        $bom = $this->bom();
        $order = $this->draft($bom);
        $viewer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $viewer->givePermissionTo(['view inventory', 'view items']);
        $this->actingAs($viewer);

        $this->get(route('bill-of-materials.index'))->assertOk();
        $this->get(route('bill-of-materials.show', $bom))->assertOk()->assertDontSee('>Build</a>', false);
        $this->get(route('bill-of-materials.create'))->assertForbidden();
        $this->get(route('bill-of-materials.edit', $bom))->assertForbidden();
        $this->delete(route('bill-of-materials.destroy', $bom))->assertForbidden();
        $this->get(route('assembly-orders.index'))->assertForbidden();
        $this->get(route('assembly-orders.show', $order))->assertForbidden();
        $this->post(route('assembly-orders.complete', $order))->assertForbidden();
        $this->postJson('/api/v1/assembly-orders', [])->assertForbidden();
        $this->getJson('/api/v1/assembly-orders')->assertOk();
        $this->get(route('items.index'))->assertOk()->assertSee('">Bills of materials</a>', false)->assertDontSee('">Assembly orders</a>', false);
    }

    public function test_switched_off_the_module_is_hidden(): void
    {
        $bom = $this->bom();
        $this->get(route('items.index'))->assertSee('">Bills of materials</a>', false)->assertSee('">Assembly orders</a>', false);

        config(['mybooks.features.assembly' => false]);
        $this->get(route('bill-of-materials.index'))->assertNotFound();
        $this->get(route('bill-of-materials.show', $bom))->assertNotFound();
        $this->get(route('assembly-orders.index'))->assertNotFound();
        $this->getJson('/api/v1/assembly-orders')->assertNotFound();
        $this->get(route('items.index'))->assertDontSee('">Bills of materials</a>', false)->assertDontSee('">Assembly orders</a>', false);
    }

    public function test_the_api_can_list_read_and_create_with_complete(): void
    {
        $this->stockUp();
        $bom = $this->bom();

        $this->getJson('/api/v1/bill-of-materials')->assertOk()->assertJsonPath('data.0.output_quantity', 40)
            ->assertJsonPath('data.0.components.0.item_id', $this->maize->id);
        $this->postJson('/api/v1/assembly-orders', [
            'bill_of_materials_id' => $bom->id, 'assembly_date' => '2026-10-10', 'quantity' => 40, 'complete' => true, 'quantity_made' => 39,
        ])->assertCreated()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.quantity_made', 39)
            ->assertJsonPath('data.total_cost', 463000)->assertJsonPath('data.order_number', 'ASM-000001');
        $this->assertSame(39.0, $this->onHand($this->feed));

        $this->postJson('/api/v1/assembly-orders', [
            'bill_of_materials_id' => $bom->id, 'assembly_date' => '2026-10-10', 'quantity' => 40,
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.items.0.planned_quantity', 700);

        $this->postJson('/api/v1/assembly-orders', [
            'bill_of_materials_id' => $bom->id, 'assembly_date' => '2026-10-10', 'quantity' => 400, 'complete' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->getJson('/api/v1/assembly-orders?status=draft')->assertOk()->assertJsonCount(1, 'data');
        $id = AssemblyOrder::where('status', 'completed')->value('id');
        $this->getJson('/api/v1/assembly-orders/'.$id)->assertOk()->assertJsonPath('data.items.0.cost', 218000);
    }

    // ---- the migration ---------------------------------------------------------------

    public function test_migration_adds_the_production_costs_account_converts_old_orders_and_reruns(): void
    {
        $migration = require database_path('migrations/2026_10_14_140001_rework_assembly.php');
        ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '5500')->forceDelete();
        $other = $this->otherTenant();
        ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $other->id)->where('account_code', '5500')->update(['name' => 'Packaging']);

        // An order saved by the old code: quantity in batches, no date or lines.
        $bom = $this->bom();
        DB::table('assembly_orders')->insert([
            'tenant_id' => $this->tenant->id, 'order_number' => 'ASM-00007', 'bill_of_materials_id' => $bom->id, 'warehouse_id' => null,
            'quantity' => 1, 'status' => 'in_progress', 'total_cost' => 0, 'created_at' => '2026-05-04 09:00:00', 'updated_at' => '2026-05-04 09:00:00',
        ]);

        $migration->up();
        $migration->up();

        $this->assertSame('Production Costs Applied', ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '5500')->value('name'));
        $this->assertSame(1, ChartOfAccount::where('tenant_id', $this->tenant->id)->where('name', 'Production Costs Applied')->count());
        $this->assertSame('Packaging', ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $other->id)->where('account_code', '5500')->value('name'));
        $this->assertSame('5510', AccountCodeService::resolve($other->id, 'production_costs_applied'));

        $old = AssemblyOrder::where('order_number', 'ASM-00007')->sole();
        $this->assertSame(40.0, (float) $old->planned_quantity, '1 batch of 40');
        $this->assertSame('draft', $old->status);
        $this->assertSame('2026-05-04', $old->assembly_date->toDateString());

        // Two bills for one item (versions) are allowed now.
        $this->bom();
        $this->assertSame(2, BillOfMaterial::where('item_id', $this->feed->id)->count());

        // The old draft can still be completed: its lines come from the bill.
        $this->stockUp();
        $done = app(CompleteAssemblyOrder::class)->handle($old);
        $this->assertSame(40.0, (float) $done->quantity_made);
        $this->assertSame(700.0, (float) $done->items->first()->quantity);
    }

    // ---- regressions in the old assembly code ------------------------------------------

    /** Made straight in the table, the way the old screens would have (quantity = batches). */
    private function oldStyleOrder(BillOfMaterial $bom, float $batches = 1): AssemblyOrder
    {
        return AssemblyOrder::create([
            'tenant_id' => $this->tenant->id, 'order_number' => 'ASM-'.uniqid(), 'bill_of_materials_id' => $bom->id,
            'warehouse_id' => $this->main->id, 'quantity' => $batches, 'status' => 'draft',
        ]);
    }

    /** A bill made straight in the tables, as the old form did. */
    private function oldStyleBom(): BillOfMaterial
    {
        $bom = BillOfMaterial::create(['tenant_id' => $this->tenant->id, 'item_id' => $this->feed->id, 'name' => 'Layer feed', 'output_quantity' => 40]);
        $bom->components()->create(['item_id' => $this->maize->id, 'quantity' => 700]);
        $bom->components()->create(['item_id' => $this->soya->id, 'quantity' => 250]);

        return $bom;
    }

    public function test_regression_completing_costs_the_components_at_what_they_really_cost(): void
    {
        $this->stockUp();
        $order = $this->oldStyleOrder($this->oldStyleBom());

        // The old code never found the bill (wrong column) and, had it, used the cost price (0 here).
        $this->post(route('assembly-orders.complete', $order));

        $this->assertSame([[40.0, round((218000 + 150000) / 40, 4)]], $this->layers($this->feed));
    }

    public function test_regression_completing_takes_the_components_out_of_the_cost_layers(): void
    {
        $this->stockUp();
        $order = $this->oldStyleOrder($this->oldStyleBom());

        $this->post(route('assembly-orders.complete', $order));

        $this->assertSame([[300.0, 340.0]], $this->layers($this->maize), 'the old code left all 1,000 kg in the cost layers');
    }

    public function test_regression_completing_can_not_take_more_than_is_free(): void
    {
        $this->buy($this->maize, 300, 300);
        $this->buy($this->soya, 300, 600);
        $order = $this->oldStyleOrder($this->oldStyleBom());

        $this->post(route('assembly-orders.complete', $order));

        $this->assertSame(300.0, $this->onHand($this->maize), 'the old code would take 700 and leave -400');
        $this->assertSame(0.0, $this->onHand($this->feed));
        $this->assertSame('draft', $order->fresh()->status);

        // With enough maize it goes through (the old code never found its bill at all).
        $this->buy($this->maize, 400, 300);
        $this->post(route('assembly-orders.complete', $order))->assertSessionHasNoErrors();
        $this->assertSame(0.0, $this->onHand($this->maize));
        $this->assertSame(40.0, $this->onHand($this->feed));
    }

    public function test_regression_a_bill_cannot_use_an_item_that_keeps_no_stock(): void
    {
        $service = Item::factory()->service()->create(['tenant_id' => $this->tenant->id, 'name' => 'Mixing service']);

        $this->post(route('bill-of-materials.store'), [
            'item_id' => $this->feed->id, 'name' => 'Feed', 'output_quantity' => 40,
            'components' => [['item_id' => $service->id, 'quantity' => 1]],
        ]);

        $this->assertSame(0, BillOfMaterial::count(), 'the old code saved it');
    }

    public function test_regression_a_bill_cannot_loop_through_another_bill(): void
    {
        $mash = $this->item('Mash', 'fifo', 'kg');
        $bom = BillOfMaterial::create(['tenant_id' => $this->tenant->id, 'item_id' => $mash->id, 'name' => 'Mash', 'output_quantity' => 1]);
        $bom->components()->create(['item_id' => $this->feed->id, 'quantity' => 1]);

        $this->post(route('bill-of-materials.store'), [
            'item_id' => $this->feed->id, 'name' => 'Feed', 'output_quantity' => 40,
            'components' => [['item_id' => $mash->id, 'quantity' => 1]],
        ]);

        $this->assertSame(1, BillOfMaterial::count(), 'the old code only checked an item against itself');
    }

    public function test_regression_unticking_in_use_switches_a_bill_off(): void
    {
        $bom = $this->oldStyleBom();

        $this->put(route('bill-of-materials.update', $bom), [
            'item_id' => $this->feed->id, 'name' => 'Layer feed', 'output_quantity' => 40,
            'components' => [['item_id' => $this->maize->id, 'quantity' => 700]],
        ]);

        $this->assertFalse($bom->fresh()->is_active, 'the old code read a missing box as "on"');
    }

    public function test_regression_a_bill_used_on_an_order_cannot_be_deleted(): void
    {
        $bom = $this->oldStyleBom();
        $order = $this->oldStyleOrder($bom);

        // The old check looked at a column that doesn't exist, so this crashed.
        $this->delete(route('bill-of-materials.destroy', $bom))->assertRedirect()
            ->assertSessionHas('error', 'Layer feed has been used on assembly orders, so it can\'t be deleted. Untick "In use" to stop using it.');

        $this->assertNotNull(BillOfMaterial::find($bom->id));
        $this->assertNotNull(AssemblyOrder::find($order->id));
    }
}
