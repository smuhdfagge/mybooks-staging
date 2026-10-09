<?php

namespace Tests\Feature\Features;

use App\Actions\Payments\RecordPaymentReceived;
use App\Models\AdminUser;
use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\Invoice;
use App\Models\MessageSetting;
use App\Models\NotificationSetting;
use App\Notifications\InvoiceOverdueNotification;
use App\Notifications\MessageAllowanceUsedNotification;
use App\Services\Messaging\CustomerMessenger;
use App\Services\Messaging\MessageTemplates;
use App\Support\PhoneNumber;
use App\Support\SmsText;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Session 16: SMS and WhatsApp reminders and receipts to customers.
 */
class SmsWhatsAppRemindersTest extends TestCase
{
    private const KEY = 'TLtestKEYdo-not-log-1234';

    private const TOKEN = 'hook-token-abc123';

    private int $nextId = 0;

    private int $invoiceNo = 100;

    /** HTTP status the fake Termii answers with. */
    private int $termiiStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();
        // 11:00 in Lagos
        $this->travelTo(Carbon::parse('2026-10-06 10:00:00'));
        config([
            'mybooks.features.sms_whatsapp' => true,
            'services.termii.api_key' => self::KEY,
            'services.termii.base_url' => 'https://termii.test',
            'services.termii.sender_id' => 'MyBooks',
            'services.termii.sms_channel' => 'dnd',
            'services.termii.secret_key' => null,
            'services.termii.whatsapp_device_id' => null,
            'services.whatsapp_meta.token' => 'meta-token',
            'services.whatsapp_meta.phone_number_id' => '1099887766',
            'services.whatsapp_meta.api_version' => 'v25.0',
            'services.whatsapp_meta.base_url' => 'https://graph.facebook.com',
            'services.whatsapp_meta.app_secret' => null,
            'mybooks.messaging.sms_driver' => 'auto',
            'mybooks.messaging.whatsapp_driver' => 'auto',
            'mybooks.messaging.webhook_token' => self::TOKEN,
        ]);
        Notification::fake();

        $this->createAuthenticatedUser(['view settings', 'edit settings', 'view invoices', 'send invoices', 'create customers', 'create payments-received']);
        $this->tenant->update(['name' => 'Kano Traders Ltd', 'currency' => 'NGN']);
        $this->plan->update(['sms_monthly_limit' => 10, 'whatsapp_monthly_limit' => 5]);
        $this->subscription->update(['ends_at' => now()->addYear()]);

