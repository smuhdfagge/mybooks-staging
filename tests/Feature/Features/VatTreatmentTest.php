<?php

namespace Tests\Feature\Features;

use App\Actions\Bills\SaveBill;
use App\Actions\Invoices\SaveInvoice;
use App\Models\BillItem;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Customer;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\TaxRate;
use App\Models\Vendor;
use App\Services\Accounting\VatTreatment;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * VAT treatment on tax rates and document lines (VAT return, part 1).
 */
class VatTreatmentTest extends TestCase
{
    private function rate(string $code): TaxRate
    {
        return TaxRate::where('tenant_id', $this->tenant->id)->where('code', $code)->firstOrFail();
    }

    private function item(string $name, ?TaxRate $rate, bool $taxable = true): Item
    {
        return Item::factory()->create([
            'tenant_id' => $this->tenant->id, 'name' => $name, 'type' => 'service', 'track_inventory' => false,
            'tax_rate_id' => $rate?->id, 'is_taxable' => $taxable,
        ]);
    }

    public function test_every_business_starts_with_standard_zero_rated_and_exempt_rates(): void
    {
        [$other] = $this->createTenantWithSubscription();
        $this->createAuthenticatedUser();

        foreach ([$this->tenant->id, $other->id] as $tenantId) {
            $rates = TaxRate::withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('sort_order')->get();
            $this->assertSame(['VAT-STD', 'VAT-ZERO', 'VAT-EXEMPT'], $rates->pluck('code')->all());
            $this->assertSame(['standard', 'zero', 'exempt'], $rates->pluck('vat_treatment')->all());
            $this->assertEqualsWithDelta(7.5, (float) $rates[0]->rate, 0.0001);
            $this->assertFalse((bool) $rates[0]->is_default, 'not forced onto items as a default');
            $this->assertStringContainsString('Nigeria Tax Act 2025', $rates[0]->description);
        }
    }

