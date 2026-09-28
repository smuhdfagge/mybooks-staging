<?php

namespace Tests\Feature\Regression;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentMade;
use App\Models\PaymentReceived;
use App\Models\SalesReceipt;
use App\Models\Vendor;
use Tests\Support\AssertsLedger;
use Tests\TestCase;

/**
 * Regression tests for the Phase 3 (ledger integrity) fixes.
 */
class Phase3RegressionTest extends TestCase
{
    use AssertsLedger;

    // ── C4: balances follow the journal when documents are edited ─

    public function test_c4_editing_a_posted_invoice_keeps_balances_in_step(): void
    {
        $this->createAuthenticatedUser();
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->sent()->create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-000001',
        ]);
        $this->assertStoredBalancesMatchLedger($this->tenant->id);

        Invoice::find($invoice->id)->update(['subtotal' => 2000, 'tax_amount' => 150, 'total' => 2150, 'balance_due' => 2150]);

        $this->assertSame(2150.0, $this->accountBalance($this->tenant->id, '1200'));
        $this->assertStoredBalancesMatchLedger($this->tenant->id);

        // And back down again
        Invoice::find($invoice->id)->update(['subtotal' => 100, 'tax_amount' => 7.5, 'total' => 107.5, 'balance_due' => 107.5]);
        $this->assertSame(107.5, $this->accountBalance($this->tenant->id, '1200'));
        $this->assertStoredBalancesMatchLedger($this->tenant->id);
    }

    public function test_c4_editing_a_posted_bill_keeps_balances_in_step(): void
    {
        $this->createAuthenticatedUser();
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $bill = Bill::factory()->create([
            'tenant_id' => $this->tenant->id, 'vendor_id' => $vendor->id, 'status' => 'unpaid', 'bill_number' => 'BIL-000001',
        ]);
        $this->assertStoredBalancesMatchLedger($this->tenant->id);

        Bill::find($bill->id)->update(['subtotal' => 1000, 'tax_amount' => 75, 'total' => 1075, 'balance_due' => 1075]);

        $this->assertSame(1075.0, $this->accountBalance($this->tenant->id, '2000'));
        $this->assertStoredBalancesMatchLedger($this->tenant->id);
    }

    public function test_c4_editing_payments_keeps_balances_in_step(): void
    {
        $this->createAuthenticatedUser();
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->sent()->create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-000002',
        ]);
        $bill = Bill::factory()->create([
            'tenant_id' => $this->tenant->id, 'vendor_id' => $vendor->id, 'status' => 'unpaid', 'bill_number' => 'BIL-000002',
        ]);

        $received = PaymentReceived::create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_id' => $invoice->id,
            'payment_number' => 'PR-000001', 'payment_date' => now(), 'amount' => 100, 'payment_method' => 'cash',
            'is_deposit' => false, 'unused_amount' => 0, 'created_by' => $this->user->id,
        ]);
        $made = PaymentMade::create([
            'tenant_id' => $this->tenant->id, 'vendor_id' => $vendor->id, 'bill_id' => $bill->id,
            'payment_number' => 'PM-000001', 'payment_date' => now(), 'amount' => 50, 'payment_method' => 'cash',
            'created_by' => $this->user->id,
        ]);
        $this->assertStoredBalancesMatchLedger($this->tenant->id);

        PaymentReceived::find($received->id)->update(['amount' => 300]);
        PaymentMade::find($made->id)->update(['amount' => 200]);

        $this->assertStoredBalancesMatchLedger($this->tenant->id);
    }

    public function test_c4_editing_a_sales_receipt_keeps_balances_in_step(): void
    {
        $this->createAuthenticatedUser();
        $receipt = SalesReceipt::create([
            'tenant_id' => $this->tenant->id, 'receipt_number' => 'SR-000001', 'receipt_date' => now(),
            'payment_method' => 'cash', 'subtotal' => 1000, 'tax_amount' => 75, 'discount_amount' => 0, 'total' => 1075,
            'created_by' => $this->user->id,
        ]);
        $this->assertStoredBalancesMatchLedger($this->tenant->id);

        SalesReceipt::find($receipt->id)->update(['subtotal' => 400, 'tax_amount' => 30, 'total' => 430]);

        $this->assertStoredBalancesMatchLedger($this->tenant->id);
    }

    public function test_c4_recalculation_keeps_opening_balances(): void
    {
        $this->createAuthenticatedUser();
        $cash = \App\Models\ChartOfAccount::where('account_code', '1000')->first();
        $cash->update(['opening_balance' => 5000, 'current_balance' => 5000]);

        $this->artisan('accounts:recalculate', ['--tenant' => $this->tenant->id])->assertSuccessful();

        $this->assertSame(5000.0, $this->accountBalance($this->tenant->id, '1000'));
    }
}
