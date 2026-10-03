<?php

namespace App\Services\Statements;

use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\CreditNote;
use App\Models\CreditNoteRefund;
use App\Models\Customer;
use App\Models\FixedAsset;
use App\Models\Invoice;
use App\Models\InvoiceRefund;
use App\Models\Journal;
use App\Models\PaymentMade;
use App\Models\PaymentReceived;
use App\Models\Vendor;
use App\Models\VendorCredit;
use App\Models\VendorCreditRefund;
use App\Services\AccountCodeService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Receivables and payables control account reconciliation (session 10).
 *
 * Compares, as at a date, the Accounts Receivable (Payable) balance in
 * the ledger with the total of all customer (supplier) statement
 * balances, and lists what makes up any difference:
 *   - the account's opening balance (entered on the account, not on any customer)
 *   - journals posted straight to the account (manual journals and the like)
 *   - documents whose journal puts a different amount on the account than
 *     the document says, or that have no journal at all
 *   - deposits and advances, which are on statements but kept in their own account
 *   - documents not linked to a customer (supplier)
 * Each document is matched against its own journals: the receivables
 * (payables) amount on them must equal what the document puts there.
 */
class ControlReconciliation
{
    /** Document types that move receivables / payables, with a plain name. */
    public const DOCUMENTS = [
        Subledger::CUSTOMERS => [
            Invoice::class => 'Invoice', PaymentReceived::class => 'Payment received', CreditNote::class => 'Credit note',
            CreditNoteRefund::class => 'Credit note refund', InvoiceRefund::class => 'Invoice refund',
        ],
        Subledger::SUPPLIERS => [
            Bill::class => 'Bill', PaymentMade::class => 'Payment made', VendorCredit::class => 'Supplier credit',
            VendorCreditRefund::class => 'Supplier credit refund', FixedAsset::class => 'Asset bought on account',
        ],
    ];

    public function __construct(private Subledger $ledger) {}

    /** @return array{receivables: array<string, mixed>, payables: array<string, mixed>} */
    public function run(int $tenantId, string $asOf): array
    {
        return [
            'receivables' => $this->section($tenantId, Subledger::CUSTOMERS, $asOf),
            'payables' => $this->section($tenantId, Subledger::SUPPLIERS, $asOf),
        ];
    }

    /** The control accounts manual journals shouldn't normally touch. @return array<int, string> account id => name */
    public static function controlAccounts(int $tenantId): array
    {
        $codes = [AccountCodeService::resolve($tenantId, 'accounts_receivable'), AccountCodeService::resolve($tenantId, 'accounts_payable')];

        return ChartOfAccount::where('tenant_id', $tenantId)->whereIn('account_code', $codes)
            ->get(['id', 'account_code', 'name'])->mapWithKeys(fn ($a) => [$a->id => "{$a->account_code} - {$a->name}"])->all();
    }

