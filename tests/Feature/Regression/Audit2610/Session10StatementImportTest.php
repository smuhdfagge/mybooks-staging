<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Models\Bank;
use App\Models\BankStatementLine;
use App\Models\BankTransaction;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Services\BankLines;
use App\Services\BankStatements\StatementCsv;
use App\Services\BankStatements\StatementImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 10b (MB-08, part 2): reconcile a bank
 * without a feed by importing its statement as CSV. The statement's lines
 * are matched to the lines from the books (exact amount, same direction,
 * within 3 days) and ticked on the reconcile screen.
 */
class Session10StatementImportTest extends TestCase
{
    private const PERMISSIONS = ['view banks', 'reconcile banks', 'view journals', 'create journals', 'post journals'];

    private ChartOfAccount $ledger;

    private Bank $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-20 09:00:00');
        $this->createAuthenticatedUser(self::PERMISSIONS);
        $this->subscription->update(['ends_at' => '2030-12-31']);

        $this->ledger = ChartOfAccount::factory()->asset()->create(['tenant_id' => $this->tenant->id, 'account_code' => '1120', 'name' => 'GTBank current', 'sub_type' => 'bank']);
        $this->bank = Bank::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'GTBank', 'chart_of_account_id' => $this->ledger->id, 'opening_balance' => 1000, 'current_balance' => 1000]);
    }

    private function journal(string $date, float $in, float $out, string $reference): void
    {
        $other = ChartOfAccount::where('account_code', $in > 0 ? '4000' : '6990')->firstOrFail();
        $this->post(route('journals.store'), ['journal_date' => $date, 'description' => 'Manual', 'reference' => $reference, 'entries' => [
            ['account_id' => $this->ledger->id, 'debit' => $in, 'credit' => $out],
            ['account_id' => $other->id, 'debit' => $out, 'credit' => $in],
        ]])->assertSessionHasNoErrors();
        $this->post(route('journals.post', Journal::where('is_posted', false)->latest('id')->firstOrFail()))->assertSessionHasNoErrors();
    }

    private function csv(string $body, string $name = 'statement.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $body);
    }

    private function parse(string $body): array
    {
        $path = tempnam(sys_get_temp_dir(), 'stmt');
        file_put_contents($path, $body);

        return app(StatementCsv::class)->parse($path);
    }

    private function statement(): string
    {
        return "Account Name,Kano Hardware Ltd\nAccount Number,0123456789\n\n"
            ."Trans. Date,Value Date,Narration,Reference,Debit,Credit,Balance\n"
            ."05/10/2026,05/10/2026,TRF FROM ADAMU STORES,INV-2001,,\"4,000.00\",\"5,000.00\"\n"
            ."07/10/2026,06/10/2026,SMS ALERT CHARGES,,150.00,,\"4,850.00\"\n"
            ."08/10/2026,08/10/2026,COT,,12.50,,\"4,837.50\"\n"
            .",,Closing balance,,,,\"4,837.50\"\n";
    }

    public function test_a_statement_csv_is_read_day_first_with_separators(): void
    {
        $parsed = $this->parse($this->statement());

        $this->assertSame([], $parsed['problems']);
        $this->assertSame([
            ['date' => '2026-10-05', 'description' => 'TRF FROM ADAMU STORES', 'reference' => 'INV-2001', 'amount' => 4000.0, 'balance' => 5000.0],
            ['date' => '2026-10-07', 'description' => 'SMS ALERT CHARGES', 'reference' => null, 'amount' => -150.0, 'balance' => 4850.0],
            ['date' => '2026-10-08', 'description' => 'COT', 'reference' => null, 'amount' => -12.5, 'balance' => 4837.5],
        ], $parsed['lines']);
    }

    public function test_one_signed_amount_column_and_unreadable_rows(): void
    {
        $parsed = $this->parse("Date;Description;Amount\n31-Oct-2026;Interest;(1.234,00)\n2026-10-30;POS purchase;-2,500.00 \n31/02/2026;Bad date;100\n");

        $this->assertSame([-1234.0, -2500.0], array_column($parsed['lines'], 'amount'), 'semicolons, brackets, a decimal comma');
        $this->assertCount(1, $parsed['problems']);
        $this->assertStringContainsString('31/02/2026', $parsed['problems'][0]);
    }

    public function test_a_file_without_the_columns_is_refused(): void
    {
        $this->expectException(ValidationException::class);
        $this->parse("Name,Phone\nAdamu,0803\n");
    }

    public function test_import_matches_and_ticks_then_reconciles_to_zero(): void
    {
        $this->journal('2026-10-05', 4000, 0, 'INV-2001');
        $this->journal('2026-10-06', 0, 150, 'Bank charges'); // a day before the bank's date

        $this->post(route('banks.statement.preview', $this->bank), ['file' => $this->csv($this->statement())])
            ->assertOk()->assertSee('Import 3 line(s)')->assertSee('SMS ALERT CHARGES');
        $this->assertSame(0, BankStatementLine::count(), 'nothing saved before the import is confirmed');

        $token = session('bank_statement_import.token');
        $this->post(route('banks.statement.store', $this->bank), ['token' => $token])
            ->assertRedirect(route('banks.reconcile', $this->bank))->assertSessionHas('success');
        $this->assertSame(3, BankStatementLine::count());
        $this->assertSame(2, BankStatementLine::whereNotNull('bank_transaction_id')->count());

        // The screen ticks the two matched lines and offers the statement's closing balance.
        $page = $this->get(route('banks.reconcile', $this->bank))->assertOk()
            ->assertSee('Not in your books')->assertSee('COT')
            ->assertSee('data-closing="4837.50"', false)
            ->assertSee('value="2026-10-08"', false);
        $this->assertSame(2, preg_match_all('/name="transaction_ids\[\]"[^>]*\bchecked\b/', $page->getContent()));

        // COT recorded in the books: it is matched on the next visit.
        $this->journal('2026-10-08', 0, 12.5, 'COT Oct');
        $this->get(route('banks.reconcile', $this->bank))->assertOk()->assertDontSee('Not in your books');

        $ids = BankTransaction::where('bank_id', $this->bank->id)->pluck('id')->all();
        $this->post(route('banks.reconcile.process', $this->bank), ['transaction_ids' => $ids, 'statement_balance' => 4837.50, 'statement_date' => '2026-10-08'])
            ->assertSessionHas('success');
        $this->get(route('banks.reconcile', $this->bank))->assertOk()->assertSee('All caught up')->assertDontSee('Your statement');
    }

    public function test_uploading_the_same_statement_twice_adds_nothing(): void
    {
        $import = app(StatementImport::class);
        $lines = $this->parse($this->statement())['lines'];

        $this->assertSame(['added' => 3, 'skipped' => 0], $import->save($this->bank, $lines, $this->user->id));
        $this->assertSame(['added' => 0, 'skipped' => 3], $import->save($this->bank, $lines, $this->user->id));

        // Two identical transfers on one day are both kept.
        $twice = [...$lines, $lines[1]];
        $this->assertSame(['added' => 1, 'skipped' => 3], $import->save($this->bank, $twice, $this->user->id));
        $this->assertSame(4, BankStatementLine::count());
    }

    public function test_matching_needs_the_exact_amount_the_same_direction_and_three_days(): void
    {
        $this->journal('2026-10-01', 0, 150, 'Too early'); // 6 days before
        $this->journal('2026-10-07', 0, 151, 'Off by one');
        $this->journal('2026-10-07', 150, 0, 'Money in, not out');
        $this->journal('2026-10-10', 0, 150, 'Three days after');
        app(BankLines::class)->sync($this->bank);

        $import = app(StatementImport::class);
        $import->save($this->bank, [['date' => '2026-10-07', 'description' => 'SMS ALERT CHARGES', 'reference' => null, 'amount' => -150.0, 'balance' => null]], null);

        $this->assertSame(1, $import->match($this->bank));
        $this->assertSame('Three days after', BankStatementLine::sole()->bankTransaction->reference);
    }

    public function test_an_expired_or_foreign_preview_is_refused_and_other_tenants_are_out_of_reach(): void
    {
        $this->post(route('banks.statement.store', $this->bank), ['token' => 'nope'])->assertSessionHas('error');
        $this->assertSame(0, BankStatementLine::count());

        auth()->forgetGuards(); // seeding a tenant while signed in writes into this one
        [$other] = $this->createTenantWithSubscription();
        $theirs = Bank::factory()->create(['tenant_id' => $other->id]);
        $this->actingAs($this->user);

        $this->post(route('banks.statement.preview', $theirs), ['file' => $this->csv($this->statement())])->assertNotFound();
        $this->delete(route('banks.statement.destroy', $theirs))->assertNotFound();
    }

    public function test_removing_an_import_keeps_what_was_reconciled(): void
    {
        $this->journal('2026-10-05', 4000, 0, 'INV-2001');
        app(BankLines::class)->sync($this->bank);
        $import = app(StatementImport::class);
        $import->save($this->bank, $this->parse($this->statement())['lines'], null);
        $import->match($this->bank);
        $this->post(route('banks.reconcile.process', $this->bank), [
            'transaction_ids' => [BankTransaction::sole()->id], 'statement_balance' => 5000, 'statement_date' => '2026-10-05',
        ])->assertSessionHas('success');

        $this->delete(route('banks.statement.destroy', $this->bank))->assertSessionHas('success');

        $this->assertSame(1, BankStatementLine::count(), 'the reconciled line stays as history');
    }
}
