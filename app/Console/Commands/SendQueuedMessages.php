<?php

namespace App\Console\Commands;

use App\Enums\MessageStatus;
use App\Models\CustomerMessage;
use App\Services\Messaging\CustomerMessenger;
use Illuminate\Console\Command;

/**
 * Hands waiting SMS / WhatsApp messages to the queue (session 16): those
 * held over quiet hours, retries after a temporary failure, and any whose
 * queue job was lost. Runs every five minutes.
 */
class SendQueuedMessages extends Command
{
    protected $signature = 'messages:send-queued';

    protected $description = 'Queue SMS and WhatsApp messages that are due to go out';

    public function handle(): int
    {
        // A message stuck in "sending" (worker died mid-send) is let go so
        // it stops counting against the allowance.
        CustomerMessage::withoutGlobalScopes()->where('status', MessageStatus::Sending->value)
            ->where('updated_at', '<', now()->subMinutes(30))
            ->update(['status' => MessageStatus::Failed->value, 'error' => 'Sending was interrupted; please check and send again.']);

        $count = 0;
        CustomerMessage::withoutGlobalScopes()
            ->where('status', MessageStatus::Queued->value)
            ->where(fn ($q) => $q->whereNull('send_after')->orWhere('send_after', '<=', now()))
            ->where(fn ($q) => $q->whereNull('dispatched_at')->orWhere('dispatched_at', '<', now()->subMinutes(15)))
            ->orderBy('id')
            ->limit(1000)
            ->get()
            ->each(function (CustomerMessage $message) use (&$count) {
                CustomerMessenger::dispatch($message);
                $count++;
            });

        $this->info("{$count} messages queued");

        return self::SUCCESS;
    }
}
