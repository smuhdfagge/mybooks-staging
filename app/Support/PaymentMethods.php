<?php

namespace App\Support;

use App\Models\PaymentMade;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * The payment methods a person can choose (audit MB-03).
 *
 * payment_method was free text on every payment form and API, and the edit
 * pages put it into an Alpine expression, so a stored value could run
 * script. Every path that saves one now checks it against this list.
 *
 * "deposit" (a customer deposit applied to an invoice) and "advance" (a
 * supplier advance applied to a bill) are set by the app itself, never
 * chosen; a payment that already has one may keep it when edited.
 */
class PaymentMethods
{
    public const LABELS = [
        'cash' => 'Cash',
        'check' => 'Check',
        'cheque' => 'Cheque',
        'bank_transfer' => 'Bank Transfer',
        'credit_card' => 'Credit Card',
        'debit_card' => 'Debit Card',
        'mobile_money' => 'Mobile Money',
        'online' => 'Online Payment',
        'other' => 'Other',
    ];

    public const DEPOSIT = 'deposit';

    public const INTERNAL = [self::DEPOSIT, PaymentMade::METHOD_ADVANCE];

    /** @return list<string> */
    public static function selectable(): array
    {
        return array_keys(self::LABELS);
    }

    /** The rule for a new payment; $extra adds methods a path handles itself. */
    public static function rule(string ...$extra): In
    {
        return Rule::in([...self::selectable(), ...$extra]);
    }

    /** The rule when changing a payment: its current internal method may stay. */
    public static function ruleKeeping(?string $current): In
    {
        return self::rule(...(in_array($current, self::INTERNAL, true) ? [$current] : []));
    }

    /** A value from an import file: "Bank transfer" → bank_transfer, unknown → other. */
    public static function normalise(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $key = str_replace([' ', '-'], '_', strtolower($value));

        return isset(self::LABELS[$key]) ? $key : 'other';
    }
}
