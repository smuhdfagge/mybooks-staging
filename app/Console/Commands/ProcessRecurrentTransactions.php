<?php

namespace App\Console\Commands;

use App\Actions\Bills\SaveBill;
use App\Actions\Invoices\SaveInvoice;
use App\Models\Bill;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\RecurrentBill;
use App\Models\RecurrentExpense;
use App\Models\RecurrentInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessRecurrentTransactions extends Command
{
    protected $signature = 'transactions:process-recurring {--dry-run : Show what would be generated without creating records}';

    protected $description = 'Process due recurring invoices, bills, and expenses and generate the actual transactions';

    protected int $invoicesCreated = 0;

    protected int $billsCreated = 0;

    protected int $expensesCreated = 0;

    protected int $errors = 0;

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('Processing recurring transactions...');
        $this->newLine();

        $this->processRecurrentInvoices($dryRun);
        $this->processRecurrentBills($dryRun);
        $this->processRecurrentExpenses($dryRun);

        $this->newLine();
        $this->info('Summary:');
        $this->table(
            ['Type', 'Created'],
            [
                ['Invoices', $this->invoicesCreated],
                ['Bills', $this->billsCreated],
                ['Expenses', $this->expensesCreated],
                ['Errors', $this->errors],
            ]
        );

        if ($dryRun) {
            $this->warn('Dry run — no records were actually created.');
        }

        return $this->errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function processRecurrentInvoices(bool $dryRun): void
    {
        $profiles = RecurrentInvoice::withoutGlobalScopes()
            ->where('status', 'active')
            ->where('next_invoice_date', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('next_invoice_date', '<=', DB::raw('end_date'));
            })
            ->with(['customer', 'items'])
            ->get();

        foreach ($profiles as $profile) {
            if ($dryRun) {
                $this->line("  [DRY RUN] Invoice for {$profile->customer->name} from profile \"{$profile->profile_name}\" (Tenant #{$profile->tenant_id})");
                $this->invoicesCreated++;

                continue;
            }

            try {
                DB::transaction(function () use ($profile) {
                    // The same rules as every other invoice (R3): totals from
                    // the profile's lines with today's VAT rules, stock checked
                    // and reserved, journal posted with the lines in place.
                    app(SaveInvoice::class)->create($profile->tenant_id, [
                        'customer_id' => $profile->customer_id,
                        'recurrent_invoice_id' => $profile->id,
                        'invoice_date' => $profile->next_invoice_date->toDateString(),
                        'due_date' => $profile->next_invoice_date->copy()->addDays($profile->payment_terms)->toDateString(),
                        'status' => 'sent',
                        'notes' => $profile->notes,
                        'terms' => $profile->terms,
                        'discount_type' => $profile->discount_type,
                        'discount_amount' => $profile->discount_amount ?? 0,
                        'items' => $profile->items->map(fn ($i) => [
                            'item_id' => $i->item_id,
                            'description' => $i->description,
                            'quantity' => $i->quantity,
                            'unit_price' => $i->unit_price,
                            'discount' => $i->discount ?? 0,
                            'discount_type' => 'fixed',
                            'tax_rate' => $i->tax_rate ?? 0,
                        ])->all(),
                    ], $profile->created_by);

                    $profile->advanceNextDate();
                });

                $this->invoicesCreated++;
                $this->line("  Created invoice for {$profile->customer->name} (Profile: {$profile->profile_name})");

            } catch (\Exception $e) {
                $this->errors++;
                $this->error("  Failed: {$profile->profile_name} — {$e->getMessage()}");
                Log::error("Recurring invoice failed for profile #{$profile->id}: {$e->getMessage()}");
            }
        }
    }

    protected function processRecurrentBills(bool $dryRun): void
    {
        $profiles = RecurrentBill::withoutGlobalScopes()
            ->where('status', 'active')
            ->where('next_bill_date', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('next_bill_date', '<=', DB::raw('end_date'));
            })
            ->with(['vendor', 'items'])
            ->get();

        foreach ($profiles as $profile) {
            if ($dryRun) {
                $this->line("  [DRY RUN] Bill for {$profile->vendor->name} from profile \"{$profile->profile_name}\" (Tenant #{$profile->tenant_id})");
                $this->billsCreated++;

                continue;
            }

            try {
                DB::transaction(function () use ($profile) {
                    // The same rules as every other bill (R3): totals from the
                    // profile's lines, journal by line and stock in (A21).
                    app(SaveBill::class)->create($profile->tenant_id, [
                        'vendor_id' => $profile->vendor_id,
                        'recurrent_bill_id' => $profile->id,
                        'bill_date' => $profile->next_bill_date->toDateString(),
                        'due_date' => $profile->next_bill_date->copy()->addDays(30)->toDateString(),
                        'status' => 'unpaid',
                        'notes' => $profile->notes,
                        'items' => $profile->items->map(fn ($i) => [
                            'item_id' => $i->item_id,
                            'account_id' => $i->account_id,
                            'description' => $i->description,
                            'quantity' => $i->quantity,
                            'unit_price' => $i->unit_price,
                            'tax_rate' => $i->tax_rate ?? 0,
                        ])->all(),
                    ], $profile->created_by);

                    $profile->advanceNextDate();
                });

                $this->billsCreated++;
                $this->line("  Created bill for {$profile->vendor->name} (Profile: {$profile->profile_name})");

            } catch (\Exception $e) {
                $this->errors++;
                $this->error("  Failed: {$profile->profile_name} — {$e->getMessage()}");
                Log::error("Recurring bill failed for profile #{$profile->id}: {$e->getMessage()}");
            }
        }
    }

    protected function processRecurrentExpenses(bool $dryRun): void
    {
        $profiles = RecurrentExpense::withoutGlobalScopes()
            ->where('status', 'active')
            ->where('next_expense_date', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('next_expense_date', '<=', DB::raw('end_date'));
            })
            ->with(['vendor', 'expenseAccount'])
            ->get();

        foreach ($profiles as $profile) {
            if ($dryRun) {
                $vendorName = $profile->vendor ? $profile->vendor->name : 'N/A';
                $this->line("  [DRY RUN] Expense \"{$profile->profile_name}\" for {$vendorName} (Tenant #{$profile->tenant_id})");
                $this->expensesCreated++;

                continue;
            }

            try {
                DB::transaction(function () use ($profile) {
                    Expense::withoutEvents(function () use ($profile) {
                        Expense::create([
                            'tenant_id' => $profile->tenant_id,
                            'vendor_id' => $profile->vendor_id,
                            'expense_account_id' => $profile->expense_account_id ?? $profile->account_id,
                            'paid_through_id' => $profile->paid_through_id,
                            'recurrent_expense_id' => $profile->id,
                            'expense_number' => Expense::generateNumber($profile->tenant_id),
                            'name' => $profile->profile_name,
                            'expense_date' => $profile->next_expense_date,
                            'amount' => $profile->amount,
                            'tax_amount' => $profile->tax_amount ?? 0,
                            'total' => $profile->total,
                            'payment_method' => $profile->payment_method,
                            'description' => $profile->description,
                            'status' => Expense::STATUS_DRAFT,
                            'notes' => $profile->notes,
                            'created_by' => $profile->created_by,
                        ]);
                    });

                    $profile->advanceNextDate();
                });

                $this->expensesCreated++;
                $vendorName = $profile->vendor ? $profile->vendor->name : 'N/A';
                $this->line("  Created expense \"{$profile->profile_name}\" for {$vendorName}");

            } catch (\Exception $e) {
                $this->errors++;
                $this->error("  Failed: {$profile->profile_name} — {$e->getMessage()}");
                Log::error("Recurring expense failed for profile #{$profile->id}: {$e->getMessage()}");
            }
        }
    }
}
