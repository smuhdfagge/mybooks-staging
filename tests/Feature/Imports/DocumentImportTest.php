<?php

namespace Tests\Feature\Imports;

use App\Enums\BillStatus;
use App\Enums\InvoiceStatus;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Import;
use App\Models\Inventory;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Journal;
use App\Models\Vendor;
use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Importing invoices, bills and journal entries.
 *
 * These three were on the import menu with templates to download and no
 * reader behind them, so they were withdrawn and are now built. They take
 * several rows to a document, one per line, so most of what can go wrong
 * is about holding the rows of one document together and refusing a
 * document whole rather than half of it.
 *
 * Invoices and bills go through the same actions the forms use, so an
 * imported document is a document: VAT worked out the same way, stock
 * moved, the journal posted.
 */
class DocumentImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, string>  $mapping
     * @param  array<string, mixed>  $options
     */
    private function runImport(string $type, string $csv, array $mapping = [], array $options = []): Import
    {
        Storage::fake('imports');
        Storage::disk('imports')->put('imports/test.csv', $csv);

        $import = Import::create([
            'tenant_id' => $this->user->tenant_id,
            'user_id' => $this->user->id,
            'type' => $type,
            'format' => Import::FORMAT_CSV,
            'status' => Import::STATUS_PENDING,
            'original_filename' => 'test.csv',
            'file_path' => 'imports/test.csv',
            'column_mapping' => $mapping,
            'options' => $options,
        ]);

        app(ImportService::class)->processImport($import);

        return $import->fresh();
    }

    private function errorText(Import $import): string
    {
        return implode(' | ', $import->errors ?? []);
    }

    private function account(string $code): ChartOfAccount
    {
        return ChartOfAccount::where('tenant_id', $this->user->tenant_id)
            ->where('account_code', $code)
            ->firstOrFail();
    }

    private function stockedItem(string $name, float $quantity): Item
    {
        $item = Item::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => $name,
            'type' => 'product',
            'sku' => 'SKU-'.strtoupper(substr(md5($name), 0, 5)),
            'sale_price' => 1000,
            'purchase_price' => 800,
            'track_inventory' => true,
        ]);

        Inventory::create([
            'tenant_id' => $this->user->tenant_id,
            'item_id' => $item->id,
            'quantity' => $quantity,
            'reserved_quantity' => 0,
            'unit_cost' => 800,
        ]);

        return $item;
    }

    // ---------------------------------------------------------------
    // Journals
    // ---------------------------------------------------------------

    public function test_the_rows_sharing_a_reference_become_one_journal(): void
    {
        $this->createAuthenticatedUser();

        $import = $this->runImport(Import::TYPE_JOURNALS, <<<'CSV'
        reference,date,description,account_code,debit,credit
        JE-001,05/11/2026,Owner investment,1000,10000000.00,
        JE-001,05/11/2026,Owner investment,3000,,10000000.00
        CSV);

        $this->assertSame(2, $import->successful_rows, $this->errorText($import));
        $this->assertSame(0, $import->failed_rows);

        $journals = Journal::where('tenant_id', $this->user->tenant_id)->get();
        $this->assertCount(1, $journals, 'Two rows with one reference are one journal, not two.');

        $journal = $journals->first();
        $this->assertSame('JE-001', $journal->reference);
        // Read day first, like every other date in the import.
        $this->assertSame('2026-11-05', $journal->journal_date->format('Y-m-d'));
        $this->assertEqualsWithDelta(10000000.0, (float) $journal->total_debit, 0.001);
        $this->assertEqualsWithDelta(10000000.0, (float) $journal->total_credit, 0.001);

        $entries = $journal->entries()->get();
        $this->assertCount(2, $entries);
        $this->assertEqualsWithDelta(10000000.0, (float) $entries->firstWhere('account_id', $this->account('1000')->id)->debit, 0.001);
        $this->assertEqualsWithDelta(10000000.0, (float) $entries->firstWhere('account_id', $this->account('3000')->id)->credit, 0.001);
    }

    public function test_a_journal_arrives_as_a_draft_like_one_typed_on_the_form(): void
    {
        $this->createAuthenticatedUser();

        $this->runImport(Import::TYPE_JOURNALS, <<<'CSV'
        reference,date,account_code,debit,credit
        JE-001,05/11/2026,1000,5000,
        JE-001,05/11/2026,3000,,5000
        CSV);

        $journal = Journal::where('tenant_id', $this->user->tenant_id)->firstOrFail();

        // The journal form leaves a journal unposted, and posting is its own
        // decision. An import should not make that decision quietly.
        $this->assertSame('draft', $journal->status);
        $this->assertFalse((bool) $journal->is_posted);
    }

    public function test_a_file_can_ask_for_the_journal_to_be_posted(): void
    {
        $this->createAuthenticatedUser();

        $import = $this->runImport(Import::TYPE_JOURNALS, <<<'CSV'
        reference,date,status,account_code,debit,credit
        JE-001,05/11/2026,posted,1000,5000,
        JE-001,05/11/2026,posted,3000,,5000
        CSV);

        $journal = Journal::where('tenant_id', $this->user->tenant_id)->firstOrFail();

        $this->assertTrue((bool) $journal->is_posted, $this->errorText($import));
        $this->assertSame('posted', $journal->status);
    }

    public function test_an_unbalanced_journal_is_refused_and_the_rest_of_the_file_still_imports(): void
    {
        $this->createAuthenticatedUser();

        $import = $this->runImport(Import::TYPE_JOURNALS, <<<'CSV'
        reference,date,account_code,debit,credit
        JE-001,05/11/2026,1000,5000,
        JE-001,05/11/2026,3000,,4000
        JE-002,05/11/2026,1000,2000,
        JE-002,05/11/2026,3000,,2000
        CSV);

        // One journal out of balance is that journal's problem. Refusing the
        // whole file would make a person fix one line and import 500 again.
        $references = Journal::where('tenant_id', $this->user->tenant_id)->pluck('reference')->all();
        $this->assertSame(['JE-002'], $references);

        $this->assertSame(2, $import->failed_rows);
        $this->assertSame(2, $import->successful_rows);
        $this->assertStringContainsString('does not balance', $this->errorText($import));
        $this->assertStringContainsString('1,000.00', $this->errorText($import), 'The message should name the difference.');
    }

    public function test_a_line_with_both_a_debit_and_a_credit_is_refused(): void
    {
        $this->createAuthenticatedUser();

        $import = $this->runImport(Import::TYPE_JOURNALS, <<<'CSV'
        reference,date,account_code,debit,credit
        JE-001,05/11/2026,1000,5000,5000
        JE-001,05/11/2026,3000,,5000
        CSV);

        // Both on one line hides which way the entry goes, and the two would
        // net off into a journal that balances while saying something else.
        $this->assertSame(0, Journal::where('tenant_id', $this->user->tenant_id)->count());
        $this->assertStringContainsString('either a debit or a credit', $this->errorText($import));
    }

    public function test_an_amount_that_cannot_be_read_stops_its_journal(): void
    {
        $this->createAuthenticatedUser();

        $import = $this->runImport(Import::TYPE_JOURNALS, <<<'CSV'
        reference,date,account_code,debit,credit
        JE-001,05/11/2026,1000,see attached,
        JE-001,05/11/2026,3000,,5000
        CSV);

        // Read as zero it would drop out of the totals, the journal would
        // still balance, and the missing line would never be noticed.
        $this->assertSame(0, Journal::where('tenant_id', $this->user->tenant_id)->count());
        $this->assertStringContainsString('not an amount I can read', $this->errorText($import));
    }

    public function test_a_journal_naming_an_account_that_does_not_exist_is_refused(): void
    {
        $this->createAuthenticatedUser();

        $import = $this->runImport(Import::TYPE_JOURNALS, <<<'CSV'
        reference,date,account_code,debit,credit
        JE-001,05/11/2026,9999,5000,
        JE-001,05/11/2026,3000,,5000
        CSV);

        $this->assertSame(0, Journal::where('tenant_id', $this->user->tenant_id)->count());
        $this->assertStringContainsString("Account '9999' not found", $this->errorText($import));
    }

    public function test_a_journal_can_name_its_accounts_by_name(): void
    {
        $this->createAuthenticatedUser();

        $import = $this->runImport(Import::TYPE_JOURNALS, <<<'CSV'
        reference,date,account_code,debit,credit
        JE-001,05/11/2026,Cash,5000,
        JE-001,05/11/2026,Owner's Capital,,5000
        CSV);

        $journal = Journal::where('tenant_id', $this->user->tenant_id)->first();
        $this->assertNotNull($journal, $this->errorText($import));
        $this->assertSame(
            [$this->account('1000')->id, $this->account('3000')->id],
            $journal->entries()->orderBy('id')->pluck('account_id')->all()
        );
    }

    // ---------------------------------------------------------------
    // Invoices
    // ---------------------------------------------------------------

    public function test_the_rows_sharing_an_invoice_number_become_one_invoice(): void
    {
        $this->createAuthenticatedUser();
        $this->stockedItem('Bag of Rice 50kg', 100);

        $import = $this->runImport(Import::TYPE_INVOICES, <<<'CSV'
        invoice_number,customer,date,due_date,status,item,description,quantity,unit_price,tax_rate
        INV-001,Danjuma Foods,15/01/2026,14/02/2026,unpaid,Bag of Rice 50kg,Rice order,10,95000.00,7.5
        INV-001,Danjuma Foods,15/01/2026,14/02/2026,unpaid,,Delivery to Bompai,1,25000.00,7.5
        CSV);

        $invoices = Invoice::where('tenant_id', $this->user->tenant_id)->get();
        $this->assertCount(1, $invoices, $this->errorText($import));

        $invoice = $invoices->first();
        $this->assertCount(2, $invoice->items()->get());
        $this->assertSame(2, $import->successful_rows);

        // 950,000 + 25,000 of goods, VAT at 7.5%.
        $this->assertEqualsWithDelta(975000.0, (float) $invoice->subtotal, 0.01);
        $this->assertEqualsWithDelta(73125.0, (float) $invoice->tax_amount, 0.01);
        $this->assertEqualsWithDelta(1048125.0, (float) $invoice->total, 0.01);
        $this->assertSame('2026-01-15', $invoice->invoice_date->format('Y-m-d'));
        $this->assertSame('2026-02-14', $invoice->due_date->format('Y-m-d'));
    }

    public function test_the_invoice_number_in_the_file_is_kept_as_the_reference(): void
    {
        $this->createAuthenticatedUser();

        $this->runImport(Import::TYPE_INVOICES, <<<'CSV'
        invoice_number,customer,date,due_date,item,description,quantity,unit_price,tax_rate
        OLD-SYSTEM-4471,Danjuma Foods,15/01/2026,14/02/2026,,Consulting,1,50000,0
        CSV);

        $invoice = Invoice::where('tenant_id', $this->user->tenant_id)->firstOrFail();

        // MyBooks numbers invoices from the business's own locked sequence,
        // so an imported invoice cannot take a number already spoken for.
        // The old number is kept where it can still be searched for.
        $this->assertSame('OLD-SYSTEM-4471', $invoice->reference);
        $this->assertNotSame('OLD-SYSTEM-4471', $invoice->invoice_number);
        $this->assertNotEmpty($invoice->invoice_number);
    }

    public function test_an_imported_invoice_holds_its_stock_like_one_entered_on_the_form(): void
    {
        $this->createAuthenticatedUser();
        $item = $this->stockedItem('Bag of Rice 50kg', 100);

        $import = $this->runImport(Import::TYPE_INVOICES, <<<'CSV'
        invoice_number,customer,date,due_date,status,item,quantity,unit_price,tax_rate
        INV-001,Danjuma Foods,15/01/2026,14/02/2026,unpaid,Bag of Rice 50kg,10,95000.00,7.5
        CSV);

        $this->assertSame(1, Invoice::where('tenant_id', $this->user->tenant_id)->count(), $this->errorText($import));

        $inventory = Inventory::where('item_id', $item->id)->firstOrFail();
        $this->assertEqualsWithDelta(10.0, (float) $inventory->reserved_quantity, 0.001, 'The goods on the invoice should be held.');
    }

    public function test_an_invoice_for_more_than_there_is_in_stock_is_refused(): void
    {
        $this->createAuthenticatedUser();
        $this->stockedItem('Bag of Rice 50kg', 5);

        $import = $this->runImport(Import::TYPE_INVOICES, <<<'CSV'
        invoice_number,customer,date,due_date,item,quantity,unit_price,tax_rate
        INV-001,Danjuma Foods,15/01/2026,14/02/2026,Bag of Rice 50kg,10,95000.00,7.5
        CSV);

        // The same answer the form gives, rather than stock going negative
        // because the figures arrived in a spreadsheet.
        $this->assertSame(0, Invoice::where('tenant_id', $this->user->tenant_id)->count());
        $this->assertSame(1, $import->failed_rows);
        $this->assertNotEmpty($import->errors);
    }

    public function test_a_line_naming_an_item_that_is_not_on_the_list_is_still_a_line(): void
    {
        $this->createAuthenticatedUser();

        $import = $this->runImport(Import::TYPE_INVOICES, <<<'CSV'
        invoice_number,customer,date,due_date,item,quantity,unit_price,tax_rate
        INV-001,Danjuma Foods,15/01/2026,14/02/2026,Borehole repair,1,180000,7.5
        CSV);

        $invoice = Invoice::where('tenant_id', $this->user->tenant_id)->first();
        $this->assertNotNull($invoice, $this->errorText($import));

        $line = $invoice->items()->firstOrFail();
        // The same as typing a line straight onto the form: it carries no
        // item, so it moves no stock and counts nothing that is not there.
        $this->assertNull($line->item_id);
        $this->assertSame('Borehole repair', $line->description);
        $this->assertSame(0, Item::where('tenant_id', $this->user->tenant_id)->count());
        $this->assertStringContainsString('written out in words', implode(' ', $import->warnings ?? []));
    }

    public function test_a_customer_not_on_the_list_is_added(): void
    {
        $this->createAuthenticatedUser();

        $import = $this->runImport(Import::TYPE_INVOICES, <<<'CSV'
        invoice_number,customer,date,due_date,item,quantity,unit_price,tax_rate
        INV-001,Danjuma Foods,15/01/2026,14/02/2026,Consulting,1,50000,0
        CSV);

        $this->assertSame(1, Customer::where('tenant_id', $this->user->tenant_id)->where('name', 'Danjuma Foods')->count());
        $this->assertStringContainsString('was not on your customer list', implode(' ', $import->warnings ?? []));
    }

    public function test_a_customer_already_on_the_list_is_not_added_twice(): void
    {
        $user = $this->createAuthenticatedUser();
        $existing = Customer::create(['tenant_id' => $user->tenant_id, 'name' => 'Danjuma Foods']);

        $this->runImport(Import::TYPE_INVOICES, <<<'CSV'
        invoice_number,customer,date,due_date,item,quantity,unit_price,tax_rate
        INV-001,danjuma foods,15/01/2026,14/02/2026,Consulting,1,50000,0
        CSV);

        // How a name is capitalised is how a person types, not a new customer.
        $this->assertSame(1, Customer::where('tenant_id', $user->tenant_id)->count());
        $this->assertSame($existing->id, Invoice::where('tenant_id', $user->tenant_id)->firstOrFail()->customer_id);
    }

    public function test_an_invoice_takes_its_status_from_the_file(): void
    {
        $this->createAuthenticatedUser();

        $this->runImport(Import::TYPE_INVOICES, <<<'CSV'
        invoice_number,customer,date,due_date,status,item,quantity,unit_price,tax_rate
        INV-001,Danjuma Foods,15/01/2026,14/02/2026,draft,Consulting,1,50000,0
        INV-002,Danjuma Foods,15/01/2026,14/02/2026,sent,Consulting,1,50000,0
        INV-003,Danjuma Foods,15/01/2026,14/02/2026,,Consulting,1,50000,0
        CSV);

        $statuses = Invoice::where('tenant_id', $this->user->tenant_id)
            ->pluck('status', 'reference')
            ->map(fn ($s) => $s instanceof InvoiceStatus ? $s->value : (string) $s)
            ->all();

        $this->assertSame('draft', $statuses['INV-001'] ?? null);
        $this->assertSame('sent', $statuses['INV-002'] ?? null);
        // Left blank, an invoice starts where the form starts it.
        $this->assertSame('draft', $statuses['INV-003'] ?? null);
    }

    public function test_an_invoice_marked_paid_is_refused_rather_than_quietly_made_unpaid(): void
    {
        $this->createAuthenticatedUser();

        $import = $this->runImport(Import::TYPE_INVOICES, <<<'CSV'
        invoice_number,customer,date,due_date,status,item,quantity,unit_price,tax_rate
        INV-001,Danjuma Foods,15/01/2026,14/02/2026,paid,Consulting,1,50000,0
        CSV);

        // What makes an invoice paid is a payment, with a date and an account
        // behind it. Inventing one would put money through an account nobody
        // chose; importing it unpaid without saying so would hide a debt that
        // is not owed.
        $this->assertSame(0, Invoice::where('tenant_id', $this->user->tenant_id)->count());
        $this->assertStringContainsString('record the payment', $this->errorText($import));
    }

    public function test_an_invoice_with_a_bad_date_is_refused_whole(): void
    {
        $this->createAuthenticatedUser();

        $import = $this->runImport(Import::TYPE_INVOICES, <<<'CSV'
        invoice_number,customer,date,due_date,item,quantity,unit_price,tax_rate
        INV-001,Danjuma Foods,45/45/2026,14/02/2026,Consulting,1,50000,0
        INV-002,Danjuma Foods,16/01/2026,14/02/2026,Consulting,1,50000,0
        CSV);

        $this->assertSame(['INV-002'], Invoice::where('tenant_id', $this->user->tenant_id)->pluck('reference')->all());
        $this->assertStringContainsString('not a date I can read', $this->errorText($import));
    }

    public function test_a_row_with_no_invoice_number_is_named_rather_than_guessed_at(): void
    {
        $this->createAuthenticatedUser();

        $import = $this->runImport(Import::TYPE_INVOICES, <<<'CSV'
        invoice_number,customer,date,due_date,item,quantity,unit_price,tax_rate
        ,Danjuma Foods,15/01/2026,14/02/2026,Consulting,1,50000,0
        CSV);

        // Attaching it to whichever invoice came before would put a line on
        // someone else's invoice.
        $this->assertSame(0, Invoice::where('tenant_id', $this->user->tenant_id)->count());
        $this->assertStringContainsString('Row 2: Invoice Number is required', $this->errorText($import));
    }

    public function test_a_due_date_left_blank_falls_back_to_the_invoice_date(): void
    {
        $this->createAuthenticatedUser();

        $this->runImport(Import::TYPE_INVOICES, <<<'CSV'
        invoice_number,customer,date,due_date,item,quantity,unit_price,tax_rate
        INV-001,Danjuma Foods,15/01/2026,,Consulting,1,50000,0
        CSV);

        $invoice = Invoice::where('tenant_id', $this->user->tenant_id)->firstOrFail();
        $this->assertSame('2026-01-15', $invoice->due_date->format('Y-m-d'), 'Due on receipt, rather than a row that cannot import.');
    }

    // ---------------------------------------------------------------
    // Bills
    // ---------------------------------------------------------------

    public function test_the_rows_sharing_a_bill_number_become_one_bill(): void
    {
        $this->createAuthenticatedUser();
        $this->stockedItem('Bag of Rice 50kg', 0);

        $import = $this->runImport(Import::TYPE_BILLS, <<<'CSV'
        bill_number,vendor,date,due_date,status,item,account,description,quantity,unit_price,tax_rate
        DSL-4471,Dangote Supplies Ltd,10/01/2026,09/02/2026,unpaid,Bag of Rice 50kg,,Rice,50,82000.00,7.5
        DSL-4471,Dangote Supplies Ltd,10/01/2026,09/02/2026,unpaid,,Utilities,Diesel for the generator,1,128500.00,0
        CSV);

        $bills = Bill::where('tenant_id', $this->user->tenant_id)->get();
        $this->assertCount(1, $bills, $this->errorText($import));

        $bill = $bills->first();
        $this->assertCount(2, $bill->items()->get());
        $this->assertSame('DSL-4471', $bill->vendor_bill_number);

        // 4,100,000 of rice with VAT, plus 128,500 of diesel without.
        $this->assertEqualsWithDelta(4228500.0, (float) $bill->subtotal, 0.01);
        $this->assertEqualsWithDelta(307500.0, (float) $bill->tax_amount, 0.01);
        $this->assertEqualsWithDelta(4536000.0, (float) $bill->total, 0.01);
    }

    public function test_a_bill_line_can_name_an_account_instead_of_an_item(): void
    {
        $this->createAuthenticatedUser();

        $import = $this->runImport(Import::TYPE_BILLS, <<<'CSV'
        bill_number,vendor,date,due_date,item,account,description,quantity,unit_price,tax_rate
        ZS-0092,Zenith Stationery,12/01/2026,11/02/2026,,Office Supplies,Reams of paper,1,45000.00,7.5
        CSV);

        $bill = Bill::where('tenant_id', $this->user->tenant_id)->first();
        $this->assertNotNull($bill, $this->errorText($import));

        // A bill for rent or diesel has no item, and the cost has to land
        // somewhere in the accounts.
        $this->assertSame($this->account('6300')->id, $bill->items()->firstOrFail()->account_id);
    }

    public function test_a_bill_line_naming_an_account_that_does_not_exist_is_refused(): void
    {
        $this->createAuthenticatedUser();

        $import = $this->runImport(Import::TYPE_BILLS, <<<'CSV'
        bill_number,vendor,date,due_date,item,account,description,quantity,unit_price,tax_rate
        ZS-0092,Zenith Stationery,12/01/2026,11/02/2026,,Stationery and sundries,Paper,1,45000.00,7.5
        CSV);

        $this->assertSame(0, Bill::where('tenant_id', $this->user->tenant_id)->count());
        $this->assertStringContainsString("account 'Stationery and sundries' not found", $this->errorText($import));
    }

    public function test_an_imported_bill_puts_the_goods_into_stock(): void
    {
        $this->createAuthenticatedUser();
        $item = $this->stockedItem('Bag of Rice 50kg', 0);

        $import = $this->runImport(Import::TYPE_BILLS, <<<'CSV'
        bill_number,vendor,date,due_date,status,item,quantity,unit_price,tax_rate
        DSL-4471,Dangote Supplies Ltd,10/01/2026,09/02/2026,unpaid,Bag of Rice 50kg,50,82000.00,7.5
        CSV);

        $this->assertSame(1, Bill::where('tenant_id', $this->user->tenant_id)->count(), $this->errorText($import));

        $inventory = Inventory::where('item_id', $item->id)->firstOrFail();
        $this->assertEqualsWithDelta(50.0, (float) $inventory->quantity, 0.001, 'The goods bought should be in stock.');
    }

    public function test_a_vendor_not_on_the_list_is_added(): void
    {
        $this->createAuthenticatedUser();

        $this->runImport(Import::TYPE_BILLS, <<<'CSV'
        bill_number,vendor,date,due_date,item,description,quantity,unit_price,tax_rate
        ZS-0092,Zenith Stationery,12/01/2026,11/02/2026,,Paper,1,45000,7.5
        CSV);

        $this->assertSame(1, Vendor::where('tenant_id', $this->user->tenant_id)->where('name', 'Zenith Stationery')->count());
    }

    public function test_a_bill_cannot_be_imported_as_sent(): void
    {
        $this->createAuthenticatedUser();

        $import = $this->runImport(Import::TYPE_BILLS, <<<'CSV'
        bill_number,vendor,date,due_date,status,item,description,quantity,unit_price,tax_rate
        ZS-0092,Zenith Stationery,12/01/2026,11/02/2026,sent,,Paper,1,45000,7.5
        CSV);

        // A bill is received, not sent, so there is no such state to put one in.
        $this->assertSame(0, Bill::where('tenant_id', $this->user->tenant_id)->count());
        $this->assertStringContainsString('not a status I know', $this->errorText($import));
    }

    public function test_a_bill_left_blank_arrives_unpaid_like_one_entered_on_the_form(): void
    {
        $this->createAuthenticatedUser();

        $this->runImport(Import::TYPE_BILLS, <<<'CSV'
        bill_number,vendor,date,due_date,status,item,description,quantity,unit_price,tax_rate
        ZS-0092,Zenith Stationery,12/01/2026,11/02/2026,,,Paper,1,45000,7.5
        CSV);

        $bill = Bill::where('tenant_id', $this->user->tenant_id)->firstOrFail();
        $status = $bill->status instanceof BillStatus ? $bill->status->value : (string) $bill->status;
        $this->assertSame('unpaid', $status);
    }

    // ---------------------------------------------------------------
    // Shared
    // ---------------------------------------------------------------

    public function test_the_rows_of_one_document_do_not_have_to_sit_together(): void
    {
        $this->createAuthenticatedUser();

        $import = $this->runImport(Import::TYPE_INVOICES, <<<'CSV'
        invoice_number,customer,date,due_date,item,description,quantity,unit_price,tax_rate
        INV-001,Danjuma Foods,15/01/2026,14/02/2026,,First line,1,50000,0
        INV-002,Adaeze Nwosu,16/01/2026,15/02/2026,,Only line,1,30000,0
        INV-001,Danjuma Foods,15/01/2026,14/02/2026,,Second line,1,20000,0
        CSV);

        // Files come out of other software sorted by date, by customer, by
        // whatever. Sorting is not the person's job.
        $invoices = Invoice::where('tenant_id', $this->user->tenant_id)->get()->keyBy('reference');
        $this->assertCount(2, $invoices, $this->errorText($import));
        $this->assertCount(2, $invoices['INV-001']->items()->get());
        $this->assertEqualsWithDelta(70000.0, (float) $invoices['INV-001']->total, 0.01);
    }

    public function test_the_templates_name_the_columns_the_importers_read(): void
    {
        foreach ([Import::TYPE_INVOICES, Import::TYPE_BILLS, Import::TYPE_JOURNALS] as $type) {
            $columns = array_keys(ImportService::getSampleData($type)[0]);
            $this->assertNotEmpty($columns, "{$type} has no template.");

            // The document number is what ties the lines together, so the
            // template has to show it repeated rather than left blank.
            $key = match ($type) {
                Import::TYPE_INVOICES => 'invoice_number',
                Import::TYPE_BILLS => 'bill_number',
                default => 'reference',
            };

            $this->assertContains($key, $columns, "{$type} must show {$key}.");
            $this->assertContains('status', $columns, "{$type} takes its status from a column, so the template must show it.");

            $numbers = array_column(ImportService::getSampleData($type), $key);
            $this->assertGreaterThan(
                count(array_unique($numbers)),
                count($numbers),
                "{$type}: the template must show a document spread over more than one row."
            );
        }
    }
}
