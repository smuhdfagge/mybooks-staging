<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class NotificationSetting extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'send_invoice_on_create',
        'send_payment_confirmation',
        'send_overdue_reminders',
        'overdue_reminder_days',
        'send_payment_reminders',
        'payment_reminder_days_before',
        'send_bill_due_reminders',
        'bill_reminder_days_before',
        'send_low_stock_alerts',
        'low_stock_alert_frequency',
        'send_payroll_notifications',
        'send_leave_notifications',
        'email_from_name',
        'email_from_address',
        'email_reply_to',
    ];

    protected $casts = [
        'send_invoice_on_create' => 'boolean',
        'send_payment_confirmation' => 'boolean',
        'send_overdue_reminders' => 'boolean',
        'send_payment_reminders' => 'boolean',
        'send_bill_due_reminders' => 'boolean',
        'send_low_stock_alerts' => 'boolean',
        'send_payroll_notifications' => 'boolean',
        'send_leave_notifications' => 'boolean',
    ];

    /**
     * Get or create notification settings for a tenant
     */
    public static function getForTenant(int $tenantId): self
    {
        return static::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'send_invoice_on_create' => false,
                'send_payment_confirmation' => true,
                'send_overdue_reminders' => true,
                'overdue_reminder_days' => 7,
                'send_payment_reminders' => true,
                'payment_reminder_days_before' => 3,
                'send_bill_due_reminders' => true,
                'bill_reminder_days_before' => 3,
                'send_low_stock_alerts' => true,
                'low_stock_alert_frequency' => 'daily',
                'send_payroll_notifications' => true,
                'send_leave_notifications' => true,
            ]
        );
    }
}
