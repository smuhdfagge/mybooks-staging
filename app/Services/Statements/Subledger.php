<?php

namespace App\Services\Statements;

use App\Models\Bill;
use App\Models\CreditNote;
use App\Models\CreditNoteRefund;
use App\Models\FixedAsset;
use App\Models\Invoice;
use App\Models\InvoiceRefund;
use App\Models\PaymentMade;
use App\Models\PaymentReceived;
use App\Models\VendorCredit;
use App\Models\VendorCreditRefund;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every line on a customer's or supplier's account, built from the
 * documents (session 10). Statements, the balance on the customer and
 * supplier pages and the control account reconciliation all use it, so
 * they agree.
 *
 * Customers: invoices, payments received (with WHT the customer deducted),
 * deposits (advances) and their use on invoices, credit notes and refunds
 * paid out of them, and refunds on paid invoices.
 * Suppliers: bills, payments made (with WHT withheld), advances and their
 * use on bills, supplier credits and refunds received on them, and assets
 * bought on account.
 *
 * A cancelled, voided or deleted document stays on the account on its own
 * date and is taken off again on the date its journal was reversed. One
 * that never reached the books (no journal) is left out.
 */
class Subledger
{
    public const CUSTOMERS = 'customers';

    public const SUPPLIERS = 'suppliers';

    /** Order of lines on the same day: what's owed first, then what settles it. */
    private const RANK = [
        'invoice' => 1, 'bill' => 1, 'asset' => 1,
        'credit_refund' => 2, 'invoice_refund' => 2,
        'credit_note' => 3, 'vendor_credit' => 3,
        'deposit' => 4, 'advance' => 4,
        'payment' => 5, 'wht' => 6, 'deposit_used' => 7, 'advance_used' => 7,
        'reversal' => 9,
    ];

    /** @var array<string, array{posted: array<int, true>, reversed: array<int, string>}> */
    private array $journalCache = [];

    /**
     * @return Collection<int, Movement> sorted by date
     */
    public function movements(int $tenantId, string $side, ?int $partyId = null, ?string $to = null): Collection
    {
        $to = $to ? Carbon::parse($to)->toDateString() : '9999-12-30'; // no end date: everything entered
        $this->journalCache = [];

        $rows = $side === self::SUPPLIERS
            ? $this->supplierMovements($tenantId, $partyId, $to)
            : $this->customerMovements($tenantId, $partyId, $to);

        return collect($rows)
            ->filter(fn (Movement $m) => $m->date <= $to)
            ->sort(fn (Movement $a, Movement $b) => [$a->date, self::RANK[$a->kind] ?? 8, $a->docId] <=> [$b->date, self::RANK[$b->kind] ?? 8, $b->docId])
            ->values();
    }

    /**
     * Statement balance of every party as at a date.
     *
     * @return array<int, float> party id => balance
     */
    public function balances(int $tenantId, string $side, ?string $asOf = null): array
    {
        return $this->movements($tenantId, $side, null, $asOf)
            ->filter(fn (Movement $m) => $m->partyId !== null)
            ->groupBy('partyId')
            ->map(fn (Collection $g) => round($g->sum(fn (Movement $m) => $m->amount()), 2))
            ->all();
    }

