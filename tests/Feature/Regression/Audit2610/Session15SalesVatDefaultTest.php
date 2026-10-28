<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Actions\Invoices\SaveInvoice;
use App\Models\Customer;
use App\Models\TaxRate;
use App\Support\TaxChoices;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 15 (MB-20): free-text sales lines started
 * at 0% VAT, the line's rate was a typed percentage, and the business's
 * default sales tax could only be set through the API.
 */
class Session15SalesVatDefaultTest extends TestCase
{
    private const PERMISSIONS = [
        'view settings', 'edit settings', 'view invoices', 'create invoices', 'view bills', 'create bills',
        'view quotations', 'create quotations', 'view sales-receipts', 'create sales-receipts',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->createAuthenticatedUser(self::PERMISSIONS);
        $this->subscription->update(['ends_at' => '2030-12-31']);
    }

    private function vat(): TaxRate
    {
        return TaxRate::where('code', 'VAT-STD')->sole();
    }

    private function saveSettings(array $extra): TestResponse
    {
        return $this->from(route('settings.company'))->post(route('settings.company.update'), $extra + [
            'name' => 'Kano Hardware Ltd', 'email' => 'hello@example.com',
        ]);
    }

    public function test_the_web_settings_save_the_default_sales_and_purchase_vat(): void
    {
        $this->get(route('settings.company'))->assertOk()->assertSee('VAT on new sales lines')->assertSee('VAT on new purchase lines');

        $this->saveSettings(['default_sales_tax_id' => $this->vat()->id, 'default_purchase_tax_id' => ''])->assertSessionHasNoErrors();
        $this->assertSame($this->vat()->id, $this->tenant->fresh()->default_sales_tax_id);
        $this->assertNull($this->tenant->fresh()->default_purchase_tax_id);

        $this->assertSame(7.5, TaxChoices::defaultRate('sales'));
        $this->assertSame(0.0, TaxChoices::defaultRate('purchases'));

        // Another business's rate is refused.
        auth()->forgetGuards();
        [$other] = $this->createTenantWithSubscription();
        $theirs = TaxRate::withoutGlobalScopes()->where('tenant_id', $other->id)->where('code', 'VAT-STD')->sole();
        $this->actingAs($this->user);
        $this->saveSettings(['default_sales_tax_id' => $theirs->id])->assertSessionHasErrors('default_sales_tax_id');
    }

    public function test_the_rate_marked_default_is_used_when_the_setting_is_empty(): void
    {
        $this->assertSame(0.0, TaxChoices::defaultRate('sales'), 'nothing chosen: no VAT');

        $this->vat()->setAsDefault();
        $this->assertSame(7.5, TaxChoices::defaultRate('sales'));
    }

    public function test_new_lines_on_sales_forms_start_with_the_business_rate_and_offer_the_configured_rates(): void
    {
        $this->tenant->update(['default_sales_tax_id' => $this->vat()->id]);

        foreach (['invoices.create', 'quotations.create', 'sales-receipts.create'] as $route) {
            $html = $this->get(route($route))->assertOk()->getContent();
            $this->assertStringContainsString('tax_rate: 7.5', $html, "{$route}: new lines start at 7.5%");
            $this->assertStringNotContainsString('tax_rate: 0,', $html, $route);
            $this->assertStringContainsString('<option value="7.5">VAT 7.5%</option>', $html, $route);
            $this->assertStringContainsString('No VAT (0%)', $html, $route);
            $this->assertStringNotContainsString('aria-label="Tax rate (%)" type="number"', $html, "{$route}: no typed percentage");
        }

        // Purchases follow their own setting: none chosen, so no VAT.
        $this->assertStringContainsString('tax_rate: 0,', $this->get(route('bills.create'))->assertOk()->getContent());
    }

    public function test_the_rate_chosen_on_a_line_is_saved(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = app(SaveInvoice::class)->create($this->tenant->id, [
            'customer_id' => $customer->id, 'invoice_date' => now()->toDateString(), 'due_date' => now()->addMonth()->toDateString(),
            'items' => [
                ['description' => 'Delivery', 'quantity' => 1, 'unit_price' => 10000, 'tax_rate' => 7.5],
                ['description' => 'Bread', 'quantity' => 1, 'unit_price' => 2000, 'tax_rate' => 0],
            ],
        ], $this->user->id);

        $this->assertSame(['7.50', '0.00'], $invoice->items()->orderBy('id')->pluck('tax_rate')->all());
        $this->assertEqualsWithDelta(750, (float) $invoice->tax_amount, 0.001);
    }
}
