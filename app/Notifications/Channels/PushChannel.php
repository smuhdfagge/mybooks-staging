<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\Push\PushMessage;
use App\Services\Push\PushSender;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a notification's toPush() to each of the user's registered phones
 * (mobile plan A2). A phone whose token Firebase rejects as dead is
 * forgotten, so it isn't tried again.
 */
class PushChannel
{
    public function __construct(private PushSender $sender) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! method_exists($notification, 'toPush')) {
            return;
        }
        $message = $notification->toPush($notifiable);
        if (! $message instanceof PushMessage) {
            return;
        }

        foreach ($notifiable->devices()->get() as $device) {
            try {
                $result = $this->sender->send($device->token, $message);
            } catch (Throwable $e) {
                // One phone's problem (or Firebase being down) doesn't stop the others.
                Log::warning('Push to device '.$device->id.' failed: '.$e->getMessage());

                continue;
            }
            if ($result === PushSender::INVALID_TOKEN) {
                $device->delete();
            } elseif ($result === PushSender::SENT) {
                $device->forceFill(['last_sent_at' => now()])->save();
            }
        }
    }
}
