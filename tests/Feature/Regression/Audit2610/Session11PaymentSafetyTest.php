<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Actions\Bills\SaveBill;
use App\Actions\Invoices\SaveInvoice;
use App\Actions\Payments\RecordPaymentMade;
use App\Actions\Payments\RecordPaymentReceived;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentMade;
use App\Models\PaymentReceived;
use App\Models\Vendor;
use App\Services\Accounting\FinancialStatements;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 11:
 * - MB-09: a payment form sent twice saved two payments, and the "not more
 *   than is owed" check ran before the transaction on an unlocked invoice,
 *   so two payments at once could overpay it;
 * - MB-13: a deposit could be edited below what had been used from it
 *   (Customer Deposits went to -2,000).
 */
class Session11PaymentSafetyTest extends TestCase
{
    private const PERMISSIONS = [
        'view invoices', 'create invoices', 'view payments-received', 'create payments-received', 'edit payments-received',
        'view bills', 'create bills', 'view payments-made', 'create payments-made', 'edit payments-made',
    ];

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-10 09:00:00');
        $this->createAuthenticatedUser(self::PERMISSIONS);
        $this->subscription->update(['ends_at' => '2030-12-31']);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function invoice(float $amount = 10000): Invoice
    {
        return app(SaveInvoice::class)->create($this->tenant->id, [
            'customer_id' => $this->customer->id, 'invoice_date' => '2026-10-01', 'due_date' => '2026-10-31', 'status' => 'sent',
            'items' => [['description' => 'Consulting', 'quantity' => 1, 'unit_price' => $amount, 'tax_rate' => 0]],
        ], $this->user->id);
    }

    private function bill(float $amount = 8000): Bill
    {
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);

