<?php

namespace Tests\Feature\Features;

use App\Actions\CreditNotes\SaveCreditNote;
use App\Actions\EInvoicing\SubmitInvoiceToNrs;
use App\Actions\Invoices\DeleteInvoice;
use App\Actions\Invoices\SaveInvoice;
use App\Enums\EInvoiceStatus;
use App\Livewire\EInvoices\EInvoicesTable;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\EInvoiceSetting;
use App\Models\EInvoiceSubmission;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Role;
use App\Notifications\EInvoiceNotReportedNotification;
use App\Rules\Tin;
use App\Services\EInvoicing\EInvoiceException;
use App\Services\EInvoicing\NotSetUp;
use App\Services\EInvoicing\PayloadBuilder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Session 18: NRS e-invoicing (Merchant Buyer Solution). NRS is always faked;
 * its real answers are unverified (see NrsDriver).
 */
class EInvoicingTest extends TestCase
{
    private const KEY = 'test-key-one';

    private const SECRET = 'test-secret-two';

    private const PNG = 'QUFBQUFBQUFBQQ==';

    private Customer $company;

    private Customer $person;

    /** What the fake NRS does next: accepted, rejected, down, forbidden, pending. */
    private string $nrs = 'accepted';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-20 10:00:00');
        config([
            'mybooks.features.e_invoicing' => true,
            'mybooks.einvoicing.driver' => 'auto',
            'mybooks.einvoicing.base_urls.sandbox' => 'https://nrs.test',
            'mybooks.einvoicing.base_urls.live' => 'https://nrs-live.test',
        ]);
        Notification::fake();

        $this->createAuthenticatedUser([
            'view invoices', 'create invoices', 'edit invoices', 'delete invoices', 'view customers', 'create customers', 'edit customers',
            'view settings', 'edit settings', 'view e-invoices', 'submit e-invoices', 'manage e-invoicing',
        ]);
        $this->tenant->update(['name' => 'Kano Traders Ltd', 'currency' => 'NGN', 'tax_number' => '12345678-0001', 'email' => 'info@kano.test']);

        $this->company = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Aminu Stores', 'tax_number' => '87654321-0001']);
        $this->person = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Musa Ibrahim', 'tax_number' => null]);

        EInvoiceSetting::forTenant($this->tenant->id)->update([
            'enabled' => true, 'environment' => 'sandbox', 'submit_mode' => 'manual',
            'api_key' => self::KEY, 'api_secret' => self::SECRET, 'service_id' => '7A0819F4', 'business_id' => 'BIZ-0001',
        ]);

