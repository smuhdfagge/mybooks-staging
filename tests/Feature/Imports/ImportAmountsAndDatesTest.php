<?php

namespace Tests\Feature\Imports;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Import;
use App\Models\Journal;
use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The import, end to end, against the way a Nigerian spreadsheet is
 * actually written: dates day first and amounts with separators.
 *
 * Before this, such a file imported "successfully" — an expense of
 * ₦1,500,000.00 was recorded as ₦1.00 and dated two years out.
 */
class ImportAmountsAndDatesTest extends TestCase
{
    use RefreshDatabase;

    private function importCsv(string $type, string $csv, array $mapping, array $options = []): Import
    {
        Storage::fake('imports');
        $path = 'imports/test.csv';
        Storage::disk('imports')->put($path, $csv);

        $user = $this->user ?? $this->createAuthenticatedUser();

        $import = Import::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'type' => $type,
            'format' => Import::FORMAT_CSV,
            'status' => Import::STATUS_PENDING,
            'original_filename' => 'test.csv',
            'file_path' => $path,
            'column_mapping' => $mapping,
            'options' => $options,
        ]);

        app(ImportService::class)->processImport($import);

        return $import->fresh();
    }

    public function test_an_expense_keeps_its_amount_and_its_date(): void
    {
        $user = $this->createAuthenticatedUser();
        $account = ChartOfAccount::where('tenant_id', $user->tenant_id)->where('type', 'expense')->firstOrFail();

        $this->importCsv(
            'expenses',
            "date,account,amount,description\n31/12/2026,{$account->account_code},\"1,531,875.00\",Generator fuel\n",
            ['date' => 'date', 'account' => 'account', 'amount' => 'amount', 'description' => 'description'],
        );

        $expense = Expense::where('tenant_id', $user->tenant_id)->first();

        $this->assertNotNull($expense, 'The row did not import.');
        // Was 1.00, dated 2028-07-12.
        $this->assertSame('1531875.00', (string) $expense->amount);
        $this->assertSame('2026-12-31', $expense->expense_date->format('Y-m-d'));
        $this->assertSame($account->id, $expense->expense_account_id);
    }

    public function test_an_amount_that_cannot_be_read_fails_its_row_rather_than_becoming_nothing(): void
    {
        $user = $this->createAuthenticatedUser();
        $account = ChartOfAccount::where('tenant_id', $user->tenant_id)->where('type', 'expense')->firstOrFail();

        $import = $this->importCsv(
            'expenses',
            "date,account,amount,description\n31/12/2026,{$account->account_code},see attached,Generator fuel\n",
            ['date' => 'date', 'account' => 'account', 'amount' => 'amount', 'description' => 'description'],
        );

        $this->assertSame(0, Expense::where('tenant_id', $user->tenant_id)->count());
        $this->assertSame(1, $import->failed_rows);
        $this->assertStringContainsString('not an amount', implode(' ', $import->errors ?? []));
    }

    public function test_opening_balances_with_separators_post_their_real_figures(): void
    {
        $user = $this->createAuthenticatedUser();
        $asset = ChartOfAccount::where('tenant_id', $user->tenant_id)->where('type', 'asset')->firstOrFail();
        $equity = ChartOfAccount::where('tenant_id', $user->tenant_id)->where('type', 'equity')->firstOrFail();

        $this->importCsv(
            'opening_balances',
            "account_code,debit,credit\n{$asset->account_code},\"2,500,000.00\",0\n{$equity->account_code},0,\"2,500,000.00\"\n",
            ['account_code' => 'account_code', 'debit' => 'debit', 'credit' => 'credit'],
        );

        $journal = Journal::where('tenant_id', $user->tenant_id)->first();

        // The dangerous case: read as 2.5 on both sides the file still
        // balanced, so a journal of nonsense posted and looked right.
        $this->assertNotNull($journal, 'No opening balance journal was posted.');
        $this->assertEqualsWithDelta(2500000.0, (float) $journal->entries()->sum('debit'), 0.001);
        $this->assertEqualsWithDelta(2500000.0, (float) $journal->entries()->sum('credit'), 0.001);
    }

    public function test_a_customer_row_without_an_email_does_not_overwrite_a_stranger(): void
    {
        $user = $this->createAuthenticatedUser();
        $other = Customer::factory()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Kano Traders',
            'email' => null,
            'phone' => '08030000000',
        ]);

        $this->importCsv(
            'customers',
            "name,phone\nDanjuma Foods,08031111111\n",
            ['name' => 'name', 'phone' => 'phone'],
            ['update_existing' => true],
        );

        // "email is null OR name = ..." used to match this one and
        // overwrite it with the imported row.
        $other->refresh();
        $this->assertSame('Kano Traders', $other->name);
        $this->assertSame('08030000000', $other->phone);
        $this->assertNotNull(
            Customer::where('tenant_id', $user->tenant_id)->where('name', 'Danjuma Foods')->first(),
            'The new customer was treated as a duplicate.'
        );
    }
}