    /** @return array<string, mixed> */
    public function section(int $tenantId, string $side, string $asOf): array
    {
        $asOf = Carbon::parse($asOf)->toDateString();
        $customers = $side === Subledger::CUSTOMERS;
        $code = AccountCodeService::resolve($tenantId, $customers ? 'accounts_receivable' : 'accounts_payable');
        $account = ChartOfAccount::withTrashed()->where('tenant_id', $tenantId)->where('account_code', $code)->first();
        $title = $customers ? 'Accounts Receivable' : 'Accounts Payable';
        $parties = $customers ? 'customer' : 'supplier';

        $movements = $this->ledger->movements($tenantId, $side, null, $asOf);
        $onStatements = round($movements->whereNotNull('partyId')->sum(fn (Movement $m) => $m->amount()), 2);

        if (! $account) {
            return [
                'side' => $side, 'title' => $title, 'account' => null, 'code' => $code, 'ledger' => 0.0,
                'statements' => $onStatements, 'difference' => round(-$onStatements, 2), 'items' => [], 'unexplained' => round(-$onStatements, 2),
            ];
        }

        // Natural direction: receivables are debits, payables credits.
        $sign = $customers ? 1 : -1;
        $journals = DB::table('journal_entries as je')
            ->join('journals as j', 'j.id', '=', 'je.journal_id')
            ->where('j.tenant_id', $tenantId)->where('je.account_id', $account->id)
            ->where('j.is_posted', true)->whereNull('j.deleted_at')
            ->where('j.journal_date', '<', Carbon::parse($asOf)->addDay()->toDateString())
            ->groupBy('j.id', 'j.journal_number', 'j.journal_date', 'j.reference_type', 'j.reference_id', 'j.description', 'j.reference')
            ->orderBy('j.journal_date')->orderBy('j.id')
            ->selectRaw('j.id, j.journal_number, j.journal_date, j.reference_type, j.reference_id, j.description, j.reference, SUM(je.debit) as debit, SUM(je.credit) as credit')
            ->get()
            ->map(function ($j) use ($sign) {
                $j->effect = round($sign * ((float) $j->debit - (float) $j->credit), 2);

                return $j;
            });

        $opening = round((float) $account->opening_balance, 2);
        $ledger = round($opening + $journals->sum('effect'), 2);
        $items = [];

        if (abs($opening) >= 0.005) {
            $items[] = $this->item('opening', "Opening balance entered on {$account->account_code} - {$account->name}",
                "It is on the account but not on any {$parties}'s statement. Enter the old unpaid invoices (or bills) instead, or move it with a journal once they are entered.",
                null, $opening, route('chart-of-accounts.show', $account->id));
        }

        $types = self::DOCUMENTS[$side];
        $byDoc = [];
        $firstJournal = [];
        foreach ($journals as $j) {
            if ($j->reference_type && isset($types[$j->reference_type])) {
                $key = $j->reference_type.'#'.$j->reference_id;
                $byDoc[$key] = ($byDoc[$key] ?? 0) + $j->effect;
                $firstJournal[$key] ??= $j;

                continue;
            }
            // Posted straight to the control account, not from a customer/supplier document.
            if (abs($j->effect) < 0.005) {
                continue;
            }
            $manual = ! $j->reference_type || $j->reference_type === Journal::class;
            $label = $manual
                ? "Manual journal {$j->journal_number} posted straight to {$title}"
                : 'Journal '.$j->journal_number.' from a '.strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', class_basename($j->reference_type)))." posted to {$title}";
            $items[] = $this->item('journal', $label,
                trim(($j->description ?: '')." It isn't linked to any {$parties}, so it doesn't show on any statement."),
                Carbon::parse($j->journal_date)->toDateString(), $j->effect, route('journals.show', $j->id));
        }

        // Each document against its own journals.
        $expected = [];
        $docs = [];
        foreach ($movements as $m) {
            $expected[$m->docKey()] = ($expected[$m->docKey()] ?? 0) + $m->ledger;
            if ($m->kind !== 'reversal') {
                $docs[$m->docKey()] ??= $m;
            }
        }
        foreach (array_unique(array_merge(array_keys($expected), array_keys($byDoc))) as $key) {
            $want = round($expected[$key] ?? 0, 2);
            $have = round($byDoc[$key] ?? 0, 2);
            if (abs($have - $want) < 0.005) {
                continue;
            }
            [$type, $id] = explode('#', $key);
            $doc = $docs[$key] ?? null;
            $name = $doc ? $doc->label : $types[$type].' #'.$id;
            $journal = $firstJournal[$key] ?? null;
            $url = $doc->url ?? ($journal ? route('journals.show', $journal->id) : null);
            $amount = round($have - $want, 2);
            if (! isset($byDoc[$key])) {
                $items[] = $this->item('no_journal', "{$name} has no journal on {$title}",
                    'The document says '.$this->money($want).' but nothing was posted to the account for it.', $doc?->date, $amount, $url);
            } elseif (! isset($expected[$key])) {
                $items[] = $this->item('mismatch', "Journal {$journal->journal_number} for a {$types[$type]} that isn't on any statement",
                    'It puts '.$this->money($have).' on the account, but the document is a draft, was deleted, or never reached the books.',
                    Carbon::parse($journal->journal_date)->toDateString(), $amount, route('journals.show', $journal->id));
            } else {
                $items[] = $this->item('mismatch', "{$name}: journal doesn't match the document",
                    'The journal puts '.$this->money($have).' on the account; the document says '.$this->money($want).'.', $doc?->date, $amount, $url);
            }
        }

        // Deposits / advances: on the statements, kept in their own account.
        $partyNames = ($customers ? Customer::withTrashed() : Vendor::withTrashed())->where('tenant_id', $tenantId)->pluck('name', 'id');
        $held = $movements->whereNotNull('partyId')->groupBy('partyId')
            ->map(fn (Collection $g) => round($g->sum(fn (Movement $m) => $m->ledger - $m->amount()), 2))
            ->filter(fn ($v) => abs($v) >= 0.005);
        foreach ($held as $partyId => $amount) {
            $name = $partyNames[$partyId] ?? "#{$partyId}";
            $items[] = $this->item('held', $customers
                ? "Unused deposit from {$name}, kept in Customer Deposits"
                : "Unused advance to {$name}, kept in Supplier Advances",
                $customers ? 'Money paid in advance lowers what the customer owes on the statement, but is kept in its own liability account until it is used on an invoice. This is expected.'
                    : 'Money paid before a bill lowers what we owe on the statement, but is kept in its own asset account until it is used on a bill. This is expected.',
                null, $amount, route($customers ? 'customers.show' : 'vendors.show', $partyId), true);
        }

        // Documents that aren't on anyone's statement.
        foreach ($movements->whereNull('partyId')->groupBy(fn (Movement $m) => $m->docKey()) as $group) {
            $amount = round($group->sum('ledger'), 2);
            if (abs($amount) >= 0.005) {
                $first = $group->first();
                $items[] = $this->item('no_party', "{$first->label} isn't linked to a {$parties}",
                    "It is on the account but on nobody's statement. Choose the {$parties} on the document.", $first->date, $amount, $first->url);
            }
        }

        $difference = round($ledger - $onStatements, 2);
        $unexplained = round($difference - array_sum(array_column($items, 'amount')), 2);

        return [
            'side' => $side, 'title' => $title, 'account' => $account, 'code' => $code,
            'ledger' => $ledger, 'statements' => $onStatements, 'difference' => $difference,
            'items' => $items, 'unexplained' => abs($unexplained) < 0.005 ? 0.0 : $unexplained,
        ];
    }

    /** @return array<string, mixed> */
    private function item(string $kind, string $label, string $detail, ?string $date, float $amount, ?string $url, bool $expected = false): array
    {
        return compact('kind', 'label', 'detail', 'date', 'url', 'expected') + ['amount' => round($amount, 2)];
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2);
    }
}
