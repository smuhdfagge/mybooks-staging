<?php

namespace App\Models;

use App\Services\Messaging\MessageTemplates;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A business's SMS and WhatsApp choices (session 16): which messages go by
 * which channel, and the SMS wording (null = the default text).
 *
 * @property array<string, string>|null $limit_notices
 */
class MessageSetting extends Model
{
    use BelongsToTenant;

    public const TYPES = ['invoice_sent', 'payment_reminder', 'overdue', 'payment_received'];

    public const CHANNELS = ['sms', 'whatsapp'];

    protected $guarded = ['id', 'tenant_id'];

    protected $casts = [
        'invoice_sent_sms' => 'boolean',
        'invoice_sent_whatsapp' => 'boolean',
        'payment_reminder_sms' => 'boolean',
        'payment_reminder_whatsapp' => 'boolean',
        'overdue_sms' => 'boolean',
        'overdue_whatsapp' => 'boolean',
        'payment_received_sms' => 'boolean',
        'payment_received_whatsapp' => 'boolean',
        'limit_notices' => 'array',
    ];

    public static function forTenant(int $tenantId): self
    {
        $settings = self::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->first();
        if ($settings) {
            return $settings;
        }

        $settings = new self;
        $settings->skipTenantGuard = true; // tenant set here, also from the scheduler
        $settings->tenant_id = $tenantId;
        $settings->save();

        return $settings->refresh();
    }

    public function channelOn(string $type, string $channel): bool
    {
        return (bool) $this->getAttribute($type.'_'.$channel);
    }

    /** The SMS wording for a message type (the business's own or the default). */
    public function text(string $type): string
    {
        $own = trim((string) $this->getAttribute($type.'_text'));

        return $own !== '' ? $own : MessageTemplates::DEFAULTS[$type];
    }
}
