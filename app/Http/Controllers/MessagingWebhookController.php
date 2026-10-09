<?php

namespace App\Http\Controllers;

use App\Enums\MessageStatus;
use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Delivery reports and replies from Termii and WhatsApp (session 16).
 *
 * The URL carries a secret token (MESSAGING_WEBHOOK_TOKEN); when the
 * provider's signing secret is set the signature is checked too:
 *  - Termii: X-Termii-Signature, HMAC-SHA512 of the body with the secret key
 *    (https://developers.termii.com/events-and-reports, checked 2026-10-06);
 *  - Meta: X-Hub-Signature-256, "sha256=" + HMAC-SHA256 with the app secret.
 *
 * A customer replying STOP (or UNSUBSCRIBE, ...) is opted out of that
 * channel by the business whose message they last got.
 */
class MessagingWebhookController extends Controller
{
    private const STOP_WORDS = ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT', 'OPTOUT'];

    public function termii(Request $request, string $token): JsonResponse
    {
        $payload = $request->getContent();
        $secret = (string) config('services.termii.secret_key');
        if (! $this->tokenMatches($token)
            || ($secret !== '' && ! hash_equals(hash_hmac('sha512', $payload, $secret), (string) $request->header('X-Termii-Signature')))) {
            Log::warning('Termii webhook refused (bad token or signature)', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid'], 401);
        }

        $event = json_decode($payload, true) ?: [];
        $status = strtolower(trim((string) ($event['status'] ?? '')));
        $channel = str_contains(strtolower((string) ($event['channel'] ?? '')), 'whatsapp') ? 'whatsapp' : 'sms';

        // A reply from a customer.
        if ($status === 'received' || str_contains(strtolower((string) ($event['type'] ?? '')), 'inbound')) {
            $this->handleReply((string) ($event['sender'] ?? ''), (string) ($event['message'] ?? ''), $channel);

            return response()->json(['received' => true]);
        }

        $id = (string) ($event['message_id'] ?? $event['id'] ?? '');
        $mapped = $this->termiiStatus($status);
        if ($id !== '' && $mapped) {
            $cost = isset($event['cost']) && is_numeric($event['cost']) ? (float) $event['cost'] : null;
            CustomerMessage::withoutGlobalScopes()->where('provider_message_id', $id)->get()
                ->each(fn (CustomerMessage $m) => $m->markFromProvider($mapped, $mapped === MessageStatus::Failed ? 'Termii: '.($event['status'] ?? 'failed') : null, $cost));
        }

        return response()->json(['received' => true]);
    }

    /** Meta's one-off check when the webhook URL is saved. */
    public function whatsappVerify(Request $request, string $token): Response
    {
        $verify = (string) config('services.whatsapp_meta.verify_token');
        if (! $this->tokenMatches($token) || $verify === '' || $request->query('hub_mode') !== 'subscribe'
            || ! hash_equals($verify, (string) $request->query('hub_verify_token'))) {
            return response('Invalid', 403);
        }

        return response((string) $request->query('hub_challenge'), 200);
    }

    public function whatsapp(Request $request, string $token): JsonResponse
    {
        $payload = $request->getContent();
        $secret = (string) config('services.whatsapp_meta.app_secret');
        if (! $this->tokenMatches($token)
            || ($secret !== '' && ! hash_equals('sha256='.hash_hmac('sha256', $payload, $secret), (string) $request->header('X-Hub-Signature-256')))) {
            Log::warning('WhatsApp webhook refused (bad token or signature)', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid'], 401);
        }

        $event = json_decode($payload, true) ?: [];
        foreach ((array) ($event['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = (array) ($change['value'] ?? []);

                foreach ((array) ($value['statuses'] ?? []) as $report) {
                    $mapped = match ($report['status'] ?? null) {
                        'sent' => MessageStatus::Sent,
                        'delivered', 'read' => MessageStatus::Delivered,
                        'failed' => MessageStatus::Failed,
                        default => null,
                    };
                    if ($mapped && ! empty($report['id'])) {
                        $error = $report['errors'][0]['title'] ?? $report['errors'][0]['message'] ?? 'failed';
                        CustomerMessage::withoutGlobalScopes()->where('provider_message_id', (string) $report['id'])->get()
                            ->each(fn (CustomerMessage $m) => $m->markFromProvider($mapped, 'WhatsApp: '.$error));
                    }
                }

                foreach ((array) ($value['messages'] ?? []) as $reply) {
                    $text = $reply['text']['body'] ?? $reply['button']['text'] ?? $reply['interactive']['button_reply']['title'] ?? '';
                    $this->handleReply('+'.ltrim((string) ($reply['from'] ?? ''), '+'), (string) $text, 'whatsapp');
                }
            }
        }

        return response()->json(['received' => true]);
    }

    private function tokenMatches(string $token): bool
    {
        $expected = (string) config('mybooks.messaging.webhook_token');

        return $expected !== '' && hash_equals($expected, $token);
    }

    private function termiiStatus(string $status): ?MessageStatus
    {
        foreach (['undeliver', 'fail', 'reject', 'expire', 'dnd', 'error'] as $bad) {
            if (str_contains($status, $bad)) {
                return MessageStatus::Failed;
            }
        }
        if (str_contains($status, 'deliver')) {
            return MessageStatus::Delivered;
        }

        return in_array($status, ['message sent', 'sent', 'accepted', 'submitted'], true) ? MessageStatus::Sent : null;
    }

    private function handleReply(string $from, string $text, string $channel): void
    {
        $word = strtoupper(preg_replace('/[^A-Za-z]/', '', strtok(trim($text), " \n") ?: ''));
        if (! in_array($word, self::STOP_WORDS, true)) {
            return;
        }
        $to = PhoneNumber::normalise($from) ?? PhoneNumber::normalise('+'.ltrim($from, '+'));
        if (! $to) {
            return;
        }

        // The business whose message this number last got.
        $last = CustomerMessage::withoutGlobalScopes()->where('to', $to)->where('channel', $channel)->latest('id')->first();
        if (! $last) {
            return;
        }

        $customers = Customer::withoutGlobalScope('tenant')->where('tenant_id', $last->tenant_id)
            ->where(fn ($q) => $q->where('id', $last->customer_id)->orWhere('phone', 'like', '%'.substr($to, -4).'%'))
            ->get()
            ->filter(fn (Customer $c) => $c->id === $last->customer_id || PhoneNumber::normalise($c->phone) === $to);

        foreach ($customers as $customer) {
            $customer->forceFill([$channel.'_opt_out' => true])->saveQuietly();
        }

        Log::info('Customer opted out of '.$channel.' messages by replying '.$word, ['tenant_id' => $last->tenant_id, 'customers' => $customers->pluck('id')->all()]);
    }
}
