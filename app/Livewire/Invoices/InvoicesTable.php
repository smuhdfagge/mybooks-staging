<?php

namespace App\Livewire\Invoices;

use App\Actions\Invoices\DeleteInvoice;
use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Invoices list (tables plan T1, the pilot for every list): status tabs with
 * counts, search, date and customer filters, sortable columns, totals for
 * everything the filter matches, one "⋯" menu per row, bulk actions on
 * ticked rows, and cards on a phone.
 */
class InvoicesTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $period = '';

    public string $customer = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'status'],
        'period' => ['except' => ''],
        'customer' => ['except' => ''],
    ];

    /** Tabs: key => statuses (overdue is worked out from the due date). */
    private const TABS = [
        '' => 'All',
        'draft' => 'Draft',
        'unpaid' => 'Unpaid',
        'overdue' => 'Overdue',
        'paid' => 'Paid',
        'cancelled' => 'Cancelled',
    ];

    /** The first column is the default sort, newest first. */
    protected function sortable(): array
    {
        return ['invoice_date', 'invoice_number', 'due_date', 'total', 'balance_due'];
    }

    protected function rowRelations(): array
    {
        return ['customer:id,name'];
    }

    protected function filterProperties(): array
    {
        return ['period', 'customer'];
    }

    /** Search, date and customer, without the status tab (the tabs count within these). */
    protected function baseQuery(): Builder
    {
        $query = Invoice::query();

        if (($term = trim($this->search)) !== '') {
            $number = preg_replace('/[^0-9.]/', '', $term);
            $query->where(function ($q) use ($term, $number) {
                $q->where('invoice_number', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%"));
                if ($number !== '' && is_numeric($number)) {
                    $q->orWhere('total', (float) $number);
                }
            });
        }

        if ($this->customer !== '' && ctype_digit($this->customer)) {
            $query->where('customer_id', (int) $this->customer);
        }

        return $this->applyPeriod($query, 'invoice_date', $this->period);
    }

    protected function filteredQuery(): Builder
    {
        $query = $this->baseQuery();
        $today = now()->toDateString();

        return match ($this->tab) {
            'draft' => $query->where('status', 'draft'),
            'unpaid' => $query->whereIn('status', ['sent', 'unpaid', 'partial', 'overdue'])->where('balance_due', '>', 0),
            'overdue' => $query->whereIn('status', ['sent', 'unpaid', 'partial', 'overdue'])->where('balance_due', '>', 0)->where('due_date', '<', $today),
            'paid' => $query->where('status', 'paid'),
            'cancelled' => $query->whereIn('status', ['cancelled', 'void']),
            default => $query,
        };
    }

    /** @return array<string, array{label: string, count: int, alert?: bool}> one grouped query */
    private function tabs(): array
    {
        $today = now()->toDateString();
        $open = "status IN ('sent','unpaid','partial','overdue') AND balance_due > 0";
        $row = $this->baseQuery()->toBase()->selectRaw(
            "COUNT(*) as all_rows,
             SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
             SUM(CASE WHEN {$open} THEN 1 ELSE 0 END) as unpaid,
             SUM(CASE WHEN {$open} AND due_date < ? THEN 1 ELSE 0 END) as overdue,
             SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
             SUM(CASE WHEN status IN ('cancelled','void') THEN 1 ELSE 0 END) as cancelled",
            [$today]
        )->first();

        $counts = ['' => $row->all_rows, 'draft' => $row->draft, 'unpaid' => $row->unpaid, 'overdue' => $row->overdue, 'paid' => $row->paid, 'cancelled' => $row->cancelled];
        $tabs = [];
        foreach (self::TABS as $key => $label) {
            if ($key === 'cancelled' && (int) $counts[$key] === 0 && $this->tab !== 'cancelled') {
                continue;
            }
            $tabs[$key] = ['label' => $label, 'count' => (int) $counts[$key], 'alert' => $key === 'overdue'];
        }

        return $tabs;
    }

    /** Delete one invoice from its row menu, by the same rules as deleting it from its page. */
    public function deleteOne(int $id, DeleteInvoice $deleteInvoice): void
    {
        $this->requirePermission('delete invoices');
        $invoice = Invoice::findOrFail($id);

        if ($reason = $deleteInvoice->blockedBecause($invoice)) {
            $this->errorMessage = "{$invoice->invoice_number} can't be deleted: {$reason}";

            return;
        }

        DB::transaction(fn () => $deleteInvoice->handle($invoice));
        $this->successMessage = "Deleted {$invoice->invoice_number}.";
        $this->selectedItems = array_values(array_diff($this->selectedItems, [(string) $id]));
    }

    /**
     * Permission required for each bulk action (see ChecksPermissions).
     */
    protected function bulkActionPermissions(): array
    {
        return [
            'mark_sent' => 'send invoices',
            'mark_cancelled' => 'edit invoices',
            'delete' => 'delete invoices',
        ];
    }

    public function applyBulkAction()
    {
        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Please select at least one invoice.';

            return;
        }

        if (empty($this->bulkAction)) {
            $this->errorMessage = 'Please select an action.';

            return;
        }

        $count = count($this->selectedItems);

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'mark_sent':
                // Save each invoice so its journal is created and the closed-
                // period check runs (C5). A query-builder update skipped both.
                $sent = 0;
                $failed = [];
                foreach (Invoice::whereIn('id', $this->selectedItems)->where('status', 'draft')->get() as $invoice) {
                    try {
                        DB::transaction(fn () => $invoice->update(['status' => 'sent']));
                        $sent++;
                    } catch (\Throwable $e) {
                        report($e);
                        $failed[] = $invoice->invoice_number;
                    }
                }
                $this->successMessage = "Marked {$sent} invoice(s) as sent.";
                if ($failed) {
                    $this->errorMessage = 'Not changed (closed period or invalid totals): '.implode(', ', $failed).'.';
                }
                break;

            case 'mark_cancelled':
                // Cancelling a posted invoice reverses its journal and releases
                // any stock it reserved. Invoices with payments are skipped.
                $cancelled = 0;
                $skipped = 0;
                $failed = [];
                $invoices = Invoice::with('items')->whereIn('id', $this->selectedItems)
                    ->whereIn('status', ['draft', 'sent', 'unpaid', 'overdue'])
                    ->get();
                foreach ($invoices as $invoice) {
                    if ((float) $invoice->amount_paid > 0) {
                        $skipped++;

                        continue;
                    }
                    try {
                        DB::transaction(function () use ($invoice) {
                            $invoice->releaseInventoryReservation();
                            $invoice->update(['status' => 'cancelled']);
                        });
                        $cancelled++;
                    } catch (\Throwable $e) {
                        report($e);
                        $failed[] = $invoice->invoice_number;
                    }
                }
                $this->successMessage = "Cancelled {$cancelled} invoice(s).".($skipped ? " Skipped {$skipped} with payments." : '');
                if ($failed) {
                    $this->errorMessage = 'Not cancelled (closed period or invalid totals): '.implode(', ', $failed).'.';
                }
                break;

            case 'delete':
                // Same rules as deleting one invoice (R3).
                $deleteInvoice = app(DeleteInvoice::class);
                $deletedCount = 0;
                $skipped = 0;
                DB::transaction(function () use (&$deletedCount, &$skipped, $deleteInvoice) {
                    foreach ($this->selectedItems as $invoiceId) {
                        $invoice = Invoice::find($invoiceId);
                        if (! $invoice) {
                            continue;
                        }
                        if ($deleteInvoice->blockedBecause($invoice)) {
                            $skipped++;

                            continue;
                        }
                        $deleteInvoice->handle($invoice);
                        $deletedCount++;
                    }
                });
                $this->successMessage = "Deleted {$deletedCount} invoice(s)."
                    .($skipped ? " {$skipped} skipped because they have payments, credits, refunds or released stock." : '');
                break;

            default:
                $this->errorMessage = 'Invalid action selected.';

                return;
        }

        $this->selectedItems = [];
        $this->bulkAction = '';
    }

    public function render()
    {
        $rows = $this->rows();

        $totals = $this->filteredQuery()->toBase()
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(total), 0) as total, COALESCE(SUM(balance_due), 0) as balance')
            ->first();

        return view('livewire.invoices.invoices-table', [
            'invoices' => $rows,
            'tabs' => $this->tabs(),
            'totals' => $totals,
            'customers' => Customer::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'periods' => self::periodOptions(),
            'filtered' => $this->isFiltered(),
            'today' => now()->startOfDay(),
        ]);
    }
}
