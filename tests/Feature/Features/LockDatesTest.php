<?php

namespace Tests\Feature\Features;

use App\Actions\Invoices\SaveInvoice;
use App\Actions\LockDates\UpdateLockDates;
use App\Actions\VatReturns\FileVatReturn;
use App\Models\AccountingPeriod;
use App\Models\AccrualSchedule;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\LockDateChange;
use App\Models\PaymentReceived;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VatReturnFiling;
use App\Models\Vendor;
use App\Services\AccountCodeService;
use App\Services\Accounting\LockDates;
use App\Services\JournalService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Session 11: staff and all-users lock dates, their history, and how every
 * path (web, API, automatic postings) respects them.
 */
class LockDatesTest extends TestCase
{
    private const STAFF = [
        'view invoices', 'create invoices', 'edit invoices', 'delete invoices',
        'view bills', 'create bills', 'edit bills', 'delete bills',
        'view payments-received', 'create payments-received', 'delete payments-received',
        'view journals', 'create journals', 'edit journals', 'post journals', 'delete journals',
        'view expenses', 'create expenses', 'edit expenses', 'delete expenses',
        'view chart-of-accounts', 'edit chart-of-accounts', 'view dashboard',
        'view accrual-schedules', 'create accrual-schedules', 'edit accrual-schedules',
    ];

    private const STAFF_MESSAGE = 'The books are locked up to 30 Sep 2026. Ask an admin to change the lock date if you need to change this.';

    private Customer $customer;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-10 09:00:00');
        $this->createAuthenticatedUser(self::STAFF);
        $this->subscription->update(['ends_at' => '2030-12-31']);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function lock(?string $staff, ?string $all = null, ?string $reason = null, ?Tenant $tenant = null): void
    {
        app(UpdateLockDates::class)->handle($tenant ?? $this->tenant, [
            'staff_lock_date' => $staff, 'all_users_lock_date' => $all, 'reason' => $reason,
        ]);
    }

    private function admin(): User
    {
        return $this->createUserForTenant($this->tenant, array_merge(self::STAFF, ['override lock-date', 'manage lock-dates']));
    }