        return app(SaveBill::class)->create($this->tenant->id, [
            'vendor_id' => $vendor->id, 'bill_date' => '2026-10-01', 'due_date' => '2026-10-31',
            'items' => [['description' => 'Stationery', 'quantity' => 1, 'unit_price' => $amount, 'tax_rate' => 0, 'account_id' => ChartOfAccount::where('account_code', '6990')->value('id')]],
        ], $this->user->id);
    }

    /** @return array<string, mixed> */
    private function payment(Invoice $invoice, float $amount, array $extra = []): array
    {
        return $extra + [
            'customer_id' => $this->customer->id, 'invoice_id' => $invoice->id, 'payment_date' => '2026-10-05',
            'amount' => $amount, 'payment_method' => 'bank_transfer',
        ];
    }

    private function ledger(string $code): float
    {
        return (float) (app(FinancialStatements::class)->accountBalances($this->tenant->id, null, '2030-12-31')->firstWhere('account_code', $code)?->balance ?? 0);
    }

    public function test_the_same_form_sent_twice_saves_one_payment(): void
    {
        $invoice = $this->invoice();
        $token = (string) Str::uuid();

        $first = $this->post(route('payments-received.store'), $this->payment($invoice, 4000, ['form_token' => $token]));
        $first->assertSessionHasNoErrors();
        $payment = PaymentReceived::sole();
        $first->assertRedirect(route('payments-received.show', $payment));

        $this->post(route('payments-received.store'), $this->payment($invoice, 4000, ['form_token' => $token]))
            ->assertRedirect(route('payments-received.show', $payment))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'already saved'));

        $this->assertSame(1, PaymentReceived::count());
        $this->assertSame('6000.00', $invoice->fresh()->balance_due);

        // A new form (new token) is a new payment.
        $this->post(route('payments-received.store'), $this->payment($invoice, 1000, ['form_token' => (string) Str::uuid()]))->assertSessionHasNoErrors();
        $this->assertSame(2, PaymentReceived::count());
    }

    public function test_a_form_refused_by_validation_can_be_corrected_and_sent_with_the_same_token(): void
    {
        $invoice = $this->invoice();
        $token = (string) Str::uuid();

        $this->post(route('payments-received.store'), $this->payment($invoice, 12000, ['form_token' => $token]))->assertSessionHasErrors('amount');
        $this->post(route('payments-received.store'), $this->payment($invoice, 10000, ['form_token' => $token]))->assertSessionHasNoErrors();

        $this->assertSame(1, PaymentReceived::count());
    }

    public function test_a_supplier_payment_form_sent_twice_saves_once(): void
    {
        $bill = $this->bill();
        $data = ['vendor_id' => $bill->vendor_id, 'bill_id' => $bill->id, 'payment_date' => '2026-10-05', 'amount' => 3000, 'payment_method' => 'bank_transfer', 'form_token' => (string) Str::uuid()];

        $this->post(route('payments-made.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('payments-made.store'), $data)->assertSessionHas('success', fn ($m) => str_contains($m, 'already saved'));

        $this->assertSame(1, PaymentMade::count());
    }

    public function test_the_forms_carry_a_token(): void
    {
        $this->get(route('payments-received.create'))->assertOk()->assertSee('name="form_token"', false);
        $this->get(route('payments-made.create'))->assertOk()->assertSee('name="form_token"', false);
        $this->get(route('invoices.show', $this->invoice()))->assertOk()->assertSee('name="form_token"', false);
    }

    public function test_a_payment_is_checked_against_the_invoice_as_it_is_when_saved(): void
    {
        $invoice = $this->invoice();

        // Someone else pays the invoice in full just after this request read it.
        $paidElsewhere = false;
        Invoice::retrieved(function (Invoice $read) use ($invoice, &$paidElsewhere) {
            if (! $paidElsewhere && $read->id === $invoice->id) {
                $paidElsewhere = true;
                DB::table('invoices')->where('id', $invoice->id)->update(['amount_paid' => 10000, 'balance_due' => 0, 'status' => 'paid']);
            }
        });

        try {
            app(RecordPaymentReceived::class)->handle($this->tenant->id, $this->payment($invoice, 10000), $this->user->id);
            $this->fail('The second payment of the full amount was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }
        $this->assertSame(0, PaymentReceived::count());
    }

    public function test_a_supplier_payment_is_checked_against_the_bill_as_it_is_when_saved(): void
    {
        $bill = $this->bill();
        $paidElsewhere = false;
        Bill::retrieved(function (Bill $read) use ($bill, &$paidElsewhere) {
            if (! $paidElsewhere && $read->id === $bill->id) {
                $paidElsewhere = true;
                DB::table('bills')->where('id', $bill->id)->update(['amount_paid' => 8000, 'balance_due' => 0, 'status' => 'paid']);
            }
        });

        $this->expectException(ValidationException::class);
        app(RecordPaymentMade::class)->handle($this->tenant->id, [
            'vendor_id' => $bill->vendor_id, 'bill_id' => $bill->id, 'payment_date' => '2026-10-05', 'amount' => 8000, 'payment_method' => 'bank_transfer',
        ], $this->user->id);
    }

    public function test_the_invoice_is_locked_while_the_payment_is_saved(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Row locks (SELECT ... FOR UPDATE) are checked on MySQL/MariaDB; SQLite has none.');
        }
        $invoice = $this->invoice();
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = strtolower($query->sql);
        });

        app(RecordPaymentReceived::class)->handle($this->tenant->id, $this->payment($invoice, 4000), $this->user->id);

        $this->assertNotEmpty(array_filter($queries, fn ($sql) => str_contains($sql, 'from `invoices`') && str_contains($sql, 'for update')));
    }

    public function test_a_deposit_cant_be_edited_below_what_has_been_used(): void
    {
        $record = app(RecordPaymentReceived::class);
        $deposit = $record->handle($this->tenant->id, ['customer_id' => $this->customer->id, 'payment_date' => '2026-10-02', 'amount' => 5000, 'payment_method' => 'bank_transfer', 'is_deposit' => true], $this->user->id);
        $record->handle($this->tenant->id, $this->payment($this->invoice(), 3000, ['apply_deposit_id' => $deposit->id, 'deposit_amount' => 3000]), $this->user->id);
        $this->assertSame('2000.00', $deposit->fresh()->unused_amount);
        $before = $this->ledger('2350');

        $edit = fn (float $amount) => ['payment_date' => '2026-10-02', 'amount' => $amount, 'payment_method' => 'bank_transfer'];
        $this->putJson(route('payments-received.update', $deposit), $edit(1000))
            ->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->assertSame('5000.00', $deposit->fresh()->amount);
        $this->assertSame($before, $this->ledger('2350'));
        $this->assertGreaterThanOrEqual(0, $this->ledger('2350'));

        // Down to exactly what was used is fine: nothing left to use.
        $this->put(route('payments-received.update', $deposit), $edit(3000))->assertSessionHasNoErrors();
        $this->assertSame('0.00', $deposit->fresh()->unused_amount);
        $this->put(route('payments-received.update', $deposit), $edit(4500))->assertSessionHasNoErrors();
        $this->assertSame('1500.00', $deposit->fresh()->unused_amount);
    }
}