    public function test_lines_record_their_treatment_when_saved(): void
    {
        $this->createAuthenticatedUser();
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $rice = $this->item('Rice (basic food)', $this->rate('VAT-ZERO'));
        $land = $this->item('Plot lease', $this->rate('VAT-EXEMPT'));
        $misc = $this->item('Untagged item', null, false);

        $invoice = app(SaveInvoice::class)->create($this->tenant->id, [
            'customer_id' => $customer->id, 'invoice_date' => '2026-09-10', 'due_date' => '2026-10-10', 'status' => 'unpaid',
            'items' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 100000, 'tax_rate' => 7.5],
                ['item_id' => $rice->id, 'description' => 'Rice', 'quantity' => 10, 'unit_price' => 5000, 'tax_rate' => 0],
                ['item_id' => $land->id, 'description' => 'Lease', 'quantity' => 1, 'unit_price' => 30000, 'tax_rate' => 0],
                ['item_id' => $misc->id, 'description' => 'Misc', 'quantity' => 1, 'unit_price' => 2000, 'tax_rate' => 0],
                ['description' => 'Export freight', 'quantity' => 1, 'unit_price' => 8000, 'tax_rate' => 0, 'vat_treatment' => 'zero'],
                // VAT was charged, so the line is standard-rated whatever it says.
                ['description' => 'Mislabelled', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 7.5, 'vat_treatment' => 'exempt'],
            ],
        ], $this->user->id);

        $this->assertSame(
            ['standard', 'zero', 'exempt', null, 'zero', 'standard'],
            $invoice->items->sortBy('id')->pluck('vat_treatment')->all(),
        );

        // An item not ticked as taxable is not taken to be exempt: left for the return to flag.
        $this->assertNull($invoice->items->firstWhere('description', 'Misc')->vat_treatment);
    }

    public function test_changing_a_tax_rate_later_does_not_rewrite_saved_lines(): void
    {
        $this->createAuthenticatedUser();
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $seed = $this->item('Maize seed', $this->rate('VAT-ZERO'));

        $bill = app(SaveBill::class)->create($this->tenant->id, [
            'vendor_id' => $vendor->id, 'bill_date' => '2026-09-05', 'due_date' => '2026-10-05',
            'items' => [['item_id' => $seed->id, 'description' => 'Seed', 'quantity' => 4, 'unit_price' => 2500, 'tax_rate' => 0]],
        ], $this->user->id);
        $this->assertSame('zero', $bill->items->first()->vat_treatment);

        $this->rate('VAT-ZERO')->update(['vat_treatment' => 'exempt']);

        $this->assertSame('zero', BillItem::where('bill_id', $bill->id)->value('vat_treatment'));
        // A header-only change keeps the lines' treatment too.
        app(SaveBill::class)->update($bill->fresh(), ['notes' => 'checked']);
        $this->assertSame('zero', BillItem::where('bill_id', $bill->id)->value('vat_treatment'));
    }

    public function test_a_credit_note_line_takes_the_treatment_of_the_invoice_line(): void
    {
        $this->createAuthenticatedUser();
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = app(SaveInvoice::class)->create($this->tenant->id, [
            'customer_id' => $customer->id, 'invoice_date' => '2026-09-10', 'due_date' => '2026-10-10', 'status' => 'unpaid',
            'items' => [['description' => 'Drugs', 'quantity' => 1, 'unit_price' => 40000, 'tax_rate' => 0, 'vat_treatment' => 'zero']],
        ], $this->user->id);

        $note = CreditNote::create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_id' => $invoice->id,
            'credit_note_number' => CreditNote::generateNumber($this->tenant->id), 'credit_note_date' => '2026-09-20', 'status' => 'draft',
        ]);
        CreditNoteItem::create(['credit_note_id' => $note->id, 'description' => 'Drugs', 'quantity' => 1, 'unit_price' => 10000, 'tax_rate' => 0, 'tax_amount' => 0, 'total' => 10000]);

        $this->assertSame('zero', CreditNoteItem::value('vat_treatment'));
    }

    public function test_tax_rate_treatment_must_fit_the_rate(): void
    {
        $this->createAuthenticatedUser(['create tax-rates', 'view tax-rates']);
        $base = ['type' => 'exclusive', 'applies_to' => 'both'];

        $this->post(route('tax-rates.store'), $base + ['name' => 'Bad zero', 'rate' => 0, 'vat_treatment' => 'standard'])
            ->assertSessionHasErrors('vat_treatment');
        $this->post(route('tax-rates.store'), $base + ['name' => 'Bad exempt', 'rate' => 5, 'vat_treatment' => 'exempt'])
            ->assertSessionHasErrors('vat_treatment');

        $this->post(route('tax-rates.store'), $base + ['name' => 'Exports', 'code' => 'EXP', 'rate' => 0, 'vat_treatment' => 'zero'])
            ->assertSessionHasNoErrors();
        $this->assertSame('zero', $this->rate('EXP')->vat_treatment);

        // A rate above 0% is standard-rated even when no treatment is sent.
        $this->post(route('tax-rates.store'), $base + ['name' => 'Other', 'code' => 'OTH', 'rate' => 5])->assertSessionHasNoErrors();
        $this->assertSame(VatTreatment::STANDARD, $this->rate('OTH')->vat_treatment);
    }

    public function test_migration_fills_in_existing_rates_and_lines(): void
    {
        $this->createAuthenticatedUser();
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $legacyExempt = TaxRate::create(['tenant_id' => $this->tenant->id, 'name' => 'VAT Exempt items', 'code' => 'OLD-EX', 'rate' => 0, 'type' => 'exclusive', 'applies_to' => 'both']);
        $legacyOther = TaxRate::create(['tenant_id' => $this->tenant->id, 'name' => 'No tax', 'code' => 'OLD-0', 'rate' => 0, 'type' => 'exclusive', 'applies_to' => 'both']);
        $item = $this->item('Old exempt item', $legacyExempt);

        $invoice = app(SaveInvoice::class)->create($this->tenant->id, [
            'customer_id' => $customer->id, 'invoice_date' => '2026-09-10', 'due_date' => '2026-10-10', 'status' => 'unpaid',
            'items' => [
                ['description' => 'A', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 7.5],
                ['item_id' => $item->id, 'description' => 'B', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0],
                ['description' => 'C', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0],
            ],
        ], $this->user->id);

        // As before the change: nothing recorded.
        DB::table('tax_rates')->whereIn('id', [$legacyExempt->id, $legacyOther->id])->update(['vat_treatment' => null]);
        DB::table('invoice_items')->update(['vat_treatment' => null]);

        $migration = require database_path('migrations/2026_10_06_000001_add_vat_treatment_to_tax_rates_and_lines.php');
        $migration->up();
        $migration->up(); // rerunnable

        $this->assertSame('exempt', $legacyExempt->fresh()->vat_treatment);
        $this->assertNull($legacyOther->fresh()->vat_treatment, 'an unnamed 0% rate is left for the business to choose');
        $this->assertSame(['standard', 'exempt', null], InvoiceItem::where('invoice_id', $invoice->id)->orderBy('id')->pluck('vat_treatment')->all());
        $this->assertSame(3, TaxRate::where('tenant_id', $this->tenant->id)->whereIn('code', ['VAT-STD', 'VAT-ZERO', 'VAT-EXEMPT'])->count());
    }
}
