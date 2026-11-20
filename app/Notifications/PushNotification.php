<?php

namespace App\Notifications;

use App\Notifications\Channels\PushChannel;
use App\Services\Push\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * A push notification to a user's phones (mobile plan A2), sent on the
 * queue once the change that caused it is committed. The text is written
 * when it is queued, in the business's own context and currency.
 */
class PushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PushMessage $message)
    {
        $this->afterCommit();
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return [PushChannel::class];
    }

    public function toPush(object $notifiable): PushMessage
    {
        return $this->message;
    }
}
