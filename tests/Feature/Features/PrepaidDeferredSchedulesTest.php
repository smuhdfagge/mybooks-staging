<?php

namespace Tests\Feature\Features;

use App\Actions\AccrualSchedules\ReleaseAccrualSchedule;
use App\Exceptions\BusinessRuleException;
use App\Models\AccountingPeriod;
use App\Models\AccrualSchedule;
use App\Models\AccrualScheduleRelease;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\Tenant;
use App\Models\Vendor;
use App\Services\Accounting\FinancialStatements;
use App\Services\ChartOfAccountService;
use App\Services\JournalService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Session 9 (S9): prepaid expense and deferred revenue schedules. An
 * amount already in Prepaid Expenses or Deferred Revenue is moved to the
 * expense or income account one month at a time, on each month's last day,
 * by accruals:release (or straight away for months already over).
 */
class PrepaidDeferredSchedulesTest extends TestCase
{
    private const ALL = [
        'view accrual-schedules', 'create accrual-schedules', 'edit accrual-schedules', 'delete accrual-schedules',
        'view journals', 'create journals', 'edit journals', 'post journals', 'delete journals',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-04-10 09:00:00');
        $this->createAuthenticatedUser(self::ALL);
        $this->subscription->update(['ends_at' => '2030-12-31']);
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
        $this->assertGreaterThan(0, $tb->count());
        $this->assertEqualsWithDelta($tb->sum('total_debit'), $tb->sum('total_credit'), 0.001);
    }

    /** The original payment, posted as a manual journal (the schedule doesn't post it). */
    private function fund(string $debit, string $credit, float $amount, string $date = '2026-01-05'): void
    {
        $this->post(route('journals.store'), [
            'journal_date' => $date, 'description' => 'Paid or received in advance',
            'entries' => [
                ['account_id' => $this->account($debit)->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $this->account($credit)->id, 'debit' => 0, 'credit' => $amount],
            ],
        ])->assertSessionHasNoErrors();
        $this->post(route('journals.post', Journal::latest('id')->firstOrFail()))->assertSessionHasNoErrors();
    }

    /** @return array<string, mixed> A year's shop rent, ₦1,200,000, from May 2026. */
    private function rent(array $overrides = []): array
    {
        return $overrides + [
            'type' => AccrualSchedule::TYPE_PREPAID,
            'description' => 'Shop rent',
            'total_amount' => 1200000,
            'start_month' => '2026-05',
            'months' => 12,
            'balance_account_id' => $this->account('1400')->id,
            'pl_account_id' => $this->account('6100')->id,
            'reference' => 'Lease 2026',
        ];
    }

    private function createSchedule(array $data): AccrualSchedule
    {
        $this->post(route('accrual-schedules.store'), $data)->assertSessionHasNoErrors()->assertRedirect();

        return AccrualSchedule::latest('id')->firstOrFail();
    }

    /** Run the daily command as the server does: nobody signed in. */
    private function runRelease(string $date, int $expectedExit = 0): void
    {
        $user = auth()->user();
        auth()->forgetGuards();
        $this->artisan('accruals:release', ['--date' => $date])->assertExitCode($expectedExit);
        if ($user) {
            $this->actingAs($user);
        }
    }

    public function test_the_total_is_split_evenly_in_whole_kobo_and_the_last_month_takes_the_difference(): void
    {
        $split = AccrualSchedule::split(1000000, 12);
        $this->assertCount(12, $split);
        $this->assertSame(83333.33, $split[1]);
        $this->assertSame(83333.33, $split[11]);
        $this->assertSame(83333.37, $split[12]);
        $this->assertSame(100000000, array_sum(array_map(fn ($a) => (int) round($a * 100), $split)));
        $this->assertSame([1 => 33.33, 2 => 33.33, 3 => 33.34], AccrualSchedule::split(100, 3));

        $schedule = $this->createSchedule($this->rent(['total_amount' => 1000000]));
        $this->assertSame([], $schedule->releases()->pluck('id')->all());
        $this->get(route('accrual-schedules.show', $schedule))->assertOk()
            ->assertSee('₦83,333.33')->assertSee('₦83,333.37')->assertSee('Upcoming')
            ->assertSee('May 2026')->assertSee('30 Apr 2027');

        // Too small to give every month something.
        $this->post(route('accrual-schedules.store'), $this->rent(['total_amount' => 0.50, 'months' => 120]))
            ->assertSessionHasErrors('total_amount');
    }

