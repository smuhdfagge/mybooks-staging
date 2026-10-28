<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Actions\Bills\SaveBill;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Vendor;
use Tests\TestCase;

/**
 * The dry run for MB-14's history: bills posted before lines went to
 * their own accounts still show their costs under Miscellaneous Expense.
 * bills:reclassify-lines --dry-run lists what would move, and changes
 * nothing.
 */
class BillLineReclassReportTest extends TestCase
{
    public function test_it_lists_bills_posted_to_miscellaneous_and_changes_nothing(): void
    {
        $this->travelTo('2026-10-10 09:00:00');
        $this->createAuthenticatedUser(['view bills', 'create bills']);
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $rent = ChartOfAccount::where('account_code', '6100')->firstOrFail();
        $misc = ChartOfAccount::where('account_code', '6990')->firstOrFail();

        $old = app(SaveBill::class)->create($this->tenant->id, [
            'vendor_id' => $vendor->id, 'bill_date' => '2026-09-05', 'due_date' => '2026-10-05',
            'items' => [['description' => 'September rent', 'quantity' => 1, 'unit_price' => 50000, 'tax_rate' => 0, 'account_id' => $rent->id]],
        ], $this->user->id);
        // As it was posted before MB-14: the rent line went to Miscellaneous Expense.
        $journal = Journal::where('reference_type', Bill::class)->where('reference_id', $old->id)->sole();
        JournalEntry::withoutEvents(fn () => $journal->entries()->where('account_id', $rent->id)->update(['account_id' => $misc->id]));

        // A bill posted correctly is not listed.
        app(SaveBill::class)->create($this->tenant->id, [
            'vendor_id' => $vendor->id, 'bill_date' => '2026-10-05', 'due_date' => '2026-11-05',
            'items' => [['description' => 'October rent', 'quantity' => 1, 'unit_price' => 50000, 'tax_rate' => 0, 'account_id' => $rent->id]],
        ], $this->user->id);

        $before = JournalEntry::orderBy('id')->get(['id', 'account_id', 'debit', 'credit'])->toArray();
        auth()->forgetGuards();

        $this->artisan('bills:reclassify-lines')->assertFailed();
        $this->artisan('bills:reclassify-lines', ['--dry-run' => true, '--tenant' => $this->tenant->id])
            ->expectsOutputToContain('1 bill(s) to reclassify')
            ->expectsOutputToContain($old->bill_number)
            ->expectsOutputToContain('-50,000.00')
            ->expectsOutputToContain('Dry run: nothing was changed.')
            ->assertSuccessful();

        $this->assertSame($before, JournalEntry::orderBy('id')->get(['id', 'account_id', 'debit', 'credit'])->toArray());
    }
}
