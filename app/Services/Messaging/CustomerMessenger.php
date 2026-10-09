<?php

namespace App\Services\Messaging;

use App\Enums\MessageStatus;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Jobs\SendCustomerMessage;
use App\Models\CustomerMessage;
use App\Models\Invoice;
use App\Models\MessageSetting;
use App\Models\NotificationSetting;
use App\Models\PaymentReceived;
use App\Models\Tenant;
use App\Support\PhoneNumber;
use App\Support\SmsText;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SMS and WhatsApp messages to a business's customers (session 16):
 * invoice sent, reminder before the due date, overdue reminders and the
 * payment receipt.
 *
 * Every message is a customer_messages row first (status queued), which
 * also reserves it against the plan's monthly allowance, then a queued job
 * sends it. Nothing is sent from the page request itself.
 *
 * The methods return the queued message, or why nothing was queued (one of
 * the REASONS keys).
 */
class CustomerMessenger
{
    public const REASONS = [
        'off' => 'SMS and WhatsApp messages are switched off.',
        'not_set_up' => 'Not set up yet: the MyBooks team needs to add the SMS / WhatsApp keys.',
        'no_customer' => 'This invoice has no customer.',
        'no_phone' => 'The customer has no mobile number we can send to. Add one on the customer page.',
        'bad_phone' => 'That is not a mobile number we can send to.',
        'opted_out' => 'The customer has asked not to get these messages.',
        'limit' => "This month's allowance for your plan is used up.",
        'duplicate' => 'This message has already been sent.',
        'recent' => 'A reminder went to this customer a few minutes ago.',
        'not_due' => 'Nothing is owed on this invoice.',
    ];

    /** Invoice statuses that can still be paid. */
    private const OPEN = ['sent', 'unpaid', 'partial', 'overdue'];

    public function __construct(
        private MessagingDrivers $drivers,
        private MessageTemplates $templates,
        private MessageAllowance $allowance,
    ) {}

    public static function enabled(): bool
    {
        return EnsureFeatureEnabled::enabled('sms_whatsapp');
    }

    /**
     * After an invoice is emailed. Never throws: a problem here must not
     * undo the email or the payment that triggered it.
     *
     * @return array<string, CustomerMessage|string> by channel
     */
    public function invoiceSent(Invoice $invoice, ?int $userId = null): array
    {
        return $this->safely(fn () => $this->automatic('invoice_sent', $invoice, null, $userId));
    }

    /** @return array<string, CustomerMessage|string> by channel */
    public function paymentReceived(PaymentReceived $payment, ?int $userId = null): array
    {
        $invoice = $payment->invoice;

        return $invoice ? $this->safely(fn () => $this->automatic('payment_received', $invoice, $payment, $userId)) : [];
    }