    /**
     * Unpaid invoices (or bills) and unused credits of one party as at a
     * date. The two together come to the statement balance at that date.
     *
     * @return array{items: list<array<string, mixed>>, credits: list<array<string, mixed>>}
     */
    public function openItems(int $tenantId, string $side, int $partyId, string $asOf): array
    {
        $asOf = Carbon::parse($asOf)->toDateString();
        $movements = $this->movements($tenantId, $side, $partyId, $asOf);
        $customers = $side === self::CUSTOMERS;

        // What's still alive at the date: added and not taken off again.
        $alive = [];
        foreach ($movements as $m) {
            $sign = $m->kind === 'reversal' ? -1 : 1;
            $alive[$m->docKey()] = ($alive[$m->docKey()] ?? 0) + $sign;
        }
        $isAlive = fn (Movement $m) => ($alive[$m->docKey()] ?? 0) > 0;

        // Settled per invoice/bill by payments (and deposits/advances used).
        $settled = [];
        foreach ($movements as $m) {
            if ($m->appliesTo) {
                $settled[$m->appliesTo] = ($settled[$m->appliesTo] ?? 0) + $m->allocated;
            }
        }
        // ... and by credit notes / supplier credits applied to them.
        $applications = $customers
            ? DB::table('credit_note_applications as a')->join('credit_notes as c', 'c.id', '=', 'a.credit_note_id')
                ->where('c.tenant_id', $tenantId)->where('c.customer_id', $partyId)
                ->where('a.applied_date', '<', $this->nextDay($asOf))
                ->get(['a.credit_note_id as credit_id', 'a.invoice_id as target_id', 'a.amount'])
            : DB::table('vendor_credit_applications as a')->join('vendor_credits as c', 'c.id', '=', 'a.vendor_credit_id')
                ->where('c.tenant_id', $tenantId)->where('c.vendor_id', $partyId)
                ->where('a.applied_date', '<', $this->nextDay($asOf))
                ->get(['a.vendor_credit_id as credit_id', 'a.bill_id as target_id', 'a.amount']);
        $usedOfCredit = [];
        foreach ($applications as $a) {
            $settled[(int) $a->target_id] = ($settled[(int) $a->target_id] ?? 0) + (float) $a->amount;
            $usedOfCredit[(int) $a->credit_id] = ($usedOfCredit[(int) $a->credit_id] ?? 0) + (float) $a->amount;
        }

        $items = [];
        $credits = [];
        $docKind = $customers ? 'invoice' : 'bill';
        foreach ($movements as $m) {
            if (! $isAlive($m) || $m->kind === 'reversal') {
                continue;
            }
            if ($m->kind === $docKind) {
                $due = round($m->charge - ($settled[$m->docId] ?? 0), 2);
                if (abs($due) >= 0.005) {
                    $items[] = $this->openItem($m, $due, $asOf);
                }
            } elseif ($m->kind === 'asset') {
                $items[] = $this->openItem($m, $m->charge, $asOf);
            } elseif ($m->kind === 'payment' && ! $m->appliesTo) {
                // Money paid on account, not linked to an invoice or bill.
                $credits[] = $this->creditItem($m, $m->credit, $customers ? 'Payment not yet matched to an invoice' : 'Payment not yet matched to a bill');
            } elseif ($m->kind === 'wht' && ! $m->appliesTo) {
                $credits[] = $this->creditItem($m, $m->credit, 'Withholding tax not yet matched');
            }
        }

        // Credit notes / supplier credits with something left.
        $refunded = $customers
            ? CreditNoteRefund::where('tenant_id', $tenantId)->where('refund_date', '<', $this->nextDay($asOf))->groupBy('credit_note_id')->selectRaw('credit_note_id as id, SUM(amount) as amount')->pluck('amount', 'id')
            : VendorCreditRefund::where('tenant_id', $tenantId)->where('refund_date', '<', $this->nextDay($asOf))->groupBy('vendor_credit_id')->selectRaw('vendor_credit_id as id, SUM(amount) as amount')->pluck('amount', 'id');
        $creditKind = $customers ? 'credit_note' : 'vendor_credit';
        foreach ($movements as $m) {
            if ($m->kind === $creditKind && $isAlive($m)) {
                $left = round($m->credit - ($usedOfCredit[$m->docId] ?? 0) - (float) ($refunded[$m->docId] ?? 0), 2);
                if ($left >= 0.005) {
                    $credits[] = $this->creditItem($m, $left, $customers ? 'Credit note not yet used' : 'Supplier credit not yet used');
                }
            }
        }

        // Deposits / advances with something left.
        $depositKind = $customers ? 'deposit' : 'advance';
        $usedOfDeposit = $this->depositUse($tenantId, $customers, $partyId, $asOf, $movements, $isAlive);
        $depositTotals = [];
        $depositFirst = [];
        foreach ($movements as $m) {
            if ($m->kind === $depositKind && $isAlive($m)) {
                $depositTotals[$m->docId] = ($depositTotals[$m->docId] ?? 0) + $m->credit;
                $depositFirst[$m->docId] ??= $m;
            }
        }
        foreach ($depositTotals as $id => $total) {
            $left = round($total - ($usedOfDeposit[$id] ?? 0), 2);
            if ($left >= 0.005) {
                $credits[] = $this->creditItem($depositFirst[$id], $left, $customers ? 'Deposit not yet used' : 'Advance not yet used');
            }
        }

        // Anything the items above don't explain (shouldn't happen) is shown
        // as one line, so the open items always add up to the balance.
        $balance = round($movements->sum(fn (Movement $m) => $m->amount()), 2);
        $listed = round(array_sum(array_column($items, 'amount')) - array_sum(array_column($credits, 'amount')), 2);
        if (abs($balance - $listed) >= 0.005) {
            $diff = round($balance - $listed, 2);
            if ($diff > 0) {
                $items[] = ['date' => $asOf, 'due_date' => $asOf, 'days_overdue' => 0, 'label' => 'Other adjustments', 'reference' => '', 'url' => null, 'total' => $diff, 'amount' => $diff];
            } else {
                $credits[] = ['date' => $asOf, 'label' => 'Other adjustments', 'reference' => '', 'url' => null, 'amount' => -$diff];
            }
        }

        usort($items, fn ($a, $b) => [$a['due_date'], $a['reference']] <=> [$b['due_date'], $b['reference']]);

        return ['items' => $items, 'credits' => $credits];
    }

