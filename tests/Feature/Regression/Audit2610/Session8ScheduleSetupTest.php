<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Actions\AccrualSchedules\ReleaseAccrualSchedule;
use App\Actions\Bills\SaveBill;
use App\Actions\Invoices\SaveInvoice;
use App\Actions\LockDates\UpdateLockDates;
use App\Exceptions\BusinessRuleException;
use App\Models\AccrualSchedule;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Vendor;
use App\Services\Accounting\FinancialStatements;
use App\Services\JournalService;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 8 (MB-53): a schedule linked to a bill or
 * invoice released its months out of Prepaid Expenses / Deferred Revenue,
 * where the money never was, so the expense or income was counted twice
 * (a ₦120,000 bill over 12 months from July left Prepaid at -₦30,000 and
 * the expense at ₦150,000 by 10 October). A linked schedule now posts a
 * setup journal that moves the amount there first; an unlinked one needs
 * the money already in the account.
 */
class Session8ScheduleSetupTest extends TestCase
{
    private const PERMISSIONS = [
        'view accrual-schedules', 'create accrual-schedules', 'edit accrual-schedules', 'delete accrual-schedules',
        'view journals', 'create journals', 'post journals', 'view bills', 'create bills', 'view invoices', 'create invoices',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-10 09:00:00');
        $this->createAuthenticatedUser(self::PERMISSIONS);
        $this->subscription->update(['ends_at' => '2030-12-31']);
    }

    private function account(string $code): ChartOfAccount
    {
        return ChartOfAccount::where('account_code', $code)->firstOrFail();
    }

    /** Ledger balance, natural sign, as the reports show it. */
    private function ledger(string $code): string
    {
        $balance = app(FinancialStatements::class)->accountBalances($this->tenant->id, null, '2030-12-31')->firstWhere('account_code', $code)?->balance ?? 0;

        return number_format((float) $balance, 2, '.', '');
    }

    private function rentBill(float $net = 120000, float $vat = 0, string $date = '2026-07-01'): Bill
    {
        return app(SaveBill::class)->create($this->tenant->id, [
            'vendor_id' => Vendor::factory()->create(['tenant_id' => $this->tenant->id])->id, 'bill_date' => $date, 'due_date' => $date,
            'items' => [['description' => 'Shop rent, a year', 'quantity' => 1, 'unit_price' => $net, 'tax_rate' => $vat, 'account_id' => $this->account('6100')->id]],
        ], $this->user->id);
    }

    /** @return array<string, mixed> */
    private function prepaid(array $overrides = []): array
    {
        return $overrides + [
            'type' => AccrualSchedule::TYPE_PREPAID, 'description' => 'Shop rent', 'total_amount' => 120000,
            'start_month' => '2026-07', 'months' => 12, 'balance_account_id' => '', 'pl_account_id' => $this->account('6100')->id,
        ];
    }

    private function save(array $data): AccrualSchedule
    {
        $this->post(route('accrual-schedules.store'), $data)->assertSessionHasNoErrors();

        return AccrualSchedule::latest('id')->firstOrFail();
    }

    public function test_a_prepaid_schedule_linked_to_a_bill_counts_the_expense_once(): void
    {
        $bill = $this->rentBill();
        $schedule = $this->save($this->prepaid(['source' => "bill:{$bill->id}"]));

        $this->assertSame(3, $schedule->releases()->count(), 'July to September are over');
        $this->assertSame('90000.00', $this->ledger('1400'));
        $this->assertSame('30000.00', $this->ledger('6100'));
        $setup = $schedule->setupJournal;
        $this->assertSame(Journal::TYPE_SCHEDULE_SETUP, $setup->journal_type);
        $this->assertSame('2026-07-01', $setup->journal_date->toDateString());
    }

    public function test_a_deferred_schedule_linked_to_an_invoice_counts_the_income_once(): void
    {
        $invoice = app(SaveInvoice::class)->create($this->tenant->id, [
            'customer_id' => Customer::factory()->create(['tenant_id' => $this->tenant->id])->id, 'invoice_date' => '2026-07-01', 'due_date' => '2026-07-31', 'status' => 'sent',
            'items' => [['description' => 'Support, six months', 'quantity' => 1, 'unit_price' => 60000, 'tax_rate' => 7.5]],
        ], $this->user->id);

        $this->save([
            'type' => AccrualSchedule::TYPE_DEFERRED, 'description' => 'Support contract', 'total_amount' => 60000,
            'start_month' => '2026-07', 'months' => 6, 'balance_account_id' => '', 'pl_account_id' => $this->account('4000')->id,
            'source' => "invoice:{$invoice->id}",
        ]);

        $this->assertSame('30000.00', $this->ledger('2440'));
        $this->assertSame('30000.00', $this->ledger('4000'));
        $this->assertSame('4500.00', $this->ledger('2400'), 'output VAT stays where the invoice put it');
    }

    public function test_a_bill_with_vat_is_spread_net_of_vat(): void
    {
        $bill = $this->rentBill(120000, 7.5);
        $this->save($this->prepaid(['source' => "bill:{$bill->id}"]));

        $this->assertSame('90000.00', $this->ledger('1400'));
        $this->assertSame('30000.00', $this->ledger('6100'));
        $this->assertSame('9000.00', $this->ledger('1410'));
    }

