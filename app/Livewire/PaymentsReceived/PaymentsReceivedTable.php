<?php

namespace App\Livewire\PaymentsReceived;

use App\Actions\Payments\DeletePaymentReceived;
use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\Customer;
use App\Models\PaymentReceived;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Payments received list (tables plan T2: the shared list design, see InvoicesTable).
 */
class PaymentsReceivedTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $period = '';

    public string $customer = '';

    public string $method = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'type'],
        'period' => ['except' => ''],
        'customer' => ['except' => ''],
        'method' => ['except' => ''],
    ];

    protected function sortable(): array
    {
        return ['payment_date', 'payment_number', 'amount'];
    }

    protected function filterProperties(): array
    {
        return ['period', 'customer', 'method'];
    }

    protected function rowRelations(): array
    {
        return ['customer:id,name', 'invoice:id,invoice_number'];
    }

    protected function baseQuery(): Builder
    {
        $query = PaymentReceived::query();
        if (($term = trim($this->search)) !== '') {
            $number = preg_replace('/[^0-9.]/', '', $term);
            $query->where(function ($q) use ($term, $number) {
                $q->where('payment_number', 'like', "%{$term}%")
                    ->orWhere('reference', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('invoice', fn ($i) => $i->where('invoice_number', 'like', "%{$term}%"));
                if ($number !== '' && is_numeric($number)) {
                    $q->orWhere('amount', (float) $number);
                }
            });
        }
        if ($this->customer !== '' && ctype_digit($this->customer)) {
            $query->where('customer_id', (int) $this->customer);
        }
        if ($this->method !== '') {
            $query->where('payment_method', $this->method);
        }

        return $this->applyPeriod($query, 'payment_date', $this->period);
    }

    /** Tabs: all, payments against invoices, deposits (and deposits with credit left). */
    protected function applyTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'payments' => $query->where(fn ($q) => $q->where('is_deposit', false)->orWhereNull('is_deposit')),
            'deposits' => $query->where('is_deposit', true),
            'unused' => $query->where('is_deposit', true)->where('unused_amount', '>', 0),
            default => $query,
        };
    }

    /** @return array<string, array{label: string, count: int, alert: bool}> */
    private function tabs(): array
    {
        $row = $this->baseQuery()->toBase()->selectRaw(
            'COUNT(*) as all_rows, SUM(CASE WHEN is_deposit = 0 OR is_deposit IS NULL THEN 1 ELSE 0 END) as payments,
             SUM(CASE WHEN is_deposit = 1 THEN 1 ELSE 0 END) as deposits,
             SUM(CASE WHEN is_deposit = 1 AND unused_amount > 0 THEN 1 ELSE 0 END) as unused'
        )->first();

        return [
            '' => ['label' => 'All', 'count' => (int) $row->all_rows, 'alert' => false],
            'payments' => ['label' => 'Against invoices', 'count' => (int) $row->payments, 'alert' => false],
            'deposits' => ['label' => 'Deposits', 'count' => (int) $row->deposits, 'alert' => false],
            'unused' => ['label' => 'Deposits not yet used', 'count' => (int) $row->unused, 'alert' => false],
        ];
    }

    /**
     * Permission required for each bulk action (see ChecksPermissions).
     */
    protected function bulkActionPermissions(): array
    {
        return [
            'delete' => 'delete payments-received',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Please select at least one payment.';

            return;
        }

        if (empty($this->bulkAction)) {
            $this->errorMessage = 'Please select an action.';

            return;
        }

        $count = count($this->selectedItems);

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'delete':
                $deletedCount = 0;
                $skippedCount = 0;

                DB::transaction(function () use (&$deletedCount, &$skippedCount) {
                    foreach ($this->selectedItems as $paymentId) {
                        $payment = PaymentReceived::find($paymentId);
                        if (! $payment) {
                            continue;
                        }

                        // Same rules as the web and API delete (R3): bank balance
                        // back, journal reversed, invoice balances recalculated.
                        $delete = app(DeletePaymentReceived::class);
                        if ($delete->blockedBecause($payment)) {
                            $skippedCount++;

                            continue;
                        }

                        $delete->handle($payment);
                        $deletedCount++;
                    }
                });

                if ($deletedCount > 0 && $skippedCount > 0) {
                    $this->successMessage = "Deleted {$deletedCount} payment(s). Skipped {$skippedCount} deposit(s) with applied amounts. Journal entries and chart of account balances have been updated.";
                } elseif ($deletedCount > 0) {
                    $this->successMessage = "Successfully deleted {$deletedCount} payment(s). Journal entries and chart of account balances have been updated.";
                } else {
                    $this->errorMessage = 'Could not delete any payments. Selected deposits have been applied to invoices.';
                }
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
        return view('livewire.payments-received.payments-received-table', [
            'payments' => $this->rows(),
            'tabs' => $this->tabs(),
            'totals' => $this->filteredQuery()->toBase()->selectRaw('COUNT(*) as n, COALESCE(SUM(amount), 0) as amount, COALESCE(SUM(unused_amount), 0) as unused')->first(),
            'customers' => Customer::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'methods' => PaymentReceived::query()->whereNotNull('payment_method')->distinct()->orderBy('payment_method')->pluck('payment_method'),
            'periods' => self::periodOptions(),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