    public function test_a_prepaid_expense_is_released_on_each_month_end_with_balanced_journals_once(): void
    {
        $this->fund('1400', '1000', 1200000);
        $schedule = $this->createSchedule($this->rent());
        $this->assertSame('2026-05-01', $schedule->start_date->toDateString());
        $this->assertSame(AccrualSchedule::STATUS_ACTIVE, $schedule->status);

        $this->runRelease('2026-05-30');
        $this->assertSame(0, $schedule->releases()->count());

        $this->runRelease('2026-05-31');
        $this->runRelease('2026-05-31'); // again: nothing more
        $this->assertSame(1, $schedule->releases()->count());
        $release = $schedule->releases()->first();
        $journal = $release->journal;
        $this->assertSame('2026-05-31', $journal->journal_date->toDateString());
        $this->assertSame(Journal::TYPE_SCHEDULE_RELEASE, $journal->journal_type);
        $this->assertSame(AccrualSchedule::class, $journal->reference_type);
        $this->assertSame($schedule->id, $journal->reference_id);
        $this->assertSame($this->tenant->id, $journal->tenant_id);
        $this->assertTrue((bool) $journal->is_posted);
        $this->assertSame('100000.00', (string) $journal->total_debit);
        $this->assertSame('100000.00', (string) $journal->total_credit);
        $this->assertStringContainsString('Shop rent - month 1 of 12', $journal->description);
        $lines = $journal->entries()->with('account')->get()->keyBy(fn ($e) => $e->account->account_code);
        $this->assertEquals(100000, $lines['6100']->debit);
        $this->assertEquals(100000, $lines['1400']->credit);

        $this->runRelease('2026-07-31');
        $this->assertSame(3, $schedule->releases()->count());
        $this->assertSame(['2026-05-31', '2026-06-30', '2026-07-31'], $schedule->releases()->get()->map(fn ($r) => $r->journal->journal_date->toDateString())->all());
        $this->assertSame(300000.0, $this->balance('6100'));
        $this->assertSame(900000.0, $this->balance('1400'));
        $this->assertSame('300000.00', (string) $schedule->fresh()->released_amount);
        $this->assertBooksBalance();

        // The rest, then nothing more: the schedule is completed.
        $this->runRelease('2027-06-30');
        $schedule->refresh();
        $this->assertSame(AccrualSchedule::STATUS_COMPLETED, $schedule->status);
        $this->assertSame(12, $schedule->releases()->count());
        $this->assertSame(0.0, $this->balance('1400'));
        $this->assertSame(1200000.0, $this->balance('6100'));
        $this->assertSame(12, Journal::where('journal_type', Journal::TYPE_SCHEDULE_RELEASE)->count());
        $this->assertBooksBalance();
    }

    public function test_deferred_revenue_becomes_income_monthly(): void
    {
        $this->assertSame('2440', ChartOfAccount::where('account_code', '2440')->value('account_code'));
        $this->fund('1000', '2440', 600000, '2026-04-02');
        $schedule = $this->createSchedule([
            'type' => AccrualSchedule::TYPE_DEFERRED, 'description' => 'Six months of support for Dangote Stores',
            'total_amount' => 600000, 'start_month' => '2026-04', 'months' => 6,
            'balance_account_id' => $this->account('2440')->id, 'pl_account_id' => $this->account('4100')->id,
        ]);

        $this->runRelease('2026-04-30');
        $journal = $schedule->releases()->first()->journal;
        $lines = $journal->entries()->with('account')->get()->keyBy(fn ($e) => $e->account->account_code);
        $this->assertEquals(100000, $lines['2440']->debit);
        $this->assertEquals(100000, $lines['4100']->credit);
        $this->assertSame(500000.0, $this->balance('2440'));
        $this->assertSame(100000.0, $this->balance('4100'));

        $this->runRelease('2026-09-30');
        $this->assertSame(AccrualSchedule::STATUS_COMPLETED, $schedule->fresh()->status);
        $this->assertSame(0.0, $this->balance('2440'));
        $this->assertSame(600000.0, $this->balance('4100'));
        $this->assertBooksBalance();
    }

