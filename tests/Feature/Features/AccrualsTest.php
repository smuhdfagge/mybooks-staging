<?php

namespace Tests\Feature\Features;

use App\Actions\Accruals\CreateAccrualSchedule;
use App\Models\AccountingPeriod;
use App\Models\AccrualSchedule;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\Tenant;
use App\Services\Accounting\FinancialStatements;
use Tests\TestCase;

/**
 * Feature 2: journals that reverse themselves, and prepaid expense /
 * deferred revenue schedules released monthly.
 */
class AccrualsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-01-15 09:00:00');
        $this->createAuthenticatedUser(['view journals', 'create journals', 'edit journals', 'post journals']);
    }

    private function account(string $code, ?Tenant $tenant = null): ChartOfAccount
    {
        return ChartOfAccount::withoutGlobalScopes()->where('tenant_id', ($tenant ?? $this->tenant)->id)->where('account_code', $code)->firstOrFail();
    }

    private function balance(string $code, ?Tenant $tenant = null): float
    {
        return round((float) $this->account($code, $tenant)->current_balance, 2);
    }

    private function assertBooksBalance(?Tenant $tenant = null): void
    {
        $tb = app(FinancialStatements::class)->trialBalance(($tenant ?? $this->tenant)->id, '2030-12-31');
        $this->assertEqualsWithDelta($tb->sum('total_debit'), $tb->sum('total_credit'), 0.001);
    }

    /** An accrual: Dr electricity (utilities) 25,000 / Cr accrued expenses, reversed on 1 Feb. */
    private function accrual(string $date = '2026-01-31', string $reverseOn = '2026-02-01'): Journal
    {
        $this->post(route('journals.store'), [
            'journal_date' => $date, 'reverse_on' => $reverseOn, 'description' => 'January electricity not yet billed',
            'entries' => [
                ['account_id' => $this->account('6200')->id, 'debit' => 25000, 'credit' => 0],
                ['account_id' => $this->account('2200')->id, 'debit' => 0, 'credit' => 25000],
            ],
        ])->assertSessionHasNoErrors();
        $journal = Journal::latest('id')->firstOrFail();
        $this->post(route('journals.post', $journal))->assertSessionHasNoErrors();

        return $journal->fresh();
    }

    public function test_a_journal_with_a_reverse_date_is_reversed_on_that_date_once(): void
    {
        $journal = $this->accrual();
        $this->assertSame('2026-02-01', $journal->reverse_on->toDateString());
        $this->assertSame(25000.0, $this->balance('2200'));

        // Not due yet.
        $this->artisan('journals:post-reversals', ['--date' => '2026-01-31'])->assertSuccessful();
        $this->assertNull($journal->fresh()->auto_reversal_journal_id);

        $this->artisan('journals:post-reversals', ['--date' => '2026-02-01'])->assertSuccessful();
        $journal->refresh();
        $reversal = $journal->autoReversal;
        $this->assertNotNull($reversal);
        $this->assertSame('2026-02-01', $reversal->journal_date->toDateString());
        $this->assertSame(Journal::TYPE_AUTO_REVERSAL, $reversal->journal_type);
        $this->assertSame('posted', $journal->status, 'the accrual itself stays posted');
        $this->assertSame(0.0, $this->balance('2200'));
        $this->assertSame(0.0, $this->balance('6200'));

        // Running again posts nothing more.
        $this->artisan('journals:post-reversals', ['--date' => '2026-02-05'])->assertSuccessful();
        $this->assertSame(1, Journal::where('journal_type', Journal::TYPE_AUTO_REVERSAL)->count());
        $this->assertSame(0.0, $this->balance('2200'));
        $this->assertBooksBalance();

        $this->get(route('journals.show', $journal))->assertOk()->assertSee('Reversed by')->assertSee($reversal->journal_number);
    }

    public function test_a_reversal_due_in_a_closed_period_is_posted_on_the_first_open_date_and_says_so(): void
    {
        $journal = $this->accrual('2026-01-31', '2026-02-01');
        AccountingPeriod::create(['tenant_id' => $this->tenant->id, 'name' => 'February 2026', 'start_date' => '2026-02-01', 'end_date' => '2026-02-28', 'status' => 'closed', 'fiscal_year' => 2026]);

        $this->artisan('journals:post-reversals', ['--date' => '2026-03-02'])->assertSuccessful();

        $reversal = $journal->fresh()->autoReversal;
        $this->assertSame('2026-03-01', $reversal->journal_date->toDateString());
        $this->assertStringContainsString('due 01 Feb 2026', $reversal->description);
        $this->assertStringContainsString('posted on 01 Mar 2026', $reversal->description);
        $this->assertBooksBalance();
    }

    public function test_drafts_are_not_reversed_and_the_reverse_date_must_follow_the_journal_date(): void
    {
        $this->post(route('journals.store'), [
            'journal_date' => '2026-01-31', 'reverse_on' => '2026-01-31', 'description' => 'Wrong',
            'entries' => [
                ['account_id' => $this->account('6200')->id, 'debit' => 100],
                ['account_id' => $this->account('2200')->id, 'credit' => 100],
            ],
        ])->assertSessionHasErrors('reverse_on');

        $this->post(route('journals.store'), [
            'journal_date' => '2026-01-31', 'reverse_on' => '2026-02-01', 'description' => 'Draft only',
            'entries' => [
                ['account_id' => $this->account('6200')->id, 'debit' => 100],
                ['account_id' => $this->account('2200')->id, 'credit' => 100],
            ],
        ])->assertSessionHasNoErrors();

        $this->artisan('journals:post-reversals', ['--date' => '2026-03-01'])->assertSuccessful();
        $this->assertSame(0, Journal::where('journal_type', Journal::TYPE_AUTO_REVERSAL)->count());
    }

    public function test_the_reversal_command_keeps_each_business_to_its_own_books(): void
    {
        $mine = $this->accrual();

        auth()->logout();
        [$other] = $this->createTenantWithSubscription();
        $theirs = Journal::create([
            'tenant_id' => $other->id, 'journal_number' => Journal::generateNumber($other->id), 'journal_date' => '2026-01-31',
            'reverse_on' => '2026-02-01', 'description' => 'Other business accrual', 'status' => 'draft',
        ]);
        $theirs->entries()->create(['account_id' => $this->account('6200', $other)->id, 'debit' => 7000, 'credit' => 0]);
        $theirs->entries()->create(['account_id' => $this->account('2200', $other)->id, 'debit' => 0, 'credit' => 7000]);
        $theirs->post();

        $this->artisan('journals:post-reversals', ['--date' => '2026-02-01'])->assertSuccessful();

        $mineReversal = $mine->fresh()->autoReversal()->withoutGlobalScopes()->first();
        $theirReversal = Journal::withoutGlobalScopes()->find($theirs->fresh()->auto_reversal_journal_id);
        $this->assertSame($this->tenant->id, $mineReversal->tenant_id);
        $this->assertSame($other->id, $theirReversal->tenant_id);
        $this->assertSame([$other->id], $theirReversal->entries()->with('account')->get()->pluck('account.tenant_id')->unique()->values()->all());
        $this->assertSame(0.0, $this->balance('2200', $other));
        $this->assertSame(0.0, $this->balance('2200'));
        $this->assertBooksBalance($other);
    }

    private function schedule(array $overrides = []): AccrualSchedule
    {
        return app(CreateAccrualSchedule::class)->handle($this->tenant->id, array_merge([
            'type' => AccrualSchedule::TYPE_PREPAID, 'description' => 'Shop rent 2026', 'total_amount' => 120000,
            'recorded_date' => '2025-12-20', 'start_date' => '2026-01-01', 'months' => 12,
            'balance_account_id' => $this->account('1400')->id, 'pl_account_id' => $this->account('6100')->id,
            'funding' => 'bank', 'funding_account_id' => $this->account('1100')->id,
        ], $overrides), $this->user->id);
    }

    public function test_a_prepaid_expense_is_released_one_month_at_a_time(): void
    {
        $schedule = $this->schedule();

        // Paid: Dr Prepaid, Cr bank. Nothing due yet on 15 Jan.
        $this->assertSame(120000.0, $this->balance('1400'));
        $this->assertSame(-120000.0, $this->balance('1100'));
        $this->assertSame(0.0, $this->balance('6100'));
        $this->assertSame('SCH-000001', $schedule->schedule_number);

        $this->artisan('accruals:release', ['--date' => '2026-03-31'])->assertSuccessful();
        $schedule->refresh();
        $this->assertSame(3, $schedule->releases()->count());
        $this->assertEquals(30000, (float) $schedule->released_amount);
        $this->assertSame(30000.0, $this->balance('6100'));
        $this->assertSame(90000.0, $this->balance('1400'));
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], $schedule->releases->map(fn ($r) => $r->posted_date->toDateString())->all());

        // Again: nothing more.
        $this->artisan('accruals:release', ['--date' => '2026-03-31'])->assertSuccessful();
        $this->assertSame(3, $schedule->releases()->count());
        $this->assertSame(30000.0, $this->balance('6100'));

        $this->artisan('accruals:release', ['--date' => '2027-06-01'])->assertSuccessful();
        $schedule->refresh();
        $this->assertSame('completed', $schedule->status);
        $this->assertSame(0.0, $this->balance('1400'));
        $this->assertSame(120000.0, $this->balance('6100'));
        $this->assertBooksBalance();
    }

    public function test_monthly_amounts_add_up_exactly_and_income_in_advance_is_earned_monthly(): void
    {
        $schedule = $this->schedule([
            'type' => AccrualSchedule::TYPE_DEFERRED, 'description' => 'Maintenance contract', 'total_amount' => 100000, 'months' => 3,
            'balance_account_id' => $this->account('2380')->id, 'pl_account_id' => $this->account('4100')->id,
        ]);
        $this->assertSame([1 => 33333.33, 2 => 33333.33, 3 => 33333.34], $schedule->monthlyAmounts());
        $this->assertSame(100000.0, $this->balance('2380'));
        $this->assertSame(100000.0, $this->balance('1100'));

        $this->artisan('accruals:release', ['--date' => '2026-12-31'])->assertSuccessful();
        $this->assertSame(0.0, $this->balance('2380'));
        $this->assertSame(100000.0, $this->balance('4100'));
        $this->assertBooksBalance();
    }

    public function test_reclassifying_from_an_expense_and_a_closed_month_moves_to_the_first_open_date(): void
    {
        AccountingPeriod::create(['tenant_id' => $this->tenant->id, 'name' => 'January 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'status' => 'closed', 'fiscal_year' => 2026]);
        $schedule = $this->schedule(['funding' => 'reclassify', 'funding_account_id' => null, 'recorded_date' => '2026-02-01', 'months' => 2, 'total_amount' => 2000]);

        // Moved out of rent expense into prepaid.
        $this->assertSame(-2000.0, $this->balance('6100'));
        $this->assertSame(2000.0, $this->balance('1400'));

        $this->artisan('accruals:release', ['--date' => '2026-02-28'])->assertSuccessful();
        $first = $schedule->releases()->where('sequence', 1)->first();
        $this->assertSame('2026-01-31', $first->due_date->toDateString());
        $this->assertSame('2026-02-01', $first->posted_date->toDateString());
        $this->assertStringContainsString('closed or locked', $first->note);
        $this->assertSame(0.0, $this->balance('6100'));
        $this->assertBooksBalance();
    }

    public function test_stopping_a_schedule_stops_the_releases(): void
    {
        $schedule = $this->schedule();
        $this->post(route('accrual-schedules.cancel', $schedule))->assertSessionHasNoErrors();
        $this->artisan('accruals:release', ['--date' => '2026-12-31'])->assertSuccessful();
        $this->assertSame(0, $schedule->releases()->count());
        $this->assertSame('cancelled', $schedule->fresh()->status);
    }

    public function test_schedule_screens_permissions_and_other_businesses(): void
    {
        $this->get(route('accrual-schedules.create', ['type' => 'prepaid_expense']))->assertOk()->assertSee('Paid in advance');
        $this->post(route('accrual-schedules.store'), [
            'type' => 'prepaid_expense', 'description' => 'Generator insurance', 'total_amount' => 24000,
            'recorded_date' => '2026-01-10', 'start_date' => '2026-01-01', 'months' => 12,
            'balance_account_id' => $this->account('1400')->id, 'pl_account_id' => $this->account('6400')->id,
            'funding' => 'bank', 'funding_account_id' => $this->account('1000')->id,
        ])->assertSessionHasNoErrors();
        $schedule = AccrualSchedule::firstOrFail();

        $this->get(route('accrual-schedules.index'))->assertOk()->assertSee('Generator insurance');
        $this->get(route('accrual-schedules.show', $schedule))->assertOk()->assertSee('Month by month')->assertSee('To come');

        // The wrong kind of account is refused in plain words.
        $this->post(route('accrual-schedules.store'), [
            'type' => 'prepaid_expense', 'description' => 'Bad', 'total_amount' => 100, 'recorded_date' => '2026-01-10',
            'start_date' => '2026-01-01', 'months' => 2, 'balance_account_id' => $this->account('6400')->id,
            'pl_account_id' => $this->account('6400')->id, 'funding' => 'existing',
        ])->assertSessionHasErrors(['balance_account_id' => 'Choose an asset account, such as Prepaid Expenses.']);

        $viewer = $this->createUserForTenant($this->tenant, ['view journals']);
        $this->actingAs($viewer);
        $this->get(route('accrual-schedules.show', $schedule))->assertOk();
        $this->get(route('accrual-schedules.create'))->assertForbidden();

        auth()->logout();
        [$other] = $this->createTenantWithSubscription();
        $this->actingAs($this->createUserForTenant($other, ['view journals', 'create journals']));
        $this->get(route('accrual-schedules.show', $schedule))->assertNotFound();
        $this->post(route('accrual-schedules.cancel', $schedule))->assertNotFound();
    }
}
