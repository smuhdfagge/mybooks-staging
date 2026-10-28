<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Actions\LockDates\UpdateLockDates;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Import;
use App\Models\Invoice;
use App\Models\Journal;
use App\Services\ImportService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 16 (MB-18, MB-19).
 *
 * The Bulk Update page went in session 1 (Session1ContainmentTest). The
 * invoice, bill and journal imports MB-19 found missing were built since
 * (tests/Feature/Imports/DocumentImportTest); this checks them against the
 * plan's acceptance list and the two gaps found doing so:
 * - a file could post journals for someone without "post journals";
 * - "Skip duplicate records" (ticked by default) was ignored, so importing
 *   a file again after fixing what it refused made every other document twice.
 */
class Session16DocumentImportsTest extends TestCase
{
    private const PERMISSIONS = ['view imports', 'import data', 'create journals', 'create invoices', 'create bills'];

    private const JOURNALS = <<<'CSV'
    reference,date,status,account_code,debit,credit
    JE-001,05/10/2026,posted,1000,5000,
    JE-001,05/10/2026,posted,3000,,5000
    JE-002,06/10/2026,,1000,2000,
    JE-002,06/10/2026,,3000,,2000
    CSV;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-20 09:00:00');
    }

    private function runImport(string $type, string $csv, array $options = []): Import
    {
        Storage::fake('imports');
        Storage::disk('imports')->put('imports/test.csv', $csv);

        $import = Import::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'type' => $type,
            'format' => Import::FORMAT_CSV, 'status' => Import::STATUS_PENDING,
            'original_filename' => 'test.csv', 'file_path' => 'imports/test.csv',
            'column_mapping' => [], 'options' => $options,
        ]);
        app(ImportService::class)->processImport($import);

        return $import->fresh();
    }

    private function messages(Import $import): string
    {
        return implode(' | ', array_merge($import->errors ?? [], $import->warnings ?? []));
    }

    public function test_a_file_cant_post_journals_for_someone_who_may_not_post_them(): void
    {
        $this->createAuthenticatedUser(self::PERMISSIONS);

        $import = $this->runImport(Import::TYPE_JOURNALS, self::JOURNALS);

        $journal = Journal::where('reference', 'JE-001')->sole();
        $this->assertFalse((bool) $journal->is_posted, 'posted with only "create journals"');
        $this->assertSame('draft', $journal->status);
        $this->assertSame(4, $import->successful_rows);
        $this->assertStringContainsString("arrived as a draft: you don't have permission to post journals", $this->messages($import));
    }

    public function test_someone_who_may_post_journals_can_import_them_posted(): void
    {
        $this->createAuthenticatedUser([...self::PERMISSIONS, 'post journals']);

        $this->runImport(Import::TYPE_JOURNALS, self::JOURNALS);

        $this->assertTrue((bool) Journal::where('reference', 'JE-001')->sole()->is_posted);
        $this->assertFalse((bool) Journal::where('reference', 'JE-002')->sole()->is_posted, 'no status: a draft');
    }

    public function test_importing_the_journals_again_skips_the_ones_already_in(): void
    {
        $this->createAuthenticatedUser(self::PERMISSIONS);
        // JE-003 is refused (out of balance); the rest go in.
        $first = self::JOURNALS."\nJE-003,07/10/2026,,1000,900,\nJE-003,07/10/2026,,3000,,800";
        $this->assertSame(4, $this->runImport(Import::TYPE_JOURNALS, $first)->successful_rows);

        // Fixed and imported again: only JE-003 is new.
        $fixed = self::JOURNALS."\nJE-003,07/10/2026,,1000,900,\nJE-003,07/10/2026,,3000,,900";
        $again = $this->runImport(Import::TYPE_JOURNALS, $fixed);

        $this->assertSame(2, $again->successful_rows, $this->messages($again));
        $this->assertSame(4, $again->skipped_rows);
        $this->assertSame(0, $again->failed_rows);
        $this->assertSame(1, Journal::where('reference', 'JE-001')->count());
        $this->assertSame(1, Journal::where('reference', 'JE-003')->count());
        $this->assertStringContainsString('Journal JE-001 is already in your books', $this->messages($again));

        // A journal with the same reference but another amount is a different journal.
        $other = "reference,date,account_code,debit,credit\nJE-001,05/10/2026,1000,7000,\nJE-001,05/10/2026,3000,,7000";
        $this->assertSame(2, $this->runImport(Import::TYPE_JOURNALS, $other)->successful_rows);

        // Unticked: imported again on purpose.
        $this->assertSame(6, $this->runImport(Import::TYPE_JOURNALS, $fixed, ['skip_duplicates' => false])->successful_rows);
        $this->assertSame(3, Journal::where('reference', 'JE-001')->count());
    }

    public function test_importing_invoices_and_bills_again_skips_the_ones_already_in(): void
    {
        $this->createAuthenticatedUser(self::PERMISSIONS);
        $invoices = "invoice_number,customer,date,description,quantity,unit_price\n"
            ."INV-1,Dangote Stores,05/10/2026,Consulting,1,10000\nINV-2,Dangote Stores,05/10/2026,Training,1,5000";
        $bills = "bill_number,vendor,date,description,account,quantity,unit_price\n"
            .'B-77,Kano Power,05/10/2026,Diesel,6990,1,8000';

        $this->runImport(Import::TYPE_INVOICES, $invoices);
        $this->runImport(Import::TYPE_BILLS, $bills);
        $invoicesAgain = $this->runImport(Import::TYPE_INVOICES, $invoices);
        $billsAgain = $this->runImport(Import::TYPE_BILLS, $bills);

        $this->assertSame(2, Invoice::count());
        $this->assertSame(1, Bill::count());
        $this->assertSame([0, 2], [$invoicesAgain->successful_rows, $invoicesAgain->skipped_rows]);
        $this->assertSame([0, 1], [$billsAgain->successful_rows, $billsAgain->skipped_rows]);
        $this->assertStringContainsString('Invoice INV-1 for Dangote Stores is already in your books', $this->messages($invoicesAgain));
        $this->assertStringContainsString('Bill B-77 from Kano Power is already in your books', $this->messages($billsAgain));

        // The same number for another customer is another invoice.
        $this->runImport(Import::TYPE_INVOICES, "invoice_number,customer,date,description,quantity,unit_price\nINV-1,Zaria Mills,05/10/2026,Consulting,1,10000");
        $this->assertSame(3, Invoice::count());
    }

    public function test_documents_dated_behind_the_lock_date_are_refused(): void
    {
        $this->createAuthenticatedUser([...self::PERMISSIONS, 'post journals']);
        app(UpdateLockDates::class)->handle($this->tenant, ['staff_lock_date' => '2026-09-30', 'all_users_lock_date' => '2026-09-30', 'reason' => 'Q3 filed']);

        $journals = $this->runImport(Import::TYPE_JOURNALS, "reference,date,status,account_code,debit,credit\n"
            ."JE-9,15/09/2026,posted,1000,100,\nJE-9,15/09/2026,posted,3000,,100\n"
            ."JE-8,15/09/2026,,1000,100,\nJE-8,15/09/2026,,3000,,100");
        $invoices = $this->runImport(Import::TYPE_INVOICES, "invoice_number,customer,date,description,quantity,unit_price\nINV-9,Dangote Stores,15/09/2026,Consulting,1,10000");
        $bills = $this->runImport(Import::TYPE_BILLS, "bill_number,vendor,date,description,account,quantity,unit_price\nB-9,Kano Power,15/09/2026,Diesel,6990,1,8000");

        $this->assertSame(0, Journal::count(), 'posted or draft, nothing lands in a locked month');
        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, Bill::count());
        foreach ([$journals, $invoices, $bills] as $import) {
            $this->assertSame(0, $import->successful_rows);
            $this->assertStringContainsString('locked', $this->messages($import));
        }
    }

    public function test_an_import_only_sees_its_own_business(): void
    {
        $this->createAuthenticatedUser(self::PERMISSIONS);
        $me = $this->user;
        $mine = $this->tenant;

        // Another business: a customer, an account and the same journal.
        auth()->forgetGuards();
        $this->createAuthenticatedUser(self::PERMISSIONS);
        $theirs = $this->tenant;
        ChartOfAccount::create(['tenant_id' => $theirs->id, 'account_code' => '6995', 'name' => 'Generator Hire', 'type' => 'expense', 'is_active' => true]);
        $theirCustomer = Customer::create(['tenant_id' => $theirs->id, 'name' => 'Dangote Stores']);
        $this->runImport(Import::TYPE_JOURNALS, self::JOURNALS);
        $this->runImport(Import::TYPE_INVOICES, "invoice_number,customer,date,description,quantity,unit_price\nINV-1,Dangote Stores,05/10/2026,Consulting,1,10000");

        $this->user = $me;
        $this->tenant = $mine;
        $this->actingAs($me);

        // Their journals and invoice don't count as duplicates here.
        $this->assertSame(4, $this->runImport(Import::TYPE_JOURNALS, self::JOURNALS)->successful_rows);
        $this->assertSame(2, Journal::withoutGlobalScopes()->where('tenant_id', $mine->id)->count());
        $this->assertSame(1, $this->runImport(Import::TYPE_INVOICES, "invoice_number,customer,date,description,quantity,unit_price\nINV-1,Dangote Stores,05/10/2026,Consulting,1,10000")->successful_rows);

        // Their customer isn't used: one of mine is added.
        $invoice = Invoice::withoutGlobalScopes()->where('tenant_id', $mine->id)->sole();
        $this->assertNotSame($theirCustomer->id, $invoice->customer_id);
        $this->assertSame($mine->id, Customer::withoutGlobalScopes()->find($invoice->customer_id)->tenant_id);

        // Nor their account.
        $refused = $this->runImport(Import::TYPE_JOURNALS, "reference,date,account_code,debit,credit\nJE-5,05/10/2026,Generator Hire,100,\nJE-5,05/10/2026,1000,,100");
        $this->assertSame(0, $refused->successful_rows);
        $this->assertStringContainsString("Account 'Generator Hire' not found", $this->messages($refused));
    }

    public function test_the_options_say_what_counts_as_a_duplicate_and_offer_no_update_for_documents(): void
    {
        $this->createAuthenticatedUser(self::PERMISSIONS);
        $this->subscription->update(['ends_at' => '2030-12-31']);
        Storage::fake('imports');
        Storage::disk('imports')->put('imports/j.csv', self::JOURNALS);
        $import = Import::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'type' => Import::TYPE_JOURNALS,
            'format' => Import::FORMAT_CSV, 'status' => Import::STATUS_PENDING,
            'original_filename' => 'j.csv', 'file_path' => 'imports/j.csv',
        ]);

        $this->withoutVite()->get(route('imports.mapping', $import))->assertOk()
            ->assertSee('Skip duplicate records')
            ->assertSee('same reference, date and total is skipped')
            ->assertDontSee('options[update_existing]', false);
    }
}
