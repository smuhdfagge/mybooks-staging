<?php

namespace Tests\Feature\Features;

use App\Actions\SalesOrders\SaveSalesOrder;
use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\Inventory;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\SalesOrder;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Delivery notes made from sales orders: partial deliveries, the order's
 * status, cancelling, the printable note, and the stock rule (a delivery
 * note never moves stock; the invoice release does).
 */
class DeliveryNotesTest extends TestCase
{
    private const PERMISSIONS = ['view invoices', 'create invoices', 'edit invoices', 'delete invoices',
        'view sales-orders', 'create sales-orders', 'edit sales-orders', 'create payments-received'];

    private Customer $customer;

    private Item $rice;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mybooks.features.delivery_notes' => true]);
        $this->createAuthenticatedUser(self::PERMISSIONS);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->rice = Item::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Rice', 'type' => 'product', 'track_inventory' => true, 'cost_price' => 30000]);
        Inventory::create(['tenant_id' => $this->tenant->id, 'item_id' => $this->rice->id, 'quantity' => 20, 'reserved_quantity' => 0]);
    }

    /** A confirmed order: 10 bags of rice and 5 hours of labour. */
    private function order(): SalesOrder
    {
        return app(SaveSalesOrder::class)->create($this->tenant->id, [
            'customer_id' => $this->customer->id, 'order_date' => now()->toDateString(), 'status' => 'confirmed',
            'items' => [
                ['item_id' => $this->rice->id, 'description' => 'Rice', 'quantity' => 10, 'unit_price' => 45000, 'tax_rate' => 7.5],
                ['description' => 'Labour', 'quantity' => 5, 'unit_price' => 2000, 'tax_rate' => 0],
            ],
        ], $this->user->id);
    }

    private function deliver(SalesOrder $order, array $quantities): TestResponse
    {
        $lines = [];
        foreach ($order->items()->orderBy('id')->get() as $i => $line) {
            $lines[] = ['sales_order_item_id' => $line->id, 'quantity' => $quantities[$i] ?? 0];
        }

        return $this->post(route('delivery-notes.store'), [
            'sales_order_id' => $order->id, 'delivery_date' => now()->toDateString(), 'shipping_method' => 'Musa (driver)', 'lines' => $lines,
        ]);
    }

    private function fulfilled(SalesOrder $order): array
    {
        return $order->items()->orderBy('id')->pluck('quantity_fulfilled')->map(fn ($q) => (float) $q)->all();
    }

    public function test_partial_deliveries_add_up_and_move_the_order_on(): void
    {
        $order = $this->order();

        $this->deliver($order, [4, 0])->assertSessionHasNoErrors();
        $first = DeliveryNote::latest('id')->firstOrFail();
        $this->assertSame('draft', $first->status);
        $this->assertSame([0.0, 0.0], $this->fulfilled($order), 'a draft counts nothing yet');

        // The draft holds its 4: only 6 more can be put on another note
        $this->deliver($order, [7, 0])->assertSessionHasErrors('lines.0.quantity');

        $this->post(route('delivery-notes.dispatch', $first))->assertSessionHas('success');
        $this->assertSame([4.0, 0.0], $this->fulfilled($order));
        $this->assertSame('processing', $order->fresh()->status);

        $this->deliver($order, [6, 5])->assertSessionHasNoErrors();
        $second = DeliveryNote::latest('id')->firstOrFail();
        $this->post(route('delivery-notes.dispatch', $second));
        $this->assertSame([10.0, 5.0], $this->fulfilled($order));
        $this->assertSame('completed', $order->fresh()->status);

        // Nothing is left to deliver on a completed order
        $this->deliver($order, [1, 0])->assertSessionHasErrors('sales_order');

        $this->post(route('delivery-notes.confirm', $second), ['received_by' => 'Aisha'])->assertSessionHas('success');
        $this->assertSame('delivered', $second->fresh()->status);
        $this->assertSame('Aisha', $second->fresh()->received_by);
    }

    public function test_cancelling_a_dispatched_note_takes_its_quantities_off_the_order(): void
    {
        $order = $this->order();
        $this->deliver($order, [4, 2]);
        $note = DeliveryNote::firstOrFail();
        $this->post(route('delivery-notes.dispatch', $note));
        $this->assertSame('processing', $order->fresh()->status);

        $this->post(route('delivery-notes.cancel', $note))->assertSessionHas('success');

        $this->assertSame('cancelled', $note->fresh()->status);
        $this->assertSame([0.0, 0.0], $this->fulfilled($order));
        $this->assertSame('confirmed', $order->fresh()->status);

        // A dispatched note can't be deleted, a cancelled one can
        $this->delete(route('delivery-notes.destroy', $note))->assertRedirect(route('delivery-notes.index'));
        $this->assertSoftDeleted($note);
    }

    public function test_a_dispatched_note_cannot_be_deleted_or_confirmed_twice(): void
    {
        $order = $this->order();
        $this->deliver($order, [2, 0]);
        $note = DeliveryNote::firstOrFail();

        $this->post(route('delivery-notes.confirm', $note), ['received_by' => 'X'])->assertSessionHas('error');
        $this->post(route('delivery-notes.dispatch', $note));
        $this->post(route('delivery-notes.dispatch', $note))->assertSessionHas('error');
        $this->delete(route('delivery-notes.destroy', $note))->assertSessionHas('error');
        $this->assertNotSoftDeleted($note);
    }

    /** Stock rule: delivering takes nothing out; the invoice release takes it out once. */
    public function test_delivery_notes_never_move_stock(): void
    {
        $order = $this->order();
        $this->deliver($order, [10, 5]);
        $this->post(route('delivery-notes.dispatch', DeliveryNote::firstOrFail()));
        $this->post(route('delivery-notes.confirm', DeliveryNote::firstOrFail()), ['received_by' => 'Aisha']);

        $stock = Inventory::where('item_id', $this->rice->id)->firstOrFail();
        $this->assertSame(20.0, (float) $stock->quantity);
        $this->assertSame(0.0, (float) $stock->reserved_quantity);

        $this->post(route('sales-orders.convert', $order))->assertSessionHasNoErrors();
        $invoice = Invoice::firstOrFail();
        $this->assertSame(10.0, (float) $stock->fresh()->reserved_quantity);

        $invoice->update(['status' => 'unpaid']);
        $this->post(route('payments-received.store'), [
            'customer_id' => $this->customer->id, 'invoice_id' => $invoice->id, 'payment_date' => now()->toDateString(),
            'amount' => $invoice->fresh()->total, 'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();
        $this->post(route('invoices.release', $invoice))->assertSessionHas('success');

        $this->assertSame(10.0, (float) $stock->fresh()->quantity, 'taken out once, by the release');
        $this->assertSame(0.0, (float) $stock->fresh()->reserved_quantity);
    }

    /** Converting used to invoice "ordered minus delivered", so delivered goods were never billed. */
    public function test_converting_after_a_partial_delivery_invoices_the_whole_order(): void
    {
        $order = $this->order();
        $order->items()->where('item_id', $this->rice->id)->update(['quantity_fulfilled' => 4]);
        $order->update(['status' => 'processing']);

        $this->post(route('sales-orders.convert', $order))->assertSessionHasNoErrors();

        $invoice = Invoice::firstOrFail();
        $this->assertSame(10.0, (float) $invoice->items()->where('item_id', $this->rice->id)->value('quantity'));
        $this->assertSame((float) $order->total, (float) $invoice->total);

        // Everything is invoiced now, so a second conversion has nothing to do
        $this->post(route('sales-orders.convert', $order))->assertSessionHas('error');
        $this->assertSame(1, Invoice::count());
    }

    public function test_pages_open_and_the_note_prints(): void
    {
        $order = $this->order();
        $this->get(route('sales-orders.show', $order))->assertOk()->assertSee('Deliver (delivery note)');
        $this->get(route('delivery-notes.create'))->assertOk()->assertSee($order->order_number);
        $this->get(route('delivery-notes.create', ['sales_order_id' => $order->id]))->assertOk()->assertSee('Still to deliver');

        $this->deliver($order, [3, 0]);
        $note = DeliveryNote::firstOrFail();
        $this->get(route('delivery-notes.index'))->assertOk()->assertSee($note->delivery_number);
        $this->get(route('delivery-notes.show', $note))->assertOk()->assertSee('Dispatch');
        $this->get(route('delivery-notes.print', $note))->assertOk()->assertSee('DELIVERY NOTE')->assertSee('Received in good condition');
        $this->get(route('delivery-notes.pdf', $note))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('sales-orders.show', $order))->assertSee($note->delivery_number);
    }

    public function test_draft_orders_cannot_be_delivered(): void
    {
        $order = $this->order();
        $order->update(['status' => 'cancelled']);

        $this->deliver($order, [1, 0])->assertSessionHasErrors('sales_order');
        $this->assertSame(0, DeliveryNote::count());
    }

    public function test_another_business_cannot_see_or_deliver_an_order(): void
    {
        $order = $this->order();
        $this->deliver($order, [1, 0]);
        $note = DeliveryNote::firstOrFail();

        auth()->logout();
        $this->createAuthenticatedUser(self::PERMISSIONS);

        $this->get(route('delivery-notes.show', $note))->assertNotFound();
        $this->post(route('delivery-notes.dispatch', $note))->assertNotFound();
        $this->get(route('delivery-notes.create', ['sales_order_id' => $order->id]))->assertNotFound();
        $this->deliver($order, [1, 0])->assertSessionHasErrors('sales_order_id');
        $this->assertSame('draft', $note->fresh()->status);
    }

    public function test_permissions_are_checked(): void
    {
        $order = $this->order();
        $this->deliver($order, [1, 0]);
        $note = DeliveryNote::firstOrFail();
        $this->actingAs($this->createUserForTenant($this->tenant, ['view invoices']));

        $this->get(route('delivery-notes.show', $note))->assertOk()->assertDontSee('Dispatch (goods leave)');
        $this->post(route('delivery-notes.dispatch', $note))->assertForbidden();
        $this->deliver($order, [1, 0])->assertForbidden();
    }
}
