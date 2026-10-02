<?php

namespace App\Services\Payroll;

use Carbon\Carbon;

/**
 * One statutory body's position for a month (tax pack 1): owed from
 * payroll, paid, still to pay, and when it is due.
 */
final class StatutoryDue
{
    public function __construct(
        public readonly string $body,
        public readonly string $label,
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly float $due,
        public readonly float $paid,
        public readonly float $outstanding,
        public readonly Carbon $due_date,
        public readonly string $due_rule,
    ) {}
}
