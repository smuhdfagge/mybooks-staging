<?php

namespace App\Services\Statements;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Vendor;
use App\Notifications\StatementNotification;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Emailing statements, one or many (session 10). The email is queued; the
 * customer or supplier page's activity log and the database notification
 * record that it was sent. Parties without an email address are skipped.
 *
 * Subject and message may use {name}, {period}, {balance} and {business};
 * they are filled in for each customer or supplier.
 */
class StatementMailer
{
    public function __construct(private Subledger $ledger) {}

    public function defaultSubject(string $side): string
    {
        return $side === Subledger::SUPPLIERS
            ? 'Statement of your account with {business} ({period})'
            : 'Your statement from {business} ({period})';
    }

    public function defaultMessage(string $side): string
    {
        if ($side === Subledger::SUPPLIERS) {
            return "Please find attached our statement of your account ({period}). By our records the balance is {balance}.\n\n"
                .'Please check it against your own records and let us know if anything is different.';
        }

        return "Please find attached your statement of account ({period}). The balance is {balance}.\n\n"
            ."If anything on it doesn't match your records, please reply to this email and let us know.\n\n"
            .'Thank you for your business.';
    }

    public static function periodText(string $type, ?string $from, string $to): string
    {
        $end = Carbon::parse($to)->format('j M Y');

        return $type === Statement::ACTIVITY && $from ? Carbon::parse($from)->format('j M Y').' to '.$end : 'as at '.$end;
    }

    /** {balance} in words: "₦12,500.00 owed to us", "₦3,000.00 in your favour". */
    public function balanceText(string $side, float $balance, ?string $currency): string
    {
        if (abs($balance) < 0.005) {
            return 'nil';
        }
        $amount = Money::format(abs($balance), $currency);
        if ($side === Subledger::SUPPLIERS) {
            return $balance > 0 ? "{$amount} owed to you" : "{$amount} owed to us";
        }

        return $balance > 0 ? "{$amount} owed to us" : "{$amount} in your favour";
    }

    public function fill(string $text, Customer|Vendor $party, string $period, float $balance, ?Tenant $tenant): string
    {
        $side = $party instanceof Vendor ? Subledger::SUPPLIERS : Subledger::CUSTOMERS;

        return strtr($text, [
            '{name}' => $party->name,
            '{period}' => $period,
            '{balance}' => $this->balanceText($side, $balance, $tenant?->currency),
            '{business}' => $tenant->name ?? config('app.name'),
        ]);
    }

    /** Queue one statement email. False when the party has no email address. */
    public function send(Customer|Vendor $party, string $type, ?string $from, string $to, string $subject, string $message, ?float $balance = null): bool
    {
        if (! $party->email) {
            return false;
        }
        $side = $party instanceof Vendor ? Subledger::SUPPLIERS : Subledger::CUSTOMERS;
        $tenant = $party->tenant;
        $from = $type === Statement::ACTIVITY ? $from : null;
        $balance ??= round($this->ledger->movements((int) $party->tenant_id, $side, (int) $party->id, $to)->sum(fn (Movement $m) => $m->amount()), 2);
        $period = self::periodText($type, $from, $to);

        $party->notify(new StatementNotification($type, $from, $to,
            $this->fill($subject, $party, $period, $balance, $tenant),
            $this->fill($message, $party, $period, $balance, $tenant)));

        $party->logCustomActivity(ActivityLog::ACTION_SENT, "Statement ({$period}) emailed to {$party->email}");
        Log::info('Statement queued for '.class_basename($party)." #{$party->id}");

        return true;
    }

    /**
     * Queue statements for several customers (or suppliers): the ones
     * given, or everyone with a balance when $ids is null.
     *
     * @param  array<int, int|string>|null  $ids
     * @return array{sent: int, skipped: list<string>}
     */
    public function sendMany(int $tenantId, string $side, ?array $ids, string $type, ?string $from, string $to, string $subject, string $message): array
    {
        $balances = $this->ledger->balances($tenantId, $side, $to);
        $query = ($side === Subledger::SUPPLIERS ? Vendor::query() : Customer::query())->where('tenant_id', $tenantId)->orderBy('name');
        if ($ids === null) {
            $query->whereIn('id', array_keys(array_filter($balances, fn ($b) => abs($b) >= 0.005)));
        } else {
            $query->whereIn('id', array_map('intval', $ids));
        }

        $sent = 0;
        $skipped = [];
        foreach ($query->get() as $party) {
            if ($this->send($party, $type, $from, $to, $subject, $message, $balances[$party->id] ?? 0.0)) {
                $sent++;
            } else {
                $skipped[] = $party->name;
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }
}
