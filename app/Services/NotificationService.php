<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Leave;
use App\Models\Payroll;
use App\Models\User;
use App\Notifications\BillDueNotification;
use App\Notifications\InvoiceOverdueNotification;
use App\Notifications\InvoicePaymentReminderNotification;
use App\Notifications\InvoiceSentNotification;
use App\Notifications\LeaveRequestNotification;
use App\Notifications\LowStockNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\PayrollApprovedNotification;
use App\Notifications\WelcomeUserNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Default cooldown period in hours for de-duplicating batch notifications.
     */
    protected int $cooldownHours = 24;

    /**
     * Check if a notifiable was already sent a notification of the given type
     * for the given entity within the cooldown period.
     *
     * @param  object  $notifiable  User or Customer with Notifiable trait
     * @param  string  $type  The 'type' value stored in the notification data JSON
     * @param  string  $entityKey  JSON key that holds the entity ID (e.g. 'invoice_id')
     * @param  int  $entityId  The entity ID to match
     */
    protected function wasRecentlyNotified(
        object $notifiable,
        string $type,
        string $entityKey,
        int $entityId,
    ): bool {
        $since = now()->subHours($this->cooldownHours);

        return $notifiable->notifications()
            ->where('created_at', '>=', $since)
            ->get()
            ->contains(function (DatabaseNotification $n) use ($type, $entityKey, $entityId) {
                $data = $n->data;

                return ($data['type'] ?? null) === $type
                    && ($data[$entityKey] ?? null) == $entityId;
            });
    }

    /**
     * Send invoice to customer via email
     */
    public function sendInvoice(Invoice $invoice, ?string $customMessage = null): bool
    {
        try {
            $customer = $invoice->customer;

            if (! $customer || ! $customer->email) {
                Log::warning("Cannot send invoice {$invoice->invoice_number}: Customer has no email");

                return false;
            }

            $customer->notify(new InvoiceSentNotification($invoice, $customMessage));

            Log::info("Invoice {$invoice->invoice_number} sent to customer #{$customer->id}");

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send invoice {$invoice->invoice_number}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Send payment confirmation to customer
     */
    public function sendPaymentConfirmation($payment): bool
    {
        try {
            $customer = $payment->customer ?? $payment->invoice?->customer;

            if (! $customer || ! $customer->email) {
                Log::warning('Cannot send payment confirmation: Customer has no email');

                return false;
            }

            $customer->notify(new PaymentReceivedNotification($payment));

            Log::info("Payment confirmation sent to customer #{$customer->id}");

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send payment confirmation: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Send overdue invoice reminders
     */
    public function sendOverdueReminders(int $tenantId): int
    {
        $count = 0;

        $overdueInvoices = Invoice::where('tenant_id', $tenantId)
            ->whereIn('status', ['unpaid', 'partial'])
            ->where('due_date', '<', now())
            ->where('balance_due', '>', 0)
            ->with(['customer', 'tenant'])
            ->get();

        foreach ($overdueInvoices as $invoice) {
            $customer = $invoice->customer;

            if (! $customer || ! $customer->email) {
                continue;
            }

            // Skip if this customer already received an overdue reminder for this invoice today
            if ($this->wasRecentlyNotified($customer, 'invoice_overdue', 'invoice_id', $invoice->id)) {
                continue;
            }

            $daysOverdue = now()->diffInDays($invoice->due_date);
            $customer->notify(new InvoiceOverdueNotification($invoice, $daysOverdue));
            $count++;
        }

        Log::info("Sent {$count} overdue invoice reminders for tenant {$tenantId}");

        return $count;
    }

    /**
     * Send upcoming payment reminders
     */
    public function sendUpcomingPaymentReminders(int $tenantId, int $daysBefore = 3): int
    {
        $count = 0;

        $targetDate = now()->addDays($daysBefore)->toDateString();

        $upcomingInvoices = Invoice::where('tenant_id', $tenantId)
            ->whereIn('status', ['unpaid', 'partial'])
            ->whereDate('due_date', $targetDate)
            ->where('balance_due', '>', 0)
            ->with(['customer', 'tenant'])
            ->get();

        foreach ($upcomingInvoices as $invoice) {
            $customer = $invoice->customer;

            if (! $customer || ! $customer->email) {
                continue;
            }

            // Skip if this customer already received a payment reminder for this invoice today
            if ($this->wasRecentlyNotified($customer, 'payment_reminder', 'invoice_id', $invoice->id)) {
                continue;
            }

            $customer->notify(new InvoicePaymentReminderNotification($invoice, $daysBefore));
            $count++;
        }

        Log::info("Sent {$count} upcoming payment reminders for tenant {$tenantId}");

        return $count;
    }

    /**
     * Send bill due reminders to internal users
     */
    public function sendBillDueReminders(int $tenantId, int $daysBefore = 3): int
    {
        $count = 0;

        $targetDate = now()->addDays($daysBefore)->toDateString();

        $upcomingBills = Bill::where('tenant_id', $tenantId)
            ->whereIn('status', ['unpaid', 'partial'])
            ->whereDate('due_date', $targetDate)
            ->where('balance_due', '>', 0)
            ->with(['vendor'])
            ->get();

        // Get users with permission to view bills
        $usersToNotify = User::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->permission('view bills')
            ->get();

        foreach ($upcomingBills as $bill) {
            foreach ($usersToNotify as $user) {
                // Skip if this user already received a bill due reminder for this bill today
                if ($this->wasRecentlyNotified($user, 'bill_due', 'bill_id', $bill->id)) {
                    continue;
                }
                $user->notify(new BillDueNotification($bill, $daysBefore));
            }
            $count++;
        }

        Log::info("Sent {$count} bill due reminders for tenant {$tenantId}");

        return $count;
    }

    /**
     * Send low stock alerts
     */
    public function sendLowStockAlerts(int $tenantId): bool
    {
        $lowStockItems = Item::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('track_inventory', true)
            ->whereHas('inventory', function ($query) {
                $query->whereRaw('quantity <= items.reorder_level');
            })
            ->with('inventory')
            ->get();

        if ($lowStockItems->isEmpty()) {
            return false;
        }

        // Get users with inventory permissions
        $usersToNotify = User::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->permission('view inventory')
            ->get();

        foreach ($usersToNotify as $user) {
            // Skip if this user already received a low-stock alert today
            $alreadySent = $user->notifications()
                ->where('created_at', '>=', now()->subHours($this->cooldownHours))
                ->get()
                ->contains(fn (DatabaseNotification $n) => ($n->data['type'] ?? null) === 'low_stock');

            if ($alreadySent) {
                continue;
            }

            $user->notify(new LowStockNotification($lowStockItems));
        }

        Log::info("Sent low stock alerts for {$lowStockItems->count()} items to ".$usersToNotify->count().' users');

        return true;
    }

    /**
     * Send payroll approval notification
     */
    public function sendPayrollApprovalNotification(Payroll $payroll): bool
    {
        try {
            $employee = $payroll->employee;
            $user = $employee?->user;

            if (! $user || ! $user->email) {
                Log::warning('Cannot send payroll notification: Employee has no user account');

                return false;
            }

            // Skip if this user already received a payroll approval notification for this payroll today
            if ($this->wasRecentlyNotified($user, 'payroll_approved', 'payroll_id', $payroll->id)) {
                Log::info("Skipping duplicate payroll notification for payroll {$payroll->id}");

                return false;
            }

            $user->notify(new PayrollApprovedNotification($payroll));

            Log::info("Payroll notification sent to user #{$user->id}");

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send payroll notification: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Send leave request notification
     */
    public function sendLeaveRequestNotification(Leave $leave, string $action = 'submitted'): bool
    {
        try {
            if ($action === 'submitted') {
                // Notify managers/HR
                $managersToNotify = User::where('tenant_id', $leave->tenant_id ?? auth()->user()->tenant_id)
                    ->where('is_active', true)
                    ->permission('approve leaves')
                    ->get();

                foreach ($managersToNotify as $manager) {
                    // Skip if this manager already received a leave request notification for this leave today
                    if ($this->wasRecentlyNotified($manager, 'leave_request', 'leave_id', $leave->id)) {
                        continue;
                    }
                    $manager->notify(new LeaveRequestNotification($leave, $action));
                }
            } else {
                // Notify the employee
                $user = $leave->employee?->user;
                if ($user) {
                    // Skip if this user already received a leave status notification for this leave today
                    if ($this->wasRecentlyNotified($user, 'leave_request', 'leave_id', $leave->id)) {
                        return true;
                    }
                    $user->notify(new LeaveRequestNotification($leave, $action));
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send leave notification: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Send welcome notification to new user
     */
    public function sendWelcomeNotification(User $user, ?string $temporaryPassword = null): bool
    {
        try {
            $user->notify(new WelcomeUserNotification($temporaryPassword));
            Log::info("Welcome notification sent to user #{$user->id}");

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send welcome notification: '.$e->getMessage());

            return false;
        }
    }
}