    public function test_a_schedule_starting_in_the_past_releases_the_months_already_over_straight_away(): void
    {
        $this->fund('1400', '1000', 1200000);
        $response = $this->post(route('accrual-schedules.store'), $this->rent(['start_month' => '2026-01']));
        $schedule = AccrualSchedule::latest('id')->firstOrFail();
        $response->assertRedirect(route('accrual-schedules.show', $schedule))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '3 month(s) already past were released straight away'));

        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], $schedule->releases()->get()->map(fn ($r) => $r->journal->journal_date->toDateString())->all());
        $this->assertSame(900000.0, $this->balance('1400'));
        $this->assertBooksBalance();

        $this->get(route('accrual-schedules.show', $schedule))->assertOk()
            ->assertSee('Released')->assertSee($schedule->releases()->first()->journal->journal_number)
            ->assertSee('Upcoming');
    }

    public function test_a_month_due_in_a_closed_period_is_posted_on_the_first_open_date_and_says_so(): void
    {
        AccountingPeriod::create(['tenant_id' => $this->tenant->id, 'name' => 'January 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'status' => 'closed', 'fiscal_year' => 2026]);
        AccountingPeriod::create(['tenant_id' => $this->tenant->id, 'name' => 'February 2026', 'start_date' => '2026-02-01', 'end_date' => '2026-02-28', 'status' => 'locked', 'fiscal_year' => 2026]);

        $schedule = $this->createSchedule($this->rent(['start_month' => '2026-01']));
        $releases = $schedule->releases()->get();
        $this->assertSame(['2026-03-01', '2026-03-01', '2026-03-31'], $releases->map(fn ($r) => $r->journal->journal_date->toDateString())->all());
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], $releases->map(fn ($r) => $r->due_date->toDateString())->all());
        $this->assertStringContainsString('due 31 Jan 2026, but that period is closed, so posted on 1 Mar 2026', $releases[0]->journal->description);
        $this->assertSame('Due 31 Jan 2026, but that period is closed, so posted on 1 Mar 2026', $releases[0]->note);
        $this->assertNull($releases[2]->note);
        $this->get(route('accrual-schedules.show', $schedule))->assertSee('but that period is closed, so posted on 1 Mar 2026');
        $this->assertBooksBalance();
    }

    public function test_a_due_month_shows_as_due_and_can_be_released_from_the_screen(): void
    {
        $schedule = $this->createSchedule($this->rent());
        $this->travelTo('2026-06-02 08:00:00');
        $this->get(route('accrual-schedules.show', $schedule))->assertOk()
            ->assertSee('Release due months now')->assertSee('is due and will be released at the next daily run');

        $this->post(route('accrual-schedules.release', $schedule))->assertSessionHas('success', '1 month(s) released.');
        $this->post(route('accrual-schedules.release', $schedule))->assertSessionHas('success', fn ($m) => str_contains($m, 'Nothing is due yet'));
        $this->assertSame(1, $schedule->releases()->count());
        $this->get(route('accrual-schedules.show', $schedule))->assertDontSee('Release due months now');
    }

    public function test_cancelling_stops_the_months_to_come_and_leaves_the_rest_in_the_account(): void
    {
        $this->fund('1400', '1000', 1200000);
        $schedule = $this->createSchedule($this->rent(['start_month' => '2026-01']));

        $this->post(route('accrual-schedules.cancel', $schedule))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '₦900,000.00 stays in Prepaid Expenses'));
        $schedule->refresh();
        $this->assertSame(AccrualSchedule::STATUS_CANCELLED, $schedule->status);
        $this->assertNotNull($schedule->cancelled_at);

        $this->runRelease('2026-12-31');
        $this->post(route('accrual-schedules.release', $schedule));
        $this->assertSame(3, $schedule->releases()->count());
        $this->assertSame(900000.0, $this->balance('1400'));

        $this->get(route('accrual-schedules.show', $schedule))->assertOk()
            ->assertSee('Not released (cancelled)')->assertSee('is left in Prepaid Expenses')
            ->assertDontSee('Release due months now');

        // Cancelled is final.
        $this->post(route('accrual-schedules.cancel', $schedule))->assertSessionHas('error', 'Only an active schedule can be cancelled.');
        $this->expectException(ValidationException::class);
        $schedule->update(['status' => AccrualSchedule::STATUS_ACTIVE]);
    }

    public function test_edit_and_delete_only_while_nothing_is_released(): void
    {
        // Not started: can be changed and deleted.
        $future = $this->createSchedule($this->rent());
        $this->get(route('accrual-schedules.edit', $future))->assertOk()->assertSee('Shop rent');
        $this->put(route('accrual-schedules.update', $future), $this->rent(['months' => 6, 'description' => 'Shop rent, half year']))
            ->assertRedirect(route('accrual-schedules.show', $future));
        $this->assertSame(6, $future->fresh()->months);

        // Moved into the past: the months now over are released on save.
        $this->put(route('accrual-schedules.update', $future), $this->rent(['months' => 6, 'start_month' => '2026-02']))->assertSessionHasNoErrors();
        $this->assertSame(2, $future->releases()->count());

        $started = $this->createSchedule($this->rent(['start_month' => '2026-02']));
        $this->assertSame(2, $started->releases()->count());
        $this->get(route('accrual-schedules.edit', $started))->assertRedirect(route('accrual-schedules.show', $started))->assertSessionHas('error');
        $this->put(route('accrual-schedules.update', $started), $this->rent(['months' => 3]))->assertSessionHas('error');
        $this->assertSame(12, $started->fresh()->months);
        $this->delete(route('accrual-schedules.destroy', $started))->assertSessionHas('error', fn ($m) => str_contains($m, "can't be deleted"));
        $this->assertNotNull($started->fresh());
        $this->get(route('accrual-schedules.show', $started))->assertDontSee('>Edit<', false)->assertDontSee('>Delete<', false);

        $untouched = $this->createSchedule($this->rent());
        $this->delete(route('accrual-schedules.destroy', $untouched))->assertRedirect(route('accrual-schedules.index'));
        $this->assertNull(AccrualSchedule::find($untouched->id));
    }

    public function test_release_journals_cannot_be_edited_deleted_voided_or_reversed_on_their_own(): void
    {
        $schedule = $this->createSchedule($this->rent(['start_month' => '2026-03']));
        $journal = $schedule->releases()->first()->journal;

        $this->get(route('journals.show', $journal))->assertOk()
            ->assertSee($schedule->schedule_number)->assertSee('one month released from a prepaid or deferred revenue schedule');
        $this->get(route('journals.edit', $journal))->assertRedirect();
        $this->delete(route('journals.destroy', $journal))->assertRedirect();
        $this->assertNotNull(Journal::find($journal->id));

        $this->postJson("/api/v1/journals/{$journal->id}/reverse")->assertStatus(422)
            ->assertJsonFragment(['message' => $journal->manualReversalBlockedReason()]);

        try {
            app(JournalService::class)->reverseJournal($journal->fresh());
            $this->fail('A schedule release was reversed on its own.');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('Cancel the schedule instead', $e->getMessage());
        }
        $this->assertSame('posted', $journal->fresh()->status);
        $this->assertSame(1, Journal::where('reference_type', AccrualSchedule::class)->count());
    }

    public function test_each_business_only_sees_and_releases_its_own_schedules(): void
    {
        $mine = $this->createSchedule($this->rent());
        $me = $this->user;
        $myBill = Bill::factory()->create(['tenant_id' => $this->tenant->id, 'vendor_id' => Vendor::factory()->create(['tenant_id' => $this->tenant->id])->id]);

        auth()->forgetGuards();
        [$other] = $this->createTenantWithSubscription();
        $otherUser = $this->createUserForTenant($other, self::ALL);
        $this->actingAs($otherUser);

        foreach (['show', 'edit'] as $page) {
            $this->get(route("accrual-schedules.{$page}", $mine))->assertNotFound();
        }
        $this->post(route('accrual-schedules.release', $mine))->assertNotFound();
        $this->post(route('accrual-schedules.cancel', $mine))->assertNotFound();
        $this->delete(route('accrual-schedules.destroy', $mine))->assertNotFound();
        $this->get(route('accrual-schedules.index'))->assertOk()->assertDontSee($mine->schedule_number);

        // My accounts and my bill can't be used.
        $this->post(route('accrual-schedules.store'), $this->rent())->assertSessionHasErrors(['balance_account_id', 'pl_account_id']);
        $this->post(route('accrual-schedules.store'), $this->rent([
            'balance_account_id' => $this->account('1400', $other)->id, 'pl_account_id' => $this->account('6100', $other)->id, 'source' => "bill:{$myBill->id}",
        ]))->assertSessionHasErrors('source');

        $theirs = $this->createSchedule($this->rent([
            'balance_account_id' => $this->account('1400', $other)->id, 'pl_account_id' => $this->account('6100', $other)->id,
        ]));
        $this->assertSame($other->id, $theirs->tenant_id);

        $this->runRelease('2026-05-31');
        $theirJournal = Journal::withoutGlobalScopes()->find(AccrualScheduleRelease::withoutGlobalScopes()->where('accrual_schedule_id', $theirs->id)->value('journal_id'));
        $this->assertSame($other->id, $theirJournal->tenant_id);
        $this->assertSame([$other->id], $theirJournal->entries()->with(['account' => fn ($q) => $q->withoutGlobalScopes()])->get()->pluck('account.tenant_id')->unique()->values()->all());
        $this->assertSame(-100000.0, $this->balance('1400', $other));
        $this->assertSame(-100000.0, $this->balance('1400'));
        $this->assertBooksBalance($other);

        $this->actingAs($me);
        $this->get(route('accrual-schedules.show', $theirs))->assertNotFound();
        $this->assertBooksBalance();
    }

    public function test_one_failing_schedule_does_not_stop_the_others_and_is_logged(): void
    {
        $broken = $this->createSchedule($this->rent());
        $good = $this->createSchedule($this->rent());
        AccrualScheduleRelease::creating(function (AccrualScheduleRelease $release) use ($broken) {
            if ($release->accrual_schedule_id === $broken->id) {
                throw new \RuntimeException('Disk full');
            }
        });

        Log::spy();
        $this->runRelease('2026-05-31', 1);

        $this->assertSame(0, $broken->releases()->count());
        $this->assertSame(1, $good->releases()->count());
        // The failed month's journal was rolled back with it.
        $this->assertSame(1, Journal::where('journal_type', Journal::TYPE_SCHEDULE_RELEASE)->count());
        $this->assertSame('0.00', (string) $broken->fresh()->released_amount);
        Log::shouldHaveReceived('error')->withArgs(fn ($msg, $ctx) => $msg === 'Prepaid/deferred schedule release failed' && $ctx['schedule_id'] === $broken->id)->once();
    }

    public function test_accounts_and_linked_documents_are_checked(): void
    {
        // Wrong kinds of account.
        $this->post(route('accrual-schedules.store'), $this->rent([
            'balance_account_id' => $this->account('6100')->id, 'pl_account_id' => $this->account('1400')->id,
        ]))->assertSessionHasErrors(['balance_account_id', 'pl_account_id']);
        $this->post(route('accrual-schedules.store'), $this->rent(['balance_account_id' => $this->account('1000')->id]))
            ->assertSessionHasErrors('balance_account_id'); // cash isn't a prepaid account
        $this->post(route('accrual-schedules.store'), $this->rent(['months' => 0]))->assertSessionHasErrors('months');
        $this->post(route('accrual-schedules.store'), $this->rent(['months' => 121]))->assertSessionHasErrors('months');
        $this->post(route('accrual-schedules.store'), $this->rent(['start_month' => 'May']))->assertSessionHasErrors('start_month');
        $this->assertSame(0, AccrualSchedule::count());

        // Linked to the bill that paid the rent.
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id, 'vendor_id' => Vendor::factory()->create(['tenant_id' => $this->tenant->id])->id, 'total' => 1200000]);
        $this->get(route('accrual-schedules.create', ['source' => "bill:{$bill->id}"]))->assertOk()
            ->assertSee($bill->bill_number)->assertSee('1200000');
        $schedule = $this->createSchedule($this->rent(['source' => "bill:{$bill->id}"]));
        $this->assertSame(['bill', $bill->id], [$schedule->source_type, $schedule->source_id]);
        $this->get(route('accrual-schedules.show', $schedule))->assertSee(route('bills.show', $bill))->assertSee('Lease 2026');
        // A bill can't be the source of deferred revenue.
        $this->post(route('accrual-schedules.store'), [
            'type' => AccrualSchedule::TYPE_DEFERRED, 'description' => 'Support', 'total_amount' => 600, 'start_month' => '2026-05', 'months' => 6,
            'pl_account_id' => $this->account('4100')->id, 'source' => "bill:{$bill->id}",
        ])->assertSessionHasErrors('source');
    }

    public function test_the_default_account_is_used_and_added_when_missing(): void
    {
        $this->account('2440')->delete();
        $schedule = $this->createSchedule([
            'type' => AccrualSchedule::TYPE_DEFERRED, 'description' => 'Annual maintenance contract', 'total_amount' => 600000,
            'start_month' => '2026-05', 'months' => 6, 'balance_account_id' => '', 'pl_account_id' => $this->account('4100')->id,
        ]);
        $account = $this->account('2440');
        $this->assertSame('Deferred Revenue', $account->name);
        $this->assertSame('liability', $account->type);
        $this->assertSame($account->id, $schedule->balance_account_id);

        // New businesses get it from the default chart, with no code used twice.
        $codes = array_column(ChartOfAccountService::getDefaultAccounts(), 'account_code');
        $this->assertContains('2440', $codes);
        $this->assertSame(count($codes), count(array_unique($codes)));
    }

    public function test_screens_need_the_permissions(): void
    {
        $schedule = $this->createSchedule($this->rent());

        $viewer = $this->createUserForTenant($this->tenant, ['view accrual-schedules', 'view journals']);
        $this->actingAs($viewer);
        $this->get(route('accrual-schedules.index'))->assertOk()->assertSee('Prepaid &amp; Deferred', false)->assertDontSee('New schedule');
        $this->get(route('accrual-schedules.show', $schedule))->assertOk()->assertDontSee('Cancel schedule')->assertDontSee('>Delete<', false);
        $this->get(route('accrual-schedules.create'))->assertForbidden();
        $this->post(route('accrual-schedules.store'), $this->rent())->assertForbidden();
        $this->get(route('accrual-schedules.edit', $schedule))->assertForbidden();
        $this->post(route('accrual-schedules.release', $schedule))->assertForbidden();
        $this->post(route('accrual-schedules.cancel', $schedule))->assertForbidden();
        $this->delete(route('accrual-schedules.destroy', $schedule))->assertForbidden();

        $nobody = $this->createUserForTenant($this->tenant, ['view journals']);
        $this->actingAs($nobody);
        $this->get(route('accrual-schedules.index'))->assertForbidden();
        $this->get(route('journals.index'))->assertOk()->assertDontSee(route('accrual-schedules.index'));

        $this->actingAs($this->user);
        $this->get(route('accrual-schedules.index'))->assertOk()->assertSee('New schedule')->assertSee($schedule->schedule_number);
        $this->get(route('accrual-schedules.create'))->assertOk()->assertSee('Month by month')->assertSee('name="start_month"', false);
    }

    public function test_switched_off_the_screens_link_and_releases_stop(): void
    {
        $schedule = $this->createSchedule($this->rent());
        config(['mybooks.features.prepaid_schedules' => false]);

        $this->get(route('accrual-schedules.index'))->assertNotFound();
        $this->get(route('accrual-schedules.show', $schedule))->assertNotFound();
        $this->post(route('accrual-schedules.store'), $this->rent())->assertNotFound();
        $this->get(route('journals.index'))->assertOk()->assertDontSee(route('accrual-schedules.index'));

        $this->runRelease('2026-05-31');
        $this->assertSame(0, app(ReleaseAccrualSchedule::class)->handle($schedule, now()->parse('2026-05-31')));
        $this->assertSame(0, $schedule->releases()->count());

        config(['mybooks.features.prepaid_schedules' => true]);
        $this->get(route('journals.index'))->assertSee(route('accrual-schedules.index'));
        $this->runRelease('2026-05-31');
        $this->assertSame(1, $schedule->releases()->count());
    }

    public function test_the_release_command_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains((string) $e->command, 'accruals:release'));
        $this->assertCount(1, $events);
        $this->assertSame('25 0 * * *', $events->first()->expression);
    }
}
