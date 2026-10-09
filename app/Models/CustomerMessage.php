<?php

namespace App\Models;

use App\Enums\MessageStatus;
use App\Support\PhoneNumber;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One SMS or WhatsApp message to a customer (session 16), with its status
 * from the provider.
 */
class CustomerMessage extends Model
{
    use BelongsToTenant;

    public const TYPE_LABELS = [
        'invoice_sent' => 'Invoice sent',
        'payment_reminder' => 'Payment reminder',
        'overdue' => 'Overdue reminder',
        'payment_received' => 'Payment receipt',
        'test' => 'Test message',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'params' => 'array',
        'cost' => 'decimal:4',
        'send_after' => 'datetime',
        'dispatched_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function maskedTo(): string
    {
        return PhoneNumber::mask($this->to);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    public function channelLabel(): string
    {
        return $this->channel === 'whatsapp' ? 'WhatsApp' : 'SMS';
    }

    /**
     * Apply a delivery report. A late "sent" never undoes "delivered", and
     * a delivered message can't turn failed.
     */
    public function markFromProvider(MessageStatus $status, ?string $error = null, ?float $cost = null): void
    {
        if ($this->status === MessageStatus::Delivered->value && $status !== MessageStatus::Delivered) {
            return;
        }

        $changes = ['status' => $status->value];
        if ($status === MessageStatus::Delivered) {
            $changes['delivered_at'] = now();
            $changes['error'] = null;
        }
        if ($status === MessageStatus::Failed) {
            $changes['error'] = mb_substr($error ?: 'Not delivered', 0, 255);
        }
        if ($cost !== null) {
            $changes['cost'] = $cost;
        }

        $this->forceFill($changes)->save();
    }
}
