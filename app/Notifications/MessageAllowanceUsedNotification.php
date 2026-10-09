<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app notice, once a month: the plan's SMS or WhatsApp allowance is
 * used up, so reminders go by email only until next month (session 16).
 */
class MessageAllowanceUsedNotification extends Notification
{
    use Queueable;

    public function __construct(public string $channel, public string $summary) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $label = $this->channel === 'whatsapp' ? 'WhatsApp' : 'SMS';

        return [
            'type' => 'message_allowance_used',
            'channel' => $this->channel,
            'message' => "{$this->summary} No more {$label} messages go out until next month; emails still go as usual.",
            'url' => route('settings.messaging'),
        ];
    }
}
