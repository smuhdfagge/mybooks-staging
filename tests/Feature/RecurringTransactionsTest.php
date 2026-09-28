<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\RecurrentBill;
use App\Models\RecurrentExpense;
use App\Models\RecurrentInvoice;
use App\Models\Vendor;
use Carbon\Carbon;
use Tests\TestCase;

class RecurringTransactionsTest extends TestCase
{
    // ── RecurrentInvoice isDue / advanceNextDate ────────────────

    public function test_recurrent_invoice_is_due_when_active_and_date_passed(): void
    {
        $this->createAuthenticatedUser();

        $customer = Customer::withoutEvents(fn () => Customer::factory()->create(['tenant_id' => $this->tenant->id]));

        $profile = RecurrentInvoice::withoutEvents(function () use ($customer) {
            return RecurrentInvoice::create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $customer->id,
                'profile_name' => 'Monthly Invoice',
                'frequency' => 'monthly',
                'start_date' => now()->subMonths(2),
                'next_invoice_date' => now()->subDay(),
                'subtotal' => 100,
                'tax_amount' => 0,
                'total' => 100,
                'status' => 'active',
                'created_by' => $this->user->id,
            ]);
        });

        $this->assertTrue($profile->isDue());
    }

    public function test_recurrent_invoice_is_not_due_when_paused(): void
    {
        $this->createAuthenticatedUser();

        $customer = Customer::withoutEvents(fn () => Customer::factory()->create(['tenant_id' => $this->tenant->id]));

        $profile = RecurrentInvoice::withoutEvents(function () use ($customer) {
            return RecurrentInvoice::create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $customer->id,
                'profile_name' => 'Paused Invoice',
                'frequency' => 'monthly',
                'start_date' => now()->subMonths(2),
                'next_invoice_date' => now()->subDay(),
                'subtotal' => 100,
                'tax_amount' => 0,
                'total' => 100,
                'status' => 'paused',
                'created_by' => $this->user->id,
            ]);
        });

        $this->assertFalse($profile->isDue());
    }

    public function test_recurrent_invoice_is_not_due_when_future_date(): void
    {
        $this->createAuthenticatedUser();

        $customer = Customer::withoutEvents(fn () => Customer::factory()->create(['tenant_id' => $this->tenant->id]));

        $profile = RecurrentInvoice::withoutEvents(function () use ($customer) {
            return RecurrentInvoice::create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $customer->id,
                'profile_name' => 'Future Invoice',
                'frequency' => 'monthly',
                'start_date' => now(),
                'next_invoice_date' => now()->addWeek(),
                'subtotal' => 100,
                'tax_amount' => 0,
                'total' => 100,
                'status' => 'active',
                'created_by' => $this->user->id,
            ]);
        });

        $this->assertFalse($profile->isDue());
    }

    public function test_recurrent_invoice_is_not_due_past_end_date(): void
    {
        $this->createAuthenticatedUser();

        $customer = Customer::withoutEvents(fn () => Customer::factory()->create(['tenant_id' => $this->tenant->id]));

        $profile = RecurrentInvoice::withoutEvents(function () use ($customer) {
            return RecurrentInvoice::create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $customer->id,
                'profile_name' => 'Ended Invoice',
                'frequency' => 'monthly',
                'start_date' => now()->subMonths(6),
                'end_date' => now()->subMonth(),
                'next_invoice_date' => now()->subDay(),
                'subtotal' => 100,
                'tax_amount' => 0,
                'total' => 100,
                'status' => 'active',
                'created_by' => $this->user->id,
            ]);
        });

        $this->assertFalse($profile->isDue());
    }

    public function test_recurrent_invoice_advance_next_date_monthly(): void
    {
        $this->createAuthenticatedUser();

        $customer = Customer::withoutEvents(fn () => Customer::factory()->create(['tenant_id' => $this->tenant->id]));

        $baseDate = Carbon::parse('2025-06-01');

        $profile = RecurrentInvoice::withoutEvents(function () use ($customer, $baseDate) {
            return RecurrentInvoice::create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $customer->id,
                'profile_name' => 'Monthly Advance',
                'frequency' => 'monthly',
                'start_date' => $baseDate->copy()->subMonths(2),
                'next_invoice_date' => $baseDate,
                'subtotal' => 100,
                'tax_amount' => 0,
                'total' => 100,
                'status' => 'active',
                'created_by' => $this->user->id,
            ]);
        });

        $profile->advanceNextDate();

        $this->assertEquals('2025-07-01', $profile->fresh()->next_invoice_date->toDateString());
    }

    public function test_recurrent_invoice_auto_stops_past_end_date(): void
    {
        $this->createAuthenticatedUser();

        $customer = Customer::withoutEvents(fn () => Customer::factory()->create(['tenant_id' => $this->tenant->id]));

        $profile = RecurrentInvoice::withoutEvents(function () use ($customer) {
            return RecurrentInvoice::create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $customer->id,
                'profile_name' => 'Auto-Stop Invoice',
                'frequency' => 'monthly',
                'start_date' => now()->subMonths(6),
                'end_date' => now()->addDays(10),
                'next_invoice_date' => now(),
                'subtotal' => 100,
                'tax_amount' => 0,
                'total' => 100,
                'status' => 'active',
                'created_by' => $this->user->id,
            ]);
        });

        $profile->advanceNextDate();

        $updated = $profile->fresh();
        $this->assertEquals('stopped', $updated->status);
    }

    // ── RecurrentBill isDue ─────────────────────────────────────

    public function test_recurrent_bill_is_due_when_active(): void
    {
        $this->createAuthenticatedUser();

        $vendor = Vendor::withoutEvents(fn () => Vendor::factory()->create(['tenant_id' => $this->tenant->id]));

        $profile = RecurrentBill::withoutEvents(function () use ($vendor) {
            return RecurrentBill::create([
                'tenant_id' => $this->tenant->id,
                'vendor_id' => $vendor->id,
                'profile_name' => 'Monthly Bill',
                'frequency' => 'monthly',
                'start_date' => now()->subMonths(2),
                'next_bill_date' => now()->subDay(),
                'subtotal' => 200,
                'tax_amount' => 0,
                'total' => 200,
                'status' => 'active',
                'created_by' => $this->user->id,
            ]);
        });

        $this->assertTrue($profile->isDue());
    }

    public function test_recurrent_bill_advance_next_date_quarterly(): void
    {
        $this->createAuthenticatedUser();

        $vendor = Vendor::withoutEvents(fn () => Vendor::factory()->create(['tenant_id' => $this->tenant->id]));

        $baseDate = Carbon::parse('2025-03-01');

        $profile = RecurrentBill::withoutEvents(function () use ($vendor, $baseDate) {
            return RecurrentBill::create([
                'tenant_id' => $this->tenant->id,
                'vendor_id' => $vendor->id,
                'profile_name' => 'Quarterly Bill',
                'frequency' => 'quarterly',
                'start_date' => $baseDate->copy()->subMonths(6),
                'next_bill_date' => $baseDate,
                'subtotal' => 500,
                'tax_amount' => 0,
                'total' => 500,
                'status' => 'active',
                'created_by' => $this->user->id,
            ]);
        });

        $profile->advanceNextDate();

        $this->assertEquals('2025-06-01', $profile->fresh()->next_bill_date->toDateString());
    }

    // ── RecurrentExpense isDue ───────────────────────────────────

    public function test_recurrent_expense_is_due_when_active(): void
    {
        $this->createAuthenticatedUser();

        $profile = RecurrentExpense::withoutEvents(function () {
            return RecurrentExpense::create([
                'tenant_id' => $this->tenant->id,
                'profile_name' => 'Monthly Rent',
                'frequency' => 'monthly',
                'start_date' => now()->subMonths(2),
                'next_expense_date' => now()->subDay(),
                'amount' => 1500,
                'total' => 1500,
                'status' => 'active',
                'created_by' => $this->user->id,
            ]);
        });

        $this->assertTrue($profile->isDue());
    }

    public function test_recurrent_expense_is_not_due_when_stopped(): void
    {
        $this->createAuthenticatedUser();

        $profile = RecurrentExpense::withoutEvents(function () {
            return RecurrentExpense::create([
                'tenant_id' => $this->tenant->id,
                'profile_name' => 'Stopped Expense',
                'frequency' => 'monthly',
                'start_date' => now()->subMonths(2),
                'next_expense_date' => now()->subDay(),
                'amount' => 1500,
                'total' => 1500,
                'status' => 'stopped',
                'created_by' => $this->user->id,
            ]);
        });

        $this->assertFalse($profile->isDue());
    }

    public function test_recurrent_expense_advance_next_date_weekly(): void
    {
        $this->createAuthenticatedUser();

        $baseDate = Carbon::parse('2025-06-01');

        $profile = RecurrentExpense::withoutEvents(function () use ($baseDate) {
            return RecurrentExpense::create([
                'tenant_id' => $this->tenant->id,
                'profile_name' => 'Weekly Supply',
                'frequency' => 'weekly',
                'start_date' => $baseDate->copy()->subMonths(1),
                'next_expense_date' => $baseDate,
                'amount' => 50,
                'total' => 50,
                'status' => 'active',
                'created_by' => $this->user->id,
            ]);
        });

        $profile->advanceNextDate();

        $this->assertEquals('2025-06-08', $profile->fresh()->next_expense_date->toDateString());
    }
}
