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

    // ── C5: bulk actions go through the accounting ─────────────

    private function bulk(string $component, array $ids, string $action)
    {
        return \Livewire\Livewire::test($component)
            ->set('selectedItems', array_map('strval', $ids))
            ->set('bulkAction', $action)
            ->call('applyBulkAction');
    }

    public function test_c5_bulk_mark_sent_posts_the_invoice_journal(): void
    {
        $this->createAuthenticatedUser(['send invoices']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-000020']);
        $this->assertSame(0.0, $this->accountBalance($this->tenant->id, '1200'));

        $this->bulk(\App\Livewire\Invoices\InvoicesTable::class, [$invoice->id], 'mark_sent')->assertOk();

        $this->assertSame('sent', $invoice->fresh()->status);
        $this->assertSame(1075.0, $this->accountBalance($this->tenant->id, '1200'));
        $this->assertStoredBalancesMatchLedger($this->tenant->id);
    }

    public function test_c5_bulk_mark_paid_is_no_longer_offered(): void
    {
        $this->createSuperAdmin();
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->sent()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-000021']);

        $this->bulk(\App\Livewire\Invoices\InvoicesTable::class, [$invoice->id], 'mark_paid')->assertForbidden();
        $this->assertSame('sent', $invoice->fresh()->status);

        \Livewire\Livewire::test(\App\Livewire\Invoices\InvoicesTable::class)->assertDontSeeHtml('value="mark_paid"');
    }

    public function test_c5_cancelling_a_sent_invoice_reverses_its_journal(): void
    {
        $this->createAuthenticatedUser(['edit invoices']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->sent()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-000022']);
        $this->assertSame(1075.0, $this->accountBalance($this->tenant->id, '1200'));

        $this->bulk(\App\Livewire\Invoices\InvoicesTable::class, [$invoice->id], 'mark_cancelled')->assertOk();

        $this->assertSame('cancelled', $invoice->fresh()->status);
        $this->assertSame(0.0, $this->accountBalance($this->tenant->id, '1200'));
        $this->assertSame(0.0, $this->accountBalance($this->tenant->id, '4000'));
        $journals = \App\Models\Journal::where('reference_type', Invoice::class)->where('reference_id', $invoice->id)->get();
        $this->assertCount(2, $journals, 'Original journal and its reversal are both kept');
        $this->assertStoredBalancesMatchLedger($this->tenant->id);
        $this->assertAllJournalsBalance($this->tenant->id);

        // Saving the cancelled invoice again doesn't reverse twice
        $invoice->fresh()->update(['notes' => 'cancelled by customer']);
        $this->assertSame(2, \App\Models\Journal::where('reference_type', Invoice::class)->where('reference_id', $invoice->id)->count());
    }

    public function test_c5_cancelling_an_unpaid_bill_reverses_its_journal(): void
    {
        $this->createAuthenticatedUser(['edit bills']);
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id, 'vendor_id' => $vendor->id, 'status' => 'unpaid', 'bill_number' => 'BIL-000022']);
        $this->assertSame(537.5, $this->accountBalance($this->tenant->id, '2000'));

        $this->bulk(\App\Livewire\Bills\BillsTable::class, [$bill->id], 'mark_cancelled')->assertOk();

        $this->assertSame('cancelled', $bill->fresh()->status);
        $this->assertSame(0.0, $this->accountBalance($this->tenant->id, '2000'));
        $this->assertStoredBalancesMatchLedger($this->tenant->id);
    }

    private function manualJournal(float $debit, float $credit): \App\Models\Journal
    {
        $journal = \App\Models\Journal::create([
            'tenant_id' => $this->tenant->id, 'journal_number' => 'JE-'.random_int(100000, 999999),
            'journal_date' => now(), 'description' => 'Manual', 'status' => 'draft',
        ]);
        $service = app(\App\Services\JournalService::class);
        $service->createEntry($journal, '1000', $debit, 0, 'Cash in');
        $service->createEntry($journal, '4000', 0, $credit, 'Other income');

        return $journal;
    }

    public function test_c5_bulk_post_updates_account_balances(): void
    {
        $this->createAuthenticatedUser(['post journals']);
        $journal = $this->manualJournal(250, 250);

        $this->bulk(\App\Livewire\Journals\JournalsTable::class, [$journal->id], 'post')->assertOk();

        $this->assertSame('posted', $journal->fresh()->status);
        $this->assertSame(250.0, $this->accountBalance($this->tenant->id, '1000'));
        $this->assertStoredBalancesMatchLedger($this->tenant->id);
    }

    public function test_c5_bulk_post_refuses_an_unbalanced_journal(): void
    {
        $this->createAuthenticatedUser(['post journals']);
        $journal = $this->manualJournal(250, 200);

        $this->bulk(\App\Livewire\Journals\JournalsTable::class, [$journal->id], 'post')->assertOk();

        $this->assertSame('draft', $journal->fresh()->status);
        $this->assertSame(0.0, $this->accountBalance($this->tenant->id, '1000'));
    }

    public function test_c5_bulk_void_reverses_a_posted_manual_journal(): void
    {
        $this->createAuthenticatedUser(['post journals', 'edit journals']);
        $journal = $this->manualJournal(250, 250);
        $this->bulk(\App\Livewire\Journals\JournalsTable::class, [$journal->id], 'post');
        $this->assertSame(250.0, $this->accountBalance($this->tenant->id, '1000'));

        $this->bulk(\App\Livewire\Journals\JournalsTable::class, [$journal->id], 'void')->assertOk();

        $this->assertSame('reversed', $journal->fresh()->status);
        $this->assertSame(0.0, $this->accountBalance($this->tenant->id, '1000'));
        $this->assertNotNull(\App\Models\Journal::where('reference', 'REV-'.$journal->journal_number)->first());
        $this->assertStoredBalancesMatchLedger($this->tenant->id);
    }

    public function test_c5_bulk_void_skips_journals_that_belong_to_a_document(): void
    {
        $this->createAuthenticatedUser(['edit journals']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->sent()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-000023']);
        $journal = \App\Models\Journal::where('reference_type', Invoice::class)->where('reference_id', $invoice->id)->first();

        $this->bulk(\App\Livewire\Journals\JournalsTable::class, [$journal->id], 'void')->assertOk();

        $this->assertSame('posted', $journal->fresh()->status);
        $this->assertSame(1075.0, $this->accountBalance($this->tenant->id, '1200'));
    }

    public function test_c5_cancel_skips_invoices_with_payments(): void
    {
        $this->createAuthenticatedUser(['edit invoices']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->sent()->create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-000024',
            'amount_paid' => 100, 'balance_due' => 975, 'status' => 'partial',
        ]);
        $unpaid = Invoice::factory()->sent()->create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-000025',
            'status' => 'overdue',
        ]);

        $this->bulk(\App\Livewire\Invoices\InvoicesTable::class, [$invoice->id, $unpaid->id], 'mark_cancelled')->assertOk();

        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertSame('cancelled', $unpaid->fresh()->status);
    }

    public function test_c5_an_invoice_in_a_closed_period_is_reported_not_crashed(): void
    {
        $this->createAuthenticatedUser(['edit invoices', 'send invoices']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $old = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-000026',
            'invoice_date' => now()->subYear(),
        ]);
        $current = Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-000027']);
        \App\Models\AccountingPeriod::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Last year', 'status' => 'closed',
            'start_date' => now()->subYear()->startOfYear(), 'end_date' => now()->subYear()->endOfYear(),
        ]);

        $this->bulk(\App\Livewire\Invoices\InvoicesTable::class, [$old->id, $current->id], 'mark_sent')
            ->assertOk()
            ->assertSet('errorMessage', 'Not changed (closed period or invalid totals): INV-000026.');

        $this->assertSame('draft', $old->fresh()->status);
        $this->assertSame('sent', $current->fresh()->status);
        $this->assertStoredBalancesMatchLedger($this->tenant->id);
    }

    // ── M6: deleting a posted document reverses its journal ─────

    public function test_m6_deleting_a_sent_invoice_keeps_the_journal_and_reverses_it(): void
    {
        $this->createAuthenticatedUser();
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->sent()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-000030']);
        $this->assertSame(1075.0, $this->accountBalance($this->tenant->id, '1200'));

        $invoice->delete();

        $journals = \App\Models\Journal::where('reference_type', Invoice::class)->where('reference_id', $invoice->id)->orderBy('id')->get();
        $this->assertCount(2, $journals);
        $this->assertSame('reversed', $journals[0]->status);
        $this->assertSame('REV-'.$journals[0]->journal_number, $journals[1]->reference);
        $this->assertSame(0.0, $this->accountBalance($this->tenant->id, '1200'));
        $this->assertStoredBalancesMatchLedger($this->tenant->id);
        $this->assertAllJournalsBalance($this->tenant->id);
    }

    public function test_m6_deleting_a_payment_reverses_it_and_reopens_the_invoice(): void
    {
        $this->createAuthenticatedUser();
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->sent()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-000031']);
        $payment = PaymentReceived::create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_id' => $invoice->id,
            'payment_number' => 'PR-000031', 'payment_date' => now(), 'amount' => 1075, 'payment_method' => 'cash',
            'is_deposit' => false, 'unused_amount' => 0, 'created_by' => $this->user->id,
        ]);
        $this->assertSame('paid', $invoice->fresh()->status);

        $payment->delete();

        $this->assertEqualsWithDelta(1075.0, (float) $invoice->fresh()->balance_due, 0.001);
        $this->assertSame(1075.0, $this->accountBalance($this->tenant->id, '1200'));
        $this->assertSame(2, \App\Models\Journal::where('reference_type', PaymentReceived::class)->where('reference_id', $payment->id)->count());
        $this->assertStoredBalancesMatchLedger($this->tenant->id);
    }

    public function test_m6_deleting_a_draft_leaves_no_journal(): void
    {
        $this->createAuthenticatedUser();
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-000032']);

        $invoice->delete();

        $this->assertSame(0, \App\Models\Journal::withTrashed()->where('reference_type', Invoice::class)->where('reference_id', $invoice->id)->count());
    }

    private function asset(): \App\Models\FixedAsset
    {
        return \App\Models\FixedAsset::create([
            'tenant_id' => $this->tenant->id, 'asset_number' => 'FA-'.random_int(1000, 9999), 'name' => 'Laptop',
            'purchase_date' => now()->subMonths(2), 'in_service_date' => now()->subMonths(2),
            'purchase_cost' => 1200, 'salvage_value' => 0, 'depreciable_amount' => 1200,
            'useful_life' => 1, 'depreciation_method' => 'straight_line', 'accumulated_depreciation' => 0,
            'book_value' => 1200, 'status' => 'active',
        ]);
    }

    public function test_m6_depreciation_keeps_balances_in_step_with_the_ledger(): void
    {
        $this->createAuthenticatedUser();
        $service = app(\App\Services\DepreciationService::class);

        $depreciation = $service->recordDepreciation($this->asset(), now());

        $this->assertSame(100.0, $this->accountBalance($this->tenant->id, '6800'));
        // Accumulated depreciation is a credit on an asset account: it lowers the balance
        $this->assertSame(-100.0, $this->accountBalance($this->tenant->id, '1600'));
        $this->assertStoredBalancesMatchLedger($this->tenant->id);

        $service->reverseDepreciation($depreciation->fresh());

        $this->assertSame(0.0, $this->accountBalance($this->tenant->id, '6800'));
        $this->assertSame(0.0, $this->accountBalance($this->tenant->id, '1600'));
        $this->assertStoredBalancesMatchLedger($this->tenant->id);
    }

    public function test_m6_disposing_an_asset_keeps_balances_in_step_with_the_ledger(): void
    {
        $this->createAuthenticatedUser();
        $service = app(\App\Services\DepreciationService::class);
        $asset = $this->asset();
        $service->recordDepreciation($asset, now());

        $service->disposeAsset($asset->fresh(), 'sale', 1150, now());

        // Sold for 1,150 with book value 1,100: gain of 50
        $this->assertSame(0.0, $this->accountBalance($this->tenant->id, '1600'));
        $this->assertSame(50.0, $this->accountBalance($this->tenant->id, '4200'));
        $this->assertStoredBalancesMatchLedger($this->tenant->id);
        $this->assertAllJournalsBalance($this->tenant->id);
    }
}