    /**
     * Ageing of open items: not yet due, 1-30, 31-60, 61-90 and over 90
     * days overdue, less unused credits.
     *
     * @param  array{items: list<array<string, mixed>>, credits: list<array<string, mixed>>}  $open
     * @return array<string, float>
     */
    public static function ageing(array $open): array
    {
        $buckets = ['current' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'over_90' => 0.0];
        foreach ($open['items'] as $item) {
            $days = (int) $item['days_overdue'];
            $key = match (true) {
                $days <= 0 => 'current',
                $days <= 30 => '1_30',
                $days <= 60 => '31_60',
                $days <= 90 => '61_90',
                default => 'over_90',
            };
            $buckets[$key] += (float) $item['amount'];
        }
        $buckets = array_map(fn ($v) => round($v, 2), $buckets);
        $buckets['credits'] = -round(array_sum(array_column($open['credits'], 'amount')), 2);
        $buckets['total'] = round(array_sum($buckets), 2);

        return $buckets;
    }

    public const AGEING_LABELS = [
        'current' => 'Not yet due', '1_30' => '1–30 days', '31_60' => '31–60 days',
        '61_90' => '61–90 days', 'over_90' => 'Over 90 days', 'credits' => 'Unused credits', 'total' => 'Total',
    ];

    // ---- customers --------------------------------------------------------

