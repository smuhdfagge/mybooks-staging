<?php

namespace App\Actions\AccrualSchedules;

use App\Models\AccrualSchedule;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Services\AccountCodeService;
use App\Services\JournalService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The setup journal of a schedule linked to a bill, expense or invoice
 * (audit MB-53).
 *
 * The document posted the full amount to an expense or income account
 * (a bill line's account, an expense's account, the invoice's sales
 * account). The schedule then released each month from Prepaid Expenses
 * or Deferred Revenue, where the money never was: a ₦120,000 bill spread
 * over 12 months left Prepaid at -₦30,000 and the expense at ₦150,000
 * after three months. The setup journal moves the amount first:
 *   prepaid:  Dr Prepaid Expenses, Cr the account the bill or expense debited
 *   deferred: Dr the account the invoice credited, Cr Deferred Revenue
 * so each release then moves it on, and nothing is counted twice.
 */
class ScheduleSetup
{
    /** Accounts that are never "where the cost or income went". */
    private const CONTROL_ACCOUNTS = [
        'accounts_payable', 'accounts_receivable', 'input_vat', 'sales_tax_payable',
        'inventory', 'cost_of_goods_sold', 'cash', 'checking', 'wht_payable', 'wht_receivable',
    ];

    public function __construct(protected JournalService $journals) {}

    /**
     * The account to take the schedule's amount from, or null when the
     * document already posted it to the schedule's own balance account.
     *
     * @throws ValidationException (key "source") when the document isn't posted or doesn't hold enough in one account
     */
    public function sourceAccount(AccrualSchedule $schedule): ?string
    {
        $document = $schedule->sourceDocument();
        if (! $document) {
            return null;
        }
        $journal = Journal::withoutGlobalScope('tenant')
            ->where('tenant_id', $schedule->tenant_id)
            ->where('reference_type', $document::class)->where('reference_id', $document->getKey())
            ->where('status', 'posted')->whereNot('reference', 'like', 'REV-%')
            ->whereNull('journal_type')
            ->with(['entries.account' => fn ($q) => $q->withoutGlobalScope('tenant')])
            ->oldest('id')
            ->first();
        $name = $this->documentName($schedule);
        if (! $journal) {
            throw ValidationException::withMessages(['source' => "{$name} hasn't been posted yet (it may still be a draft), so there is nothing to spread. Post it first, or leave the link empty."]);
        }

        $control = array_map(fn ($key) => AccountCodeService::resolve($schedule->tenant_id, $key), self::CONTROL_ACCOUNTS);
        $amounts = [];
        foreach ($journal->entries as $entry) {
            $account = $entry->account;
            if (! $account || in_array($account->account_code, $control, true) || in_array($account->sub_type, ['cash', 'bank'], true)) {
                continue;
            }
            $amount = $schedule->isPrepaid() ? (float) $entry->debit - (float) $entry->credit : (float) $entry->credit - (float) $entry->debit;
            if ($amount > 0) {
                $amounts[$account->account_code] = round(($amounts[$account->account_code] ?? 0) + $amount, 2);
            }
        }

        $total = round((float) $schedule->total_amount, 2);
        $balanceCode = $this->code($schedule->balance_account_id, $schedule->tenant_id);
        $plCode = $this->code($schedule->pl_account_id, $schedule->tenant_id);

        // Already in Prepaid Expenses / Deferred Revenue (a line coded there): nothing to move.
        if (($amounts[$balanceCode] ?? 0) + 0.005 >= $total) {
            return null;
        }
        // Prefer the schedule's own expense or income account, then the largest one.
        if (($amounts[$plCode] ?? 0) + 0.005 >= $total) {
            return $plCode;
        }
        arsort($amounts);
        foreach ($amounts as $code => $amount) {
            if ($amount + 0.005 >= $total) {
                return (string) $code;
            }
        }

        $most = $amounts ? max($amounts) : 0;
        throw ValidationException::withMessages(['source' => sprintf(
            '%s put at most %s in any one %s account, less than the schedule\'s total of %s. Lower the total, or record the payment into %s with a journal and leave the link empty.',
            $name, number_format($most, 2), $schedule->isPrepaid() ? 'expense' : 'income', number_format($total, 2),
            $schedule->isPrepaid() ? 'Prepaid Expenses' : 'Deferred Revenue',
        )]);
    }

