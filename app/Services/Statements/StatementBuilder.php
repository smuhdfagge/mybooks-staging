<?php

namespace App\Services\Statements;

use App\Models\Customer;
use App\Models\Vendor;
use Carbon\Carbon;

/**
 * Builds customer and supplier statements from the subledger (session 10).
 * The closing balance is the same figure the customer and supplier pages
 * show as the balance owed.
 */
class StatementBuilder
{
    public function __construct(private Subledger $ledger) {}

    public function build(Customer|Vendor $party, string $type, ?string $from, string $to): Statement
    {
        $side = $party instanceof Vendor ? Subledger::SUPPLIERS : Subledger::CUSTOMERS;
        $to = Carbon::parse($to)->toDateString();
        $type = $type === Statement::OPEN_ITEMS ? Statement::OPEN_ITEMS : Statement::ACTIVITY;
        $from = $type === Statement::ACTIVITY ? Carbon::parse($from ?? Carbon::parse($to)->startOfMonth())->toDateString() : null;
        if ($from !== null && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $movements = $this->ledger->movements((int) $party->tenant_id, $side, (int) $party->id, $to);

        $opening = 0.0;
        $rows = [];
        $charges = 0.0;
        $credits = 0.0;
        $running = 0.0;
        foreach ($movements as $m) {
            $amount = $m->amount();
            if ($from !== null && $m->date < $from) {
                $opening += $amount;
                $running += $amount;

                continue;
            }
            if ($m->charge == 0 && $m->credit == 0) {
                continue; // a deposit or advance used on an invoice: no change to the balance
            }
            $running += $amount;
            $charges += $m->charge;
            $credits += $m->credit;
            $rows[] = [
                'date' => $m->date, 'label' => $m->label, 'reference' => $m->reference, 'url' => $m->url,
                'due_date' => $m->dueDate, 'charge' => round($m->charge, 2), 'credit' => round($m->credit, 2),
                'balance' => round($running, 2), 'kind' => $m->kind,
            ];
        }

        $open = $this->ledger->openItems((int) $party->tenant_id, $side, (int) $party->id, $to);

        return new Statement(
            side: $side,
            type: $type,
            party: $party,
            from: $from,
            to: $to,
            opening: round($opening, 2),
            rows: $type === Statement::ACTIVITY ? $rows : [],
            totalCharges: round($charges, 2),
            totalCredits: round($credits, 2),
            closing: round($running, 2),
            open: $open,
            ageing: Subledger::ageing($open),
        );
    }

    /** Balance owed on a date (default: everything entered so far). */
    public function balance(Customer|Vendor $party, ?string $asOf = null): float
    {
        $side = $party instanceof Vendor ? Subledger::SUPPLIERS : Subledger::CUSTOMERS;

        return round($this->ledger->movements((int) $party->tenant_id, $side, (int) $party->id, $asOf)
            ->sum(fn (Movement $m) => $m->amount()), 2);
    }
}
