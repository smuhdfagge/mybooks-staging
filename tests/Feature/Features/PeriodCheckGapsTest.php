<?php

namespace Tests\Feature\Features;

use App\Models\AccountingPeriod;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\CustomerDepositApplication;
use App\Models\Employee;
use App\Models\FixedAsset;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\PaymentReceived;
use App\Models\Payroll;
use App\Models\RecurrentInvoice;
use App\Models\SalesReceipt;
use App\Models\Vendor;
use App\Services\JournalService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Session 11: places where a change in a closed accounting period got past
 * the period check before lock dates were added. Each test fails on the
 * code before the fix.
 */
class PeriodCheckGapsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-10 09:00:00');
        $this->createAuthenticatedUser([
            'view invoices', 'create invoices', 'edit invoices', 'delete invoices',
            'view bills', 'create bills', 'edit bills',
            'view sales-receipts', 'create sales-receipts', 'edit sales-receipts', 'delete sales-receipts',
            'view payroll', 'create payroll', 'edit payroll',
            'view fixed-assets', 'create fixed-assets', 'edit fixed-assets', 'delete fixed-assets', 'depreciate fixed-assets',
        ]);
        $this->subscription->update(['ends_at' => '2030-12-31']);
    }

    private function closeAugust(): void
    {
        AccountingPeriod::create([
            'tenant_id' => $this->tenant->id, 'name' => 'August 2026', 'start_date' => '2026-08-01',
            'end_date' => '2026-08-31', 'status' => 'closed', 'fiscal_year' => 2026,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function lines(float $price = 10000): array
    {
        return [['description' => 'Service', 'quantity' => 1, 'unit_price' => $price, 'tax_rate' => 0]];
    }

    public function test_an_invoice_in_a_closed_period_cannot_be_edited(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $data = ['customer_id' => $customer->id, 'invoice_date' => '2026-08-15', 'due_date' => '2026-09-15', 'status' => 'sent', 'items' => $this->lines()];
        $this->post(route('invoices.store'), $data)->assertSessionHasNoErrors();
        $invoice = Invoice::sole();
        $this->closeAugust();

        // Same lines, so the journal didn't change: the header was saved with
        // events off and nothing checked the period.
        $this->put(route('invoices.update', $invoice), ['due_date' => '2026-12-31', 'notes' => 'Changed after closing'] + $data)
            ->assertSessionHasErrors();

        $invoice->refresh();
        $this->assertSame('2026-09-15', $invoice->due_date->toDateString());
        $this->assertNull($invoice->notes);
    }

    public function test_a_bill_in_a_closed_period_cannot_be_edited(): void
    {
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $data = ['vendor_id' => $vendor->id, 'bill_date' => '2026-08-15', 'due_date' => '2026-09-15', 'items' => $this->lines()];
        $this->post(route('bills.store'), $data)->assertSessionHasNoErrors();
        $bill = Bill::sole();
        $this->closeAugust();

        $this->put(route('bills.update', $bill), ['notes' => 'Changed after closing'] + $data)->assertSessionHasErrors();

        $this->assertNull($bill->fresh()->notes);
    }

    public function test_a_sales_receipt_in_a_closed_period_cannot_be_edited(): void
    {
        $data = ['receipt_date' => '2026-08-15', 'payment_method' => 'cash', 'items' => $this->lines()];
        $this->post(route('sales-receipts.store'), $data)->assertSessionHasNoErrors();
        $receipt = SalesReceipt::sole();
        $this->closeAugust();

        // The receipt was checked against its created_at, not its receipt
        // date, and its header was saved with events off.
        $this->put(route('sales-receipts.update', $receipt), ['notes' => 'Changed after closing', 'reference' => 'X1'] + $data)
            ->assertSessionHasErrors();

        $this->assertNull($receipt->fresh()->notes);
    }

    public function test_an_approved_payroll_in_a_closed_period_cannot_be_edited(): void
    {
        $employee = Employee::withoutEvents(fn () => Employee::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => 'EMP-001', 'first_name' => 'Hauwa', 'last_name' => 'Musa',
            'email' => 'hauwa@example.com', 'hire_date' => '2025-01-01', 'status' => 'active',
        ]));
        $payroll = Payroll::withoutEvents(fn () => Payroll::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id, 'payroll_number' => 'PAY-000001',
            'pay_period_start' => '2026-08-01', 'pay_period_end' => '2026-08-31', 'pay_date' => '2026-08-31',
            'basic_salary' => 100000, 'allowances' => 0, 'gross_salary' => 100000, 'tax_deduction' => 0,
            'total_deductions' => 0, 'net_salary' => 100000, 'status' => 'approved', 'payment_method' => 'bank_transfer',
            'created_by' => $this->user->id,
        ]));
        $this->closeAugust();

        // Payroll was checked against created_at (October), not its pay date.
        $this->put(route('payroll.update', $payroll), [
            'pay_period_start' => '2026-08-01', 'pay_period_end' => '2026-08-31', 'basic_salary' => 150000,
        ])->assertSessionHasErrors();

        $this->assertEqualsWithDelta(100000, (float) $payroll->fresh()->basic_salary, 0.001);
    }

    public function test_a_customer_deposit_cannot_be_applied_in_a_closed_period(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_date' => '2026-10-01']);
        $deposit = PaymentReceived::withoutEvents(fn () => PaymentReceived::create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'payment_number' => 'PR-000001',
            'payment_date' => '2026-10-01', 'amount' => 5000, 'payment_method' => 'cash', 'is_deposit' => true,
        ]));
        $this->closeAugust();

        // Checked against created_at (today) instead of the application date.
        $this->expectException(ValidationException::class);
        CustomerDepositApplication::create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'deposit_payment_id' => $deposit->id, 'invoice_id' => $invoice->id,
            'amount' => 1000, 'application_date' => '2026-08-20',
        ]);
    }

    public function test_lines_of_a_posted_journal_in_a_closed_period_cannot_be_changed(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->post(route('invoices.store'), ['customer_id' => $customer->id, 'invoice_date' => '2026-08-15', 'due_date' => '2026-09-15', 'status' => 'sent', 'items' => $this->lines()])
            ->assertSessionHasNoErrors();
        $journal = Journal::where('reference_type', Invoice::class)->sole();
        $this->closeAugust();

        // A journal rebuilt with the same totals is never "dirty", so only
        // its lines changed and nothing checked the period.
        try {
            app(JournalService::class)->createEntry($journal, '1000', 1, 0, 'Sneaked in');
            $this->fail('A line was added to a journal in a closed period.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('closed accounting period', $e->getMessage());
        }
        $this->assertSame(2, $journal->entries()->count());
    }

    public function test_a_recurring_invoice_due_in_a_closed_period_goes_on_the_first_open_date(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $profile = RecurrentInvoice::create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'profile_name' => 'Monthly retainer', 'frequency' => 'monthly',
            'start_date' => '2026-08-20', 'next_invoice_date' => '2026-08-20', 'payment_terms' => 30, 'status' => 'active',
            'created_by' => $this->user->id, 'subtotal' => 10000, 'tax_amount' => 0, 'total' => 10000,
        ]);
        $profile->items()->create(['description' => 'Retainer', 'quantity' => 1, 'unit_price' => 10000, 'tax_rate' => 0, 'tax_amount' => 0, 'total' => 10000]);
        $this->closeAugust();

        // It failed every day and never moved on. One invoice per profile per run.
        $this->artisan('transactions:process-recurring')->assertSuccessful();
        $this->artisan('transactions:process-recurring')->assertSuccessful();

        $invoices = Invoice::orderBy('id')->get();
        $this->assertCount(2, $invoices, 'the August one (moved) and the September one');
        $this->assertSame('2026-09-01', $invoices[0]->invoice_date->toDateString());
        $this->assertSame('2026-09-20', $invoices[1]->invoice_date->toDateString());
        $this->assertSame('2026-10-20', $profile->fresh()->next_invoice_date->toDateString());
    }

    public function test_depreciation_for_a_closed_month_goes_on_the_first_open_date(): void
    {
        $this->post(route('fixed-assets.store'), [
            'name' => 'Delivery van', 'purchase_date' => '2026-07-01', 'in_service_date' => '2026-07-01',
            'purchase_cost' => 1200000, 'salvage_value' => 0, 'useful_life' => 5, 'depreciation_method' => 'straight_line',
            'funding_source' => 'bank',
        ])->assertSessionHasNoErrors();
        $this->closeAugust();

        $this->post(route('fixed-assets.run-depreciation'), ['depreciation_date' => '2026-08-31'])->assertSessionHasNoErrors();

        $asset = FixedAsset::sole();
        $depreciation = $asset->depreciations()->sole();
        $this->assertSame('2026-08-31', $depreciation->depreciation_date->toDateString(), 'still August\'s charge');
        $this->assertSame('2026-09-01', $depreciation->journal->journal_date->toDateString());
        $this->assertStringContainsString('that period is closed, so posted on 1 Sep 2026', $depreciation->journal->description);
    }
}
