<?php

namespace App\Services\Statements;

/**
 * One line of a customer's or supplier's account (session 10).
 *
 * charge / credit are what the statement shows: a charge raises what the
 * customer owes us (or what we owe the supplier), a credit lowers it.
 * ledger is what the same line does to the receivables or payables control
 * account, in the same direction. They differ only for money held in a
 * separate account (customer deposits, supplier advances) and for refunds
 * on paid invoices, which don't touch receivables.
 *
 * appliesTo / allocated: the invoice or bill a payment settles, and how
 * much of it (used for the open-items statement).
 */
final class Movement
{
    public function __construct(
        public readonly string $date,
        public readonly ?int $partyId,
        public readonly string $kind,
        public readonly string $label,
        public readonly string $reference,
        public readonly string $docType,
        public readonly int $docId,
        public readonly float $charge,
        public readonly float $credit,
        public readonly float $ledger,
        public readonly ?string $url = null,
        public readonly ?string $dueDate = null,
        public readonly ?int $appliesTo = null,
        public readonly float $allocated = 0.0,
    ) {}

    /** Effect on the statement balance. */
    public function amount(): float
    {
        return round($this->charge - $this->credit, 2);
    }

    /** Same document (for matching against its journal). */
    public function docKey(): string
    {
        return $this->docType.'#'.$this->docId;
    }
}