    public function test_more_than_the_document_holds_is_refused(): void
    {
        $bill = $this->rentBill(50000);

        $this->post(route('accrual-schedules.store'), $this->prepaid(['source' => "bill:{$bill->id}"]))->assertSessionHasErrors('source');
        $this->assertSame(0, AccrualSchedule::count());
        $this->assertSame('50000.00', $this->ledger('6100'));
    }

    public function test_an_unlinked_schedule_needs_the_money_already_in_the_account(): void
    {
        $this->post(route('accrual-schedules.store'), $this->prepaid())->assertSessionHasErrors('balance_account_id');
        $this->assertSame(0, AccrualSchedule::count());

        // Paid in advance by journal into Prepaid Expenses: now it is covered.
        $this->post(route('journals.store'), ['journal_date' => '2026-07-01', 'description' => 'Rent paid', 'entries' => [
            ['account_id' => $this->account('1400')->id, 'debit' => 120000, 'credit' => 0],
            ['account_id' => $this->account('1000')->id, 'debit' => 0, 'credit' => 120000],
        ]])->assertSessionHasNoErrors();
        $this->post(route('journals.post', Journal::latest('id')->firstOrFail()))->assertSessionHasNoErrors();

        $this->save($this->prepaid());
        $this->assertSame('90000.00', $this->ledger('1400'));
        $this->assertSame('30000.00', $this->ledger('6100'));

        // A second schedule on the same money is refused.
        $this->post(route('accrual-schedules.store'), $this->prepaid(['description' => 'Again']))->assertSessionHasErrors('balance_account_id');
    }

    public function test_cancelling_can_move_the_rest_back_and_deleting_undoes_the_setup(): void
    {
        $schedule = $this->save($this->prepaid(['source' => 'bill:'.$this->rentBill()->id]));

        $this->post(route('accrual-schedules.cancel', $schedule), ['move_back' => 1])->assertSessionHas('success');
        $this->assertSame('0.00', $this->ledger('1400'));
        $this->assertSame('120000.00', $this->ledger('6100'));

        // Not started yet, so it can be deleted: everything goes back.
        $future = $this->save($this->prepaid(['source' => 'bill:'.$this->rentBill(60000, 0, '2026-10-02')->id, 'total_amount' => 60000, 'start_month' => '2026-11', 'months' => 6]));
        $this->assertSame('60000.00', $this->ledger('1400'));
        $this->delete(route('accrual-schedules.destroy', $future))->assertRedirect(route('accrual-schedules.index'));
        $this->assertSame('0.00', $this->ledger('1400'));
        $this->assertSame('180000.00', $this->ledger('6100'));
    }

    public function test_a_setup_dated_behind_the_lock_goes_on_the_first_open_date(): void
    {
        $bill = $this->rentBill();
        app(UpdateLockDates::class)->handle($this->tenant, ['staff_lock_date' => '2026-09-30', 'all_users_lock_date' => '2026-09-30', 'reason' => 'Q3 filed']);

        $schedule = $this->save($this->prepaid(['source' => "bill:{$bill->id}"]));

        $this->assertSame('2026-10-01', $schedule->setupJournal->journal_date->toDateString());
        $this->assertSame('90000.00', $this->ledger('1400'));
        $this->assertSame('30000.00', $this->ledger('6100'));
    }

    public function test_the_setup_journal_cannot_be_reversed_on_its_own(): void
    {
        $schedule = $this->save($this->prepaid(['source' => 'bill:'.$this->rentBill()->id]));

        $this->expectException(BusinessRuleException::class);
        app(JournalService::class)->reverseJournal($schedule->setupJournal, 'Voided');
    }

    public function test_the_repair_command_fixes_schedules_linked_before_the_setup_journal(): void
    {
        // As it was before session 8: linked, no setup, three months released.
        $bill = $this->rentBill();
        $schedule = AccrualSchedule::create([
            'tenant_id' => $this->tenant->id, 'schedule_number' => 'PDS-000009', 'type' => AccrualSchedule::TYPE_PREPAID,
            'description' => 'Shop rent', 'total_amount' => 120000, 'start_date' => '2026-07-01', 'months' => 12,
            'balance_account_id' => $this->account('1400')->id, 'pl_account_id' => $this->account('6100')->id,
            'source_type' => 'bill', 'source_id' => $bill->id, 'status' => AccrualSchedule::STATUS_ACTIVE, 'released_amount' => 0,
        ]);
        app(ReleaseAccrualSchedule::class)->handle($schedule);
        $this->assertSame('-30000.00', $this->ledger('1400'));
        $this->assertSame('150000.00', $this->ledger('6100'));

        auth()->forgetGuards();
        $this->artisan('accruals:repair-setup', ['--tenant' => $this->tenant->id])
            ->expectsOutputToContain('PDS-000009')
            ->expectsOutputToContain('1 schedule(s) need a setup journal. Dry run')
            ->assertSuccessful();
        $this->assertSame('-30000.00', $this->ledger('1400'));

        $this->artisan('accruals:repair-setup', ['--tenant' => $this->tenant->id, '--apply' => true])
            ->expectsOutputToContain('1 setup journal(s) posted.')
            ->assertSuccessful();
        $this->assertSame('90000.00', $this->ledger('1400'));
        $this->assertSame('30000.00', $this->ledger('6100'));
        $this->assertNotNull($schedule->fresh()->setup_journal_id);
    }
}
