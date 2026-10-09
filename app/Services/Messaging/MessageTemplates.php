<?php

namespace App\Services\Messaging;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentReceived;
use App\Models\Tenant;
use App\Support\SmsText;
use Illuminate\Support\Str;

/**
 * The wording of SMS and WhatsApp messages (session 16).
 *
 * SMS: plain text the business can edit, starting with the business name
 * (the sender ID is MyBooks's, so the customer must see who it is from).
 * WhatsApp: fixed templates approved by Meta; the text here is what the
 * MyBooks team submits for approval (Utility category, English), with the
 * values in the order set in config('mybooks.messaging.whatsapp_templates').
 */
class MessageTemplates
{
    public const DEFAULTS = [
        'invoice_sent' => '{business}: Hello {customer}, invoice {invoice} for {amount} is due on {due_date}. Thank you for your business.',
        'payment_reminder' => '{business}: Hello {customer}, a reminder that invoice {invoice} for {amount} is due on {due_date}. Please pay on time. Thank you.',
        'overdue' => '{business}: Hello {customer}, invoice {invoice} for {amount} was due on {due_date} and is not yet paid. Please pay or call us. Thank you.',
        'payment_received' => '{business}: Thank you {customer}, we have received {amount} for invoice {invoice}. Balance left: {balance}.',
        'test' => '{business}: This is a test message from MyBooks. Your SMS messages are working.',
    ];

    /** Text to submit for approval. Footer for all: "Reply STOP to stop these messages." */
    public const WHATSAPP_TEXT = [
        'invoice_sent' => 'Hello {{1}}, {{2}} has sent you invoice {{3}} for {{4}}, due on {{5}}. Thank you for your business.',
        'payment_reminder' => 'Hello {{1}}, this is a reminder from {{2}} that invoice {{3}} for {{4}} is due on {{5}}. Please pay on time. Thank you.',
        'overdue' => 'Hello {{1}}, {{2}} reminds you that invoice {{3}} for {{4}} was due on {{5}} and is not yet paid. Please pay or contact them. Thank you.',
        'payment_received' => 'Hello {{1}}, {{2}} has received your payment of {{3}} for invoice {{4}}. Balance left: {{5}}. Thank you.',
        'test' => 'This is a test message from {{1}} on MyBooks. WhatsApp messages are working.',
    ];

    public const PLACEHOLDERS = [
        '{customer}' => "Customer's name",
        '{business}' => 'Your business name',
        '{invoice}' => 'Invoice number',
        '{amount}' => 'Amount due (amount paid, on a receipt)',
        '{due_date}' => 'Due date',
        '{balance}' => 'Balance left after a payment',
    ];

    /** Values for the placeholders. @return array<string, string> */
    public function values(Tenant $tenant, ?Customer $customer = null, ?Invoice $invoice = null, ?PaymentReceived $payment = null): array
    {
        $currency = $tenant->currency ?: 'NGN';

        return [
            'customer' => Str::limit(trim((string) $customer?->name), 30, ''),
            'business' => Str::limit(trim((string) $tenant->name), 30, ''),
            'invoice' => (string) $invoice?->invoice_number,
            'amount' => $this->money($payment ? (float) $payment->amount : (float) $invoice?->balance_due, $currency),
            'due_date' => $invoice?->due_date?->format('j M Y') ?? '',
            'balance' => $this->money(max(0, (float) $invoice?->balance_due), $currency),
        ];
    }

    /** Example values for the settings preview. @return array<string, string> */
    public function sample(Tenant $tenant): array
    {
        return [
            'customer' => 'Musa Ibrahim',
            'business' => Str::limit(trim((string) $tenant->name), 30, ''),
            'invoice' => 'INV-000123',
            'amount' => $this->money(125000, $tenant->currency ?: 'NGN'),
            'due_date' => now()->addDays(7)->format('j M Y'),
            'balance' => $this->money(0, $tenant->currency ?: 'NGN'),
        ];
    }

    /** @param array<string, string> $values */
    public function render(string $text, array $values): string
    {
        $pairs = [];
        foreach ($values as $key => $value) {
            $pairs['{'.$key.'}'] = $value;
        }

        return SmsText::clean(strtr($text, $pairs));
    }

    /**
     * The template values in the template's order.
     *
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    public function whatsappParams(string $type, array $values): array
    {
        $params = [];
        foreach ((array) config("mybooks.messaging.whatsapp_templates.{$type}.params", []) as $key) {
            $params[$key] = $values[$key] !== '' ? $values[$key] : '-'; // WhatsApp refuses empty values
        }

        return $params;
    }

    public function whatsappTemplate(string $type): string
    {
        return (string) config("mybooks.messaging.whatsapp_templates.{$type}.name");
    }

    /** The approved WhatsApp text filled in, for the log and the preview. @param array<string, string> $params */
    public function whatsappPreview(string $type, array $params): string
    {
        $pairs = [];
        foreach (array_values($params) as $i => $value) {
            $pairs['{{'.($i + 1).'}}'] = $value;
        }

        return strtr(self::WHATSAPP_TEXT[$type] ?? '', $pairs);
    }

    /** "NGN 125,000" (no ₦: it would make the SMS Unicode, 70 characters a page). */
    public function money(float $amount, string $currency): string
    {
        $decimals = abs($amount - round($amount)) < 0.005 ? 0 : 2;

        return $currency.' '.number_format($amount, $decimals);
    }
}
