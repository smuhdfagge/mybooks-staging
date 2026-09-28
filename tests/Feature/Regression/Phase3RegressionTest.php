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

    // ── M2: journals must balance; amounts are rounded ──────────

    public function test_m2_an_unbalanced_journal_is_refused_and_nothing_is_posted(): void
    {
        $this->createAuthenticatedUser();
        $journalsBefore = \App\Models\Journal::count();

        // Total does not equal subtotal + tax, so the journal cannot balance.
        try {
            \Illuminate\Support\Facades\DB::transaction(fn () => SalesReceipt::create([
                'tenant_id' => $this->tenant->id, 'receipt_number' => 'SR-000009', 'receipt_date' => now(),
                'payment_method' => 'cash', 'subtotal' => 1000, 'tax_amount' => 75, 'discount_amount' => 0, 'total' => 1000,
                'created_by' => $this->user->id,
            ]));
            $this->fail('An unbalanced journal was posted.');
        } catch (\App\Exceptions\UnbalancedJournalException $e) {
            $this->assertStringContainsString('not balanced', $e->getMessage());
        }

        $this->assertSame($journalsBefore, \App\Models\Journal::count());
        $this->assertSame(0.0, $this->accountBalance($this->tenant->id, '1000'));
        $this->assertSame(0.0, $this->accountBalance($this->tenant->id, '4000'));
    }

    public function test_m2_journal_lines_are_rounded_to_two_decimals(): void
    {
        $this->createAuthenticatedUser();
        $journal = \App\Models\Journal::create([
            'tenant_id' => $this->tenant->id, 'journal_number' => 'JE-900001', 'journal_date' => now(),
            'description' => 'Rounding', 'status' => 'draft',
        ]);

        $line = app(\App\Services\JournalService::class)->createEntry($journal, '1000', 10.004999, 0, 'x');

        $this->assertSame('10.00', (string) $line->fresh()->debit);
    }

    public function test_m2_all_journals_created_by_normal_documents_balance(): void
    {
        $this->createAuthenticatedUser();
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        Invoice::factory()->sent()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-000010']);
        Bill::factory()->create(['tenant_id' => $this->tenant->id, 'vendor_id' => $vendor->id, 'status' => 'unpaid', 'bill_number' => 'BIL-000010']);

        $this->assertAllJournalsBalance($this->tenant->id);
    }

    public function test_m2_invoice_totals_are_built_from_rounded_parts_so_the_journal_balances(): void
    {
        // 3 x 1.235 at 7.5% VAT: subtotal 3.705 -> 3.71, VAT 0.277875 -> 0.28.
        // The unrounded total 3.982875 used to be stored as 3.98, one kobo
        // short of 3.71 + 0.28, so the journal could not balance.
        $this->createAuthenticatedUser(['create invoices']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => [['description' => 'Widget', 'quantity' => 3, 'unit_price' => 1.235, 'tax_rate' => 7.5]],
        ])->assertSessionHasNoErrors();

        $invoice = Invoice::latest('id')->first();
        $this->assertSame(['3.71', '0.28', '3.99', '3.99'], [
            (string) $invoice->subtotal, (string) $invoice->tax_amount, (string) $invoice->total, (string) $invoice->balance_due,
        ]);

        $invoice->update(['status' => 'sent']);

        $this->assertAllJournalsBalance($this->tenant->id);
        $this->assertSame(3.99, $this->accountBalance($this->tenant->id, '1200'));
        $this->assertStoredBalancesMatchLedger($this->tenant->id);
    }

    public function test_m2_a_real_inconsistency_is_not_papered_over(): void
    {
        $this->createAuthenticatedUser();
        $receipt = new SalesReceipt([
            'subtotal' => 1000, 'tax_amount' => 75, 'discount_amount' => 0, 'total' => 1000,
        ]);

        $receipt->reconcileTotals();

        $this->assertSame(1000.0, (float) $receipt->total, 'Only rounding-sized differences are corrected.');
    }

    // ── Bill journals (found while fixing M2) ──────────────────

    public function test_bill_journal_stays_balanced_after_payment(): void
    {
        // Bill lines store totals including tax. When the journal was rebuilt
        // from the lines (on any re-save, e.g. a payment), the tax was debited
        // twice: Dr 1,150 / Cr 1,075.
        $this->createAuthenticatedUser(['create bills']);
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->post(route('bills.store'), [
            'vendor_id' => $vendor->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => [
                ['description' => 'Paper', 'quantity' => 10, 'unit_price' => 100, 'tax_rate' => 7.5],
                ['description' => 'Ink', 'quantity' => 3, 'unit_price' => 1.235, 'tax_rate' => 7.5],
            ],
        ])->assertSessionHasNoErrors();
        $bill = Bill::latest('id')->first();

        PaymentMade::create([
            'tenant_id' => $this->tenant->id, 'vendor_id' => $vendor->id, 'bill_id' => $bill->id,
            'payment_number' => 'PM-000009', 'payment_date' => now(), 'amount' => 100, 'payment_method' => 'cash',
            'created_by' => $this->user->id,
        ]);

        $this->assertAllJournalsBalance($this->tenant->id);
        $this->assertSame((float) $bill->fresh()->total, $this->accountBalance($this->tenant->id, '2000') + 100);
        $this->assertStoredBalancesMatchLedger($this->tenant->id);
    }
}
