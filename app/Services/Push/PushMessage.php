<?php

namespace App\Services\Push;

/**
 * One push notification (mobile plan A2): what the phone shows, and the
 * type and id the app uses to open the right screen.
 */
final class PushMessage
{
    public const EXPENSE_AWAITING_APPROVAL = 'expense_awaiting_approval';

    public const INVOICE_PAID = 'invoice_paid';

    public const INVOICE_OVERDUE = 'invoice_overdue';

    public const BILL_DUE = 'bill_due';

    public function __construct(
        public readonly string $type,
        public readonly int $id,
        public readonly string $title,
        public readonly string $body,
    ) {}

    /** @return array<string, string> FCM data values must be strings */
    public function data(): array
    {
        return ['type' => $this->type, 'id' => (string) $this->id];
    }
}
