<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Actions\LockDates\UpdateLockDates;
use App\Exceptions\BusinessRuleException;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Services\JournalService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 6: a draft journal could be posted into
 * a closed or locked month (MB-02), and posting or reversing the same
 * journal twice at once applied it twice (MB-16).
 */
class Session6JournalPostingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-10 09:00:00');
        $this->createAuthenticatedUser(['view journals', 'create journals', 'edit journals', 'post journals', 'delete journals']);
        $this->subscription->update(['ends_at' => '2030-12-31']);
    }

    private function account(string $code): ChartOfAccount
    {
        return ChartOfAccount::where('account_code', $code)->firstOrFail();
    }

    private function balance(string $code): string
    {
        return number_format((float) $this->account($code)->fresh()->current_balance, 2, '.', '');
    }

    /** A balanced draft: Dr expense 6100, Cr cash 1000. */
    private function draft(string $date): Journal
    {
        $this->post(route('journals.store'), [
            'journal_date' => $date, 'description' => 'Office repairs',
            'entries' => [
                ['account_id' => $this->account('6100')->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $this->account('1000')->id, 'debit' => 0, 'credit' => 1000],
            ],
        ])->assertSessionHasNoErrors();

        return Journal::latest('id')->firstOrFail();
    }

    public static function closures(): array
    {
        return ['closed period' => ['period'], 'lock date' => ['lock']];
    }

    // MB-02
    #[DataProvider('closures')]
    public function test_a_draft_dated_in_a_closed_month_cannot_be_posted(string $how): void
    {
        $journal = $this->draft('2026-09-15');
        if ($how === 'period') {
            AccountingPeriod::create([
                'tenant_id' => $this->tenant->id, 'name' => 'September 2026', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30',
                'status' => 'closed', 'closed_at' => now(), 'fiscal_year' => 2026,
            ]);
        } else {
            app(UpdateLockDates::class)->handle($this->tenant, ['staff_lock_date' => '2026-09-30', 'all_users_lock_date' => '2026-09-30', 'reason' => 'Filed']);
        }

        $this->post(route('journals.post', $journal))->assertSessionHas('error');

        $this->assertFalse($journal->fresh()->is_posted);
        $this->assertSame('0.00', $this->balance('6100'));

        $this->expectException(BusinessRuleException::class);
        $journal->fresh()->post();
    }

    // MB-16
    public function test_posting_the_same_journal_twice_applies_it_once(): void
    {
        $journal = $this->draft('2026-10-05');
        $stale = Journal::findOrFail($journal->id); // a second request read it before the first posted

        $journal->post();
        try {
            $stale->post();
            $this->fail('The second post should be refused.');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('already posted', $e->getMessage());
        }

        $this->assertSame('1000.00', $this->balance('6100'));
        $this->assertSame('-1000.00', $this->balance('1000'));
    }

    public function test_reversing_the_same_journal_twice_writes_one_reversal(): void
    {
        $journal = $this->draft('2026-10-05');
        $journal->post();
        $stale = Journal::with('entries.account')->findOrFail($journal->id);

        app(JournalService::class)->reverseJournal(Journal::with('entries.account')->findOrFail($journal->id), 'Voided');
        try {
            app(JournalService::class)->reverseJournal($stale, 'Voided');
            $this->fail('The second reversal should be refused.');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('already been reversed', $e->getMessage());
        }

        $this->assertSame(1, Journal::where('reference', "REV-{$journal->journal_number}")->count());
        $this->assertSame('0.00', $this->balance('6100'));
    }

    public function test_an_open_month_draft_still_posts(): void
    {
        $journal = $this->draft('2026-10-05');

        $this->post(route('journals.post', $journal))->assertSessionHas('success');

        $this->assertTrue($journal->fresh()->is_posted);
        $this->assertSame('1000.00', $this->balance('6100'));
    }
}
