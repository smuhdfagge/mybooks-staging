<?php

namespace App\Jobs;

use App\Enums\MessageStatus;
use App\Models\CustomerMessage;
use App\Services\Messaging\MessageTemplates;
use App\Services\Messaging\MessagingDrivers;
use App\Services\Messaging\TemporaryFailure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one queued SMS / WhatsApp message (session 16).
 *
 * Retries are kept on the message itself (attempts, send_after) rather than
 * the queue, so they work the same on every queue driver: a temporary
 * failure puts the message back as queued for later, and
 * messages:send-queued hands it to the queue again. Up to max_attempts in
 * all, then failed (a failed message doesn't use up the allowance).
 */
class SendCustomerMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $messageId) {}

    public function handle(MessagingDrivers $drivers, MessageTemplates $templates): void
    {
        $message = CustomerMessage::withoutGlobalScopes()->find($this->messageId);
        if (! $message || $message->status !== MessageStatus::Queued->value) {
            return;
        }
        if ($message->send_after && $message->send_after->isFuture()) {
            $message->forceFill(['dispatched_at' => null])->save(); // picked up again at send_after

            return;
        }
        if ($message->customer && $message->customer->getAttribute($message->channel.'_opt_out')) {
            $message->forceFill(['status' => MessageStatus::Failed->value, 'error' => 'The customer has opted out.'])->save();

            return;
        }

        // Claim it, so two workers can't both send it.
        $claimed = CustomerMessage::withoutGlobalScopes()->whereKey($message->id)->where('status', MessageStatus::Queued->value)
            ->update(['status' => MessageStatus::Sending->value, 'attempts' => $message->attempts + 1, 'updated_at' => now()]);
        if (! $claimed) {
            return;
        }
        $message->refresh();

        try {
            if ($message->channel === 'whatsapp') {
                $driver = $drivers->whatsapp();
                $result = $driver->sendTemplate($message->to, $templates->whatsappTemplate($message->type), (array) $message->params, $message->body);
            } else {
                $driver = $drivers->sms();
                $result = $driver->sendSms($message->to, $message->body);
            }
        } catch (TemporaryFailure $e) {
            $this->retryLater($message, $e->getMessage());

            return;
        } catch (Throwable $e) {
            Log::error('Customer message could not be sent', ['message_id' => $message->id, 'error' => $e->getMessage()]);
            $message->forceFill(['status' => MessageStatus::Failed->value, 'error' => 'Could not send: an unexpected error.'])->save();

            return;
        }

        $message->forceFill($result->ok
            ? ['status' => MessageStatus::Sent->value, 'provider' => $driver->name(), 'provider_message_id' => $result->providerId, 'cost' => $result->cost, 'sent_at' => now(), 'error' => null]
            : ['status' => MessageStatus::Failed->value, 'provider' => $driver->name(), 'error' => $result->error]
        )->save();
    }

    private function retryLater(CustomerMessage $message, string $error): void
    {
        $max = (int) config('mybooks.messaging.max_attempts', 3);
        if ($message->attempts >= $max) {
            $message->forceFill(['status' => MessageStatus::Failed->value, 'error' => mb_substr($error, 0, 255)])->save();

            return;
        }

        $minutes = (array) config('mybooks.messaging.retry_minutes', [1, 5]);
        $wait = (int) ($minutes[$message->attempts - 1] ?? end($minutes) ?: 5);
        $message->forceFill([
            'status' => MessageStatus::Queued->value,
            'send_after' => now()->addMinutes($wait),
            'dispatched_at' => null,
            'error' => mb_substr($error, 0, 255),
        ])->save();
    }
}