    /** @return list<Movement> */
    private function customerMovements(int $t, ?int $partyId, string $to): array
    {
        $out = [];
        $before = $this->nextDay($to);

        $invoices = Invoice::withTrashed()->where('tenant_id', $t)
            ->when($partyId, fn ($q) => $q->where('customer_id', $partyId))
            ->where('invoice_date', '<', $before)
            ->get(['id', 'customer_id', 'invoice_number', 'invoice_date', 'due_date', 'status', 'total', 'deleted_at', 'updated_at']);
        $journals = $this->journals($t, Invoice::class);
        foreach ($invoices as $inv) {
            $gone = $inv->trashed() || $inv->status === 'cancelled';
            if ((! $gone && $inv->status === 'draft') || (float) $inv->total <= 0 || ($gone && ! isset($journals['posted'][$inv->id]))) {
                continue;
            }
            $total = round((float) $inv->total, 2);
            $url = $inv->trashed() ? null : route('invoices.show', $inv->id);
            $out[] = new Movement($this->d($inv->invoice_date), $inv->customer_id, 'invoice', "Invoice {$inv->invoice_number}", $inv->invoice_number,
                Invoice::class, $inv->id, $total, 0, $total, $url, $this->d($inv->due_date ?? $inv->invoice_date));
            if ($gone) {
                $out[] = new Movement($this->goneDate($journals, $inv), $inv->customer_id, 'reversal',
                    "Invoice {$inv->invoice_number} ".($inv->trashed() ? 'deleted' : 'cancelled'), $inv->invoice_number,
                    Invoice::class, $inv->id, 0, $total, -$total, $url);
            }
        }
        $invoiceNumbers = Invoice::withTrashed()->where('tenant_id', $t)
            ->when($partyId, fn ($q) => $q->where('customer_id', $partyId))->pluck('invoice_number', 'id');

        $payments = PaymentReceived::withTrashed()->where('tenant_id', $t)
            ->when($partyId, fn ($q) => $q->where('customer_id', $partyId))
            ->where('payment_date', '<', $before)
            ->get(['id', 'customer_id', 'invoice_id', 'payment_number', 'payment_date', 'amount', 'wht_amount', 'payment_method', 'is_deposit', 'deleted_at', 'updated_at']);
        $journals = $this->journals($t, PaymentReceived::class);
        foreach ($payments as $p) {
            $amount = round((float) $p->amount, 2);
            if ($amount <= 0 || ($p->trashed() && ! isset($journals['posted'][$p->id]))) {
                continue;
            }
            $date = $this->d($p->payment_date);
            $url = $p->trashed() ? null : route('payments-received.show', $p->id);
            $for = $p->invoice_id && isset($invoiceNumbers[$p->invoice_id]) ? ' for '.$invoiceNumbers[$p->invoice_id] : '';
            $lines = [];
            if ($p->is_deposit) {
                $lines[] = ['deposit', "Advance payment {$p->payment_number} (held as a deposit)", 0, $amount, 0, null, 0];
            } elseif ($p->payment_method === 'deposit') {
                $lines[] = ['deposit_used', "Deposit used{$for}", 0, 0, -$amount, $p->invoice_id, $amount];
            } else {
                $label = "Payment {$p->payment_number}".($for ?: ' (not linked to an invoice)');
                $lines[] = ['payment', $label, 0, $amount, -$amount, $p->invoice_id, $amount];
                $wht = round((float) $p->wht_amount, 2);
                if ($wht > 0) {
                    $lines[] = ['wht', "Withholding tax you deducted from payment {$p->payment_number}", 0, $wht, -$wht, $p->invoice_id, $wht];
                }
            }
            array_push($out, ...$this->lines($lines, $date, $p->customer_id, $p->payment_number, PaymentReceived::class, $p->id, $url,
                $p->trashed() ? [$this->goneDate($journals, $p), "Payment {$p->payment_number} deleted"] : null));
        }

        $notes = CreditNote::withTrashed()->where('tenant_id', $t)
            ->when($partyId, fn ($q) => $q->where('customer_id', $partyId))
            ->where('credit_note_date', '<', $before)
            ->get(['id', 'customer_id', 'credit_note_number', 'credit_note_date', 'status', 'total', 'deleted_at', 'updated_at']);
        $journals = $this->journals($t, CreditNote::class);
        foreach ($notes as $n) {
            $gone = $n->trashed() || $n->status === 'void';
            $total = round((float) $n->total, 2);
            if ((! $gone && $n->status === 'draft') || $total <= 0 || ($gone && ! isset($journals['posted'][$n->id]))) {
                continue;
            }
            $url = $n->trashed() ? null : route('credit-notes.show', $n->id);
            array_push($out, ...$this->lines([['credit_note', "Credit note {$n->credit_note_number}", 0, $total, -$total, null, 0]],
                $this->d($n->credit_note_date), $n->customer_id, $n->credit_note_number, CreditNote::class, $n->id, $url,
                $gone ? [$this->goneDate($journals, $n), "Credit note {$n->credit_note_number} ".($n->trashed() ? 'deleted' : 'voided')] : null));
        }

        $refunds = DB::table('credit_note_refunds as r')->join('credit_notes as c', 'c.id', '=', 'r.credit_note_id')
            ->where('r.tenant_id', $t)
            ->when($partyId, fn ($q) => $q->where('c.customer_id', $partyId))
            ->where('r.refund_date', '<', $before)
            ->get(['r.id', 'r.credit_note_id', 'r.refund_date', 'r.amount', 'c.customer_id', 'c.credit_note_number']);
        foreach ($refunds as $r) {
            $amount = round((float) $r->amount, 2);
            $out[] = new Movement($this->d($r->refund_date), (int) $r->customer_id, 'credit_refund',
                "Refund paid to you from credit note {$r->credit_note_number}", (string) $r->credit_note_number,
                CreditNoteRefund::class, (int) $r->id, $amount, 0, $amount, route('credit-notes.show', $r->credit_note_id));
        }

        // Refund on a paid invoice: credits the sale and pays the money
        // back in one go, so the balance doesn't move (A6).
        $invoiceRefunds = InvoiceRefund::where('tenant_id', $t)->where('status', 'completed')
            ->when($partyId, fn ($q) => $q->where('customer_id', $partyId))
            ->where('refund_date', '<', $before)
            ->get(['id', 'customer_id', 'invoice_id', 'refund_number', 'refund_date', 'amount']);
        foreach ($invoiceRefunds as $r) {
            $amount = round((float) $r->amount, 2);
            $for = isset($invoiceNumbers[$r->invoice_id]) ? ' on '.$invoiceNumbers[$r->invoice_id] : '';
            $out[] = new Movement($this->d($r->refund_date), $r->customer_id, 'invoice_refund',
                "Refund {$r->refund_number}{$for}: credited and paid back to you", $r->refund_number,
                InvoiceRefund::class, $r->id, $amount, $amount, 0, route('invoices.refunds.show', $r->id));
        }

        return $out;
    }

