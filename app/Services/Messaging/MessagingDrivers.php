<?php

namespace App\Services\Messaging;

/**
 * Picks the SMS and WhatsApp drivers from config (session 16). 'auto'
 * means the provider whose keys are set, else 'log' (nothing sent).
 */
class MessagingDrivers
{
    public function smsDriverName(): string
    {
        $name = (string) config('mybooks.messaging.sms_driver', 'auto');
        if ($name === 'auto') {
            return app(TermiiClient::class)->configured() ? 'termii' : 'log';
        }

        return $name === 'termii' && app(TermiiClient::class)->configured() ? 'termii' : 'log';
    }

    public function whatsappDriverName(): string
    {
        $termii = app(TermiiClient::class)->configured() && filled(config('services.termii.whatsapp_device_id'));
        $meta = filled(config('services.whatsapp_meta.token')) && filled(config('services.whatsapp_meta.phone_number_id'));

        return match ((string) config('mybooks.messaging.whatsapp_driver', 'auto')) {
            'termii' => $termii ? 'termii' : 'log',
            'meta' => $meta ? 'meta' : 'log',
            'auto' => $termii ? 'termii' : ($meta ? 'meta' : 'log'),
            default => 'log',
        };
    }

    public function driverName(string $channel): string
    {
        return $channel === 'whatsapp' ? $this->whatsappDriverName() : $this->smsDriverName();
    }

    /** Real messages go out on this channel (keys are set). */
    public function isLive(string $channel): bool
    {
        return $this->driverName($channel) !== 'log';
    }

    public function sms(): SmsDriver
    {
        return $this->smsDriverName() === 'termii' ? app(TermiiSmsDriver::class) : app(LogDriver::class);
    }

    public function whatsapp(): WhatsAppDriver
    {
        return match ($this->whatsappDriverName()) {
            'termii' => app(TermiiWhatsAppDriver::class),
            'meta' => app(MetaWhatsAppDriver::class),
            default => app(LogDriver::class),
        };
    }
}