        Http::preventStrayRequests();
        Http::fake([
            'termii.test/api/sms/send' => fn () => $this->termiiStatus === 200
                ? Http::response(['code' => 'ok', 'message_id' => 'T'.(++$this->nextId), 'message' => 'Successfully Sent', 'balance' => 990, 'user' => 'MyBooks'])
                : Http::response(['message' => 'Server error'], $this->termiiStatus),
            'termii.test/api/send/template' => fn () => Http::response(['code' => 'ok', 'message_id' => 'W'.(++$this->nextId), 'message' => 'Successfully Sent']),
            'termii.test/api/get-balance*' => Http::response(['application' => 'MyBooks', 'balance' => 990.5, 'currency' => 'NGN', 'user' => 'MyBooks']),
            'graph.facebook.com/*' => fn () => Http::response(['messaging_product' => 'whatsapp', 'contacts' => [['wa_id' => '2348031234567']], 'messages' => [['id' => 'wamid.'.(++$this->nextId)]]]),
        ]);
    }

    // ---- helpers ----------------------------------------------------------

    private function customer(?string $phone = '0803 123 4567', array $attributes = []): Customer
    {
        return Customer::factory()->create(array_merge(['tenant_id' => $this->tenant->id, 'name' => 'Musa Ibrahim', 'phone' => $phone, 'email' => 'musa@example.com'], $attributes));
    }

    private function invoice(Customer $customer, array $attributes = []): Invoice
    {
        return Invoice::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-000'.(++$this->invoiceNo),
            'status' => 'unpaid', 'total' => 25000, 'subtotal' => 25000, 'tax_amount' => 0, 'balance_due' => 25000, 'due_date' => now()->addDays(3),
        ], $attributes));
    }

    private function switchOn(array $fields): MessageSetting
    {
        $settings = MessageSetting::forTenant($this->tenant->id);
        $settings->update($fields);

        return $settings;
    }

    private function runReminders(): void
    {
        $this->artisan('notifications:send-payment-reminders', ['--tenant' => $this->tenant->id])->assertSuccessful();
    }

    private function smsRequests(): array
    {
        return Http::recorded(fn (Request $r) => str_contains($r->url(), '/api/sms/send'))->all();
    }

    // ---- phone numbers and SMS length --------------------------------------

    public function test_phone_numbers_become_plus_234(): void
    {
        foreach (['08031234567', '0803 123 4567', '803-123-4567', '2348031234567', '+234 803 123 4567', '+234 (0) 803 123 4567'] as $raw) {
            $this->assertSame('+2348031234567', PhoneNumber::normalise($raw), $raw);
        }
        $this->assertSame('+2347012345678', PhoneNumber::normalise('07012345678'));
        $this->assertSame('+2349123456789', PhoneNumber::normalise('0912 345 6789'));
        $this->assertSame('+2348112345678', PhoneNumber::normalise('0811-234-5678'));
        $this->assertNull(PhoneNumber::normalise('01-2345678'));       // Lagos landline
        $this->assertNull(PhoneNumber::normalise('06012345678'));      // not a mobile prefix
        $this->assertNull(PhoneNumber::normalise('call me'));
        $this->assertNull(PhoneNumber::normalise(''));
        $this->assertSame('+447700900123', PhoneNumber::normalise('+44 7700 900123'));
        $this->assertSame('+447700900123', PhoneNumber::normalise('0044 7700 900123'));
        $this->assertNull(PhoneNumber::normalise('447700900123'));     // other countries need the +
        $this->assertSame('+234 803 *** 4567', PhoneNumber::mask('+2348031234567'));
    }

    public function test_customer_form_saves_a_mobile_as_plus_234_and_rejects_nonsense(): void
    {
        $this->post(route('customers.store'), ['name' => 'Aisha', 'phone' => '0803 123 4567', 'sms_opt_out' => '0', 'whatsapp_opt_out' => '1'])
            ->assertSessionHasNoErrors();
        $customer = Customer::where('name', 'Aisha')->firstOrFail();
        $this->assertSame('+2348031234567', $customer->phone);
        $this->assertTrue($customer->whatsapp_opt_out);
        $this->assertFalse($customer->sms_opt_out);

        // A landline is kept as typed (it just can't take SMS).
        $this->post(route('customers.store'), ['name' => 'Office', 'phone' => '01-2345678'])->assertSessionHasNoErrors();
        $this->assertSame('01-2345678', Customer::where('name', 'Office')->value('phone'));

        $this->post(route('customers.store'), ['name' => 'Bad', 'phone' => 'call after 5'])->assertSessionHasErrors('phone');
    }

    public function test_sms_pages_are_counted(): void
    {
        $this->assertSame(1, SmsText::segments(str_repeat('a', 160)));
        $this->assertSame(2, SmsText::segments(str_repeat('a', 161)));
        $this->assertSame(3, SmsText::segments(str_repeat('a', 307)));
        $this->assertSame(2, SmsText::length('€'));                     // GSM extension character counts twice
        $this->assertSame(1, SmsText::segments(str_repeat('a', 68).'😀')); // Unicode: 70 a page (an emoji counts 2)
        $this->assertSame(2, SmsText::segments(str_repeat('a', 69).'😀'));
        // Curly quotes and ₦ are swapped for plain ones so the SMS stays at 160 a page.
        $this->assertTrue(SmsText::isGsm(SmsText::clean('Musa’s balance is ₦5,000 – thanks…')));
    }

    // ---- provider requests --------------------------------------------------

    public function test_termii_request_uses_the_dnd_route_and_the_api_key_is_never_logged(): void
    {
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logged) {
            $logged[] = $e->message.' '.json_encode($e->context);
        });
        $this->switchOn(['payment_reminder_sms' => true]);
        $this->invoice($this->customer());

        $this->runReminders();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://termii.test/api/sms/send'
            && $r['api_key'] === self::KEY && $r['to'] === '2348031234567' && $r['from'] === 'MyBooks'
            && $r['channel'] === 'dnd' && $r['type'] === 'plain'
            && $r['sms'] === 'Kano Traders Ltd: Hello Musa Ibrahim, a reminder that invoice INV-000101 for NGN 25,000 is due on 9 Oct 2026. Please pay on time. Thank you.');
        $message = CustomerMessage::sole();
        $this->assertSame(['sent', 'termii', 'T1', 1], [$message->status, $message->provider, $message->provider_message_id, $message->segments]);
        $this->assertNotEmpty($logged);
        foreach ($logged as $line) {
            $this->assertStringNotContainsString(self::KEY, $line);
        }
    }

    public function test_whatsapp_through_meta_sends_the_approved_template_with_values_in_order(): void
    {
        $this->switchOn(['overdue_whatsapp' => true]);
        $this->invoice($this->customer(), ['due_date' => now()->subDay(), 'balance_due' => 12500.5]);

        $this->runReminders();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://graph.facebook.com/v25.0/1099887766/messages'
            && $r->hasHeader('Authorization', 'Bearer meta-token')
            && $r['messaging_product'] === 'whatsapp' && $r['to'] === '2348031234567' && $r['type'] === 'template'
            && $r['template']['name'] === 'mybooks_invoice_overdue' && $r['template']['language']['code'] === 'en'
            && array_column($r['template']['components'][0]['parameters'], 'text') === ['Musa Ibrahim', 'Kano Traders Ltd', 'INV-000101', 'NGN 12,500.50', '5 Oct 2026']);
        $message = CustomerMessage::sole();
        $this->assertSame(['whatsapp', 'overdue', 'sent', 'meta'], [$message->channel, $message->type, $message->status, $message->provider]);
        $this->assertStringContainsString('Kano Traders Ltd reminds you that invoice INV-000101', $message->body);
    }

    public function test_whatsapp_through_termii_uses_the_template_endpoint(): void
    {
        config(['services.termii.whatsapp_device_id' => 'device-123']);
        $this->switchOn(['payment_reminder_whatsapp' => true]);
        $this->invoice($this->customer());

        $this->runReminders();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://termii.test/api/send/template'
            && $r['device_id'] === 'device-123' && $r['template_id'] === 'mybooks_payment_reminder' && $r['phone_number'] === '2348031234567'
            && ((array) $r['data'])['business'] === 'Kano Traders Ltd' && ((array) $r['data'])['amount'] === 'NGN 25,000');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'graph.facebook.com'));
    }

    // ---- scheduled reminders ------------------------------------------------

    public function test_reminder_command_texts_customers_with_a_mobile_and_skips_the_rest(): void
    {
        $this->switchOn(['payment_reminder_sms' => true]);
        $withPhone = $this->invoice($this->customer());
        $this->invoice($this->customer(null, ['name' => 'No Phone']));
        $this->invoice($this->customer('01-2345678', ['name' => 'Landline']));
        $this->invoice($this->customer('08099998888', ['name' => 'Opted Out', 'sms_opt_out' => true]));
        $this->invoice($this->customer('08077776666', ['name' => 'Not due yet']), ['due_date' => now()->addDays(10)]);
        $this->invoice($this->customer('08055554444', ['name' => 'Overdue']), ['due_date' => now()->subDay()]); // overdue SMS is off
        $this->invoice($this->customer('08033332222', ['name' => 'Paid']), ['status' => 'paid', 'balance_due' => 0]);

        $this->runReminders();

        $this->assertCount(1, $this->smsRequests());
        $this->assertSame([$withPhone->id], CustomerMessage::pluck('invoice_id')->all());

        // Run again the same day: nothing goes twice.
        $this->runReminders();
        $this->assertCount(1, $this->smsRequests());
        $this->assertSame(1, CustomerMessage::count());
    }

    public function test_overdue_reminders_go_on_day_1_then_every_n_days(): void
    {
        NotificationSetting::getForTenant($this->tenant->id)->update(['overdue_reminder_days' => 7]);
        $this->switchOn(['overdue_sms' => true]);
        $customer = $this->customer();
        foreach ([1, 5, 7, 14, 21, 28] as $late) {
            $this->invoice($customer, ['due_date' => now()->subDays($late)]);
        }

        $this->runReminders();

        $sent = CustomerMessage::with('invoice')->get()->map(fn ($m) => (int) round($m->invoice->due_date->diffInDays(now()->startOfDay(), true)))->sort()->values()->all();
        $this->assertSame([1, 7, 14, 21], $sent); // at most 4 reminders, day 28 is past that
        $this->assertSame([1, 7, 14, 21], app(CustomerMessenger::class)->overdueDays(7));
        $this->assertStringContainsString('was due on 5 Oct 2026 and is not yet paid', CustomerMessage::where('dedupe_key', 'like', '%:1')->value('body'));
    }

    public function test_monthly_allowance_stops_sms_and_emails_still_go(): void
    {
        $this->plan->update(['sms_monthly_limit' => 2]);
        $this->switchOn(['overdue_sms' => true]);
        $customers = [];
        foreach (['08011111111', '08022222222', '08033333333'] as $phone) {
            $customers[] = $customer = $this->customer($phone, ['email' => "c{$phone}@example.com"]);
            $this->invoice($customer, ['due_date' => now()->subDay()]);
        }

        $this->runReminders();

        $this->assertCount(2, $this->smsRequests());
        $this->assertSame(2, CustomerMessage::count());
        foreach ($customers as $customer) {
            Notification::assertSentTo($customer, InvoiceOverdueNotification::class); // the emails all went
        }
        Notification::assertSentToTimes($this->user, MessageAllowanceUsedNotification::class, 1);

        $this->get(route('settings.messaging'))->assertOk()->assertSee('used 2 of 2 SMS this month', false);

        // The notice is shown once a month, not on every run.
        $this->travel(1)->days();
        $this->invoice($customers[0], ['due_date' => now()->subDay()]);
        $this->runReminders();
        $this->assertSame(2, CustomerMessage::count());
        Notification::assertSentToTimes($this->user, MessageAllowanceUsedNotification::class, 1);
    }

    public function test_a_long_sms_counts_once_per_page(): void
    {
        $this->plan->update(['sms_monthly_limit' => 3]);
        $this->switchOn(['payment_reminder_sms' => true, 'payment_reminder_text' => '{business}: '.str_repeat('Please pay. ', 20)]);
        $this->invoice($this->customer());
        $this->invoice($this->customer('08022222222'));

        $this->runReminders();

        $this->assertSame(1, CustomerMessage::count()); // 2 pages used; the next needs 2 but only 1 is left
        $this->assertSame(2, CustomerMessage::sole()->segments);
    }

    public function test_allowance_starts_again_next_month(): void
    {
        $this->plan->update(['sms_monthly_limit' => 1]);
        $this->switchOn(['overdue_sms' => true]);
        $customer = $this->customer();
        $this->invoice($customer, ['due_date' => now()->subDay()]);
        $this->invoice($customer, ['due_date' => now()->subDay()]);
        $this->runReminders();
        $this->assertSame(1, CustomerMessage::count());

        // 1 November, 08:00 Lagos
        $this->travelTo(Carbon::parse('2026-11-01 07:00:00'));
        $this->invoice($customer, ['due_date' => now()->subDay()]);
        $this->runReminders();

        $this->assertSame(2, CustomerMessage::count());
        $this->get(route('settings.messaging'))->assertSee('used 1 of 1 SMS this month', false);
    }

    public function test_reminders_wait_for_7am_but_receipts_go_at_once(): void
    {
        $this->travelTo(Carbon::parse('2026-10-06 20:30:00')); // 21:30 Lagos
        $this->switchOn(['payment_received_sms' => true]);
        $customer = $this->customer();
        $invoice = $this->invoice($customer, ['due_date' => now()->subDays(2)]);

        $this->post(route('invoices.messages.store', $invoice), ['channel' => 'sms'])->assertSessionHas('success');
        $held = CustomerMessage::sole();
        $this->assertSame('queued', $held->status);
        $this->assertSame('2026-10-07 06:00:00', $held->send_after->utc()->toDateTimeString()); // 07:00 Lagos
        $this->assertCount(0, $this->smsRequests());

        // A receipt late at night goes straight away.
        app(RecordPaymentReceived::class)->handle($this->tenant->id, [
            'customer_id' => $customer->id, 'invoice_id' => $invoice->id, 'payment_date' => now()->toDateString(), 'amount' => 10000, 'payment_method' => 'cash',
        ], $this->user->id);
        $this->assertCount(1, $this->smsRequests());

        // At 07:05 Lagos the held reminder goes.
        $this->travelTo(Carbon::parse('2026-10-07 06:05:00'));
        $this->artisan('messages:send-queued')->assertSuccessful();
        $this->assertSame('sent', $held->fresh()->status);
        $this->assertCount(2, $this->smsRequests());
    }

    public function test_temporary_failures_are_retried_twice_then_marked_failed(): void
    {
        $this->termiiStatus = 503;
        $this->switchOn(['payment_reminder_sms' => true]);
        $this->invoice($this->customer());

        $this->runReminders();
        $message = CustomerMessage::sole();
        $this->assertSame(['queued', 1], [$message->status, $message->attempts]);

        $this->travel(2)->minutes();
        $this->artisan('messages:send-queued');
        $this->assertSame(['queued', 2], [$message->fresh()->status, $message->fresh()->attempts]);

        $this->travel(6)->minutes();
        $this->artisan('messages:send-queued');
        $message->refresh();
        $this->assertSame(['failed', 3], [$message->status, $message->attempts]);
        $this->assertCount(3, $this->smsRequests());
        // A failed message doesn't use up the allowance.
        $this->get(route('settings.messaging'))->assertSee('used 0 of 10 SMS this month', false);
    }

    public function test_a_refused_message_is_failed_without_retrying(): void
    {
        $this->termiiStatus = 400;
        $this->switchOn(['payment_reminder_sms' => true]);
        $this->invoice($this->customer());

        $this->runReminders();

        $message = CustomerMessage::sole();
        $this->assertSame(['failed', 'Termii: Server error'], [$message->status, $message->error]);
        $this->assertCount(1, $this->smsRequests());
    }

    // ---- invoice sent, payment received, manual send -----------------------

    public function test_sending_an_invoice_also_texts_it_when_switched_on(): void
    {
        $invoice = $this->invoice($this->customer(), ['status' => 'draft', 'due_date' => '2026-10-20']);
        $this->post(route('invoices.send', $invoice))->assertSessionHas('success');
        $this->assertSame(0, CustomerMessage::count()); // off by default

        $this->switchOn(['invoice_sent_sms' => true]);
        $other = $this->invoice($this->customer('08022222222'), ['status' => 'draft', 'due_date' => '2026-10-20']);
        $this->post(route('invoices.send', $other))->assertSessionHas('success');

        $message = CustomerMessage::sole();
        $this->assertSame(['invoice_sent', 'sent', $this->user->id], [$message->type, $message->status, $message->created_by]);
        $this->assertSame('Kano Traders Ltd: Hello Musa Ibrahim, invoice INV-000102 for NGN 25,000 is due on 20 Oct 2026. Thank you for your business.', $message->body);
    }

    public function test_recording_a_payment_sends_a_thank_you_receipt(): void
    {
        $this->switchOn(['payment_received_sms' => true]);
        $customer = $this->customer();
        $invoice = $this->invoice($customer);

        $this->post(route('payments-received.store'), [
            'customer_id' => $customer->id, 'invoice_id' => $invoice->id, 'payment_date' => now()->toDateString(), 'amount' => 10000, 'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();

        $message = CustomerMessage::sole();
        $this->assertSame('payment_received', $message->type);
        $this->assertSame('Kano Traders Ltd: Thank you Musa Ibrahim, we have received NGN 10,000 for invoice INV-000101. Balance left: NGN 15,000.', $message->body);
        $this->assertCount(1, $this->smsRequests());
    }

    public function test_manual_reminder_from_the_invoice_page(): void
    {
        $invoice = $this->invoice($this->customer(), ['due_date' => now()->subDays(3)]);

        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee('Send reminder');
        $this->post(route('invoices.messages.store', $invoice), ['channel' => 'sms'])
            ->assertRedirect(route('invoices.show', $invoice))->assertSessionHas('success', 'SMS reminder to +234 803 *** 4567 is on its way.');
        $message = CustomerMessage::sole();
        $this->assertSame(['overdue', 'sent', $this->user->id], [$message->type, $message->status, $message->created_by]);

        // Not again within a few minutes.
        $this->post(route('invoices.messages.store', $invoice), ['channel' => 'sms'])->assertSessionHas('error', CustomerMessenger::REASONS['recent']);

        // The invoice page lists it, with the number masked.
        $this->get(route('invoices.show', $invoice))->assertSee('+234 803 *** 4567')->assertDontSee('+2348031234567');

        // Needs the send invoices permission.
        $this->user->revokePermissionTo('send invoices');
        $this->post(route('invoices.messages.store', $invoice), ['channel' => 'whatsapp'])->assertForbidden();
    }

    public function test_manual_reminder_explains_why_it_cannot_go(): void
    {
        $noPhone = $this->invoice($this->customer(null));
        $this->post(route('invoices.messages.store', $noPhone), ['channel' => 'sms'])->assertSessionHas('error', CustomerMessenger::REASONS['no_phone']);

        $optedOut = $this->invoice($this->customer('08022222222', ['whatsapp_opt_out' => true]));
        $this->post(route('invoices.messages.store', $optedOut), ['channel' => 'whatsapp'])->assertSessionHas('error', CustomerMessenger::REASONS['opted_out']);

        $paid = $this->invoice($this->customer('08033333333'), ['status' => 'paid', 'balance_due' => 0]);
        $this->post(route('invoices.messages.store', $paid), ['channel' => 'sms'])->assertSessionHas('error', CustomerMessenger::REASONS['not_due']);

        $this->assertSame(0, CustomerMessage::count());
        Http::assertNothingSent();
    }

    // ---- delivery reports and STOP ------------------------------------------

    public function test_termii_delivery_report_updates_the_status_and_a_bad_token_is_refused(): void
    {
        $invoice = $this->invoice($this->customer());
        $this->post(route('invoices.messages.store', $invoice), ['channel' => 'sms']);
        $message = CustomerMessage::sole();

        $report = ['type' => 'sms', 'id' => 'req-1', 'message_id' => 'T1', 'receiver' => '2348031234567', 'sender' => 'MyBooks', 'status' => 'Delivered', 'cost' => 4.95, 'channel' => 'dnd'];
        $this->postJson('/webhooks/messaging/termii/wrong-token', $report)->assertStatus(401);
        $this->assertSame('sent', $message->fresh()->status);

        $this->postJson('/webhooks/messaging/termii/'.self::TOKEN, $report)->assertOk();
        $message->refresh();
        $this->assertSame(['delivered', '4.9500'], [$message->status, $message->cost]);
        $this->assertNotNull($message->delivered_at);

        // A late "sent" doesn't undo "delivered".
        $this->postJson('/webhooks/messaging/termii/'.self::TOKEN, ['message_id' => 'T1', 'status' => 'Message Sent'])->assertOk();
        $this->assertSame('delivered', $message->fresh()->status);

        // With Termii's secret key set, the signature must match too.
        config(['services.termii.secret_key' => 'termii-secret']);
        $body = json_encode(['message_id' => 'T1', 'status' => 'Message Failed']);
        $this->call('POST', '/webhooks/messaging/termii/'.self::TOKEN, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_TERMII_SIGNATURE' => 'bad'], $body)->assertStatus(401);
        $this->call('POST', '/webhooks/messaging/termii/'.self::TOKEN, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_TERMII_SIGNATURE' => hash_hmac('sha512', $body, 'termii-secret')], $body)->assertOk();
        $this->assertSame('delivered', $message->fresh()->status); // delivered can't turn failed
    }

    public function test_failed_report_and_meta_whatsapp_reports(): void
    {
        $this->post(route('invoices.messages.store', $this->invoice($this->customer())), ['channel' => 'sms']);
        $this->postJson('/webhooks/messaging/termii/'.self::TOKEN, ['message_id' => 'T1', 'status' => 'DND Active on Phone Number'])->assertOk();
        $sms = CustomerMessage::sole();
        $this->assertSame(['failed', 'Termii: DND Active on Phone Number'], [$sms->status, $sms->error]);

        $this->post(route('invoices.messages.store', $this->invoice($this->customer('08022222222'))), ['channel' => 'whatsapp']);
        $wa = CustomerMessage::where('channel', 'whatsapp')->sole();
        config(['services.whatsapp_meta.app_secret' => 'app-secret']);
        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['field' => 'messages', 'value' => ['statuses' => [['id' => $wa->provider_message_id, 'status' => 'delivered', 'recipient_id' => '2348022222222']]]]]]]]);
        $this->call('POST', '/webhooks/messaging/whatsapp/'.self::TOKEN, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=bad'], $body)->assertStatus(401);
        $this->call('POST', '/webhooks/messaging/whatsapp/'.self::TOKEN, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'app-secret')], $body)->assertOk();
        $this->assertSame('delivered', $wa->fresh()->status);

        // Meta's set-up check.
        config(['services.whatsapp_meta.verify_token' => 'verify-me']);
        $this->get('/webhooks/messaging/whatsapp/'.self::TOKEN.'?hub_mode=subscribe&hub_verify_token=verify-me&hub_challenge=12345')->assertOk()->assertSeeText('12345');
        $this->get('/webhooks/messaging/whatsapp/'.self::TOKEN.'?hub_mode=subscribe&hub_verify_token=nope&hub_challenge=12345')->assertForbidden();
    }

    public function test_provider_webhooks_skip_the_csrf_check(): void
    {
        // Laravel 13's web group runs PreventRequestForgery; leaving out the
        // old ValidateCsrfToken name didn't remove it, so Termii, Meta and
        // Paystack got 419. (The check is off in tests, hence this look at the
        // route's middleware.)
        $router = app('router');
        foreach (['billing.webhook', 'messaging.webhooks.termii', 'messaging.webhooks.whatsapp'] as $name) {
            $middleware = $router->gatherRouteMiddleware($router->getRoutes()->getByName($name));
            $csrf = array_filter($middleware, fn ($m) => is_string($m) && is_a($m, PreventRequestForgery::class, true));
            $this->assertSame([], array_values($csrf), $name);
        }
    }

    public function test_replying_stop_opts_the_customer_out_with_that_business_only(): void
    {
        $customer = $this->customer();
        $this->post(route('invoices.messages.store', $this->invoice($customer)), ['channel' => 'sms']);

        // Another business has a customer with the same number.
        [$otherTenant] = Customer::withoutTenantGuard(fn () => $this->createTenantWithSubscription());
        $stranger = Customer::withoutTenantGuard(fn () => Customer::factory()->create(['tenant_id' => $otherTenant->id, 'phone' => '+2348031234567']));

        $this->postJson('/webhooks/messaging/termii/'.self::TOKEN, ['type' => 'inbound', 'status' => 'Received', 'sender' => '2348031234567', 'receiver' => 'MyBooks', 'message' => ' stop ', 'channel' => 'generic'])->assertOk();

        $this->assertTrue($customer->fresh()->sms_opt_out);
        $this->assertFalse($customer->fresh()->whatsapp_opt_out);
        $this->assertFalse($stranger->fresh()->sms_opt_out);

        // Next reminder is skipped.
        $this->switchOn(['payment_reminder_sms' => true]);
        $this->invoice($customer);
        $this->runReminders();
        $this->assertSame(1, CustomerMessage::count());

        // A WhatsApp STOP opts out of WhatsApp.
        $this->post(route('invoices.messages.store', $this->invoice($customer)), ['channel' => 'whatsapp']);
        $this->postJson('/webhooks/messaging/whatsapp/'.self::TOKEN, ['entry' => [['changes' => [['value' => ['messages' => [['from' => '2348031234567', 'type' => 'text', 'text' => ['body' => 'STOP']]]]]]]]])->assertOk();
        $this->assertTrue($customer->fresh()->whatsapp_opt_out);
    }

    // ---- settings, usage, admin ---------------------------------------------

    public function test_settings_save_the_wording_and_check_it(): void
    {
        $this->get(route('settings.messaging'))->assertOk()
            ->assertSee('SMS wording')
            ->assertSee('{business}: Hello {customer}, a reminder that invoice', false)
            ->assertSee('data-testid="preview-overdue"', false);

        $this->put(route('settings.messaging.update'), ['overdue_sms' => '1', 'overdue_text' => 'Please pay {amount}'])
            ->assertSessionHasErrors('overdue_text');
        $this->put(route('settings.messaging.update'), ['overdue_sms' => '1', 'overdue_text' => '{business}: pay {amount} by {deadline}'])
            ->assertSessionHasErrors('overdue_text');

        $this->put(route('settings.messaging.update'), [
            'overdue_sms' => '1', 'overdue_whatsapp' => '0', 'overdue_text' => '{business}: {customer}, {invoice} ({amount}) is late. Pls pay.',
            'payment_reminder_text' => MessageTemplates::DEFAULTS['payment_reminder'],
        ])->assertSessionHasNoErrors();

        $settings = MessageSetting::forTenant($this->tenant->id);
        $this->assertTrue($settings->overdue_sms);
        $this->assertFalse($settings->overdue_whatsapp);
        $this->assertFalse($settings->invoice_sent_sms);
        $this->assertSame('{business}: {customer}, {invoice} ({amount}) is late. Pls pay.', $settings->overdue_text);
        $this->assertNull($settings->payment_reminder_text); // unchanged default stays the default

        $this->invoice($this->customer(), ['due_date' => now()->subDay()]);
        $this->runReminders();
        $this->assertSame('Kano Traders Ltd: Musa Ibrahim, INV-000101 (NGN 25,000) is late. Pls pay.', CustomerMessage::sole()->body);

        // Only with edit settings.
        $this->user->revokePermissionTo('edit settings');
        $this->put(route('settings.messaging.update'), ['overdue_sms' => '0'])->assertForbidden();
        $this->get(route('settings.messaging'))->assertOk()->assertDontSee('Send a test');
    }

    public function test_test_message_to_own_phone(): void
    {
        $this->post(route('settings.messaging.test'), ['test_phone' => '0803 123 4567', 'test_channel' => 'sms'])
            ->assertSessionHas('success', 'Test SMS sent to +234 803 *** 4567.');
        $this->assertSame('test', CustomerMessage::sole()->type);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/api/sms/send') && str_starts_with($r['sms'], 'Kano Traders Ltd: This is a test message'));

        $this->post(route('settings.messaging.test'), ['test_phone' => '01-2345678', 'test_channel' => 'sms'])
            ->assertSessionHas('error', CustomerMessenger::REASONS['bad_phone']);
    }

    public function test_usage_is_shown_to_the_business_and_the_platform_admin(): void
    {
        $this->post(route('invoices.messages.store', $this->invoice($this->customer())), ['channel' => 'sms']);
        $this->get(route('settings.messaging'))->assertSee('used 1 of 10 SMS this month', false)->assertSee('used 0 of 5 WhatsApp messages this month', false);
        $this->get(route('settings.messaging.messages'))->assertOk()->assertSee('INV-000101')->assertSee('+234 803 *** 4567');

        $admin = AdminUser::create(['name' => 'Ops', 'email' => 'ops@example.com', 'password' => 'Secret-123!', 'is_active' => true, 'role' => 'super_admin']);
        $this->actingAsPlatformAdmin($admin)->get(route('admin.messaging.index'))->assertOk()
            ->assertSee('Kano Traders Ltd')->assertSee('1 / 10')->assertSee('Termii balance: NGN 990.50');

        $this->actingAsPlatformAdmin($admin)->put(route('admin.messaging.plans.update'), ['plans' => [$this->plan->id => ['sms_monthly_limit' => 250, 'whatsapp_monthly_limit' => 40]]])
            ->assertRedirect(route('admin.messaging.index'));
        $this->assertSame([250, 40], [$this->plan->fresh()->sms_monthly_limit, $this->plan->fresh()->whatsapp_monthly_limit]);
    }

    public function test_one_business_cannot_text_or_see_another_business_customers(): void
    {
        [$otherTenant] = Customer::withoutTenantGuard(fn () => $this->createTenantWithSubscription());
        [$theirCustomer, $theirInvoice] = Customer::withoutTenantGuard(function () use ($otherTenant) {
            $customer = Customer::factory()->create(['tenant_id' => $otherTenant->id, 'phone' => '08055555555', 'name' => 'Their Customer']);

            $invoice = Invoice::factory()->create(['tenant_id' => $otherTenant->id, 'customer_id' => $customer->id, 'status' => 'draft', 'balance_due' => 500]);
            Invoice::withoutGlobalScopes()->whereKey($invoice->id)->update(['status' => 'unpaid']); // no journal needed here

            return [$customer, $invoice];
        });
        $this->assertNotSame($this->tenant->id, $theirInvoice->tenant_id);
        $theirMessage = new CustomerMessage;
        $theirMessage->skipTenantGuard = true;
        $theirMessage->forceFill(['tenant_id' => $otherTenant->id, 'customer_id' => $theirCustomer->id, 'invoice_id' => $theirInvoice->id, 'type' => 'overdue', 'channel' => 'sms', 'to' => '+2348055555555', 'body' => 'Their secret message', 'status' => 'sent'])->save();

        $this->post(route('invoices.messages.store', $theirInvoice), ['channel' => 'sms'])->assertNotFound();
        $this->get(route('settings.messaging.messages'))->assertOk()->assertDontSee('Their secret message')->assertDontSee('Their Customer');
        $this->get(route('settings.messaging'))->assertDontSee('Their secret message');
        $this->assertSame(1, CustomerMessage::withoutGlobalScopes()->count());
        Http::assertNothingSent();

        // Their usage isn't counted against ours.
        $this->get(route('settings.messaging'))->assertSee('used 0 of 10 SMS this month', false);
    }

    // ---- switched off / not set up ----------------------------------------------

    public function test_without_keys_messages_only_go_to_the_log(): void
    {
        config(['services.termii.api_key' => null, 'services.whatsapp_meta.token' => null]);
        $this->switchOn(['payment_reminder_sms' => true]);
        $this->invoice($this->customer());

        $this->get(route('settings.messaging'))->assertSee('Not set up yet — the MyBooks team needs to add SMS keys.');
        $this->runReminders();

        Http::assertNothingSent();
        $message = CustomerMessage::sole();
        $this->assertSame(['sent', 'log'], [$message->status, $message->provider]);

        // On the live site the log driver would only pretend: nothing is queued.
        $this->app['env'] = 'production';
        $this->assertSame('not_set_up', app(CustomerMessenger::class)->remind($this->invoice($this->customer('08022222222')), 'sms'));
        $this->assertSame(1, CustomerMessage::count());
    }

    public function test_switched_off_feature_sends_nothing(): void
    {
        config(['mybooks.features.sms_whatsapp' => false]);
        $this->switchOn(['payment_reminder_sms' => true, 'invoice_sent_sms' => true]);
        $invoice = $this->invoice($this->customer());

        $this->runReminders();
        $this->post(route('invoices.send', $this->invoice($this->customer('08022222222'), ['status' => 'draft'])));

        $this->get(route('settings.messaging'))->assertNotFound();
        $this->post(route('invoices.messages.store', $invoice), ['channel' => 'sms'])->assertNotFound();
        $this->postJson('/webhooks/messaging/termii/'.self::TOKEN, ['message_id' => 'T1', 'status' => 'Delivered'])->assertNotFound();
        $this->assertSame(0, CustomerMessage::count());
        Http::assertNothingSent();
    }
}