    // ---- suppliers --------------------------------------------------------

    /** @return list<Movement> */
    private function supplierMovements(int $t, ?int $partyId, string $to): array
    {
        $out = [];
        $before = $this->nextDay($to);

        $bills = Bill::withTrashed()->where('tenant_id', $t)
            ->when($partyId, fn ($q) => $q->where('vendor_id', $partyId))
            ->where('bill_date', '<', $before)
            ->get(['id', 'vendor_id', 'bill_number', 'vendor_bill_number', 'bill_date', 'due_date', 'status', 'total', 'deleted_at', 'updated_at']);
        $journals = $this->journals($t, Bill::class);
        foreach ($bills as $b) {
            $gone = $b->trashed() || $b->status === 'cancelled';
            if ((! $gone && $b->status === 'draft') || (float) $b->total <= 0 || ($gone && ! isset($journals['posted'][$b->id]))) {
                continue;
            }
            $total = round((float) $b->total, 2);
            $url = $b->trashed() ? null : route('bills.show', $b->id);
            $label = "Bill {$b->bill_number}".($b->vendor_bill_number ? " (your invoice {$b->vendor_bill_number})" : '');
            $out[] = new Movement($this->d($b->bill_date), $b->vendor_id, 'bill', $label, $b->bill_number,
                Bill::class, $b->id, $total, 0, $total, $url, $this->d($b->due_date ?? $b->bill_date));
            if ($gone) {
                $out[] = new Movement($this->goneDate($journals, $b), $b->vendor_id, 'reversal',
                    "Bill {$b->bill_number} ".($b->trashed() ? 'deleted' : 'cancelled'), $b->bill_number,
                    Bill::class, $b->id, 0, $total, -$total, $url);
            }
        }
        $billNumbers = Bill::withTrashed()->where('tenant_id', $t)
            ->when($partyId, fn ($q) => $q->where('vendor_id', $partyId))->pluck('bill_number', 'id');

        $payments = PaymentMade::withTrashed()->where('tenant_id', $t)
            ->when($partyId, fn ($q) => $q->where('vendor_id', $partyId))
            ->where('payment_date', '<', $before)
            ->get(['id', 'vendor_id', 'bill_id', 'payment_number', 'payment_date', 'amount', 'wht_amount', 'payment_method', 'is_advance', 'deleted_at', 'updated_at']);
        $journals = $this->journals($t, PaymentMade::class);
        foreach ($payments as $p) {
            $amount = round((float) $p->amount, 2);
            if ($amount <= 0 || ($p->trashed() && ! isset($journals['posted'][$p->id]))) {
                continue;
            }
            $wht = round((float) $p->wht_amount, 2);
            $for = $p->bill_id && isset($billNumbers[$p->bill_id]) ? ' for '.$billNumbers[$p->bill_id] : '';
            $lines = [];
            if ($p->is_advance) {
                $lines[] = ['advance', "Advance {$p->payment_number} paid to you before a bill", 0, $amount, 0, null, 0];
                if ($wht > 0) {
                    $lines[] = ['advance', "Withholding tax deducted from advance {$p->payment_number} (paid to the tax office for you)", 0, $wht, 0, null, 0];
                }
                $url = $p->trashed() ? null : route('supplier-advances.show', $p->id);
            } else {
                if ($p->payment_method === PaymentMade::METHOD_ADVANCE) {
                    $lines[] = ['advance_used', "Advance used{$for}", 0, 0, -round($amount + $wht, 2), $p->bill_id, round($amount + $wht, 2)];
                } else {
                    $lines[] = ['payment', "Payment {$p->payment_number}".($for ?: ' (not linked to a bill)'), 0, $amount, -$amount, $p->bill_id, $amount];
                    if ($wht > 0) {
                        $lines[] = ['wht', "Withholding tax deducted from payment {$p->payment_number} (paid to the tax office for you)", 0, $wht, -$wht, $p->bill_id, $wht];
                    }
                }
                $url = $p->trashed() ? null : route('payments-made.show', $p->id);
            }
            array_push($out, ...$this->lines($lines, $this->d($p->payment_date), $p->vendor_id, $p->payment_number, PaymentMade::class, $p->id, $url,
                $p->trashed() ? [$this->goneDate($journals, $p), "Payment {$p->payment_number} deleted"] : null));
        }

        $credits = VendorCredit::withTrashed()->where('tenant_id', $t)
            ->when($partyId, fn ($q) => $q->where('vendor_id', $partyId))
            ->where('credit_date', '<', $before)
            ->get(['id', 'vendor_id', 'vendor_credit_number', 'vendor_reference', 'credit_date', 'status', 'total', 'deleted_at', 'updated_at']);
        $journals = $this->journals($t, VendorCredit::class);
        foreach ($credits as $c) {
            $gone = $c->trashed() || $c->status === 'void';
            $total = round((float) $c->total, 2);
            if ((! $gone && $c->status === 'draft') || $total <= 0 || ($gone && ! isset($journals['posted'][$c->id]))) {
                continue;
            }
            $url = $c->trashed() ? null : route('vendor-credits.show', $c->id);
            $label = "Supplier credit {$c->vendor_credit_number}".($c->vendor_reference ? " (your credit note {$c->vendor_reference})" : '');
            array_push($out, ...$this->lines([['vendor_credit', $label, 0, $total, -$total, null, 0]],
                $this->d($c->credit_date), $c->vendor_id, $c->vendor_credit_number, VendorCredit::class, $c->id, $url,
                $gone ? [$this->goneDate($journals, $c), "Supplier credit {$c->vendor_credit_number} ".($c->trashed() ? 'deleted' : 'voided')] : null));
        }

        $refunds = DB::table('vendor_credit_refunds as r')->join('vendor_credits as c', 'c.id', '=', 'r.vendor_credit_id')
            ->where('r.tenant_id', $t)
            ->when($partyId, fn ($q) => $q->where('c.vendor_id', $partyId))
            ->where('r.refund_date', '<', $before)
            ->get(['r.id', 'r.vendor_credit_id', 'r.refund_date', 'r.amount', 'c.vendor_id', 'c.vendor_credit_number']);
        foreach ($refunds as $r) {
            $amount = round((float) $r->amount, 2);
            $out[] = new Movement($this->d($r->refund_date), (int) $r->vendor_id, 'credit_refund',
                "Refund received from you on supplier credit {$r->vendor_credit_number}", (string) $r->vendor_credit_number,
                VendorCreditRefund::class, (int) $r->id, $amount, 0, $amount, route('vendor-credits.show', $r->vendor_credit_id));
        }

        // Assets bought on account (no bill in MyBooks): Cr accounts payable (A11).
        $assets = FixedAsset::withTrashed()->where('tenant_id', $t)->where('funding_source', 'on_account')
            ->when($partyId, fn ($q) => $q->where('vendor_id', $partyId))
            ->where('purchase_date', '<', $before)
            ->get(['id', 'vendor_id', 'asset_number', 'name', 'purchase_date', 'purchase_cost', 'deleted_at', 'updated_at']);
        $journals = $this->journals($t, FixedAsset::class);
        foreach ($assets as $a) {
            $cost = round((float) $a->purchase_cost, 2);
            if ($cost <= 0 || ($a->trashed() && ! isset($journals['posted'][$a->id]))) {
                continue;
            }
            $url = $a->trashed() ? null : route('fixed-assets.show', $a->id);
            $date = $this->d($a->purchase_date);
            $out[] = new Movement($date, $a->vendor_id, 'asset', "Asset bought on account: {$a->name}", (string) $a->asset_number,
                FixedAsset::class, $a->id, $cost, 0, $cost, $url, $date);
            if ($a->trashed()) {
                $out[] = new Movement($this->goneDate($journals, $a), $a->vendor_id, 'reversal', "Asset {$a->name} deleted", (string) $a->asset_number,
                    FixedAsset::class, $a->id, 0, $cost, -$cost, $url);
            }
        }

        return $out;
    }

