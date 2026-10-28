<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Actions\Bills\SaveBill;
use App\Actions\VendorCredits\SaveVendorCredit;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\FixedAsset;
use App\Models\Journal;
use App\Models\Vendor;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 7 (MB-14): bill journals posted every
 * non-stock line to Miscellaneous Expense and ignored the line's account,
 * while supplier credits took their lines off the line's own account, so
 * crediting a bill coded to rent left Rent Expense below zero. Assets
 * bought on a bill took their cost off Miscellaneous Expense to match.
 */
class Session7BillLineAccountsTest extends TestCase
{
    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-10 09:00:00');
        $this->createAuthenticatedUser(['view bills', 'create bills', 'edit bills', 'view fixed-assets', 'create fixed-assets']);
        $this->subscription->update(['ends_at' => '2030-12-31']);
        $this->vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function account(string $code): ChartOfAccount
    {
        return ChartOfAccount::where('account_code', $code)->firstOrFail();
    }

    private function balance(string $code): string
    {
        return number_format((float) $this->account($code)->fresh()->current_balance, 2, '.', '');
    }

    /** @return array<string, array{0: string, 1: string}> account code => [debit, credit] */
    private function journalLines(Bill $bill): array
    {
        $lines = [];
        foreach (Journal::where('reference_type', Bill::class)->where('reference_id', $bill->id)->sole()->entries()->with('account')->get() as $entry) {
            $code = $entry->account->account_code;
            $lines[$code] = [
                number_format((float) ($lines[$code][0] ?? 0) + (float) $entry->debit, 2, '.', ''),
                number_format((float) ($lines[$code][1] ?? 0) + (float) $entry->credit, 2, '.', ''),
            ];
        }
        ksort($lines);

        return $lines;
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function bill(array $lines, array $extra = []): Bill
    {
        return app(SaveBill::class)->create($this->tenant->id, $extra + [
            'vendor_id' => $this->vendor->id, 'bill_date' => '2026-10-05', 'due_date' => '2026-11-05', 'items' => $lines,
        ], $this->user->id);
    }

    public function test_each_line_is_posted_to_its_own_account(): void
    {
        $bill = $this->bill([
            ['description' => 'October rent', 'quantity' => 1, 'unit_price' => 50000, 'tax_rate' => 0, 'account_id' => $this->account('6100')->id],
            ['description' => 'Electricity', 'quantity' => 1, 'unit_price' => 12000, 'tax_rate' => 0, 'account_id' => $this->account('6200')->id],
            ['description' => 'Sundries', 'quantity' => 1, 'unit_price' => 800, 'tax_rate' => 0],
        ]);

        $lines = $this->journalLines($bill);
        $this->assertSame(['50000.00', '0.00'], $lines['6100']);
        $this->assertSame(['12000.00', '0.00'], $lines['6200']);
        $this->assertSame(['800.00', '0.00'], $lines['6990']); // no account chosen
        $this->assertSame(['0.00', '62800.00'], $lines[ChartOfAccount::where('name', 'Accounts Payable')->value('account_code')]);
    }

    public function test_a_bill_with_discount_and_vat_balances_to_the_kobo(): void
    {
        $bill = $this->bill([
            ['description' => 'Rent', 'quantity' => 3, 'unit_price' => 333.33, 'tax_rate' => 7.5, 'account_id' => $this->account('6100')->id],
            ['description' => 'Power', 'quantity' => 7, 'unit_price' => 142.86, 'tax_rate' => 7.5, 'account_id' => $this->account('6200')->id],
        ], ['discount_amount' => 101.01]);

        $journal = Journal::where('reference_type', Bill::class)->where('reference_id', $bill->id)->sole();
        $debits = round((float) $journal->entries()->sum('debit'), 2);
        $credits = round((float) $journal->entries()->sum('credit'), 2);
        $this->assertSame($credits, $debits);
        $this->assertSame(round((float) $bill->total, 2), $debits);
        $this->assertArrayNotHasKey('6990', $this->journalLines($bill));
    }

    public function test_a_supplier_credit_against_a_coded_bill_does_not_drive_the_account_negative(): void
    {
        $this->bill([['description' => 'Rent', 'quantity' => 1, 'unit_price' => 50000, 'tax_rate' => 0, 'account_id' => $this->account('6100')->id]]);

        app(SaveVendorCredit::class)->create($this->tenant->id, [
            'vendor_id' => $this->vendor->id, 'credit_date' => '2026-10-08', 'status' => 'open', 'reason' => 'overcharged',
            'items' => [['description' => 'Rent overcharged', 'quantity' => 1, 'unit_price' => 10000, 'tax_rate' => 0, 'account_id' => $this->account('6100')->id]],
        ], $this->user->id);

        $this->assertSame('40000.00', $this->balance('6100'));
        $this->assertSame('0.00', $this->balance('6990'));
    }

    public function test_a_line_cannot_be_posted_to_cash_or_bank(): void
    {
        $this->post(route('bills.store'), [
            'vendor_id' => $this->vendor->id, 'bill_date' => '2026-10-05', 'due_date' => '2026-11-05',
            'items' => [['description' => 'Rent', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0, 'account_id' => $this->account('1000')->id]],
        ])->assertSessionHasErrors('items.0.account_id');

        $this->get(route('bills.create'))->assertOk()
            ->assertSee('6100 - Rent Expense')
            ->assertDontSee('<option value="'.$this->account('1000')->id.'">', false);
    }

    public function test_an_asset_bought_on_a_bill_comes_off_the_account_the_bill_used(): void
    {
        // Coded to an expense account: the asset takes it back off that account.
        $this->bill([['description' => 'Printer', 'quantity' => 1, 'unit_price' => 30000, 'tax_rate' => 0, 'account_id' => $this->account('6300')->id]]);
        $this->post(route('fixed-assets.store'), $this->asset(['name' => 'Printer', 'purchase_cost' => 30000, 'funding_account_id' => $this->account('6300')->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame('0.00', $this->balance('6300'));
        $this->assertSame('30000.00', $this->balance('1500'));
        $this->assertSame('0.00', $this->balance('6990'));

        // Coded straight to the asset account: nothing more is posted.
        $this->bill([['description' => 'Laptop', 'quantity' => 1, 'unit_price' => 50000, 'tax_rate' => 0, 'account_id' => $this->account('1500')->id]]);
        $this->post(route('fixed-assets.store'), $this->asset(['name' => 'Laptop', 'purchase_cost' => 50000, 'funding_account_id' => $this->account('1500')->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame('80000.00', $this->balance('1500'));
        $this->assertSame('0.00', $this->balance('6990'));
        $this->assertSame(2, FixedAsset::count());
    }

    /** @return array<string, mixed> */
    private function asset(array $overrides): array
    {
        return $overrides + [
            'purchase_date' => '2026-10-05', 'in_service_date' => '2026-10-05', 'funding_source' => 'bill',
            'salvage_value' => 0, 'useful_life' => 3, 'depreciation_method' => 'straight_line',
        ];
    }
}
