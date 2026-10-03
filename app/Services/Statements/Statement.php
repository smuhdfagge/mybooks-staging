<?php

namespace App\Services\Statements;

use App\Models\Customer;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * A customer or supplier statement, ready for the screen, the PDF and the
 * email (session 10). Built by StatementBuilder.
 *
 *  - activity:   opening balance, every line in the period with a running
 *                balance, closing balance
 *  - open items: unpaid invoices (bills) and unused credits as at a date
 * Both end with the ageing strip as at the last date.
 */
final class Statement
{
    public const ACTIVITY = 'activity';

    public const OPEN_ITEMS = 'open';

    public const TYPES = [self::ACTIVITY => 'Activity', self::OPEN_ITEMS => 'Open items (unpaid)'];

    /**
     * @param  list<array<string, mixed>>  $rows  activity lines with running balance
     * @param  array{items: list<array<string, mixed>>, credits: list<array<string, mixed>>}  $open
     * @param  array<string, float>  $ageing
     */
    public function __construct(
        public readonly string $side,
        public readonly string $type,
        public readonly Customer|Vendor $party,
        public readonly ?string $from,
        public readonly string $to,
        public readonly float $opening,
        public readonly array $rows,
        public readonly float $totalCharges,
        public readonly float $totalCredits,
        public readonly float $closing,
        public readonly array $open,
        public readonly array $ageing,
    ) {}

    public function isCustomer(): bool
    {
        return $this->side === Subledger::CUSTOMERS;
    }

    public function isActivity(): bool
    {
        return $this->type === self::ACTIVITY;
    }

    public function title(): string
    {
        return $this->isCustomer() ? 'Statement of account' : 'Supplier statement';
    }

    /** "1 Sep 2026 to 30 Sep 2026" or "as at 30 Sep 2026". */
    public function periodText(): string
    {
        $to = Carbon::parse($this->to)->format('j M Y');

        return $this->isActivity() && $this->from
            ? Carbon::parse($this->from)->format('j M Y').' to '.$to
            : 'as at '.$to;
    }

    /** Plain words for the closing balance. */
    public function balanceText(): string
    {
        $owing = $this->closing;
        if (abs($owing) < 0.005) {
            return 'Nothing is owed.';
        }
        if ($this->isCustomer()) {
            return $owing > 0 ? 'Amount you owe us' : 'Amount we owe you (in your favour)';
        }

        return $owing > 0 ? 'Amount we owe you' : 'Amount you owe us (in our favour)';
    }

    public function chargesHeading(): string
    {
        return $this->isCustomer() ? 'Invoiced' : 'Billed';
    }

    public function creditsHeading(): string
    {
        return 'Paid / credited';
    }

    /** File name for the PDF: statement-aminu-stores-2026-09-30.pdf */
    public function fileName(): string
    {
        return 'statement-'.(Str::slug($this->party->name) ?: 'account').'-'.$this->to.'.pdf';
    }

    public function email(): ?string
    {
        return $this->party->email ?: null;
    }
}