    // ---- helpers ----------------------------------------------------------

    /**
     * Lines of one document, plus their mirror image on the date it was
     * taken off ($gone = [date, label]).
     *
     * @param  list<array{0: string, 1: string, 2: float|int, 3: float|int, 4: float|int, 5: ?int, 6: float|int}>  $lines  kind, label, charge, credit, ledger, applies to, allocated
     * @param  array{0: string, 1: string}|null  $gone
     * @return list<Movement>
     */
    private function lines(array $lines, string $date, ?int $partyId, string $reference, string $type, int $id, ?string $url, ?array $gone): array
    {
        $out = [];
        foreach ($lines as [$kind, $label, $charge, $credit, $ledger, $appliesTo, $allocated]) {
            $out[] = new Movement($date, $partyId, $kind, $label, $reference, $type, $id, (float) $charge, (float) $credit, (float) $ledger, $url, null, $appliesTo, (float) $allocated);
            if ($gone) {
                $out[] = new Movement($gone[0], $partyId, 'reversal', $gone[1], $reference, $type, $id, (float) $credit, (float) $charge, -(float) $ledger, $url, null, $appliesTo, -(float) $allocated);
            }
        }

        return $out;
    }

    /**
     * How much of each deposit (or supplier advance) had been used by the
     * date, counting only uses whose payment was still there then.
     *
     * @param  Collection<int, Movement>  $movements
     * @return array<int, float> deposit payment id => amount used
     */
    private function depositUse(int $tenantId, bool $customers, int $partyId, string $asOf, Collection $movements, callable $isAlive): array
    {
        $usedKind = $customers ? 'deposit_used' : 'advance_used';
        $aliveUses = $movements->filter(fn (Movement $m) => $m->kind === $usedKind && $isAlive($m))->pluck('docId')->flip();

        $rows = $customers
            ? DB::table('customer_deposit_applications')->where('tenant_id', $tenantId)->where('customer_id', $partyId)->get(['deposit_payment_id as deposit_id', 'applied_payment_id', 'amount'])
            : DB::table('vendor_advance_applications')->where('tenant_id', $tenantId)->where('vendor_id', $partyId)->get(['advance_payment_id as deposit_id', 'applied_payment_id', 'amount']);

        $used = [];
        foreach ($rows as $r) {
            if ($r->applied_payment_id && isset($aliveUses[$r->applied_payment_id])) {
                $used[(int) $r->deposit_id] = ($used[(int) $r->deposit_id] ?? 0) + (float) $r->amount;
            }
        }

        return $used;
    }