    /** @return array<string, CustomerMessage|string> */
    private function safely(\Closure $send): array
    {
        try {
            return $send();
        } catch (\Throwable $e) {
            Log::error('Could not queue an SMS / WhatsApp message', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /** The "Send reminder" button on an unpaid invoice. */
    public function remind(Invoice $invoice, string $channel, ?int $userId = null): CustomerMessage|string
    {
        if (! self::enabled()) {
            return 'off';
        }
        if ((float) $invoice->balance_due <= 0 || ! in_array($invoice->status, self::OPEN, true)) {
            return 'not_due';
        }

        $recent = CustomerMessage::withoutGlobalScope('tenant')
            ->where('tenant_id', $invoice->tenant_id)->where('invoice_id', $invoice->id)->where('channel', $channel)
            ->whereIn('type', ['payment_reminder', 'overdue'])->whereIn('status', MessageStatus::counted())
            ->where('created_at', '>=', now()->subMinutes(10))->exists();
        if ($recent) {
            return 'recent';
        }

        $type = $invoice->due_date->toDateString() < $this->today()->toDateString() ? 'overdue' : 'payment_reminder';

        return $this->forInvoice($type, $channel, $invoice, null, null, $userId, MessageSetting::forTenant($invoice->tenant_id));
    }

    /**
     * Daily reminders (run by notifications:send-payment-reminders), using
     * the business's email reminder timing: "remind N days before the due
     * date", and for overdue invoices day 1 after the due date, then every
     * "Remind every" days, at most max_overdue_reminders times.
     */
    public function runScheduledReminders(Tenant $tenant): int
    {
        if (! self::enabled()) {
            return 0;
        }

        $settings = MessageSetting::forTenant($tenant->id);
        $before = array_filter(MessageSetting::CHANNELS, fn ($c) => $settings->channelOn('payment_reminder', $c));
        $after = array_filter(MessageSetting::CHANNELS, fn ($c) => $settings->channelOn('overdue', $c));
        if (! $before && ! $after) {
            return 0;
        }

        $timing = NotificationSetting::getForTenant($tenant->id);
        $today = $this->today();
        $open = fn () => Invoice::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)
            ->whereIn('status', self::OPEN)->where('balance_due', '>', 0)->whereNotNull('due_date')->with(['customer', 'tenant']);
        $count = 0;

        if ($before) {
            $due = $today->addDays(max(1, (int) $timing->payment_reminder_days_before))->toDateString();
            foreach ($open()->whereDate('due_date', $due)->get() as $invoice) {
                foreach ($before as $channel) {
                    $sent = $this->forInvoice('payment_reminder', $channel, $invoice, null, "payment_reminder:{$invoice->id}:{$channel}:{$due}", null, $settings);
                    $count += $sent instanceof CustomerMessage ? 1 : 0;
                }
            }
        }

        if ($after) {
            $days = $this->overdueDays(max(1, (int) $timing->overdue_reminder_days));
            $invoices = $open()->whereDate('due_date', '<', $today->toDateString())
                ->whereDate('due_date', '>=', $today->subDays(max($days))->toDateString())->get();
            foreach ($invoices as $invoice) {
                $late = (int) abs(CarbonImmutable::parse($invoice->due_date->toDateString(), $today->getTimezone())->diffInDays($today));
                if (! in_array($late, $days, true)) {
                    continue;
                }
                foreach ($after as $channel) {
                    $sent = $this->forInvoice('overdue', $channel, $invoice, null, "overdue:{$invoice->id}:{$channel}:{$late}", null, $settings);
                    $count += $sent instanceof CustomerMessage ? 1 : 0;
                }
            }
        }

        return $count;
    }

    /** Days after the due date on which an overdue reminder goes. @return array<int, int> */
    public function overdueDays(int $every): array
    {
        $max = max(1, (int) config('mybooks.messaging.max_overdue_reminders', 4));
        $days = [1];
        for ($day = $every; count($days) < $max; $day += $every) {
            if ($day > 1) {
                $days[] = $day;
            }
        }

        return $days;
    }

    /** Test message to the user's own phone, from the settings page. */
    public function sendTest(Tenant $tenant, string $phone, string $channel, ?int $userId): CustomerMessage|string
    {
        if (! self::enabled()) {
            return 'off';
        }
        $to = PhoneNumber::normalise($phone);
        if (! $to) {
            return 'bad_phone';
        }
        [$body, $params] = $this->compose('test', $channel, $this->templates->sample($tenant), null);

        return $this->queue($tenant, [
            'type' => 'test', 'channel' => $channel, 'to' => $to, 'body' => $body, 'params' => $params, 'created_by' => $userId,
        ], quietHours: false);
    }

    /** @return array<string, CustomerMessage|string> */
    private function automatic(string $type, Invoice $invoice, ?PaymentReceived $payment, ?int $userId): array
    {
        if (! self::enabled()) {
            return [];
        }

        $settings = MessageSetting::forTenant($invoice->tenant_id);
        $results = [];
        foreach (MessageSetting::CHANNELS as $channel) {
            if ($settings->channelOn($type, $channel)) {
                $key = $type.':'.($payment->id ?? $invoice->id).':'.$channel;
                $results[$channel] = $this->forInvoice($type, $channel, $invoice, $payment, $key, $userId, $settings);
            }
        }

        return $results;
    }

    private function forInvoice(string $type, string $channel, Invoice $invoice, ?PaymentReceived $payment, ?string $dedupeKey, ?int $userId, MessageSetting $settings): CustomerMessage|string
    {
        $customer = $invoice->customer;
        if (! $customer) {
            return 'no_customer';
        }
        if ($customer->getAttribute($channel.'_opt_out')) {
            return 'opted_out';
        }
        $to = PhoneNumber::normalise($customer->phone);
        if (! $to) {
            return 'no_phone';
        }

        $tenant = $invoice->tenant;
        [$body, $params] = $this->compose($type, $channel, $this->templates->values($tenant, $customer, $invoice, $payment), $settings);

        return $this->queue($tenant, [
            'type' => $type, 'channel' => $channel, 'to' => $to, 'body' => $body, 'params' => $params,
            'customer_id' => $customer->id, 'invoice_id' => $invoice->id, 'dedupe_key' => $dedupeKey, 'created_by' => $userId,
        ], quietHours: $type !== 'payment_received');
    }

    /**
     * @param  array<string, string>  $values
     * @return array{0: string, 1: array<string, string>|null}
     */
    private function compose(string $type, string $channel, array $values, ?MessageSetting $settings): array
    {
        if ($channel === 'sms') {
            return [$this->templates->render($settings ? $settings->text($type) : MessageTemplates::DEFAULTS[$type], $values), null];
        }
        $params = $this->templates->whatsappParams($type, $values);

        return [$this->templates->whatsappPreview($type, $params), $params];
    }

    /**
     * Reserve the message against the allowance and queue it. A lock on the
     * business's settings row keeps two senders from both taking the last
     * one; the unique dedupe key stops a reminder going twice.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function queue(Tenant $tenant, array $attributes, bool $quietHours): CustomerMessage|string
    {
        $channel = $attributes['channel'];
        // Without keys the 'log' driver only writes to the log: fine on a
        // developer's machine, but on the live site nothing would arrive.
        if (! $this->drivers->isLive($channel) && app()->isProduction()) {
            return 'not_set_up';
        }
        $segments = $channel === 'sms' ? SmsText::segments($attributes['body']) : 1;
        $settingsId = MessageSetting::forTenant($tenant->id)->id;

        try {
            $message = DB::transaction(function () use ($tenant, $attributes, $channel, $segments, $settingsId, $quietHours) {
                MessageSetting::withoutGlobalScopes()->whereKey($settingsId)->lockForUpdate()->first();

                $key = $attributes['dedupe_key'] ?? null;
                if ($key && CustomerMessage::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('dedupe_key', $key)->exists()) {
                    return 'duplicate';
                }
                if ($this->allowance->remaining($tenant, $channel) < $segments) {
                    return 'limit';
                }

                $message = new CustomerMessage;
                $message->skipTenantGuard = true; // the tenant is set here, also from the scheduler
                $message->forceFill($attributes + [
                    'tenant_id' => $tenant->id,
                    'segments' => $segments,
                    'status' => MessageStatus::Queued->value,
                    'provider' => $this->drivers->driverName($channel),
                    'send_after' => $quietHours ? $this->afterQuietHours() : null,
                ]);
                $message->save();

                return $message;
            });
        } catch (UniqueConstraintViolationException) {
            return 'duplicate';
        }

        if ($message === 'limit') {
            $this->allowance->noticeUsedUp($tenant, $channel);
        }
        if ($message instanceof CustomerMessage && $message->send_after === null) {
            self::dispatch($message);
        }

        return $message;
    }

    /** Hand a queued message to the queue (after the surrounding transaction commits). */
    public static function dispatch(CustomerMessage $message): void
    {
        $message->forceFill(['dispatched_at' => now()])->save();
        SendCustomerMessage::dispatch($message->id)->afterCommit();
    }

    /**
     * No reminders between quiet_from and quiet_until, Lagos time: such a
     * message waits until quiet_until (07:00). Null when it can go now.
     */
    public function afterQuietHours(): ?CarbonImmutable
    {
        $now = $this->now();
        $from = (int) config('mybooks.messaging.quiet_from', 21);
        $until = (int) config('mybooks.messaging.quiet_until', 7);
        if ($now->hour < $from && $now->hour >= $until) {
            return null;
        }
        $wake = $now->setTime($until, 0);

        return ($now->hour >= $from ? $wake->addDay() : $wake)->setTimezone(config('app.timezone'));
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now()->setTimezone((string) config('mybooks.messaging.timezone', 'Africa/Lagos'));
    }

    private function today(): CarbonImmutable
    {
        return $this->now()->startOfDay();
    }
}