    /** Post the setup journal for a linked schedule (nothing if the document already used the balance account). */
    public function post(AccrualSchedule $schedule): ?Journal
    {
        $from = $this->sourceAccount($schedule);
        if ($from === null) {
            return null;
        }
        $journal = $this->move($schedule, $from, (float) $schedule->total_amount, $this->documentDate($schedule), false,
            ($schedule->isPrepaid() ? 'Prepaid from ' : 'Deferred from ').$this->documentName($schedule));
        $schedule->forceFill(['setup_journal_id' => $journal->id])->save();

        return $journal;
    }

    /**
     * Move $amount back to where the setup journal took it from: all of it
     * when a schedule is deleted, what is left when one is cancelled.
     */
    public function undo(AccrualSchedule $schedule, float $amount, string $why): ?Journal
    {
        $setup = $schedule->setup_journal_id
            ? Journal::withoutGlobalScope('tenant')->with(['entries.account' => fn ($q) => $q->withoutGlobalScope('tenant')])->find($schedule->setup_journal_id)
            : null;
        $amount = round($amount, 2);
        if (! $setup || $amount <= 0) {
            return null;
        }
        $balanceCode = $this->code($schedule->balance_account_id, $schedule->tenant_id);
        $from = $setup->entries->first(fn ($e) => $e->account && $e->account->account_code !== $balanceCode)?->account?->account_code;
        if (! $from) {
            return null;
        }

        return $this->move($schedule, $from, $amount, today(), true, $why);
    }

    /** @param bool $back true: from the balance account to $other; false: into it */
    protected function move(AccrualSchedule $schedule, string $other, float $amount, Carbon $date, bool $back, string $why): Journal
    {
        $balanceCode = $this->code($schedule->balance_account_id, $schedule->tenant_id);
        $on = $this->journals->firstOpenDate($schedule->tenant_id, $date);
        $label = "{$schedule->description} ({$schedule->schedule_number})";
        // Prepaid in: Dr balance, Cr other. Deferred in: Dr other, Cr balance. Back: the other way round.
        $intoBalanceIsDebit = $schedule->isPrepaid() !== $back;
        $lines = $intoBalanceIsDebit
            ? [[$balanceCode, $amount, 0.0, $label], [$other, 0.0, $amount, $label]]
            : [[$other, $amount, 0.0, $label], [$balanceCode, 0.0, $amount, $label]];

        return $this->journals->postLines($schedule, $schedule->schedule_number, $on->toDateString(),
            "{$why}: {$label}", $lines, $schedule->created_by, Journal::TYPE_SCHEDULE_SETUP);
    }

    protected function code(?int $accountId, int $tenantId): string
    {
        return (string) ChartOfAccount::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->whereKey($accountId)->value('account_code');
    }

    protected function documentName(AccrualSchedule $schedule): string
    {
        $document = $schedule->sourceDocument();
        $column = AccrualSchedule::SOURCES[$schedule->source_type][1] ?? null;

        return ucfirst((string) $schedule->source_type).($document && $column ? ' '.$document->{$column} : '');
    }

    protected function documentDate(AccrualSchedule $schedule): Carbon
    {
        $document = $schedule->sourceDocument();
        $date = $document?->getAttribute(match ($schedule->source_type) {
            'bill' => 'bill_date', 'expense' => 'expense_date', default => 'invoice_date',
        });

        return $date ? Carbon::parse($date) : today();
    }
}
