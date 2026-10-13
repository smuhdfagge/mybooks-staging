<?php

namespace App\Livewire\PaymentsMade;

use App\Actions\Payments\DeletePaymentMade;
use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\PaymentMade;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Payments made list (tables plan T3): money paid to suppliers, against
 * bills or in advance. Mirrors the Payments received list.
 */
class PaymentsMadeTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $period = '';

    public string $vendor = '';

    public string $method = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'type'],
        'period' => ['except' => ''],
        'vendor' => ['except' => ''],
        'method' => ['except' => ''],
    ];

    protected function sortable(): array
    {
        return ['payment_date', 'payment_number', 'amount'];
    }

    protected function rowRelations(): array
    {
        return ['vendor:id,name', 'bill:id,bill_number'];
    }

    protected function filterProperties(): array
    {
        return ['period', 'vendor', 'method'];
    }

    protected function baseQuery(): Builder
    {
        $query = PaymentMade::query();
        if (($term = trim($this->search)) !== '') {
            $number = preg_replace('/[^0-9.]/', '', $term);
            $query->where(function ($q) use ($term, $number) {
                $q->where('payment_number', 'like', "%{$term}%")
                    ->orWhere('reference', 'like', "%{$term}%")
                    ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$term}%")->orWhere('company_name', 'like', "%{$term}%"));
                if ($number !== '' && is_numeric($number)) {
                    $q->orWhere('amount', (float) $number);
                }
            });
        }
        if ($this->vendor !== '' && ctype_digit($this->vendor)) {
            $query->where('vendor_id', (int) $this->vendor);
        }
        if ($this->method !== '') {
            $query->where('payment_method', $this->method);
        }

        return $this->applyPeriod($query, 'payment_date', $this->period);
    }

    protected function applyTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'bills' => $query->where(fn ($q) => $q->where('is_advance', false)->orWhereNull('is_advance')),
            'advances' => $query->where('is_advance', true),
            'unused' => $query->where('is_advance', true)->where('unused_amount', '>', 0),
            default => $query,
        };
    }

    /** @return array<string, array{label: string, count: int, alert: bool}> */
    private function tabs(): array
    {
        $row = $this->baseQuery()->toBase()->selectRaw(
            'COUNT(*) as all_rows,
             SUM(CASE WHEN is_advance = 1 THEN 1 ELSE 0 END) as advances,
             SUM(CASE WHEN is_advance = 1 AND unused_amount > 0 THEN 1 ELSE 0 END) as unused'
        )->first();

        return [
            '' => ['label' => 'All', 'count' => (int) $row->all_rows, 'alert' => false],
            'bills' => ['label' => 'Against bills', 'count' => (int) $row->all_rows - (int) $row->advances, 'alert' => false],
            'advances' => ['label' => 'Advances', 'count' => (int) $row->advances, 'alert' => false],
            'unused' => ['label' => 'Advances not yet used', 'count' => (int) $row->unused, 'alert' => false],
        ];
    }

    /**
     * Permission required for each bulk action (see ChecksPermissions).
     */
    protected function bulkActionPermissions(): array
    {
        return [
            'delete' => 'delete payments-made',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Tick at least one payment first.';

            return;
        }

        $this->authorizeBulkAction();

        if ($this->bulkAction !== 'delete') {
            $this->errorMessage = 'Invalid action selected.';

            return;
        }

        $deleted = 0;
        $skipped = 0;
        $delete = app(DeletePaymentMade::class);
        DB::transaction(function () use (&$deleted, &$skipped, $delete) {
            foreach (PaymentMade::whereIn('id', $this->selectedItems)->get() as $payment) {
                // Same rule as deleting from the payment's page: an advance
                // that has been used can't be deleted.
                if ($delete->blockedBecause($payment)) {
                    $skipped++;

                    continue;
                }
                $delete->handle($payment);
                $deleted++;
            }
        });

        if ($deleted > 0) {
            $this->successMessage = "Deleted {$deleted} payment(s). The bills they paid show as owing again.".($skipped ? " Skipped {$skipped} advance(s) already used." : '');
        } else {
            $this->errorMessage = 'None deleted: every ticked payment is an advance that has been used.';
        }

        $this->selectedItems = [];
        $this->bulkAction = '';
    }

    /** Delete one payment from its row menu, by the same rules as its page. */
    public function deleteOne(int $id, DeletePaymentMade $delete): void
    {
        $this->requirePermission('delete payments-made');
        $payment = PaymentMade::findOrFail($id);
        if ($reason = $delete->blockedBecause($payment)) {
            $this->errorMessage = $reason;

            return;
        }
        DB::transaction(fn () => $delete->handle($payment));
        $this->successMessage = "Deleted {$payment->payment_number}.";
        $this->selectedItems = array_values(array_diff($this->selectedItems, [(string) $id]));
    }

    public function render()
    {
        return view('livewire.payments-made.payments-made-table', [
            'payments' => $this->rows(),
            'tabs' => $this->tabs(),
            'totals' => $this->filteredQuery()->toBase()->selectRaw('COUNT(*) as n, COALESCE(SUM(amount), 0) as amount, COALESCE(SUM(CASE WHEN is_advance = 1 THEN unused_amount ELSE 0 END), 0) as unused')->first(),
            'vendors' => Vendor::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'methods' => PaymentMade::query()->whereNotNull('payment_method')->distinct()->orderBy('payment_method')->pluck('payment_method'),
            'periods' => self::periodOptions(),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
