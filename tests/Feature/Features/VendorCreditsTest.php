<?php

namespace Tests\Feature\Features;

use App\Actions\Bills\SaveBill;
use App\Actions\Payments\ApplySupplierAdvance;
use App\Actions\Payments\DeletePaymentMade;
use App\Actions\Payments\RecordPaymentMade;
use App\Actions\VendorCredits\ApplyVendorCredit;
use App\Actions\VendorCredits\OpenVendorCredit;
use App\Actions\VendorCredits\RefundVendorCredit;
use App\Actions\VendorCredits\SaveVendorCredit;
use App\Actions\VendorCredits\VoidVendorCredit;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Inventory;
use App\Models\InventoryLayer;
use App\Models\Item;
use App\Models\Journal;
use App\Models\PaymentMade;
use App\Models\Vendor;
use App\Models\VendorCredit;
use App\Services\Accounting\FinancialStatements;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Feature 1: purchase returns, supplier credits and supplier advances.
 */
class VendorCreditsTest extends TestCase
{
    protected Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser([
            'view bills', 'create bills', 'edit bills', 'delete bills',
            'view vendors', 'view payments-made', 'create payments-made', 'edit payments-made', 'delete payments-made',
        ]);
        $this->vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Dangote Supplies']);
    }

    private function stockItem(string $method = 'weighted_average'): Item
    {
        return Item::factory()->product()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cement bag', 'cost_price' => 0, 'valuation_method' => $method,
        ]);
    }

    private function bill(Item $item, float $qty, float $price, float $vat = 7.5, string $date = '2026-09-01'): Bill
    {
        return app(SaveBill::class)->create($this->tenant->id, [
            'vendor_id' => $this->vendor->id, 'bill_date' => $date, 'due_date' => '2026-10-31',
            'items' => [['item_id' => $item->id, 'description' => 'Cement', 'quantity' => $qty, 'unit_price' => $price, 'tax_rate' => $vat]],
        ], $this->user->id);
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function credit(array $lines, ?Bill $bill = null, string $status = 'open'): VendorCredit
    {
        return app(SaveVendorCredit::class)->create($this->tenant->id, [
            'vendor_id' => $this->vendor->id, 'bill_id' => $bill?->id, 'credit_date' => '2026-09-10',
            'status' => $status, 'reason' => 'goods_returned', 'items' => $lines,
        ], $this->user->id);
    }

    private function balance(string $code): float
    {
        return round((float) ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', $code)->value('current_balance'), 2);
    }

    private function ledger(string $code): float
    {
        return (float) app(FinancialStatements::class)->accountBalances($this->tenant->id, null, '2026-12-31')
            ->firstWhere('account_code', $code)?->balance;
    }

    private function assertBooksBalance(): void
    {
        $tb = app(FinancialStatements::class)->trialBalance($this->tenant->id, '2026-12-31');
        $this->assertEqualsWithDelta($tb->sum('total_debit'), $tb->sum('total_credit'), 0.001);
    }

    public function test_returning_goods_against_a_bill_takes_them_out_of_stock_at_the_bill_cost_and_posts_the_credit(): void
    {
        $item = $this->stockItem();
        $bill = $this->bill($item, 10, 1000); // 10,000 + 750 VAT
        $this->assertSame(10750.0, $this->balance('2000'));

        $credit = $this->credit([['item_id' => $item->id, 'description' => 'Cement returned', 'quantity' => 4, 'unit_price' => 1000, 'tax_rate' => 7.5]], $bill);

        $this->assertSame('open', $credit->status);
        $this->assertStringStartsWith('VCN-', $credit->vendor_credit_number);
        $this->assertEquals(4300, (float) $credit->total);
        $this->assertEquals(4300, (float) $credit->balance);

        // Stock: 6 left, out of the bill's own layer.
        $this->assertEquals(6, (float) Inventory::where('item_id', $item->id)->value('quantity'));
        $this->assertEquals(6, (float) InventoryLayer::where('reference_type', 'bill')->where('reference_id', $bill->id)->value('remaining_quantity'));
        $this->assertEquals(1000, (float) $credit->items->first()->unit_cost);

        // Dr payables 4,300; Cr inventory 4,000; Cr input VAT 300.
        $journal = Journal::where('reference_type', VendorCredit::class)->where('reference_id', $credit->id)->with('entries.account')->first();
        $lines = $journal->entries->mapWithKeys(fn ($e) => [$e->account->account_code => [(float) $e->debit, (float) $e->credit]]);
        $this->assertEquals([4300, 0], $lines['2000']);
        $this->assertEquals([0, 4000], $lines['1300']);
        $this->assertEquals([0, 300], $lines['1410']);

        $this->assertSame(6450.0, $this->balance('2000'));
        $this->assertSame(6000.0, $this->balance('1300'));
        $this->assertSame(450.0, $this->balance('1410'));
        $this->assertSame(6450.0, $this->ledger('2000'));
        $this->assertBooksBalance();
    }

    public function test_a_credit_applied_to_a_bill_lowers_what_the_bill_owes_and_closes_when_used_up(): void
    {
        $item = $this->stockItem();
        $bill = $this->bill($item, 10, 1000);
        $credit = $this->credit([['item_id' => $item->id, 'description' => 'Returned', 'quantity' => 4, 'unit_price' => 1000, 'tax_rate' => 7.5]], $bill);

        $application = app(ApplyVendorCredit::class)->handle($credit, $bill, 4300);

        $bill->refresh();
        $credit->refresh();
        $this->assertEquals(6450, (float) $bill->balance_due);
        $this->assertSame('partial', $bill->status);
        $this->assertSame('closed', $credit->status);
        $this->assertEquals(0, (float) $credit->balance);
        $this->assertSame($bill->id, $application->bill_id);

        // Using a credit is inside payables: nothing more is posted.
        $this->assertSame(6450.0, $this->balance('2000'));

        // Paying the rest settles the bill.
        app(RecordPaymentMade::class)->handle($this->tenant->id, [
            'vendor_id' => $this->vendor->id, 'bill_id' => $bill->id, 'payment_date' => '2026-09-15',
            'amount' => 6450, 'payment_method' => 'bank_transfer',
        ], $this->user->id);
        $this->assertSame('paid', $bill->fresh()->status);
        $this->assertSame(0.0, $this->balance('2000'));
        $this->assertBooksBalance();
    }

    public function test_a_credit_cannot_be_used_for_more_than_is_left_or_than_the_bill_owes(): void
    {
        $item = $this->stockItem();
        $bill = $this->bill($item, 10, 1000);
        $credit = $this->credit([['item_id' => $item->id, 'description' => 'Returned', 'quantity' => 4, 'unit_price' => 1000, 'tax_rate' => 7.5]], $bill);

        $this->expectException(ValidationException::class);
        app(ApplyVendorCredit::class)->handle($credit, $bill, 4300.01);
    }

    public function test_a_supplier_refund_puts_money_in_the_bank_and_back_into_payables(): void
    {
        $item = $this->stockItem();
        $bill = $this->bill($item, 10, 1000);
        $credit = $this->credit([['item_id' => $item->id, 'description' => 'Returned', 'quantity' => 4, 'unit_price' => 1000, 'tax_rate' => 7.5]], $bill);

        app(RefundVendorCredit::class)->handle($credit, [
            'refund_date' => '2026-09-20', 'amount' => 3000, 'payment_method' => 'bank_transfer',
        ], $this->user->id);

        $credit->refresh();
        $this->assertSame('open', $credit->status);
        $this->assertEquals(1300, (float) $credit->balance);
        $this->assertSame(3000.0, $this->balance('1100'));
        $this->assertSame(9450.0, $this->balance('2000')); // 10,750 - 4,300 + 3,000
        $this->assertBooksBalance();

        $this->expectException(ValidationException::class);
        app(RefundVendorCredit::class)->handle($credit, ['refund_date' => '2026-09-21', 'amount' => 1300.5], $this->user->id);
    }

    public function test_a_credit_below_the_goods_cost_puts_the_difference_to_cost_of_sales(): void
    {
        $item = $this->stockItem();
        $bill = $this->bill($item, 10, 1000, 0);
        // Supplier keeps a restocking fee: credits 900 a bag.
        $this->credit([['item_id' => $item->id, 'description' => 'Returned less fee', 'quantity' => 4, 'unit_price' => 900, 'tax_rate' => 0]], $bill);

        $this->assertSame(6000.0, $this->balance('1300'));   // cost of 4 bags out
        $this->assertSame(400.0, $this->balance('5000'));    // 4 x 100 lost
        $this->assertSame(6400.0, $this->balance('2000'));   // 10,000 - 3,600
        $this->assertBooksBalance();
    }

    public function test_a_price_correction_without_goods_credits_the_line_account(): void
    {
        $item = $this->stockItem();
        $this->bill($item, 10, 1000);
        $rent = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '6100')->first();

        $this->credit([
            ['account_id' => $rent->id, 'description' => 'Overcharged rent', 'quantity' => 1, 'unit_price' => 5000, 'tax_rate' => 0],
            ['description' => 'Late delivery discount', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 7.5],
        ]);

        $this->assertSame(-5000.0, $this->balance('6100'));
        $this->assertSame(-1000.0, $this->balance('6990'));
        $this->assertEquals(10, (float) Inventory::where('item_id', $item->id)->value('quantity'));
        $this->assertSame(4675.0, $this->balance('2000')); // 10,750 - 6,075
        $this->assertBooksBalance();
    }

    public function test_fifo_returns_without_a_bill_use_the_oldest_cost(): void
    {
        $item = $this->stockItem('fifo');
        $this->bill($item, 5, 1000, 0, '2026-08-01');
        $this->bill($item, 5, 1200, 0, '2026-08-15');

        $credit = $this->credit([['item_id' => $item->id, 'description' => 'Returned', 'quantity' => 6, 'unit_price' => 1100, 'tax_rate' => 0]]);

        // 5 at 1,000 + 1 at 1,200 = 6,200 out of stock; credit 6,600; 400 to cost of sales.
        $this->assertEqualsWithDelta(6200 / 6, (float) $credit->items->first()->unit_cost, 0.001);
        $this->assertSame(4800.0, $this->balance('1300'));
        $this->assertSame(-400.0, $this->balance('5000'));
        $this->assertEquals(4, (float) Inventory::where('item_id', $item->id)->value('quantity'));
        $this->assertBooksBalance();
    }

    public function test_goods_can_only_go_back_if_the_bill_had_them_and_they_are_in_stock(): void
    {
        $item = $this->stockItem();
        $bill = $this->bill($item, 10, 1000);
        $this->credit([['item_id' => $item->id, 'description' => 'First return', 'quantity' => 7, 'unit_price' => 1000, 'tax_rate' => 7.5]], $bill);

        try {
            $this->credit([['item_id' => $item->id, 'description' => 'Too many', 'quantity' => 4, 'unit_price' => 1000, 'tax_rate' => 7.5]], $bill);
            $this->fail('Returned more than the bill had.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('only 3', $e->getMessage());
        }

        try {
            $this->credit([['item_id' => $item->id, 'description' => 'Not in stock', 'quantity' => 5, 'unit_price' => 1000, 'tax_rate' => 7.5]]);
            $this->fail('Returned more than is in stock.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Only 3', $e->getMessage());
        }

        // Nothing half-done: the failed credits left no rows behind.
        $this->assertSame(1, VendorCredit::count());
    }

    public function test_voiding_a_credit_reverses_its_journal_and_puts_the_goods_back(): void
    {
        $item = $this->stockItem();
        $bill = $this->bill($item, 10, 1000);
        $credit = $this->credit([['item_id' => $item->id, 'description' => 'Returned', 'quantity' => 4, 'unit_price' => 1000, 'tax_rate' => 7.5]], $bill);

        app(VoidVendorCredit::class)->handle($credit);

        $this->assertSame('void', $credit->fresh()->status);
        $this->assertEquals(10, (float) Inventory::where('item_id', $item->id)->value('quantity'));
        $this->assertEquals(10, (float) InventoryLayer::where('reference_id', $bill->id)->value('remaining_quantity'));
        $this->assertEquals(1000, (float) Inventory::where('item_id', $item->id)->value('unit_cost'));
        $this->assertSame(10750.0, $this->balance('2000'));
        $this->assertSame(10000.0, $this->balance('1300'));
        // The original journal is kept, with its reversal.
        $this->assertSame(2, Journal::where('reference_type', VendorCredit::class)->where('reference_id', $credit->id)->count());
        $this->assertBooksBalance();
    }

    public function test_a_used_credit_cannot_be_voided_and_a_draft_posts_nothing(): void
    {
        $item = $this->stockItem();
        $bill = $this->bill($item, 10, 1000);
        $draft = $this->credit([['item_id' => $item->id, 'description' => 'Returned', 'quantity' => 2, 'unit_price' => 1000, 'tax_rate' => 7.5]], $bill, 'draft');

        $this->assertSame('draft', $draft->status);
        $this->assertSame(0, Journal::where('reference_type', VendorCredit::class)->count());
        $this->assertEquals(10, (float) Inventory::where('item_id', $item->id)->value('quantity'));

        app(OpenVendorCredit::class)->handle($draft);
        app(ApplyVendorCredit::class)->handle($draft->fresh(), $bill, 1000);

        $this->expectException(ValidationException::class);
        app(VoidVendorCredit::class)->handle($draft->fresh());
    }

    public function test_a_supplier_advance_is_an_asset_until_used_against_a_bill(): void
    {
        $advance = app(RecordPaymentMade::class)->handle($this->tenant->id, [
            'vendor_id' => $this->vendor->id, 'payment_date' => '2026-08-20', 'amount' => 50000,
            'payment_method' => 'bank_transfer', 'is_advance' => true, 'bill_id' => null,
        ], $this->user->id);

        $this->assertTrue($advance->is_advance);
        $this->assertEquals(50000, (float) $advance->unused_amount);
        $this->assertSame(50000.0, $this->balance('1420'));
        $this->assertSame(-50000.0, $this->balance('1100'));
        $this->assertSame(0.0, $this->balance('2000'));
        $this->assertSame(50000.0, $this->vendor->advanceBalance());

        $item = $this->stockItem();
        $bill = $this->bill($item, 30, 1000, 0); // 30,000

        $application = app(ApplySupplierAdvance::class)->handle($advance, $bill, 30000, '2026-09-02');

        $this->assertSame('paid', $bill->fresh()->status);
        $this->assertEquals(20000, (float) $advance->fresh()->unused_amount);
        $this->assertSame(20000.0, $this->balance('1420'));
        $this->assertSame(0.0, $this->balance('2000'));
        $this->assertSame(-50000.0, $this->balance('1100')); // no more money moved
        $this->assertSame(PaymentMade::METHOD_ADVANCE, $application->appliedPayment->payment_method);
        $this->assertBooksBalance();

        // A used advance can't be deleted...
        try {
            app(DeletePaymentMade::class)->handle($advance->fresh());
            $this->fail('Deleted a used advance.');
        } catch (ValidationException) {
        }

        // ...but undoing the use gives the amount back.
        app(DeletePaymentMade::class)->handle($application->appliedPayment);
        $this->assertEquals(50000, (float) $advance->fresh()->unused_amount);
        $this->assertSame(30000.0, (float) $bill->fresh()->balance_due);
        $this->assertSame(50000.0, $this->balance('1420'));
        $this->assertSame(30000.0, $this->balance('2000'));
        $this->assertBooksBalance();
    }

    public function test_an_advance_cannot_pay_more_than_is_left_or_another_suppliers_bill(): void
    {
        $advance = app(RecordPaymentMade::class)->handle($this->tenant->id, [
            'vendor_id' => $this->vendor->id, 'payment_date' => '2026-08-20', 'amount' => 1000,
            'payment_method' => 'cash', 'is_advance' => true,
        ], $this->user->id);
        $item = $this->stockItem();
        $bill = $this->bill($item, 5, 1000, 0);

        try {
            app(ApplySupplierAdvance::class)->handle($advance, $bill, 1500);
            $this->fail('Used more than the advance.');
        } catch (ValidationException) {
        }

        $other = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherBill = app(SaveBill::class)->create($this->tenant->id, [
            'vendor_id' => $other->id, 'bill_date' => '2026-09-01', 'due_date' => '2026-10-01',
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => 0]],
        ]);
        $this->expectException(ValidationException::class);
        app(ApplySupplierAdvance::class)->handle($advance, $otherBill, 500);
    }

    public function test_another_business_cannot_see_or_use_a_credit(): void
    {
        $item = $this->stockItem();
        $bill = $this->bill($item, 10, 1000);
        $credit = $this->credit([['item_id' => $item->id, 'description' => 'Returned', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0]], $bill);

        auth()->logout();
        [$otherTenant] = $this->createTenantWithSubscription();
        $outsider = $this->createUserForTenant($otherTenant, ['view bills', 'edit bills']);
        $this->actingAs($outsider);

        $this->assertNull(VendorCredit::find($credit->id));
        $this->assertSame(0, VendorCredit::count());
    }
}