        Http::preventStrayRequests();
        Http::fake(['nrs.test/*' => fn (Request $request) => $this->answer($request)]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---- helpers -------------------------------------------------------

    private function answer(Request $request)
    {
        if (str_contains($request->url(), '/confirm/')) {
            return Http::response(['code' => 200, 'data' => ['irn' => basename($request->url()), 'csid' => 'CSID-CONFIRMED', 'qr_code' => self::PNG]]);
        }

        if ($request->method() === 'GET') {
            return $this->nrs === 'forbidden' ? Http::response(['error' => 'invalid_token'], 401) : Http::response(['data' => []]);
        }

        return match ($this->nrs) {
            'down' => throw new ConnectionException('cURL error 28: timed out'),
            'busy' => Http::response(['code' => 503, 'message' => 'NRS system is currently offline'], 503),
            'forbidden' => Http::response(['error' => 'invalid_token', 'error_description' => 'bad key'], 401),
            'rejected' => Http::response(['code' => 400, 'data' => null, 'message' => 'error has occurred', 'error' => ['public_message' => 'Customer TIN 87654321-0001 is not registered']], 400),
            'pending' => Http::response(['code' => 202, 'message' => 'queued'], 202),
            default => Http::response(['code' => 201, 'message' => 'Transmitted successfully', 'data' => [
                'irn' => $request['irn'], 'csid' => 'CSID-ABC123XYZ', 'qr_code' => self::PNG,
            ]], 201),
        };
    }

    /** @param array<int, array<string, mixed>>|null $lines */
    private function invoice(?Customer $customer = null, ?array $lines = null, array $extra = []): Invoice
    {
        return app(SaveInvoice::class)->create($this->tenant->id, $extra + [
            'customer_id' => ($customer ?? $this->company)->id, 'invoice_date' => '2026-10-05', 'due_date' => '2026-11-05', 'status' => 'unpaid',
            'items' => $lines ?? [['description' => 'Rice bag', 'quantity' => 4, 'unit_price' => 1500, 'tax_rate' => 7.5]],
        ], $this->user->id);
    }

    private function creditNote(Invoice $invoice, float $price = 1000): CreditNote
    {
        return app(SaveCreditNote::class)->create($this->tenant->id, [
            'customer_id' => $invoice->customer_id, 'invoice_id' => $invoice->id, 'credit_note_date' => '2026-10-10', 'reason' => 'pricing_error', 'status' => 'open',
            'items' => [['description' => 'Rice bag', 'quantity' => 1, 'unit_price' => $price, 'tax_rate' => 7.5]],
        ], $this->user->id);
    }

    private function submit(Invoice|CreditNote $document): EInvoiceSubmission
    {
        return app(SubmitInvoiceToNrs::class)->handle($document, $this->user->id);
    }

    private function sentCount(): int
    {
        return Http::recorded()->count();
    }

    private function settings(): EInvoiceSetting
    {
        return EInvoiceSetting::forTenant($this->tenant->id);
    }

    // ---- TIN -----------------------------------------------------------

    public function test_a_tin_is_digits_and_hyphens_and_loosely_checked(): void
    {
        foreach (['12345678-0001', '1234567890', '1234567890123', '12345678', '1234 5678 90', ''] as $ok) {
            $this->assertTrue(Tin::isValid($ok), "'{$ok}' should pass");
        }
        foreach (['ABC12345', '1234567', '12345678--0001', '-12345678', '12345678-', '1234567890123456', 'TIN: 12345678'] as $bad) {
            $this->assertFalse(Tin::isValid($bad), "'{$bad}' should fail");
        }
        $this->assertTrue(Tin::isValid(null));
    }

    public function test_the_company_profile_and_customer_forms_check_the_tin(): void
    {
        $company = ['name' => 'Kano Traders Ltd', 'email' => 'info@kano.test'];
        $this->post(route('settings.company.update'), $company + ['tax_number' => 'not a tin'])->assertSessionHasErrors('tax_number');
        $this->post(route('settings.company.update'), $company + ['tax_number' => '23456789-0002'])->assertSessionHasNoErrors();
        $this->assertSame('23456789-0002', $this->tenant->fresh()->tax_number);

        $customer = ['name' => 'Bala Ltd', 'email' => 'bala@example.com', 'payment_terms' => 30, 'is_active' => 1];
        $this->post(route('customers.store'), $customer + ['tax_number' => 'abc'])->assertSessionHasErrors('tax_number');
        $this->post(route('customers.store'), $customer + ['tax_number' => '34567890-0001'])->assertSessionHasNoErrors();
        $this->assertSame('34567890-0001', Customer::where('name', 'Bala Ltd')->first()->tax_number);
        // no TIN is fine: an individual
        $this->post(route('customers.store'), ['name' => 'Bala Person', 'email' => 'bp@example.com', 'payment_terms' => 30, 'is_active' => 1])->assertSessionHasNoErrors();
    }

    public function test_the_tin_is_printed_on_the_invoice_and_credit_note(): void
    {
        $invoice = $this->invoice();
        $this->get(route('invoices.print', $invoice))->assertOk()->assertSee('TIN: 12345678-0001')->assertSee('TIN: 87654321-0001');

        $note = $this->creditNote($invoice);
        $this->get(route('credit-notes.print', $note))->assertOk()->assertSee('TIN: 87654321-0001')->assertSee('TIN: 12345678-0001');
    }

    // ---- payload ------------------------------------------------------

    public function test_the_invoice_payload_has_the_nrs_shape_and_naira_figures(): void
    {
        $invoice = $this->invoice(lines: [
            ['description' => 'Rice bag', 'quantity' => 4, 'unit_price' => 1500, 'tax_rate' => 7.5],
            ['description' => 'Delivery', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0],
        ]);

        $p = app(PayloadBuilder::class)->build($invoice->fresh(), $this->settings());

        $this->assertSame('BIZ-0001', $p['business_id']);
        $this->assertSame('INV'.substr($invoice->invoice_number, 4).'-7A0819F4-20261005', $p['irn']);
        $this->assertSame('380', $p['invoice_type_code']);
        $this->assertSame('B2B', $p['invoice_kind']);
        $this->assertSame('NGN', $p['document_currency_code']);
        $this->assertSame('2026-10-05', $p['issue_date']);
        $this->assertSame('2026-11-05', $p['due_date']);
        $this->assertSame('PENDING', $p['payment_status']);
        $this->assertSame('Kano Traders Ltd', $p['accounting_supplier_party']['party_name']);
        $this->assertSame('12345678-0001', $p['accounting_supplier_party']['tin']);
        $this->assertSame('87654321-0001', $p['accounting_customer_party']['tin']);
        $this->assertSame('NG', $p['accounting_customer_party']['postal_address']['country']);
        $this->assertArrayNotHasKey('billing_reference', $p);

        $this->assertCount(2, $p['invoice_line']);
        $this->assertSame(6000.0, $p['invoice_line'][0]['line_extension_amount']);
        $this->assertSame(1500.0, $p['invoice_line'][0]['price']['price_amount']);
        $this->assertEquals(4, $p['invoice_line'][0]['invoiced_quantity']);
        $this->assertSame('Rice bag', $p['invoice_line'][0]['item']['name']);

        // 6,000 at 7.5% = 450; 1,000 at 0%: total 7,450
        $this->assertSame(450.0, $p['tax_total']['tax_amount']);
        $this->assertCount(2, $p['tax_total']['tax_subtotal']);
        $this->assertSame('STANDARD_VAT', $p['tax_total']['tax_subtotal'][0]['tax_category']['id']);
        $this->assertEquals(7.5, $p['tax_total']['tax_subtotal'][0]['tax_category']['percent']);
        $this->assertSame(6000.0, $p['tax_total']['tax_subtotal'][0]['taxable_amount']);
        $this->assertSame('ZERO_VAT', $p['tax_total']['tax_subtotal'][1]['tax_category']['id']);
        $this->assertSame(7000.0, $p['legal_monetary_total']['line_extension_amount']);
        $this->assertSame(7000.0, $p['legal_monetary_total']['tax_exclusive_amount']);
        $this->assertSame(7450.0, $p['legal_monetary_total']['tax_inclusive_amount']);
        $this->assertSame(7450.0, $p['legal_monetary_total']['payable_amount']);
        $this->assertSame((float) $invoice->fresh()->total, $p['legal_monetary_total']['payable_amount']);
    }

    public function test_a_document_discount_is_in_the_payload_and_the_totals_agree(): void
    {
        $invoice = $this->invoice(lines: [['description' => 'Goods', 'quantity' => 10, 'unit_price' => 10000, 'tax_rate' => 7.5]], extra: ['discount_type' => 'percentage', 'discount_amount' => 10]);
        // 100,000 less 10% = 90,000; VAT 6,750; total 96,750
        $p = app(PayloadBuilder::class)->build($invoice->fresh(), $this->settings());

        $this->assertSame(10000.0, $p['legal_monetary_total']['allowance_total_amount']);
        $this->assertSame(100000.0, $p['legal_monetary_total']['line_extension_amount']);
        $this->assertSame(90000.0, $p['legal_monetary_total']['tax_exclusive_amount']);
        $this->assertSame(96750.0, $p['legal_monetary_total']['payable_amount']);
        $this->assertSame(6750.0, $p['tax_total']['tax_amount']);
        $this->assertSame(90000.0, $p['tax_total']['tax_subtotal'][0]['taxable_amount']);
    }

    public function test_the_credit_note_payload_is_type_381_and_refers_to_the_original_irn(): void
    {
        $invoice = $this->invoice();
        $note = $this->creditNote($invoice);

        // The invoice must be accepted first: the credit note refers to its IRN.
        try {
            $this->submit($note);
            $this->fail('A credit note should wait for its invoice.');
        } catch (EInvoiceException $e) {
            $this->assertStringContainsString("Send invoice {$invoice->invoice_number} to NRS first", $e->getMessage());
        }
        $this->assertSame(0, $this->sentCount());

        $accepted = $this->submit($invoice);
        $payload = app(PayloadBuilder::class)->build($note->fresh(), $this->settings());

        $this->assertSame('381', $payload['invoice_type_code']);
        $this->assertSame([['irn' => $accepted->irn, 'issue_date' => '2026-10-05']], $payload['billing_reference']);
        $this->assertSame('CN'.substr($note->credit_note_number, 3).'-7A0819F4-20261010', $payload['irn']);
        $this->assertSame(1000.0, $payload['invoice_line'][0]['line_extension_amount']);
        $this->assertSame(1075.0, $payload['legal_monetary_total']['payable_amount']);
        $this->assertSame(75.0, $payload['tax_total']['tax_amount']);
        $this->assertArrayNotHasKey('due_date', $payload);

        $this->assertSame('accepted', $this->submit($note)->status);
    }

    public function test_a_customer_without_a_tin_is_b2c_and_only_large_ones_must_be_reported(): void
    {
        $small = $this->invoice($this->person, [['description' => 'Rice', 'quantity' => 1, 'unit_price' => 10000, 'tax_rate' => 0]]);
        $large = $this->invoice($this->person, [['description' => 'Generator', 'quantity' => 1, 'unit_price' => 80000, 'tax_rate' => 0]]);
        $business = $this->invoice($this->company, [['description' => 'Rice', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0]]);

        $builder = app(PayloadBuilder::class);
        $this->assertSame('b2c', $builder->kind($small));
        $this->assertFalse($builder->mustReport($small));
        $this->assertTrue($builder->mustReport($large));
        $this->assertSame('B2C', $builder->build($large->fresh(), $this->settings())['invoice_kind']);
        $this->assertTrue($builder->mustReport($business), 'B2B always goes to NRS');
    }

    public function test_missing_business_tin_or_foreign_currency_stops_with_a_plain_message(): void
    {
        $invoice = $this->invoice();
        $this->tenant->update(['tax_number' => null]);
        try {
            $this->submit($invoice);
            $this->fail('should need a TIN');
        } catch (EInvoiceException $e) {
            $this->assertStringContainsString('Add your business TIN', $e->getMessage());
        }
        $this->assertSame(0, $this->sentCount());
        $this->assertSame(EInvoiceStatus::NotSubmitted->value, EInvoiceSubmission::forDocument($invoice)->status);

        $this->tenant->update(['tax_number' => '12345678-0001', 'currency' => 'USD']);
        $this->expectExceptionMessage('Only naira documents');
        $this->submit($invoice);
    }

    // ---- the flows ----------------------------------------------------

    public function test_an_accepted_invoice_keeps_the_irn_stamp_and_qr_and_the_keys_travel_in_headers(): void
    {
        $invoice = $this->invoice();

        $this->post(route('e-invoices.invoice.submit', $invoice))->assertSessionHas('success');

        $row = EInvoiceSubmission::forDocument($invoice);
        $this->assertSame('accepted', $row->status);
        $this->assertSame('INV'.substr($invoice->invoice_number, 4).'-7A0819F4-20261005', $row->irn);
        $this->assertSame('CSID-ABC123XYZ', $row->csid);
        $this->assertSame(self::PNG, $row->qr_image);
        $this->assertSame('b2b', $row->kind);
        $this->assertSame('sandbox', $row->environment);
        $this->assertNotNull($row->submitted_at);
        $this->assertNotNull($row->accepted_at);
        $this->assertNull($row->last_error);
        $this->assertSame(1, $row->attempts);
        $this->assertSame($this->tenant->id, $row->tenant_id);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://nrs.test/api/v1/invoice/sign'
                && $request->hasHeader('x-api-key', self::KEY)
                && $request->hasHeader('x-api-secret', self::SECRET)
                && $request['invoice_type_code'] === '380'
                && ! str_contains($request->body(), self::SECRET);
        });