    private function account(string $code): ChartOfAccount
    {
        return ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', $code)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function invoice(string $date): array
    {
        return [
            'customer_id' => $this->customer->id, 'invoice_date' => $date, 'due_date' => '2026-12-31', 'status' => 'sent',
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 10000, 'tax_rate' => 0]],
        ];
    }

    /** @return array<string, mixed> */
    private function bill(string $date): array
    {
        return [
            'vendor_id' => $this->vendor->id, 'bill_date' => $date, 'due_date' => '2026-12-31',
            'items' => [['description' => 'Stationery', 'quantity' => 1, 'unit_price' => 5000, 'tax_rate' => 0]],
        ];
    }

    /** @return array<string, mixed> */
    private function journal(string $date, ?string $reverseOn = null): array
    {
        return [
            'journal_date' => $date, 'description' => 'Accrued electricity', 'reverse_on' => $reverseOn,
            'entries' => [
                ['account_id' => $this->account('6100')->id, 'debit' => 2000, 'credit' => 0],
                ['account_id' => $this->account('2000')->id, 'debit' => 0, 'credit' => 2000],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function expense(string $date): array
    {
        return ['expense_account_id' => $this->account('6100')->id, 'name' => 'Fuel', 'expense_date' => $date, 'amount' => 5000];
    }

    // ── Enforcement ────────────────────────────────────────────

    public function test_the_staff_lock_stops_staff_creating_editing_and_deleting(): void
    {
        $this->post(route('invoices.store'), $this->invoice('2026-09-15'))->assertSessionHasNoErrors();
        $this->post(route('bills.store'), $this->bill('2026-09-15'))->assertSessionHasNoErrors();
        $this->post(route('journals.store'), $this->journal('2026-09-15'))->assertSessionHasNoErrors();
        $this->post(route('expenses.store'), $this->expense('2026-09-15'))->assertSessionHasNoErrors();
        $invoice = Invoice::sole();
        $bill = Bill::sole();
        $journal = Journal::where('journal_type', null)->where('is_posted', false)->sole();
        $expense = Expense::sole();

        $this->lock('2026-09-30');

        // Create
        $this->post(route('invoices.store'), $this->invoice('2026-09-20'))->assertSessionHasErrors(['invoice_date' => self::STAFF_MESSAGE]);
        $this->post(route('bills.store'), $this->bill('2026-09-30'))->assertSessionHasErrors('bill_date');
        $this->post(route('journals.store'), $this->journal('2026-09-20'))->assertSessionHasErrors();
        $this->post(route('expenses.store'), $this->expense('2026-09-20'))->assertSessionHasErrors('expense_date');
        $this->post(route('payments-received.store'), [
            'customer_id' => $this->customer->id, 'invoice_id' => $invoice->id, 'payment_date' => '2026-09-25', 'amount' => 1000, 'payment_method' => 'cash',
        ])->assertSessionHasErrors();
        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, Bill::count());
        $this->assertSame(1, Expense::count());
        $this->assertSame(0, PaymentReceived::count());

        // Edit: neither the old nor the new date may be locked.
        $this->put(route('invoices.update', $invoice), ['notes' => 'Late change'] + $this->invoice('2026-09-15'))->assertSessionHasErrors();
        $this->put(route('invoices.update', $invoice), $this->invoice('2026-10-05'))->assertSessionHasErrors();
        $this->put(route('bills.update', $bill), ['notes' => 'Late change'] + $this->bill('2026-09-15'))->assertSessionHasErrors();
        $this->put(route('expenses.update', $expense), ['name' => 'Diesel'] + $this->expense('2026-09-15'))->assertSessionHasErrors();
        $this->put(route('journals.update', $journal), $this->journal('2026-09-16'))->assertSessionHasErrors();
        $this->assertSame('2026-09-15', $invoice->fresh()->invoice_date->toDateString());
        $this->assertNull($bill->fresh()->notes);
        $this->assertSame('Fuel', $expense->fresh()->name);

        // Delete
        $this->delete(route('invoices.destroy', $invoice));
        $this->delete(route('expenses.destroy', $expense));
        $this->delete(route('journals.destroy', $journal));
        $this->assertNotNull(Invoice::find($invoice->id));
        $this->assertNotNull(Expense::find($expense->id));
        $this->assertNotNull(Journal::find($journal->id));

        // The day after the lock is open.
        $this->post(route('invoices.store'), $this->invoice('2026-10-01'))->assertSessionHasNoErrors();
        $this->assertSame(2, Invoice::count());
    }

    public function test_moving_a_document_into_the_locked_range_is_blocked(): void
    {
        $this->post(route('invoices.store'), $this->invoice('2026-10-02'))->assertSessionHasNoErrors();
        $this->lock('2026-09-30');

        $this->put(route('invoices.update', Invoice::sole()), $this->invoice('2026-09-29'))->assertSessionHasErrors(['invoice_date' => self::STAFF_MESSAGE]);
        $this->assertSame('2026-10-02', Invoice::sole()->invoice_date->toDateString());
    }

    public function test_an_admin_can_post_behind_the_staff_lock_but_nobody_behind_the_lock_for_everyone(): void
    {
        $this->lock('2026-09-30');
        $this->actingAs($this->admin());

        // A warning on the form, and it saves.
        $this->get(route('invoices.create'))->assertOk()->assertSee('data-lock-date-notice', false)
            ->assertSee('You can still save because you are allowed to override the lock');
        $this->post(route('invoices.store'), $this->invoice('2026-09-20'))->assertSessionHasNoErrors();
        $invoice = Invoice::sole();
        $this->assertSame('2026-09-20', Journal::where('reference_type', Invoice::class)->sole()->journal_date->toDateString());

        $this->lock('2026-09-30', '2026-09-25');
        $this->post(route('invoices.store'), $this->invoice('2026-09-25'))
            ->assertSessionHasErrors(['invoice_date' => 'The books are locked for everyone up to 25 Sep 2026. An admin must move the lock date back, with a reason, before this can be changed.']);
        $this->put(route('invoices.update', $invoice), ['notes' => 'x'] + $this->invoice('2026-09-20'))->assertSessionHasErrors();
        $this->delete(route('invoices.destroy', $invoice));
        $this->assertNotNull(Invoice::find($invoice->id));

        // Between the two dates the admin still can.
        $this->post(route('invoices.store'), $this->invoice('2026-09-26'))->assertSessionHasNoErrors();
        $this->assertSame(2, Invoice::count());
    }

    public function test_a_super_admin_cannot_pass_the_lock_for_everyone_either(): void
    {
        auth()->logout();
        $this->createSuperAdmin();
        $this->lock('2026-09-30', '2026-09-30');
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->post(route('invoices.store'), ['customer_id' => $customer->id] + $this->invoice('2026-09-10'))->assertSessionHasErrors('invoice_date');
        $this->assertSame(0, Invoice::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_staff_see_the_lock_on_the_form_and_the_dashboard(): void
    {
        $this->lock('2026-09-30');

        $this->get(route('invoices.create'))->assertOk()->assertSee('data-lock-date-notice', false)
            ->assertSee('The books are locked up to')->assertDontSee('You can still save because');
        foreach (['bills.create', 'expenses.create', 'journals.create', 'payments-received.create'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('data-lock-date-notice', false);
        }
        $this->get(route('dashboard'))->assertOk()->assertSee('Books locked up to 30 Sep 2026');
    }

    public function test_api_writes_get_a_422_with_the_same_message_and_settings_show_the_dates(): void
    {
        $this->lock('2026-09-30', '2026-06-30');
        $api = $this->actingAs($this->user, 'sanctum');

        $api->postJson('/api/v1/invoices', $this->invoice('2026-09-20'))
            ->assertStatus(422)->assertJsonPath('errors.invoice_date.0', self::STAFF_MESSAGE);
        $api->postJson('/api/v1/expenses', $this->expense('2026-09-20'))->assertStatus(422)->assertJsonValidationErrors('expense_date');
        $api->postJson('/api/v1/journals', $this->journal('2026-09-20'))->assertStatus(422);
        $this->assertSame(0, Invoice::count());

        $this->user->givePermissionTo(Permission::findOrCreate('view settings', 'web'));
        $api->getJson('/api/v1/settings')->assertOk()
            ->assertJsonPath('data.accounting.lock_dates.staff_lock_date', '2026-09-30')
            ->assertJsonPath('data.accounting.lock_dates.all_users_lock_date', '2026-06-30')
            ->assertJsonPath('data.accounting.lock_dates.locked_for_you_up_to', '2026-09-30');
    }

    public function test_lines_of_a_posted_journal_behind_the_lock_cannot_change(): void
    {
        $this->post(route('invoices.store'), $this->invoice('2026-09-15'))->assertSessionHasNoErrors();
        $journal = Journal::where('reference_type', Invoice::class)->sole();
        $this->lock('2026-09-30');

        $this->expectException(ValidationException::class);
        app(JournalService::class)->createEntry($journal, '1000', 1, 0, 'Sneaked in');
    }

    // ── Automatic postings ─────────────────────────────────────

    public function test_an_automatic_reversal_due_behind_the_lock_goes_on_the_first_open_date(): void
    {
        // Posted at the end of August, to reverse on 1 September.
        $this->travelTo('2026-08-31 09:00:00');
        $this->post(route('journals.store'), $this->journal('2026-08-31', '2026-09-01'))->assertSessionHasNoErrors();
        $journal = Journal::latest('id')->firstOrFail();
        $this->post(route('journals.post', $journal))->assertSessionHasNoErrors();
        $this->assertNull($journal->fresh()->autoReversal);

        // The daily run was missed, and September was locked meanwhile.
        $this->travelTo('2026-10-10 09:00:00');
        $this->lock('2026-09-30');

        auth()->forgetGuards();
        $this->artisan('journals:post-reversals')->assertSuccessful();

        $reversal = $journal->fresh()->autoReversal;
        $this->assertNotNull($reversal);
        $this->assertSame('2026-10-01', $reversal->journal_date->toDateString());
        $this->assertStringContainsString('due 1 Sep 2026, but the books are locked up to 30 Sep 2026, so posted on 1 Oct 2026', $reversal->description);
    }

    public function test_a_prepaid_release_due_behind_the_lock_goes_on_the_first_open_date(): void
    {
        $this->lock('2026-09-30', '2026-09-30');
        $this->actingAs($this->admin());

        $this->post(route('accrual-schedules.store'), [
            'type' => AccrualSchedule::TYPE_PREPAID, 'description' => 'Shop rent', 'total_amount' => 120000,
            'start_month' => '2026-08', 'months' => 12,
            'balance_account_id' => $this->account('1400')->id, 'pl_account_id' => $this->account('6100')->id,
        ])->assertSessionHasNoErrors();

        $releases = AccrualSchedule::sole()->releases()->orderBy('sequence')->get();
        $this->assertCount(2, $releases, 'August and September are over');
        foreach ($releases as $release) {
            $this->assertSame('2026-10-01', $release->posted_date->toDateString());
            $this->assertStringContainsString('the books are locked up to 30 Sep 2026', $release->note);
        }
    }

    public function test_first_open_date_steps_past_the_later_lock_and_closed_periods(): void
    {
        $this->lock('2026-09-30', '2026-08-31');
        AccountingPeriod::create(['tenant_id' => $this->tenant->id, 'name' => 'October 2026', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'status' => 'closed', 'fiscal_year' => 2026]);

        $date = app(JournalService::class)->firstOpenDate($this->tenant->id, now()->setDate(2026, 8, 15));
        $this->assertSame('2026-11-01', $date->toDateString());
        $this->assertSame('2026-11-01', app(JournalService::class)->firstOpenDate($this->tenant->id, now()->setDate(2026, 10, 5))->toDateString());
        $this->assertSame('2026-11-03', app(JournalService::class)->firstOpenDate($this->tenant->id, now()->setDate(2026, 11, 3))->toDateString());
    }

    // ── Setting the dates ──────────────────────────────────────

    public function test_the_date_for_everyone_cannot_be_after_the_staff_date_or_in_the_future(): void
    {
        $this->actingAs($this->admin());

        $this->put(route('lock-dates.update'), ['staff_lock_date' => '2026-08-31', 'all_users_lock_date' => '2026-09-30'])
            ->assertSessionHasErrors(['all_users_lock_date' => 'The lock date for everyone must be on or before the staff lock date.']);
        $this->put(route('lock-dates.update'), ['staff_lock_date' => '2026-11-30'])->assertSessionHasErrors('staff_lock_date');
        $this->put(route('lock-dates.update'), ['all_users_lock_date' => '2026-08-31'])->assertSessionHasErrors('staff_lock_date');
        $this->assertNull($this->tenant->fresh()->staff_lock_date);

        $this->put(route('lock-dates.update'), ['staff_lock_date' => '2026-09-30', 'all_users_lock_date' => '2026-09-30'])->assertSessionHasNoErrors();
        $this->assertSame('2026-09-30', $this->tenant->fresh()->all_users_lock_date->toDateString());
    }

    public function test_moving_forward_needs_no_reason_and_moving_back_does_and_both_are_logged(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->put(route('lock-dates.update'), ['staff_lock_date' => '2026-08-31'])->assertSessionHasNoErrors();
        $this->put(route('lock-dates.update'), ['staff_lock_date' => '2026-09-30', 'all_users_lock_date' => '2026-08-31'])->assertSessionHasNoErrors();

        // Back, with no reason: refused and nothing changes.
        $this->put(route('lock-dates.update'), ['staff_lock_date' => '2026-09-15', 'all_users_lock_date' => '2026-08-31'])->assertSessionHasErrors('reason');
        $this->put(route('lock-dates.update'), ['staff_lock_date' => '2026-09-30'])->assertSessionHasErrors('reason');
        $this->assertSame('2026-09-30', $this->tenant->fresh()->staff_lock_date->toDateString());
        $this->assertSame('2026-08-31', $this->tenant->fresh()->all_users_lock_date->toDateString());

        $this->put(route('lock-dates.update'), ['staff_lock_date' => '2026-09-15', 'all_users_lock_date' => '', 'reason' => 'Supplier invoice for September arrived late'])
            ->assertSessionHasNoErrors();

        $history = LockDateChange::orderBy('id')->get();
        $this->assertSame([
            'Staff lock date set to 31 Aug 2026',
            'Staff lock date moved forward from 31 Aug 2026 to 30 Sep 2026',
            'Lock date for everyone set to 31 Aug 2026',
            'Staff lock date moved back from 30 Sep 2026 to 15 Sep 2026',
            'Lock date for everyone removed (was 31 Aug 2026)',
        ], $history->pluck('description')->all());
        $this->assertNull($history[0]->reason);
        $this->assertSame('Supplier invoice for September arrived late', $history[3]->reason);
        $this->assertSame('2026-09-30', $history[3]->old_date->toDateString());
        $this->assertSame('2026-09-15', $history[3]->new_date->toDateString());
        $this->assertSame($admin->id, $history[4]->user_id);

        $this->get(route('accounting-periods.index'))->assertOk()
            ->assertSee('Lock dates')->assertSee('Books locked up to 15 Sep 2026')
            ->assertSee('Supplier invoice for September arrived late')->assertSee($admin->name);
    }

    public function test_only_users_who_manage_lock_dates_can_change_them(): void
    {
        $this->get(route('accounting-periods.index'))->assertOk()->assertSee('Only an admin can change the lock dates')
            ->assertDontSee('Save lock dates');
        $this->put(route('lock-dates.update'), ['staff_lock_date' => '2026-09-30'])->assertForbidden();
        $this->assertNull($this->tenant->fresh()->staff_lock_date);

        $this->actingAs($this->admin());
        $this->get(route('accounting-periods.index'))->assertOk()->assertSee('Save lock dates');
    }

    public function test_the_permissions_go_to_admins_and_accountants_only(): void
    {
        auth()->logout();
        $this->seed(DatabaseSeeder::class);
        $role = fn (string $name) => Role::where('name', $name)->whereNull('tenant_id')->firstOrFail();

        foreach (['admin', 'super-admin'] as $name) {
            $this->assertTrue($role($name)->hasPermissionTo('manage lock-dates'), $name);
            $this->assertTrue($role($name)->hasPermissionTo('override lock-date'), $name);
        }
        $this->assertTrue($role('accountant')->hasPermissionTo('override lock-date'));
        $this->assertFalse($role('accountant')->hasPermissionTo('manage lock-dates'));
        foreach (['sales', 'hr-manager', 'viewer'] as $name) {
            $this->assertFalse($role($name)->hasPermissionTo('override lock-date'), $name);
        }
    }

    public function test_each_business_has_its_own_lock_dates_and_history(): void
    {
        auth()->logout(); // a new business's accounts go to the signed-in one otherwise
        [$other] = $this->createTenantWithSubscription();
        $this->actingAs($this->user);
        $this->lock('2026-09-30', null, null, $other);
        $otherAdmin = $this->createUserForTenant($other, ['view chart-of-accounts', 'manage lock-dates', 'override lock-date']);

        // This business isn't locked.
        $this->post(route('invoices.store'), $this->invoice('2026-09-20'))->assertSessionHasNoErrors();
        $this->get(route('accounting-periods.index'))->assertOk()->assertSee('No lock date set')->assertDontSee('Staff lock date set to');

        $this->actingAs($otherAdmin);
        $this->get(route('accounting-periods.index'))->assertOk()->assertSee('Staff lock date set to 30 Sep 2026');
        $this->assertSame(1, LockDateChange::count(), 'only its own history');
        $this->assertNull($this->tenant->fresh()->staff_lock_date);
    }

    // ── Accounting periods ─────────────────────────────────────

    public function test_reopening_a_period_needs_a_reason_and_both_close_and_reopen_are_logged(): void
    {
        $period = AccountingPeriod::create(['tenant_id' => $this->tenant->id, 'name' => 'September 2026', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'status' => 'open', 'fiscal_year' => 2026]);

        $this->post(route('accounting-periods.close', $period), ['confirm' => 1, 'closing_notes' => 'Month done'])->assertSessionHasNoErrors();
        $this->post(route('accounting-periods.reopen', $period))->assertSessionHasErrors('reason');
        $this->assertTrue($period->fresh()->isClosed());

        $this->post(route('accounting-periods.reopen', $period), ['reason' => 'Bank charges were missed'])->assertSessionHasNoErrors();
        $this->assertTrue($period->fresh()->isOpen());

        $this->assertSame(['Period September 2026 closed', 'Period September 2026 reopened'], LockDateChange::orderBy('id')->pluck('description')->all());
        $this->assertSame('Bank charges were missed', LockDateChange::latest('id')->first()->reason);
    }

    public function test_a_period_behind_the_lock_for_everyone_cannot_be_reopened(): void
    {
        $period = AccountingPeriod::create(['tenant_id' => $this->tenant->id, 'name' => 'September 2026', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'status' => 'closed', 'fiscal_year' => 2026]);
        $this->lock('2026-09-30', '2026-09-30');

        $this->post(route('accounting-periods.reopen', $period), ['reason' => 'Bank charges were missed'])->assertSessionHas('error');
        $this->assertTrue($period->fresh()->isClosed());
    }

    public function test_a_closed_period_can_be_closed_for_the_year_end(): void
    {
        // The screen offers the year-end close only on a closed period, and
        // its closing journals were refused as "in a closed period".
        $period = AccountingPeriod::create(['tenant_id' => $this->tenant->id, 'name' => 'FY 2025', 'start_date' => '2025-01-01', 'end_date' => '2025-12-31', 'status' => 'open', 'fiscal_year' => 2025]);
        $this->post(route('journals.store'), [
            'journal_date' => '2025-06-30', 'description' => 'Sale',
            'entries' => [
                ['account_id' => $this->account('1000')->id, 'debit' => 5000, 'credit' => 0],
                ['account_id' => $this->account('4000')->id, 'debit' => 0, 'credit' => 5000],
            ],
        ])->assertSessionHasNoErrors();
        $this->post(route('journals.post', Journal::latest('id')->firstOrFail()))->assertSessionHasNoErrors();
        $this->post(route('accounting-periods.close', $period), ['confirm' => 1])->assertSessionHasNoErrors();

        $this->post(route('accounting-periods.lock', $period), ['confirm' => 1])->assertSessionHas('success');

        $this->assertTrue($period->fresh()->isLocked());
        $this->assertSame(2, Journal::where('journal_type', Journal::TYPE_CLOSING)->count(), 'accounts to Income Summary, then to Retained Earnings');
        $this->assertStringContainsString('locked permanently', LockDateChange::latest('id')->first()->description);
    }

    // ── VAT return reopen ──────────────────────────────────────

    /** August: one sale with ₦1,500 VAT, filed on 5 September. */
    private function filedAugust(): VatReturnFiling
    {
        $this->travelTo('2026-08-15 09:00:00');
        app(SaveInvoice::class)->create($this->tenant->id, [
            'customer_id' => $this->customer->id, 'invoice_date' => '2026-08-10', 'due_date' => '2026-08-10', 'status' => 'unpaid',
            'items' => [['description' => 'Work', 'quantity' => 1, 'unit_price' => 20000, 'tax_rate' => 7.5]],
        ], $this->user->id);
        $this->travelTo('2026-09-05 09:00:00');
        $filing = app(FileVatReturn::class)->handle($this->tenant->id, '2026-08', [], $this->user->id, 'NRS-123');
        $this->travelTo('2026-10-10 09:00:00');

        return $filing;
    }

    private function vatPayable(): float
    {
        return round((float) $this->account(AccountCodeService::resolve($this->tenant->id, 'vat_payable'))->fresh()->current_balance, 2);
    }

    public function test_a_filed_vat_return_can_be_reopened_with_a_reason_and_filed_again(): void
    {
        $this->user->givePermissionTo(Permission::findOrCreate('file vat-returns', 'web'), Permission::findOrCreate('view reports', 'web'));
        $filing = $this->filedAugust();
        $settlement = $filing->settlementJournal;
        $this->assertEqualsWithDelta(1500, $this->vatPayable(), 0.001);

        $this->get(route('reports.vat-return', ['month' => '2026-08']))->assertOk()->assertSee('Reopen this return');
        $this->post(route('reports.vat-return.reopen'), ['month' => '2026-08'])->assertSessionHasErrors('reason');
        $this->assertSame(1, VatReturnFiling::count());

        $this->post(route('reports.vat-return.reopen'), ['month' => '2026-08', 'reason' => 'Customer credit note for August came in late'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame(0, VatReturnFiling::count());
        $this->assertSame('reversed', $settlement->fresh()->status);
        $reversal = Journal::where('reference', 'REV-'.$settlement->journal_number)->sole();
        $this->assertSame('2026-08-31', $reversal->journal_date->toDateString(), 'dated like the settlement');
        $this->assertEqualsWithDelta(0, $this->vatPayable(), 0.001);
        $change = LockDateChange::sole();
        $this->assertSame(LockDates::VAT_RETURN, $change->kind);
        $this->assertSame('Customer credit note for August came in late', $change->reason);
        $this->assertStringContainsString('VAT return for August 2026 reopened (filed 5 Sep 2026, NRS reference NRS-123, VAT payable ₦1,500.00)', $change->description);

        // Filed again: settled again.
        $this->post(route('reports.vat-return.file'), ['month' => '2026-08'])->assertSessionHasNoErrors();
        $this->assertNotSame($settlement->id, VatReturnFiling::sole()->settlement_journal_id);
        $this->assertEqualsWithDelta(1500, $this->vatPayable(), 0.001);
    }

    public function test_a_vat_return_behind_a_lock_date_cannot_be_reopened_except_by_an_admin_behind_the_staff_lock(): void
    {
        $this->user->givePermissionTo(Permission::findOrCreate('file vat-returns', 'web'), Permission::findOrCreate('view reports', 'web'));
        $this->filedAugust();
        $reopen = ['month' => '2026-08', 'reason' => 'Amended return needed'];

        $this->lock('2026-08-31');
        $this->post(route('reports.vat-return.reopen'), $reopen)->assertSessionHasErrors(['month' => 'The books are locked up to 31 Aug 2026. Ask an admin to change the lock date if you need to change this.']);

        $admin = $this->admin();
        $admin->givePermissionTo('file vat-returns', 'view reports');
        $this->actingAs($admin);
        $this->lock('2026-08-31', '2026-08-31');
        $this->post(route('reports.vat-return.reopen'), $reopen)->assertSessionHasErrors('month');
        $this->assertSame(1, VatReturnFiling::count());

        $this->lock('2026-08-31', '2026-07-31', 'Amended August return');
        $this->post(route('reports.vat-return.reopen'), $reopen)->assertSessionHasNoErrors();
        $this->assertSame(0, VatReturnFiling::count());
    }

    public function test_a_vat_return_cannot_be_reopened_while_a_later_month_is_filed_or_by_another_business(): void
    {
        $this->user->givePermissionTo(Permission::findOrCreate('file vat-returns', 'web'), Permission::findOrCreate('view reports', 'web'));
        $this->filedAugust();
        app(FileVatReturn::class)->handle($this->tenant->id, '2026-09', [], $this->user->id);

        $this->post(route('reports.vat-return.reopen'), ['month' => '2026-08', 'reason' => 'Amended return needed'])
            ->assertSessionHasErrors(['month' => 'The return for September 2026 is filed and carried this month\'s figures forward. Reopen it first.']);
        $this->assertSame(2, VatReturnFiling::count());

        auth()->logout();
        [$other] = $this->createTenantWithSubscription();
        $this->actingAs($this->createUserForTenant($other, ['file vat-returns', 'view reports']));
        $this->post(route('reports.vat-return.reopen'), ['month' => '2026-09', 'reason' => 'Not my return at all'])
            ->assertSessionHasErrors(['month' => 'The VAT return for this month hasn\'t been filed.']);
        $this->assertSame(2, VatReturnFiling::withoutGlobalScopes()->count());
    }

    // ── Feature flag ───────────────────────────────────────────

    public function test_switched_off_nothing_is_locked_and_the_card_is_hidden(): void
    {
        $this->lock('2026-09-30', '2026-09-30');
        config(['mybooks.features.lock_dates' => false]);
        app()->forgetScopedInstances();

        $this->post(route('invoices.store'), $this->invoice('2026-09-20'))->assertSessionHasNoErrors();
        $this->get(route('accounting-periods.index'))->assertOk()->assertDontSee('Save lock dates')->assertDontSee('No lock date set');
        $this->get(route('invoices.create'))->assertOk()->assertDontSee('data-lock-date-notice', false);
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Books locked up to');
        $this->actingAs($this->admin());
        $this->put(route('lock-dates.update'), ['staff_lock_date' => '2026-08-31'])->assertNotFound();
    }

    public function test_the_lock_dates_service_answers_for_each_user(): void
    {
        $this->lock('2026-09-30', '2026-06-30');
        $lock = LockDates::instance();
        $admin = $this->admin();

        $this->assertSame('2026-09-30', $lock->lockedUpTo($this->tenant->id, $this->user)->toDateString());
        $this->assertSame('2026-06-30', $lock->lockedUpTo($this->tenant->id, $admin)->toDateString());
        $this->assertNull($lock->blockReason('2026-07-01', $this->tenant->id, $admin));
        $this->assertNotNull($lock->warning('2026-07-01', $this->tenant->id, $admin));
        $this->assertNull($lock->warning('2026-07-01', $this->tenant->id, $this->user), 'staff are blocked, not warned');
        $this->assertNotNull($lock->blockReason('2026-06-30', $this->tenant->id, $admin));
    }
}
