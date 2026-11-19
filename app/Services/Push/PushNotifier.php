<?php

namespace App\Services\Push;

use App\Models\Bill;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\PushNotification;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Who gets which push notification (mobile plan A2). Only active users of
 * the same business, with a registered phone, who may see the record:
 *
 * - expense waiting for approval: users who could approve it (admins who
 *   did not raise it)
 * - invoice paid, invoice overdue: users with "view invoices"
 * - bill due tomorrow: users with "view bills"
 *
 * A push never stops the change that caused it: failures are logged.
 */
class PushNotifier
{
    public function expenseAwaitingApproval(Expense $expense): void
    {
        $this->guarded(function () use ($expense) {
            $users = $this->usersWithPhones((int) $expense->tenant_id)
                ->filter(fn (User $u) => $expense->canBeApprovedBy($u));
            $name = $expense->name ?: $expense->expense_number;

            $this->push($users, new PushMessage(
                PushMessage::EXPENSE_AWAITING_APPROVAL,
                (int) $expense->id,
                'Expense to approve',
                "{$expense->expense_number} · {$name} · ".Money::format($expense->total),
            ));
        });
    }

    public function invoicePaid(Invoice $invoice): void
    {
        $this->guarded(function () use ($invoice) {
            $customer = $invoice->customer->name ?? 'A customer';

            $this->push($this->usersWithPhones((int) $invoice->tenant_id, 'view invoices'), new PushMessage(
                PushMessage::INVOICE_PAID,
                (int) $invoice->id,
                'Invoice paid',
                "{$customer} paid {$invoice->invoice_number} in full · ".Money::format($invoice->total),
            ));
        });
    }

    /**
     * The daily reminders, for one business: invoices that became overdue
     * today (due yesterday, Lagos time) and bills due tomorrow. Each record
     * matches on one day only, so it is sent once.
     *
     * @return array{overdue: int, bills: int}
     */
    public function dailyReminders(int $tenantId): array
    {
        $today = CarbonImmutable::now((string) config('mybooks.messaging.timezone', 'Africa/Lagos'));
        $counts = ['overdue' => 0, 'bills' => 0];

        $invoiceUsers = $this->usersWithPhones($tenantId, 'view invoices');
        if ($invoiceUsers->isNotEmpty()) {
            Invoice::where('tenant_id', $tenantId)->open()
                ->whereDate('due_date', $today->subDay()->toDateString())
                ->with('customer:id,name')->get()
                ->each(function (Invoice $invoice) use ($invoiceUsers, &$counts) {
                    $this->push($invoiceUsers, new PushMessage(
                        PushMessage::INVOICE_OVERDUE,
                        (int) $invoice->id,
                        'Invoice overdue',
                        "{$invoice->invoice_number} · ".($invoice->customer->name ?? 'Customer').' owes '.Money::format($invoice->balance_due),
                    ));
                    $counts['overdue']++;
                });
        }

        $billUsers = $this->usersWithPhones($tenantId, 'view bills');
        if ($billUsers->isNotEmpty()) {
            Bill::where('tenant_id', $tenantId)
                ->whereIn('status', ['unpaid', 'partial', 'overdue'])
                ->where('balance_due', '>', 0)
                ->whereDate('due_date', $today->addDay()->toDateString())
                ->with('vendor:id,name')->get()
                ->each(function (Bill $bill) use ($billUsers, &$counts) {
                    $this->push($billUsers, new PushMessage(
                        PushMessage::BILL_DUE,
                        (int) $bill->id,
                        'Bill due tomorrow',
                        "{$bill->bill_number} · ".($bill->vendor->name ?? 'Vendor').' · '.Money::format($bill->balance_due),
                    ));
                    $counts['bills']++;
                });
        }

        return $counts;
    }

    /** @return Collection<int, User> */
    private function usersWithPhones(int $tenantId, ?string $permission = null): Collection
    {
        // Phones first: most users have none, so this usually ends here.
        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereHas('devices')
            ->get();

        return $permission === null ? $users : $users->filter(fn (User $u) => $u->checkPermissionTo($permission))->values();
    }

    /** @param Collection<int, User> $users */
    private function push(Collection $users, PushMessage $message): void
    {
        if ($users->isNotEmpty()) {
            Notification::send($users, new PushNotification($message));
        }
    }

    private function guarded(callable $work): void
    {
        try {
            $work();
        } catch (Throwable $e) {
            Log::warning('Push notification not sent: '.$e->getMessage());
        }
    }
}
