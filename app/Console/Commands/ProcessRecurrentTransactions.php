<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
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
                    // Recalculate from the profile's lines: VAT after the discount (A4).
                    $totals = \App\Services\Sales\DocumentTotals::calculate(
                        $profile->items->map(fn ($i) => [
                            'item_id' => $i->item_id,
                            'description' => $i->description,
                            'quantity' => $i->quantity,
                            'unit_price' => $i->unit_price,
                            'discount' => $i->discount ?? 0,
                            'tax_rate' => $i->tax_rate ?? 0,
                        ])->all(),
                        $profile->discount_type,
                        $profile->discount_amount ?? 0
                    );

                    $invoice = Invoice::withoutEvents(function () use ($profile, $totals) {
                        $inv = Invoice::create([
                            'tenant_id' => $profile->tenant_id,
                            'customer_id' => $profile->customer_id,
                            'recurrent_invoice_id' => $profile->id,
                            'invoice_number' => Invoice::generateNumber($profile->tenant_id),
                            'invoice_date' => $profile->next_invoice_date,
                            'due_date' => $profile->next_invoice_date->copy()->addDays($profile->payment_terms),
                            'status' => 'sent',
                            'subtotal' => $totals['subtotal'],
                            'tax_amount' => $totals['tax_amount'],
                            'discount_amount' => $totals['discount_amount'],
                            'discount_type' => $profile->discount_type,
                            'total' => $totals['total'],
                            'amount_paid' => 0,
                            'balance_due' => $totals['total'],
                            'notes' => $profile->notes,
                            'terms' => $profile->terms,
                            'created_by' => $profile->created_by,
                        ]);

                        foreach ($totals['lines'] as $line) {
                            InvoiceItem::create([
                                'invoice_id' => $inv->id,
                                'item_id' => $line['item_id'],
                                'description' => $line['description'],
                                'quantity' => $line['quantity'],
                                'unit_price' => $line['unit_price'],
                                'discount' => $line['discount'],
                                'tax_rate' => $line['tax_rate'],
                                'tax_amount' => $line['tax_amount'],
                                'total' => $line['total'],
                            ]);
                        }

                        return $inv;
                    });

                    // Create journal entry for the new invoice (outside withoutEvents)
                    if ($invoice->total > 0) {
                        $invoice->createJournalEntry();
                    }

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
                    $bill = Bill::withoutEvents(function () use ($profile) {
                        $b = Bill::create([
                            'tenant_id' => $profile->tenant_id,
                            'vendor_id' => $profile->vendor_id,
                            'recurrent_bill_id' => $profile->id,
                            'bill_number' => Bill::generateNumber($profile->tenant_id),
                            'bill_date' => $profile->next_bill_date,
                            'due_date' => $profile->next_bill_date->copy()->addDays(30),
                            'status' => 'unpaid',
                            'subtotal' => $profile->subtotal,
                            'tax_amount' => $profile->tax_amount,
                            'total' => $profile->total,
                            'amount_paid' => 0,
                            'balance_due' => $profile->total,
                            'notes' => $profile->notes,
                            'created_by' => $profile->created_by,
                        ]);

                        foreach ($profile->items as $profileItem) {
                            BillItem::create([
                                'bill_id' => $b->id,
                                'item_id' => $profileItem->item_id,
                                'account_id' => $profileItem->account_id,
                                'description' => $profileItem->description,
                                'quantity' => $profileItem->quantity,
                                'unit_price' => $profileItem->unit_price,
                                'tax_rate' => $profileItem->tax_rate ?? 0,
                                'tax_amount' => $profileItem->tax_amount ?? 0,
                                'total' => $profileItem->total,
                            ]);
                        }

                        return $b;
                    });

                    // Create journal entry for the new bill (outside withoutEvents)
                    if ($bill->total > 0) {
                        $bill->createJournalEntry();
                    }

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
