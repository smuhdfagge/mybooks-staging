<?php

namespace Tests\Feature\Features;

use App\Actions\Bills\SaveBill;
use App\Actions\Invoices\SaveInvoice;
use App\Actions\SalesReceipts\SaveSalesReceipt;
use App\Models\ChartOfAccount;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Item;
use App\Models\TaxRate;
use App\Models\Vendor;
use App\Services\JournalService;
use Carbon\Carbon;

/**
 * A worked September 2026 VAT month for the VAT return tests.
 */
trait VatSeptemberScenario
{
    /**
     * Sales                                    net      VAT
     *   INV1 5 Sep  consulting 7.5%         200,000   15,000
     *               rice (zero-rated item)   50,000        0
     *   INV2 12 Sep land lease (exempt item) 100,000       0
     *   INV3 15 Sep laptop 80,000 7.5% and medical kit 20,000 zero-rated,
     *               10,000 discount shared 8,000/2,000:
     *               laptop                   72,000    5,400
     *               medical kit              18,000        0
     *   CS1  20 Sep water 7.5% (cash sale)   30,000    2,250
     *   INV4 10 Sep 10,000 7.5%, cancelled 28 Sep (nets out)
     *   CN1  25 Sep credit on INV1 consulting -20,000  -1,500
     *   INV5 2 Oct (next month, left out)
     *
     * Purchases
     *   BILL1 8 Sep  stock 7.5%              60,000    4,500
     *                maize seed (zero item)  10,000        0
     *   BILL2 18 Sep office rent (exempt)    40,000        0
     *   EXP1  22 Sep internet                10,000      750
     */
    protected function september(): array
    {
        Carbon::setTestNow('2026-09-28 10:00:00');
        $t = $this->tenant->id;
        $u = $this->user->id;
        $rate = fn ($code) => TaxRate::where('tenant_id', $t)->where('code', $code)->first();
        $item = fn ($name, $code) => Item::factory()->create(['tenant_id' => $t, 'name' => $name, 'type' => 'service', 'track_inventory' => false, 'tax_rate_id' => $rate($code)->id]);

        $customer = Customer::factory()->create(['tenant_id' => $t, 'name' => 'Dangote Stores', 'tax_number' => '12345678-0001']);
        $vendor = Vendor::factory()->create(['tenant_id' => $t, 'name' => 'Kano Agro Supplies', 'tax_number' => '87654321-0001']);
        $rice = $item('Rice', 'VAT-ZERO');
        $land = $item('Land lease', 'VAT-EXEMPT');
        $seed = $item('Maize seed', 'VAT-ZERO');

        $invoices = app(SaveInvoice::class);
        $inv = fn ($date, $items, $extra = []) => $invoices->create($t, $extra + [
            'customer_id' => $customer->id, 'invoice_date' => $date, 'due_date' => $date, 'status' => 'unpaid', 'items' => $items,
        ], $u);

        $inv1 = $inv('2026-09-05', [
            ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 200000, 'tax_rate' => 7.5],
            ['item_id' => $rice->id, 'description' => 'Rice', 'quantity' => 10, 'unit_price' => 5000, 'tax_rate' => 0],
        ]);
        $inv('2026-09-12', [['item_id' => $land->id, 'description' => 'Land lease', 'quantity' => 1, 'unit_price' => 100000, 'tax_rate' => 0]]);
        $inv('2026-09-15', [
            ['description' => 'Laptop', 'quantity' => 1, 'unit_price' => 80000, 'tax_rate' => 7.5],
            ['description' => 'Medical kit', 'quantity' => 1, 'unit_price' => 20000, 'tax_rate' => 0, 'vat_treatment' => 'zero'],
        ], ['discount_type' => 'fixed', 'discount_amount' => 10000]);
        $cancelled = $inv('2026-09-10', [['description' => 'Cancelled job', 'quantity' => 1, 'unit_price' => 10000, 'tax_rate' => 7.5]]);
        $cancelled->update(['status' => 'cancelled']);
        $inv('2026-10-02', [['description' => 'October job', 'quantity' => 1, 'unit_price' => 40000, 'tax_rate' => 7.5]]);

        app(SaveSalesReceipt::class)->create($t, [
            'receipt_date' => '2026-09-20', 'payment_method' => 'cash',
            'items' => [['description' => 'Water', 'quantity' => 100, 'unit_price' => 300, 'tax_rate' => 7.5]],
        ], $u);

        $note = CreditNote::create([
            'tenant_id' => $t, 'customer_id' => $customer->id, 'invoice_id' => $inv1->id,
            'credit_note_number' => CreditNote::generateNumber($t), 'credit_note_date' => '2026-09-25', 'status' => 'draft',
        ]);
        CreditNoteItem::create(['credit_note_id' => $note->id, 'description' => 'Consulting', 'quantity' => 1, 'unit_price' => 20000, 'tax_rate' => 7.5, 'tax_amount' => 1500, 'total' => 21500]);
        $note->update(['subtotal' => 20000, 'tax_amount' => 1500, 'total' => 21500, 'balance' => 21500]);
        $note->open();

        $bills = app(SaveBill::class);
        $bills->create($t, ['vendor_id' => $vendor->id, 'bill_date' => '2026-09-08', 'due_date' => '2026-10-08', 'reference' => 'KAS-881', 'items' => [
            ['description' => 'Stock', 'quantity' => 1, 'unit_price' => 60000, 'tax_rate' => 7.5],
            ['item_id' => $seed->id, 'description' => 'Maize seed', 'quantity' => 1, 'unit_price' => 10000, 'tax_rate' => 0],
        ]], $u);
        $bills->create($t, ['vendor_id' => $vendor->id, 'bill_date' => '2026-09-18', 'due_date' => '2026-10-18', 'items' => [
            ['description' => 'Office rent', 'quantity' => 1, 'unit_price' => 40000, 'tax_rate' => 0, 'vat_treatment' => 'exempt'],
        ]], $u);

        $expense = Expense::withoutEvents(fn () => Expense::factory()->create([
            'tenant_id' => $t, 'name' => 'Internet', 'expense_date' => '2026-09-22', 'amount' => 10000, 'tax_amount' => 750, 'total' => 10750,
            'status' => Expense::STATUS_PAID,
            'expense_account_id' => ChartOfAccount::where('tenant_id', $t)->where('type', 'expense')->value('id'),
        ]));
        app(JournalService::class)->createExpenseJournal($expense);

        return compact('customer', 'vendor', 'inv1', 'cancelled');
    }
}
