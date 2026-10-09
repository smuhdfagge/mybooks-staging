<?php

namespace App\Enums;

/**
 * Where a document stands with NRS (session 18).
 *
 * Not submitted: nothing sent yet. Pending: sent, waiting for NRS's
 * answer. Accepted: NRS cleared it and gave an IRN; final. Rejected: NRS
 * refused it (the invoice needs fixing). Failed: it could not be sent
 * (NRS down, network); sent again by itself or with the Retry button.
 */
enum EInvoiceStatus: string implements DocumentStatus
{
    use StatusTransitions;

    case NotSubmitted = 'not_submitted';
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Failed = 'failed';

    /** @return array<int, self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::NotSubmitted => [self::Pending],
            self::Pending => [self::Accepted, self::Rejected, self::Failed],
            self::Failed, self::Rejected => [self::Pending],
            self::Accepted => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::NotSubmitted => 'Not submitted',
            default => ucfirst($this->value),
        };
    }

    /** Can be sent (again) by a person. */
    public function canSubmit(): bool
    {
        return in_array($this, [self::NotSubmitted, self::Failed, self::Rejected], true);
    }
}