    /** @return array<string, mixed> */
    private function openItem(Movement $m, float $due, string $asOf): array
    {
        $dueDate = $m->dueDate ?? $m->date;

        return [
            'date' => $m->date, 'due_date' => $dueDate,
            'days_overdue' => max(0, (int) Carbon::parse($dueDate)->diffInDays(Carbon::parse($asOf), false)),
            'label' => $m->label, 'reference' => $m->reference, 'url' => $m->url,
            'total' => $m->charge, 'amount' => round($due, 2),
        ];
    }

    /** @return array<string, mixed> */
    private function creditItem(Movement $m, float $amount, string $label): array
    {
        return ['date' => $m->date, 'label' => $label, 'reference' => $m->reference, 'url' => $m->url, 'amount' => round($amount, 2)];
    }

    /**
     * Posted journals of one document type: which documents have one, and
     * the date each was reversed (cancelled, voided or deleted).
     *
     * @return array{posted: array<int, true>, reversed: array<int, string>}
     */
    private function journals(int $tenantId, string $type): array
    {
        if (isset($this->journalCache[$type])) {
            return $this->journalCache[$type];
        }
        $out = ['posted' => [], 'reversed' => []];
        $rows = DB::table('journals')->where('tenant_id', $tenantId)->where('reference_type', $type)
            ->where('is_posted', true)->whereNull('deleted_at')
            ->orderBy('journal_date')->orderBy('id')
            ->get(['reference_id', 'reference', 'journal_date']);
        foreach ($rows as $j) {
            $id = (int) $j->reference_id;
            if (str_starts_with((string) $j->reference, 'REV-')) {
                $out['reversed'][$id] ??= Carbon::parse($j->journal_date)->toDateString();
            } else {
                $out['posted'][$id] = true;
            }
        }

        return $this->journalCache[$type] = $out;
    }

    /** When a document came off the account: its reversal journal's date, else when it was deleted or last changed. */
    private function goneDate(array $journals, $document): string
    {
        return $journals['reversed'][$document->id]
            ?? Carbon::parse($document->deleted_at ?? $document->updated_at ?? now())->toDateString();
    }

    private function d(mixed $date): string
    {
        return Carbon::parse($date)->toDateString();
    }

    /** "Before the next day": SQLite keeps a time part on dates. */
    private function nextDay(string $date): string
    {
        return Carbon::parse($date)->addDay()->toDateString();
    }
}
