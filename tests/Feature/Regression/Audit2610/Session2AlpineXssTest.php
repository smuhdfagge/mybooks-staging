<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Models\Customer;
use App\Models\Import;
use App\Models\PaymentMade;
use App\Models\PaymentReceived;
use App\Models\Vendor;
use App\Support\PaymentMethods;
use Illuminate\Support\Facades\Blade;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 2: XSS through values echoed as
 * '{{ … }}' inside Alpine/Livewire attributes. The browser decodes &#039;
 * before Alpine runs the expression, so a quote in the value broke out of
 * the string (MB-03 stored through payment_method, MB-04 reflected through
 * ?customer_id, ?vendor_id and ?type).
 */
class Session2AlpineXssTest extends TestCase
{
    private const PAYLOAD = "'+alert(document.domain)+'";

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser([
            'view payments-received', 'create payments-received', 'edit payments-received',
            'view payments-made', 'create payments-made', 'edit payments-made',
            'view invoices', 'create invoices', 'view bills', 'create bills',
            'view expenses', 'create expenses', 'import data', 'create customers',
        ]);
    }

    private function customer(): Customer
    {
        return Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function vendor(): Vendor
    {
        return Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    /** A payment saved before the fix with a hostile method. */
    private function storedReceipt(string $method): PaymentReceived
    {
        return PaymentReceived::create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $this->customer()->id, 'payment_number' => 'PR-000900',
            'payment_date' => now(), 'amount' => 100, 'payment_method' => $method, 'is_deposit' => false,
            'unused_amount' => 0, 'created_by' => $this->user->id,
        ]);
    }

    // MB-03
    public function test_a_payment_method_outside_the_list_is_refused_on_the_web(): void
    {
        $this->post(route('payments-received.store'), [
            'customer_id' => $this->customer()->id, 'payment_date' => now()->toDateString(), 'amount' => 100, 'payment_method' => self::PAYLOAD,
        ])->assertSessionHasErrors('payment_method');
        $this->post(route('payments-made.store'), [
            'vendor_id' => $this->vendor()->id, 'payment_date' => now()->toDateString(), 'amount' => 100, 'payment_method' => self::PAYLOAD,
        ])->assertSessionHasErrors('payment_method');

        $this->assertSame(0, PaymentReceived::count() + PaymentMade::count());
    }

    public function test_a_payment_method_outside_the_list_is_refused_by_the_api(): void
    {
        $token = $this->user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/payments-received', [
            'customer_id' => $this->customer()->id, 'payment_date' => now()->toDateString(), 'amount' => 100, 'payment_method' => self::PAYLOAD,
        ])->assertStatus(422)->assertJsonValidationErrors('payment_method');
        $this->withToken($token)->postJson('/api/v1/payments-made', [
            'vendor_id' => $this->vendor()->id, 'payment_date' => now()->toDateString(), 'amount' => 100, 'payment_method' => self::PAYLOAD,
        ])->assertStatus(422)->assertJsonValidationErrors('payment_method');
    }

    public function test_changing_a_payment_checks_the_method_but_keeps_an_internal_one(): void
    {
        $payment = $this->storedReceipt('cash');
        $form = ['payment_date' => now()->toDateString(), 'amount' => 100];

        $this->put(route('payments-received.update', $payment), $form + ['payment_method' => self::PAYLOAD])->assertSessionHasErrors('payment_method');
        $this->put(route('payments-received.update', $payment), $form + ['payment_method' => 'deposit'])->assertSessionHasErrors('payment_method');
        $this->put(route('payments-received.update', $payment), $form + ['payment_method' => 'bank_transfer'])->assertSessionHasNoErrors();

        $payment->forceFill(['payment_method' => PaymentMethods::DEPOSIT])->saveQuietly();
        $this->put(route('payments-received.update', $payment), $form + ['payment_method' => PaymentMethods::DEPOSIT])->assertSessionHasNoErrors();
    }

    public function test_a_hostile_method_already_stored_cannot_run_on_the_edit_page(): void
    {
        $payment = $this->storedReceipt(self::PAYLOAD);

        $html = $this->get(route('payments-received.edit', $payment))->assertOk()->getContent();

        $this->assertStringNotContainsString('&#039;+alert', $html);
        $this->assertStringContainsString("paymentMethod: '\\u0027+alert(document.domain)+\\u0027'", $html);
    }

    // MB-04
    public function test_query_values_are_not_reflected_into_scripts(): void
    {
        $payload = urlencode(self::PAYLOAD);
        foreach ([
            route('invoices.create')."?customer_id={$payload}",
            route('bills.create')."?vendor_id={$payload}",
            route('expenses.create')."?vendor_id={$payload}",
            route('imports.create')."?type={$payload}",
        ] as $url) {
            $this->get($url)->assertOk()
                ->assertDontSee('&#039;+alert', false)
                ->assertDontSee('alert(document.domain)', false);
        }

        // Arrays are ignored rather than breaking the page.
        $this->get(route('invoices.create').'?customer_id[]=1')->assertOk();
        $this->get(route('imports.create').'?type[]=customers')->assertOk();
    }

    public function test_a_real_id_or_type_is_still_preselected(): void
    {
        $customer = $this->customer();
        $this->get(route('invoices.create', ['customer_id' => $customer->id]))->assertOk()
            ->assertSee("selectedId: '{$customer->id}'", false);
        $this->get(route('imports.create', ['type' => Import::TYPE_CUSTOMERS]))->assertOk()
            ->assertSee(e(route('imports.template', ['type' => 'customers', 'format' => 'csv'])), false);
    }

    public function test_js_text_directive_escapes_quotes_and_drops_arrays(): void
    {
        $this->assertSame("'\\u0027+alert(1)+\\u0027'", Blade::render('@jsText($v)', ['v' => "'+alert(1)+'"]));
        $this->assertSame("''", Blade::render('@jsText($v)', ['v' => ['x']]));
        $this->assertSame("''", Blade::render('@jsText($v)', ['v' => null]));
        $this->assertSame("'12'", Blade::render('@jsText($v)', ['v' => 12]));
    }

    public function test_imported_expense_methods_are_normalised(): void
    {
        $this->assertSame('bank_transfer', PaymentMethods::normalise('Bank Transfer'));
        $this->assertSame('other', PaymentMethods::normalise(self::PAYLOAD));
        $this->assertNull(PaymentMethods::normalise('  '));
    }

    public function test_audit_command_reports_stored_methods_outside_the_list(): void
    {
        $this->storedReceipt(self::PAYLOAD);
        auth()->forgetGuards();

        $this->artisan('mybooks:audit-affected-data', ['--tenant' => $this->tenant->id])
            ->expectsOutputToContain('Payment methods not on the list: 1')
            ->assertSuccessful();
        $this->assertSame(self::PAYLOAD, PaymentReceived::withoutGlobalScopes()->sole()->payment_method);
    }

    /** Guard: no view may put a Blade echo inside a quoted JavaScript string. */
    public function test_no_view_echoes_into_a_quoted_javascript_string(): void
    {
        $offenders = [];
        foreach ((new Finder)->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
            foreach (preg_split('/\R/', $file->getContents()) as $i => $line) {
                if (preg_match("/'\\{\\{|'\\{!!/", $line)) {
                    $offenders[] = $file->getRelativePathname().':'.($i + 1);
                }
            }
        }

        $this->assertSame([], $offenders, "Use @jsText(\$value) or @js(\$value) instead of '{{ \$value }}':\n".implode("\n", $offenders));
    }
}