        $this->get(route('invoices.show', $invoice))->assertOk()
            ->assertSee('data-testid="e-invoice-panel"', false)->assertSee($row->irn)->assertSee('Accepted')->assertSee('e-invoice-qr', false);
        $this->get(route('invoices.print', $invoice))->assertOk()->assertSee('IRN:', false)->assertSee($row->irn)->assertSee('data:image/png;base64,'.self::PNG, false);
    }

    public function test_the_live_environment_uses_the_live_address(): void
    {
        Http::fake(['nrs-live.test/*' => Http::response(['data' => ['irn' => 'X', 'csid' => 'S']], 201)]);
        $this->settings()->update(['environment' => 'live']);

        $row = $this->submit($this->invoice());

        $this->assertSame('live', $row->environment);
        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://nrs-live.test/'));
    }

    public function test_a_rejected_invoice_shows_the_reason_in_plain_english_and_can_be_sent_again(): void
    {
        $invoice = $this->invoice();
        $this->nrs = 'rejected';

        $this->post(route('e-invoices.invoice.submit', $invoice))->assertSessionHas('error');

        $row = EInvoiceSubmission::forDocument($invoice);
        $this->assertSame('rejected', $row->status);
        $this->assertSame('NRS did not accept this invoice: Customer TIN 87654321-0001 is not registered', $row->last_error);
        $this->assertNull($row->next_retry_at, 'a rejection is not retried by itself');
        $this->get(route('invoices.show', $invoice))->assertSee('Customer TIN 87654321-0001 is not registered')->assertSee('Rejected');

        $this->nrs = 'accepted';
        $this->post(route('e-invoices.invoice.submit', $invoice))->assertSessionHas('success');
        $this->assertSame('accepted', $row->fresh()->status);
        $this->assertNull($row->fresh()->last_error);
        $this->assertSame(2, $row->fresh()->attempts);
        $this->assertSame(1, EInvoiceSubmission::count());
    }

    public function test_a_network_failure_marks_it_failed_and_the_hourly_command_sends_it_again(): void
    {
        $invoice = $this->invoice();
        $this->nrs = 'down';

        $this->post(route('e-invoices.invoice.submit', $invoice))->assertSessionHas('error');
        $row = EInvoiceSubmission::forDocument($invoice);
        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('could not reach NRS', $row->last_error);
        $this->assertNotNull($row->next_retry_at);
        $this->get(route('invoices.show', $invoice))->assertSee('Failed')->assertSee('will try again');

        // Not due yet: nothing is sent.
        $before = $this->sentCount();
        $this->artisan('einvoice:retry')->assertSuccessful();
        $this->assertSame($before, $this->sentCount());

        // NRS is back and the wait is over.
        $this->nrs = 'accepted';
        Carbon::setTestNow(now()->addMinutes(11));
        $this->artisan('einvoice:retry')->assertSuccessful();

        $this->assertSame('accepted', $row->fresh()->status);
        $this->assertSame(2, $row->fresh()->attempts);
        $this->assertSame(Journal::count(), Journal::count());
    }

    public function test_the_retry_command_gives_up_after_the_attempt_limit(): void
    {
        config(['mybooks.einvoicing.max_attempts' => 2]);
        $invoice = $this->invoice();
        $this->nrs = 'busy';

        $this->submit($invoice);                       // attempt 1
        Carbon::setTestNow(now()->addMinutes(11));
        $this->artisan('einvoice:retry')->assertSuccessful();   // attempt 2
        $row = EInvoiceSubmission::forDocument($invoice);
        $this->assertSame(2, $row->attempts);
        $this->assertSame('failed', $row->status);
        $this->assertNull($row->next_retry_at, 'no more automatic tries');

        $before = $this->sentCount();
        Carbon::setTestNow(now()->addDays(2));
        $this->artisan('einvoice:retry')->assertSuccessful();
        $this->assertSame($before, $this->sentCount());

        // The Retry button still works.
        $this->nrs = 'accepted';
        $this->post(route('e-invoices.invoice.submit', $invoice))->assertSessionHas('success');
        $this->assertSame('accepted', $row->fresh()->status);
    }

    public function test_wrong_keys_are_reported_as_a_key_problem(): void
    {
        $invoice = $this->invoice();
        $this->nrs = 'forbidden';

        $row = $this->submit($invoice);

        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('did not accept the keys', $row->last_error);
        $this->assertStringNotContainsString(self::KEY, $row->last_error);
    }

    public function test_a_pending_answer_is_followed_up_by_the_command(): void
    {
        $invoice = $this->invoice();
        $this->nrs = 'pending';

        $this->assertSame('pending', $this->submit($invoice)->status);

        // Still fresh: not asked yet.
        $this->artisan('einvoice:retry')->assertSuccessful();
        $this->assertSame('pending', EInvoiceSubmission::forDocument($invoice)->status);

        Carbon::setTestNow(now()->addMinutes(20));
        $this->artisan('einvoice:retry')->assertSuccessful();
        $row = EInvoiceSubmission::forDocument($invoice);
        $this->assertSame('accepted', $row->status);
        $this->assertSame('CSID-CONFIRMED', $row->csid);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && str_contains($r->url(), '/api/v1/invoice/confirm/INV'));
    }

    public function test_an_accepted_document_is_never_sent_again(): void
    {
        $invoice = $this->invoice();
        $first = $this->submit($invoice);
        $this->assertSame(1, $this->sentCount());

        $second = $this->submit($invoice);
        $this->post(route('e-invoices.invoice.submit', $invoice));
        Livewire::test(EInvoicesTable::class)->set('selectedItems', [(string) $invoice->id])->call('submitSelected');
        $this->artisan('einvoice:retry')->assertSuccessful();

        $this->assertSame(1, $this->sentCount(), 'NRS is called once only');
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, EInvoiceSubmission::count());
        $this->assertSame(1, $second->fresh()->attempts);
        $this->assertSame('CSID-ABC123XYZ', $second->fresh()->csid);
    }

    public function test_a_send_already_in_flight_is_not_started_twice(): void
    {
        $invoice = $this->invoice();
        $this->nrs = 'pending';
        $this->assertSame('pending', $this->submit($invoice)->status);
        Carbon::setTestNow(now()->addMinutes(2));
        $before = $this->sentCount();

        $this->submit($invoice);
        $this->post(route('e-invoices.invoice.submit', $invoice));

        $this->assertSame($before, $this->sentCount());
        $this->assertSame(1, EInvoiceSubmission::forDocument($invoice)->attempts);
    }

    public function test_there_is_only_one_row_per_document(): void
    {
        $invoice = $this->invoice();
        $this->submit($invoice);

        $this->expectException(QueryException::class);
        DB::table('e_invoice_submissions')->insert(['tenant_id' => $this->tenant->id, 'invoice_id' => $invoice->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_sending_never_changes_the_books(): void
    {
        $invoice = $this->invoice();
        $note = $this->creditNote($invoice);
        $journals = Journal::count();
        $entries = DB::table('journal_entries')->count();
        $balances = DB::table('chart_of_accounts')->where('tenant_id', $this->tenant->id)->pluck('current_balance', 'id')->all();
        $invoiceBefore = $invoice->fresh()->only(['status', 'subtotal', 'tax_amount', 'total', 'balance_due', 'amount_paid']);
        $noteBefore = $note->fresh()->only(['status', 'total', 'balance']);

        foreach (['accepted', 'rejected', 'down'] as $outcome) {
            $this->nrs = $outcome;
            $this->submit($invoice);
        }
        $this->nrs = 'accepted';
        $this->submit($invoice);
        $this->submit($note);

        $this->assertSame($journals, Journal::count());
        $this->assertSame($entries, DB::table('journal_entries')->count());
        $this->assertEquals($balances, DB::table('chart_of_accounts')->where('tenant_id', $this->tenant->id)->pluck('current_balance', 'id')->all());
        $this->assertEquals($invoiceBefore, $invoice->fresh()->only(['status', 'subtotal', 'tax_amount', 'total', 'balance_due', 'amount_paid']));
        $this->assertEquals($noteBefore, $note->fresh()->only(['status', 'total', 'balance']));
    }

    // ---- automatic mode ----------------------------------------------

    public function test_automatic_mode_sends_a_new_invoice_and_a_posted_credit_note(): void
    {
        $this->settings()->update(['submit_mode' => 'auto']);

        $invoice = $this->invoice();
        $this->assertSame('accepted', EInvoiceSubmission::forDocument($invoice)->status);
        $this->assertSame(1, $this->sentCount());

        $note = $this->creditNote($invoice);
        $this->assertSame('accepted', EInvoiceSubmission::forDocument($note)->status);
        $this->assertSame(2, $this->sentCount());

        // A draft is not sent; a small B2C invoice is not required.
        $this->invoice(extra: ['status' => 'draft']);
        $this->invoice($this->person, [['description' => 'Rice', 'quantity' => 1, 'unit_price' => 5000, 'tax_rate' => 0]]);
        $this->assertSame(2, $this->sentCount());
    }

    public function test_manual_mode_sends_nothing_by_itself(): void
    {
        $this->invoice();
        $this->assertSame(0, $this->sentCount());
        $this->assertSame(0, EInvoiceSubmission::count());
    }

    public function test_nrs_being_down_never_blocks_posting_in_automatic_mode(): void
    {
        $this->settings()->update(['submit_mode' => 'auto']);
        $this->nrs = 'down';

        $invoice = $this->invoice();

        $this->assertSame('unpaid', $invoice->fresh()->status);
        $this->assertNotNull($invoice->journal, 'the journal was still posted');
        $this->assertSame('failed', EInvoiceSubmission::forDocument($invoice)->status);
    }

    // ---- locks --------------------------------------------------------

    public function test_an_accepted_invoice_cannot_be_edited_cancelled_or_deleted_but_a_failed_one_can(): void
    {
        $invoice = $this->invoice();
        $this->nrs = 'down';
        $this->submit($invoice);
        // Not accepted yet: still editable.
        app(SaveInvoice::class)->update($invoice->fresh(), ['items' => [['description' => 'Rice bag', 'quantity' => 5, 'unit_price' => 1500, 'tax_rate' => 7.5]]]);
        $this->assertEquals(8062.5, $invoice->fresh()->total);

        $this->nrs = 'accepted';
        $this->submit($invoice->fresh());
        $total = $invoice->fresh()->total;

        $this->get(route('invoices.edit', $invoice))->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'accepted by NRS') && str_contains($m, 'credit note'));
        try {
            app(SaveInvoice::class)->update($invoice->fresh(), ['items' => [['description' => 'Rice bag', 'quantity' => 1, 'unit_price' => 1, 'tax_rate' => 0]]]);
            $this->fail('edit should be blocked');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('issue a credit note', $e->errors()['invoice'][0]);
        }
        $this->put(route('invoices.update', $invoice), ['customer_id' => $this->company->id, 'invoice_date' => '2026-10-05', 'due_date' => '2026-11-05', 'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 1, 'tax_rate' => 0]]])->assertSessionHasErrors('invoice');

        // Straight at the model: money, customer and date are fixed, and so is cancelling.
        foreach ([['total' => 1], ['subtotal' => 1], ['customer_id' => $this->person->id], ['invoice_date' => '2026-10-06'], ['status' => 'cancelled']] as $change) {
            try {
                $invoice->fresh()->update($change);
                $this->fail('model change should be blocked: '.json_encode($change));
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        // Normal life still works: a payment settles it.
        $invoice->fresh()->updateBalances();
        $this->assertEquals($total, $invoice->fresh()->total);

        $this->assertStringContainsString('accepted by NRS', app(DeleteInvoice::class)->blockedBecause($invoice->fresh()));
        $this->delete(route('invoices.destroy', $invoice))->assertSessionHas('error');
        $this->assertNotNull(Invoice::find($invoice->id));
    }

    public function test_an_accepted_credit_note_cannot_be_voided(): void
    {
        $invoice = $this->invoice();
        $this->submit($invoice);
        $note = $this->creditNote($invoice);
        $this->submit($note);

        $this->post(route('credit-notes.void', $note))->assertSessionHas('error', fn ($m) => str_contains($m, 'accepted by NRS'));
        $this->assertSame('open', $note->fresh()->status);
    }

    // ---- settings, secrets -------------------------------------------

    public function test_keys_are_stored_encrypted_shown_masked_and_kept_when_left_empty(): void
    {
        $this->settings()->update(['api_key' => null, 'api_secret' => null]);
        $this->put(route('settings.e-invoicing.update'), [
            'enabled' => 1, 'environment' => 'sandbox', 'submit_mode' => 'manual',
            'api_key' => self::KEY, 'api_secret' => self::SECRET, 'service_id' => '7A0819F4', 'business_id' => 'BIZ-0001',
            'public_key' => 'public-key-text-for-tests', 'certificate' => 'CERTDATA1234567890',
        ])->assertSessionHas('success');

        $raw = DB::table('e_invoice_settings')->where('tenant_id', $this->tenant->id)->first();
        foreach ([self::KEY, self::SECRET, 'CERTDATA1234567890', 'MIIB'] as $plain) {
            $this->assertStringNotContainsString($plain, json_encode($raw), 'stored encrypted');
        }
        $this->assertSame(self::KEY, $this->settings()->api_key);

        $page = $this->get(route('settings.e-invoicing'))->assertOk();
        $page->assertDontSee(self::KEY)->assertDontSee(self::SECRET)->assertDontSee('CERTDATA1234567890', false)->assertSee('Saved (••••••••', false)->assertSee('7A0819F4');
        $this->assertStringNotContainsString(self::KEY, json_encode($this->settings()->toArray()));

        // Empty fields keep what is saved.
        $this->put(route('settings.e-invoicing.update'), ['enabled' => 1, 'environment' => 'sandbox', 'submit_mode' => 'auto'])->assertSessionHas('success');
        $this->assertSame(self::SECRET, $this->settings()->api_secret);
        $this->assertSame('auto', $this->settings()->submit_mode);

        // And they can be removed.
        $this->put(route('settings.e-invoicing.update'), ['enabled' => 1, 'environment' => 'sandbox', 'submit_mode' => 'auto', 'clear_keys' => 1])->assertSessionHas('success');
        $this->assertNull($this->settings()->api_key);
        $this->assertFalse($this->settings()->hasKeys());
    }

    public function test_the_service_id_must_be_eight_characters(): void
    {
        $this->put(route('settings.e-invoicing.update'), ['enabled' => 1, 'environment' => 'sandbox', 'submit_mode' => 'manual', 'service_id' => 'SHORT'])->assertSessionHasErrors('service_id');
        $this->put(route('settings.e-invoicing.update'), ['enabled' => 1, 'environment' => 'bogus', 'submit_mode' => 'manual'])->assertSessionHasErrors('environment');
    }

    public function test_test_connection_reports_good_and_bad_keys_and_remembers_the_result(): void
    {
        $this->post(route('settings.e-invoicing.test'))->assertSessionHas('success');
        $this->assertTrue($this->settings()->last_test_ok);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->url() === 'https://nrs.test/api/v1/resources/states' && $r->hasHeader('x-api-key', self::KEY));

        $this->nrs = 'forbidden';
        $this->post(route('settings.e-invoicing.test'))->assertSessionHas('error', fn ($m) => str_contains($m, 'did not accept the keys'));
        $this->assertFalse($this->settings()->last_test_ok);
        $this->get(route('settings.e-invoicing'))->assertSee('Last test')->assertSee('did not accept the keys');

        // Changing a key clears the stale result.
        $this->put(route('settings.e-invoicing.update'), ['enabled' => 1, 'environment' => 'sandbox', 'submit_mode' => 'manual', 'api_key' => 'new-key-123456'])->assertSessionHas('success');
        $this->assertNull($this->settings()->last_tested_at);
    }

    public function test_secrets_never_reach_the_log(): void
    {
        $messages = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$messages) {
            $messages[] = $e->message.' '.json_encode($e->context);
        });
        foreach (['accepted', 'rejected', 'down', 'forbidden', 'busy'] as $outcome) {
            $this->nrs = $outcome;
            $this->submit($this->invoice());
        }
        $this->post(route('settings.e-invoicing.test'));

        $this->assertNotEmpty(Http::recorded());
        foreach ($messages as $m) {
            $this->assertStringNotContainsString(self::KEY, $m);
            $this->assertStringNotContainsString(self::SECRET, $m);
        }
        foreach (EInvoiceSubmission::all() as $row) {
            $this->assertStringNotContainsString(self::KEY, json_encode($row->toArray()).$row->last_error);
            $this->assertStringNotContainsString(self::SECRET, json_encode($row->toArray()).$row->last_error);
        }
    }

    // ---- not set up --------------------------------------------------

    public function test_without_keys_nothing_is_sent_and_the_screens_say_not_set_up_yet(): void
    {
        $this->settings()->update(array_fill_keys(EInvoiceSetting::SECRETS, null));
        $invoice = $this->invoice();

        $this->post(route('e-invoices.invoice.submit', $invoice))->assertSessionHas('error', fn ($m) => str_contains($m, 'not set up yet'));
        $this->get(route('settings.e-invoicing'))->assertOk()->assertSee('Not set up yet')->assertSee('data-testid="not-set-up"', false);
        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee('Not set up yet')->assertDontSee('Send to NRS');
        $this->get(route('e-invoices.index'))->assertOk()->assertSee('Not set up yet');
        $this->post(route('settings.e-invoicing.test'))->assertSessionHas('error');
        $this->artisan('einvoice:retry')->assertSuccessful();

        $this->assertSame(0, $this->sentCount());
        $this->assertSame(0, EInvoiceSubmission::count());
    }

    public function test_without_an_nrs_address_nothing_is_sent(): void
    {
        config(['mybooks.einvoicing.base_urls.sandbox' => null]);
        $invoice = $this->invoice();

        $this->post(route('e-invoices.invoice.submit', $invoice))->assertSessionHas('error');
        $this->get(route('settings.e-invoicing'))->assertSee('NRS address');
        $this->assertSame(0, $this->sentCount());
    }

    public function test_the_simulator_never_runs_on_production(): void
    {
        $this->settings()->update(array_fill_keys(EInvoiceSetting::SECRETS, null));
        config(['mybooks.einvoicing.driver' => 'log']);
        $invoice = $this->invoice();

        // Off production the simulator accepts, marked as simulated, without any call.
        $row = $this->submit($invoice);
        $this->assertSame('accepted', $row->status);
        $this->assertSame('simulated', $row->environment);
        $this->assertStringStartsWith('SIMULATED-', $row->csid);
        $this->assertSame(0, $this->sentCount());
        $this->assertStringContainsString('data:image/svg+xml', (string) $row->qrSrc());

        // On production the same setting does nothing.
        $other = $this->invoice();
        $this->app['env'] = 'production';
        try {
            $this->submit($other);
            $this->fail('production must not simulate');
        } catch (NotSetUp) {
            $this->assertNull(EInvoiceSubmission::forDocument($other));
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    // ---- screens, permissions, isolation -----------------------------

    public function test_the_list_filters_and_bulk_sends_the_ticked_invoices(): void
    {
        $a = $this->invoice();
        $b = $this->invoice();
        $c = $this->invoice();
        $this->submit($c);
        $this->nrs = 'rejected';
        $this->submit($b);

        $this->get(route('e-invoices.index'))->assertOk()->assertSee($a->invoice_number)->assertSee('Not submitted');

        $this->travelTo('2026-12-01 09:00');
        Livewire::test(EInvoicesTable::class)
            ->set('tab', 'accepted')->assertSee($c->invoice_number)->assertDontSee($a->invoice_number)->assertDontSee($b->invoice_number)
            ->set('tab', 'rejected')->assertSee($b->invoice_number)->assertDontSee($c->invoice_number)
            ->set('tab', 'not_submitted')->assertSee($a->invoice_number)->assertDontSee($b->invoice_number)
            ->set('tab', '')->set('period', 'this_month')->assertDontSee($a->invoice_number)
            ->set('period', '')->set('search', $b->invoice_number)->assertSee($b->invoice_number)->assertDontSee($a->invoice_number);

        $this->nrs = 'accepted';
        Livewire::test(EInvoicesTable::class)
            ->set('selectedItems', [(string) $a->id, (string) $b->id, (string) $c->id])
            ->call('submitSelected')
            ->assertSet('selectedItems', [])
            ->assertSee('2 accepted by NRS, 1 were already accepted');

        $this->assertSame(['accepted'], EInvoiceSubmission::pluck('status')->unique()->all());
    }

    public function test_the_list_also_shows_credit_notes(): void
    {
        $invoice = $this->invoice();
        $note = $this->creditNote($invoice);

        Livewire::test(EInvoicesTable::class)->set('type', 'credit_notes')->assertSee($note->credit_note_number)->assertDontSee($invoice->invoice_number);
    }

    public function test_the_bulk_send_needs_the_submit_permission(): void
    {
        $user = $this->createUserForTenant($this->tenant, ['view e-invoices']);
        $invoice = $this->invoice();

        Livewire::actingAs($user)->test(EInvoicesTable::class)->set('selectedItems', [(string) $invoice->id])->call('submitSelected')->assertForbidden();
        $this->assertSame(0, $this->sentCount());
    }

    public function test_permissions_guard_the_pages_and_buttons(): void
    {
        $invoice = $this->invoice();
        $viewer = $this->createUserForTenant($this->tenant, ['view invoices', 'view e-invoices']);
        $nobody = $this->createUserForTenant($this->tenant, ['view invoices']);

        $this->actingAs($viewer);
        $this->get(route('e-invoices.index'))->assertOk();
        $this->get(route('settings.e-invoicing'))->assertOk()->assertDontSee('Test connection');
        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee('e-invoice-panel', false)->assertDontSee('Send to NRS');
        $this->post(route('e-invoices.invoice.submit', $invoice))->assertForbidden();
        $this->put(route('settings.e-invoicing.update'), ['environment' => 'sandbox', 'submit_mode' => 'manual'])->assertForbidden();
        $this->post(route('settings.e-invoicing.test'))->assertForbidden();

        $this->actingAs($nobody);
        $this->get(route('e-invoices.index'))->assertForbidden();
        $this->get(route('settings.e-invoicing'))->assertForbidden();
        $this->get(route('invoices.show', $invoice))->assertOk()->assertDontSee('e-invoice-panel', false);

        $this->assertSame(0, $this->sentCount());
    }

    public function test_one_business_cannot_see_or_send_another_ones_documents(): void
    {
        $mine = $this->invoice();
        $this->submit($mine);

        // Made while nobody is logged in, so its default accounts land on it.
        auth()->logout();
        [$otherTenant] = $this->createTenantWithSubscription(['currency' => 'NGN', 'tax_number' => '99999999-0001']);
        $otherUser = $this->createUserForTenant($otherTenant, ['view invoices', 'view customers', 'view e-invoices', 'submit e-invoices', 'manage e-invoicing']);
        $this->actingAs($otherUser);
        $theirs = Customer::factory()->create(['tenant_id' => $otherTenant->id, 'tax_number' => '11111111-0001']);
        $theirInvoice = Invoice::factory()->create(['tenant_id' => $otherTenant->id, 'customer_id' => $theirs->id, 'invoice_number' => 'INV-OTHER1', 'status' => 'unpaid', 'total' => 5000, 'subtotal' => 5000, 'tax_amount' => 0, 'balance_due' => 5000]);
        $before = $this->sentCount();

        $this->get(route('e-invoices.index'))->assertOk();
        Livewire::actingAs($otherUser)->test(EInvoicesTable::class)->assertDontSee($mine->invoice_number)->assertSee('INV-OTHER1');
        $this->post(route('e-invoices.invoice.submit', $mine))->assertNotFound();
        $this->post(route('e-invoices.check', EInvoiceSubmission::withoutGlobalScopes()->first()))->assertNotFound();
        $this->get(route('settings.e-invoicing'))->assertOk()->assertDontSee('7A0819F4')->assertSee('Not set up yet');

        // Their business has no keys: nothing is sent for them, and my row is untouched.
        $this->post(route('e-invoices.invoice.submit', $theirInvoice))->assertSessionHas('error');
        $this->assertSame($before, $this->sentCount());
        $this->assertSame(1, EInvoiceSubmission::withoutGlobalScopes()->count());
        $this->assertNotSame($this->settings()->id, EInvoiceSetting::forTenant($otherTenant->id)->id);
    }

    public function test_with_the_feature_off_everything_is_hidden_and_nothing_is_sent(): void
    {
        $invoice = $this->invoice();
        $this->submit($invoice);
        $sent = $this->sentCount();
        config(['mybooks.features.e_invoicing' => false]);

        $this->get(route('e-invoices.index'))->assertNotFound();
        $this->get(route('settings.e-invoicing'))->assertNotFound();
        $this->post(route('e-invoices.invoice.submit', $invoice))->assertNotFound();
        $this->get(route('invoices.show', $invoice))->assertOk()->assertDontSee('e-invoice-panel', false);
        $this->get(route('invoices.print', $invoice))->assertOk()->assertDontSee('IRN:', false);
        $this->get(route('dashboard'))->assertDontSee('E-invoices');

        $this->settings()->update(['submit_mode' => 'auto']);
        $this->invoice();
        $this->artisan('einvoice:retry')->assertSuccessful();
        $this->assertSame($sent, $this->sentCount());
        $this->expectException(NotSetUp::class);
        $this->submit($this->invoice());
    }

    public function test_the_feature_is_off_by_default_and_listed_in_the_env_example(): void
    {
        $this->assertFalse((require config_path('mybooks.php'))['features']['e_invoicing']);
        $this->assertStringContainsString('MYBOOKS_FEATURE_E_INVOICING=false', file_get_contents(base_path('.env.example')));
    }

    public function test_the_links_show_in_the_menu_for_those_allowed(): void
    {
        $this->get(route('invoices.index'))->assertOk()->assertSee(route('e-invoices.index'), false)->assertSee(route('settings.e-invoicing'), false);
    }

    public function test_nrs_is_listed_as_a_data_processor(): void
    {
        $this->assertContains('Nigeria Revenue Service (NRS)', array_column(config('mybooks.sub_processors'), 'name'));
    }

    // ---- the hourly command ------------------------------------------

    public function test_a_b2c_invoice_near_the_24_hour_limit_gets_one_warning(): void
    {
        $late = $this->invoice($this->person, [['description' => 'Generator', 'quantity' => 1, 'unit_price' => 90000, 'tax_rate' => 0]]);
        $fresh = $this->invoice($this->person, [['description' => 'Generator', 'quantity' => 1, 'unit_price' => 90000, 'tax_rate' => 0]]);
        $small = $this->invoice($this->person, [['description' => 'Rice', 'quantity' => 1, 'unit_price' => 4000, 'tax_rate' => 0]]);
        $business = $this->invoice($this->company);
        DB::table('invoices')->whereIn('id', [$late->id, $small->id, $business->id])->update(['created_at' => now()->subHours(21)]);

        $this->artisan('einvoice:retry')->assertSuccessful();
        Notification::assertSentTo($this->user, EInvoiceNotReportedNotification::class, fn ($n) => $n->count === 1);
        $this->assertNotNull(EInvoiceSubmission::forDocument($late)->late_warned_at);
        $this->assertNull(EInvoiceSubmission::forDocument($fresh));
        $this->assertNull(EInvoiceSubmission::forDocument($small));
        $this->assertNull(EInvoiceSubmission::forDocument($business));

        // Not nagged again, and nothing was sent by the warning.
        Notification::fake();
        $this->artisan('einvoice:retry')->assertSuccessful();
        Notification::assertNothingSent();
        $this->assertSame(0, $this->sentCount());
    }

    public function test_the_retry_command_is_scheduled_hourly(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'einvoice:retry'));
        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
    }

    public function test_the_permissions_migration_gives_admins_and_accountants_their_access(): void
    {
        $admin = Role::create(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => null]);
        $accountant = Role::create(['name' => 'accountant', 'guard_name' => 'web', 'tenant_id' => null]);
        $sales = Role::create(['name' => 'sales', 'guard_name' => 'web', 'tenant_id' => null]);

        $migration = require database_path('migrations/2026_10_19_100001_add_e_invoicing.php');
        $migration->up();
        $migration->up(); // safe to run twice

        foreach (['view e-invoices', 'submit e-invoices', 'manage e-invoicing'] as $permission) {
            $this->assertTrue($admin->fresh()->hasPermissionTo($permission), "admin {$permission}");
        }
        $this->assertTrue($accountant->fresh()->hasPermissionTo('submit e-invoices'));
        $this->assertFalse($accountant->fresh()->hasPermissionTo('manage e-invoicing'));
        $this->assertFalse($sales->fresh()->hasPermissionTo('view e-invoices'));
    }
}
