<?php

namespace Tests\Feature\Features;

use App\Actions\Bills\SaveBill;
use App\Models\Bill;
use App\Models\Item;
use App\Models\PaymentMade;
use App\Models\Vendor;
use App\Models\VendorCredit;
use Tests\TestCase;

/**
 * Feature 1 screens: supplier credits and supplier advances.
 */
class VendorCreditScreensTest extends TestCase
{
    protected Vendor $vendor;

    protected Item $item;

    protected Bill $bill;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser([
            'view bills', 'create bills', 'edit bills', 'delete bills', 'view vendors',
            'view payments-made', 'create payments-made', 'edit payments-made',
        ]);
        $this->vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Kano Feeds']);
        $this->item = Item::factory()->product()->create(['tenant_id' => $this->tenant->id, 'name' => 'Layer mash']);
        $this->bill = app(SaveBill::class)->create($this->tenant->id, [
            'vendor_id' => $this->vendor->id, 'bill_date' => now()->subDays(5)->toDateString(), 'due_date' => now()->addMonth()->toDateString(),
            'items' => [['item_id' => $this->item->id, 'description' => 'Layer mash 25kg', 'quantity' => 20, 'unit_price' => 9000, 'tax_rate' => 7.5]],
        ], $this->user->id);
    }

    public function test_the_whole_supplier_credit_flow_works_from_the_screens(): void
    {
        $this->get(route('vendor-credits.create', ['bill_id' => $this->bill->id]))
            ->assertOk()->assertSee('Returning goods from bill')->assertSee('Layer mash 25kg');

        $response = $this->post(route('vendor-credits.store'), [
            'vendor_id' => $this->vendor->id, 'bill_id' => $this->bill->id, 'credit_date' => now()->toDateString(),
            'reason' => 'damaged_goods', 'status' => 'open', 'vendor_reference' => 'KF-CN-12',
            'items' => [['item_id' => $this->item->id, 'description' => 'Layer mash 25kg', 'quantity' => 2, 'unit_price' => 9000, 'tax_rate' => 7.5]],
        ]);
        $credit = VendorCredit::firstOrFail();
        $response->assertRedirect(route('vendor-credits.show', $credit))->assertSessionHasNoErrors();
        $this->assertEquals(19350, (float) $credit->total);

        $this->get(route('vendor-credits.index'))->assertOk()->assertSee($credit->vendor_credit_number)->assertSee('Kano Feeds');
        $this->get(route('vendor-credits.show', $credit))->assertOk()
            ->assertSee('Use against a bill')->assertSee('Refund from the supplier')->assertSee('KF-CN-12');

        $this->post(route('vendor-credits.apply', $credit), ['bill_id' => $this->bill->id, 'amount' => 10000])
            ->assertSessionHasNoErrors();
        $this->post(route('vendor-credits.refund', $credit), [
            'refund_date' => now()->toDateString(), 'amount' => 9350, 'payment_method' => 'bank_transfer',
        ])->assertSessionHasNoErrors();

        $credit->refresh();
        $this->assertSame('closed', $credit->status);
        $this->assertEquals(193500 - 10000, (float) $this->bill->fresh()->balance_due);

        $this->get(route('bills.show', $this->bill))->assertOk()->assertSee($credit->vendor_credit_number);
        $this->get(route('vendor-credits.show', $credit))->assertOk()->assertSee('Where the credit went');
    }

    public function test_a_draft_can_be_posted_voided_and_deleted_from_the_screens(): void
    {
        $this->post(route('vendor-credits.store'), [
            'vendor_id' => $this->vendor->id, 'credit_date' => now()->toDateString(), 'status' => 'draft',
            'items' => [['description' => 'Price correction', 'quantity' => 1, 'unit_price' => 5000, 'tax_rate' => 0]],
        ])->assertSessionHasNoErrors();
        $credit = VendorCredit::firstOrFail();
        $this->assertSame('draft', $credit->status);

        $this->post(route('vendor-credits.open', $credit))->assertSessionHasNoErrors();
        $this->assertSame('open', $credit->fresh()->status);

        $this->post(route('vendor-credits.void', $credit))->assertSessionHasNoErrors();
        $this->assertSame('void', $credit->fresh()->status);

        $this->delete(route('vendor-credits.destroy', $credit))->assertRedirect(route('vendor-credits.index'));
        $this->assertSoftDeleted($credit);
    }

    public function test_returning_more_than_the_bill_had_shows_a_plain_error(): void
    {
        $this->post(route('vendor-credits.store'), [
            'vendor_id' => $this->vendor->id, 'bill_id' => $this->bill->id, 'credit_date' => now()->toDateString(),
            'items' => [['item_id' => $this->item->id, 'description' => 'Too many', 'quantity' => 21, 'unit_price' => 9000, 'tax_rate' => 7.5]],
        ])->assertSessionHasErrors(['items' => "Bill {$this->bill->bill_number} has only 20 of Layer mash left to return."]);
        $this->assertSame(0, VendorCredit::count());
    }

    public function test_supplier_advance_screens(): void
    {
        $this->get(route('supplier-advances.create', ['vendor_id' => $this->vendor->id]))->assertOk()->assertSee('Pay a Supplier in Advance');

        $this->post(route('supplier-advances.store'), [
            'vendor_id' => $this->vendor->id, 'payment_date' => now()->toDateString(), 'amount' => 50000, 'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();
        $advance = PaymentMade::where('is_advance', true)->firstOrFail();

        $this->get(route('supplier-advances.index'))->assertOk()->assertSee($advance->payment_number);
        $this->get(route('supplier-advances.show', $advance))->assertOk()->assertSee('Use against a bill')->assertSee($this->bill->bill_number);
        $this->get(route('vendors.show', $this->vendor))->assertOk()->assertSee('Credits and advances')->assertSee('Advance '.$advance->payment_number);

        $this->post(route('supplier-advances.apply', $advance), [
            'bill_id' => $this->bill->id, 'amount' => 50000, 'application_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertEquals(0, (float) $advance->fresh()->unused_amount);
        $this->assertEquals(143500, (float) $this->bill->fresh()->balance_due);

        // An ordinary payment can't be opened as an advance.
        $ordinary = PaymentMade::where('payment_method', 'advance')->firstOrFail();
        $this->get(route('supplier-advances.show', $ordinary))->assertNotFound();
    }

    public function test_permissions_and_other_businesses(): void
    {
        $this->post(route('vendor-credits.store'), [
            'vendor_id' => $this->vendor->id, 'credit_date' => now()->toDateString(),
            'items' => [['description' => 'Discount', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0]],
        ]);
        $credit = VendorCredit::firstOrFail();

        $viewer = $this->createUserForTenant($this->tenant, ['view bills']);
        $this->actingAs($viewer);
        $this->get(route('vendor-credits.show', $credit))->assertOk();
        $this->get(route('vendor-credits.create'))->assertForbidden();
        $this->post(route('vendor-credits.void', $credit))->assertForbidden();

        auth()->logout();
        [$other] = $this->createTenantWithSubscription();
        $outsider = $this->createUserForTenant($other, ['view bills', 'edit bills', 'create bills']);
        $this->actingAs($outsider);
        $this->get(route('vendor-credits.show', $credit))->assertNotFound();
        $this->post(route('vendor-credits.void', $credit))->assertNotFound();
        $this->get(route('vendor-credits.index'))->assertOk()->assertDontSee($credit->vendor_credit_number);
        // Their own form can't use our supplier.
        $this->post(route('vendor-credits.store'), [
            'vendor_id' => $this->vendor->id, 'credit_date' => now()->toDateString(),
            'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 1, 'tax_rate' => 0]],
        ])->assertSessionHasErrors('vendor_id');
    }
}
