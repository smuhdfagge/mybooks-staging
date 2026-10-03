<?php

namespace Tests\Feature\Features;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Journals\JournalsTable;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Tenant;
use App\Services\Accounting\FinancialStatements;
use App\Services\JournalService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Session 8 (S8): auto-reversing journals (accruals). A manual journal
 * with a "reverse on" date gets its mirror-image journal posted on that
 * date by journals:post-reversals, or straight away when it is posted
 * after that date.
 */
class AutoReversingJournalsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // December: the accrual is entered before its reverse-on date.
        $this->travelTo('2025-12-20 09:00:00');
        $this->createAuthenticatedUser(['view journals', 'create journals', 'edit journals', 'post journals', 'delete journals']);
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

    /** @return array<string, mixed> Accrue December electricity: Dr Utilities 25,000 / Cr Accrued Expenses. */
    private function payload(string $date = '2025-12-31', ?string $reverseOn = '2026-01-01'): array
    {
        return [
            'journal_date' => $date, 'reverse_on' => $reverseOn, 'description' => 'December electricity not yet billed',
            'entries' => [
                ['account_id' => $this->account('6200')->id, 'debit' => 25000, 'credit' => 0, 'description' => 'NEPA bill estimate'],
                ['account_id' => $this->account('2200')->id, 'debit' => 0, 'credit' => 25000],
            ],
        ];
    }

    private function accrual(string $date = '2025-12-31', string $reverseOn = '2026-01-01', bool $post = true): Journal
    {
        $this->post(route('journals.store'), $this->payload($date, $reverseOn))->assertSessionHasNoErrors();
        $journal = Journal::latest('id')->firstOrFail();
        if ($post) {
            $this->post(route('journals.post', $journal))->assertSessionHasNoErrors();
        }

        return $journal->fresh();
    }

    /** Run the daily command as the server does: nobody signed in. */
    private function runReversals(string $date, int $expectedExit = 0): void
    {
        $user = auth()->user();
        auth()->forgetGuards();
        $this->artisan('journals:post-reversals', ['--date' => $date])->assertExitCode($expectedExit);
        if ($user) {
            $this->actingAs($user);
        }
    }

    public function test_the_command_posts_the_mirror_image_journal_on_the_reverse_date_once(): void
    {
        $journal = $this->accrual();
        $this->assertSame('2026-01-01', $journal->reverse_on->toDateString());
        $this->assertSame(25000.0, $this->balance('2200'));
        $this->assertSame(25000.0, $this->balance('6200'));

        // Not due yet.
        $this->runReversals('2025-12-31');
        $this->assertNull($journal->fresh()->auto_reversal_journal_id);

        $this->runReversals('2026-01-01');
        $journal->refresh();
        $reversal = $journal->autoReversal;
        $this->assertNotNull($reversal);
        $this->assertSame('2026-01-01', $reversal->journal_date->toDateString());
        $this->assertSame(Journal::TYPE_AUTO_REVERSAL, $reversal->journal_type);
        $this->assertSame("Automatic reversal of {$journal->journal_number}: December electricity not yet billed", $reversal->description);
        $this->assertSame(Journal::class, $reversal->reference_type);
        $this->assertSame($journal->id, $reversal->reference_id);
        $this->assertTrue($reversal->is_posted);
        $this->assertSame('posted', $journal->status, 'the accrual itself stays posted');

        // Same accounts, debits and credits swapped.
        $lines = $reversal->entries->mapWithKeys(fn ($e) => [$e->account->account_code => [(float) $e->debit, (float) $e->credit]])->all();
        ksort($lines);
        $this->assertSame(['2200' => [25000.0, 0.0], '6200' => [0.0, 25000.0]], $lines);
        $this->assertEquals(25000, (float) $reversal->total_debit);
        $this->assertEquals(25000, (float) $reversal->total_credit);

        $this->assertSame(0.0, $this->balance('2200'));
        $this->assertSame(0.0, $this->balance('6200'));
        $this->assertBooksBalance();

        // Running again posts nothing more.
        $this->runReversals('2026-01-05');
        $this->assertSame(1, Journal::where('journal_type', Journal::TYPE_AUTO_REVERSAL)->count());
        $this->assertSame(0.0, $this->balance('2200'));
        // Calling the service directly again does nothing either.
        $this->assertNull(app(JournalService::class)->postAutoReversal($journal, now()->setDate(2026, 1, 9)));
        $this->assertSame(1, Journal::where('journal_type', Journal::TYPE_AUTO_REVERSAL)->count());

        $this->get(route('journals.show', $journal))->assertOk()
            ->assertSee('Reversed by')->assertSee($reversal->journal_number)->assertSee(route('journals.show', $reversal));
        $this->get(route('journals.show', $reversal))->assertOk()
            ->assertSee('Automatic reversal of')->assertSee(route('journals.show', $journal))
            ->assertSee("can't be edited, deleted or voided on its own", false);
    }

    public function test_the_show_page_and_list_say_when_a_pending_reversal_is_due(): void
    {
        $journal = $this->accrual();

        $this->get(route('journals.show', $journal))->assertOk()->assertSee('Reverses on 1 Jan 2026');
        Livewire::test(JournalsTable::class)->assertSee('Reverses 1 Jan');
    }

    public function test_the_reverse_date_must_be_after_the_journal_date_on_web_and_api(): void
    {
        foreach (['2025-12-31', '2025-12-30'] as $bad) {
            $this->post(route('journals.store'), $this->payload('2025-12-31', $bad))->assertSessionHasErrors('reverse_on');
            $this->postJson('/api/v1/journals', $this->payload('2025-12-31', $bad))->assertStatus(422)->assertJsonValidationErrors('reverse_on');
        }
        $this->assertSame(0, Journal::count());

        // On an update the stored date counts when only the other one changes.
        $journal = $this->accrual(post: false);
        $this->putJson("/api/v1/journals/{$journal->id}", ['journal_date' => '2026-01-02'])
            ->assertStatus(422)->assertJsonValidationErrors('reverse_on');
        $this->putJson("/api/v1/journals/{$journal->id}", ['reverse_on' => '2025-12-01'])
            ->assertStatus(422)->assertJsonValidationErrors('reverse_on');
    }

    public function test_the_api_takes_and_shows_the_reverse_date(): void
    {
        $response = $this->postJson('/api/v1/journals', $this->payload())->assertCreated();
        $this->assertSame('2026-01-01', $response->json('data.reverse_on'));
        $id = $response->json('data.id');

        // A draft's reverse date can be changed or cleared.
        $this->putJson("/api/v1/journals/{$id}", ['reverse_on' => '2026-01-02'])->assertOk()->assertJsonPath('data.reverse_on', '2026-01-02');
        $this->putJson("/api/v1/journals/{$id}", ['reverse_on' => null])->assertOk()->assertJsonPath('data.reverse_on', null);
        $this->putJson("/api/v1/journals/{$id}", ['reverse_on' => '2026-01-01'])->assertOk();

        $this->postJson("/api/v1/journals/{$id}/post")->assertOk()->assertJsonPath('data.auto_reversal_journal_id', null);
        $this->runReversals('2026-01-01');

        $data = $this->getJson("/api/v1/journals/{$id}")->assertOk()->json('data');
        $reversal = Journal::where('journal_type', Journal::TYPE_AUTO_REVERSAL)->firstOrFail();
        $this->assertSame($reversal->id, $data['auto_reversal_journal_id']);
        $this->getJson("/api/v1/journals/{$reversal->id}")->assertOk()->assertJsonPath('data.journal_type', Journal::TYPE_AUTO_REVERSAL);
    }

    public function test_a_draft_can_change_its_reverse_date_and_drafts_are_never_reversed(): void
    {
        $journal = $this->accrual(post: false);
        $this->put(route('journals.update', $journal), $this->payload('2025-12-31', '2026-01-03'))->assertSessionHasNoErrors();
        $this->assertSame('2026-01-03', $journal->fresh()->reverse_on->toDateString());

        $this->runReversals('2026-02-01');
        $this->assertSame(0, Journal::where('journal_type', Journal::TYPE_AUTO_REVERSAL)->count());
        $this->get(route('journals.show', $journal))->assertSee('Once the journal is posted');
    }

    public function test_a_reversal_due_in_a_closed_period_goes_on_the_first_open_date_and_says_so(): void
    {
        $journal = $this->accrual();
        AccountingPeriod::create(['tenant_id' => $this->tenant->id, 'name' => 'January 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'status' => 'closed', 'fiscal_year' => 2026]);
        AccountingPeriod::create(['tenant_id' => $this->tenant->id, 'name' => 'February 2026', 'start_date' => '2026-02-01', 'end_date' => '2026-02-28', 'status' => 'locked', 'fiscal_year' => 2026]);

        // The first open date (1 Mar) hasn't come yet: wait.
        $this->runReversals('2026-02-15');
        $this->assertNull($journal->fresh()->auto_reversal_journal_id);

        $this->runReversals('2026-03-02');
        $reversal = $journal->fresh()->autoReversal;
        $this->assertSame('2026-03-01', $reversal->journal_date->toDateString());
        $this->assertStringContainsString('due 1 Jan 2026, but that period is closed, so posted on 1 Mar 2026', $reversal->description);
        $this->assertSame(0.0, $this->balance('2200'));
        $this->assertBooksBalance();
    }

    public function test_a_back_dated_accrual_is_reversed_as_soon_as_it_is_posted(): void
    {
        $this->travelTo('2026-01-10 09:00:00');
        $journal = $this->accrual(post: false);

        $this->post(route('journals.post', $journal))->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'reversed by'));

        $journal->refresh();
        $this->assertNotNull($journal->auto_reversal_journal_id);
        $this->assertSame('2026-01-01', $journal->autoReversal->journal_date->toDateString());
        $this->assertSame(0.0, $this->balance('2200'));
        $this->assertBooksBalance();

        // The daily run then has nothing to do.
        $this->runReversals('2026-01-10');
        $this->assertSame(1, Journal::where('journal_type', Journal::TYPE_AUTO_REVERSAL)->count());
    }

    public function test_back_dated_posting_through_the_api_and_bulk_post_also_reverses(): void
    {
        $this->travelTo('2026-01-10 09:00:00');
        $id = $this->postJson('/api/v1/journals', $this->payload())->assertCreated()->json('data.id');
        $this->postJson("/api/v1/journals/{$id}/post")->assertOk();
        $this->assertNotNull(Journal::find($id)->auto_reversal_journal_id);

        $other = $this->accrual(post: false);
        Livewire::test(JournalsTable::class)
            ->set('selectedItems', [(string) $other->id])->set('bulkAction', 'post')->call('applyBulkAction');
        $this->assertNotNull($other->fresh()->auto_reversal_journal_id);
        $this->assertSame(2, Journal::where('journal_type', Journal::TYPE_AUTO_REVERSAL)->count());
        $this->assertSame(0.0, $this->balance('2200'));
        $this->assertBooksBalance();
    }

    public function test_voiding_before_the_reverse_date_cancels_the_automatic_reversal(): void
    {
        $journal = $this->accrual();

        Livewire::test(JournalsTable::class)
            ->set('selectedItems', [(string) $journal->id])->set('bulkAction', 'void')->call('applyBulkAction');
        $this->assertSame('reversed', $journal->fresh()->status);

        $this->runReversals('2026-01-01');
        $this->assertSame(0, Journal::where('journal_type', Journal::TYPE_AUTO_REVERSAL)->count());
        $this->assertSame(0.0, $this->balance('2200'), 'reversed once, by the void');
        $this->assertBooksBalance();
        $this->get(route('journals.show', $journal))->assertSee('Cancelled: this journal was reversed by hand');

        // The same through the API.
        $second = $this->accrual();
        $this->postJson("/api/v1/journals/{$second->id}/reverse")->assertOk();
        $this->runReversals('2026-01-01');
        $this->assertSame(0, Journal::where('journal_type', Journal::TYPE_AUTO_REVERSAL)->count());
        $this->assertSame(0.0, $this->balance('2200'));
    }

    public function test_once_reversed_neither_journal_can_be_changed_voided_or_reversed_again(): void
    {
        $journal = $this->accrual();
        $this->runReversals('2026-01-01');
        $reversal = $journal->fresh()->autoReversal;

        // The original: not voided again (the accrual would be undone twice).
        Livewire::test(JournalsTable::class)
            ->set('selectedItems', [(string) $journal->id, (string) $reversal->id])->set('bulkAction', 'void')->call('applyBulkAction')
            ->assertSet('errorMessage', fn ($m) => str_contains($m, $journal->journal_number) && str_contains($m, $reversal->journal_number));
        $this->postJson("/api/v1/journals/{$journal->id}/reverse")->assertStatus(422)->assertJsonFragment(['message' => "{$journal->journal_number} was already reversed automatically by {$reversal->journal_number}, so there is nothing left to reverse."]);
        $this->expectsReversalBlocked(fn () => app(JournalService::class)->reverseJournal($journal->fresh()));

        // The reversal itself: no edit, delete or reverse.
        $this->postJson("/api/v1/journals/{$reversal->id}/reverse")->assertStatus(422);
        $this->putJson("/api/v1/journals/{$reversal->id}", ['description' => 'x'])->assertStatus(422);
        $this->deleteJson("/api/v1/journals/{$reversal->id}")->assertStatus(422);
        $this->get(route('journals.edit', $reversal))->assertRedirect()->assertSessionHas('error');
        $this->delete(route('journals.destroy', $reversal))->assertSessionHas('error');
        $this->assertNotNull($reversal->fresh());

        $this->assertSame(2, Journal::where('status', 'posted')->count());
        $this->assertSame(0.0, $this->balance('2200'));
        $this->assertBooksBalance();
    }

    private function expectsReversalBlocked(callable $fn): void
    {
        try {
            $fn();
            $this->fail('Expected the reversal to be refused.');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('nothing left to reverse', $e->getMessage());
        }
    }

    public function test_the_command_handles_every_business_and_keeps_their_books_apart(): void
    {
        $mine = $this->accrual();
        $me = $this->user;

        // Signed out, or the new business's accounts would be filed under ours.
        auth()->forgetGuards();
        [$other] = $this->createTenantWithSubscription();
        $otherUser = $this->createUserForTenant($other, ['view journals', 'create journals', 'post journals']);
        $this->actingAs($otherUser);
        $this->post(route('journals.store'), [
            'journal_date' => '2025-12-31', 'reverse_on' => '2026-01-01', 'description' => 'Other business accrual',
            'entries' => [
                ['account_id' => $this->account('6200', $other)->id, 'debit' => 7000],
                ['account_id' => $this->account('2200', $other)->id, 'credit' => 7000],
            ],
        ])->assertSessionHasNoErrors();
        $theirs = Journal::latest('id')->firstOrFail();
        $this->post(route('journals.post', $theirs))->assertSessionHasNoErrors();

        // One business can't see the other's journals.
        $this->get(route('journals.show', $mine))->assertNotFound();
        $this->actingAs($me);
        $this->get(route('journals.show', $theirs))->assertNotFound();

        $this->runReversals('2026-01-01');

        $mineReversal = Journal::withoutGlobalScopes()->find($mine->fresh()->auto_reversal_journal_id);
        $theirReversal = Journal::withoutGlobalScopes()->find(Journal::withoutGlobalScopes()->find($theirs->id)->auto_reversal_journal_id);
        $this->assertSame($this->tenant->id, $mineReversal->tenant_id);
        $this->assertSame($other->id, $theirReversal->tenant_id);
        $this->assertSame([$other->id], $theirReversal->entries()->with(['account' => fn ($q) => $q->withoutGlobalScopes()])->get()->pluck('account.tenant_id')->unique()->values()->all());
        $this->assertSame(0.0, $this->balance('2200', $other));
        $this->assertSame(0.0, $this->balance('2200'));
        $this->actingAs($otherUser);
        $this->assertBooksBalance($other);
        $this->actingAs($me);
        $this->assertBooksBalance();

    }

    public function test_one_failing_journal_does_not_stop_the_others_and_is_logged(): void
    {
        $broken = $this->accrual();
        $good = $this->accrual();
        // Damage the first journal's lines so its reversal can't balance.
        JournalEntry::where('journal_id', $broken->id)->where('debit', '>', 0)->update(['debit' => 1]);

        Log::spy();
        $this->runReversals('2026-01-01', 1);

        $this->assertNull($broken->fresh()->auto_reversal_journal_id);
        $this->assertNotNull($good->fresh()->auto_reversal_journal_id);
        Log::shouldHaveReceived('error')->withArgs(fn ($msg, $ctx) => $msg === 'Automatic journal reversal failed' && $ctx['journal_id'] === $broken->id)->once();
    }

    public function test_switched_off_the_field_is_hidden_ignored_and_nothing_is_reversed(): void
    {
        $journal = $this->accrual();
        config(['mybooks.features.auto_reversing_journals' => false]);

        $this->get(route('journals.create'))->assertOk()->assertDontSee('name="reverse_on"', false);
        $this->get(route('journals.edit', $this->accrual(post: false)))->assertOk()->assertDontSee('name="reverse_on"', false);

        $this->post(route('journals.store'), $this->payload('2025-12-31', '2025-01-01'))->assertSessionHasNoErrors();
        $this->assertNull(Journal::latest('id')->first()->reverse_on);
        $this->postJson('/api/v1/journals', $this->payload())->assertCreated()->assertJsonPath('data.reverse_on', null);

        $this->runReversals('2026-01-01');
        $this->assertNull($journal->fresh()->auto_reversal_journal_id);
        $this->get(route('journals.show', $journal))->assertSee('Automatic reversals are switched off');

        config(['mybooks.features.auto_reversing_journals' => true]);
        $this->get(route('journals.create'))->assertOk()->assertSee('name="reverse_on"', false);
        $this->runReversals('2026-01-01');
        $this->assertNotNull($journal->fresh()->auto_reversal_journal_id);
    }

    public function test_the_command_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains((string) $e->command, 'journals:post-reversals'));
        $this->assertCount(1, $events);
        $this->assertSame('20 0 * * *', $events->first()->expression);
    }
}
