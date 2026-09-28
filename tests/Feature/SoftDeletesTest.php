<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PaymentMade;
use App\Models\PaymentReceived;
use App\Models\Payroll;
use Tests\TestCase;

class SoftDeletesTest extends TestCase
{
    public function test_payment_received_supports_soft_delete(): void
    {
        $this->createAuthenticatedUser();

        $customer = \App\Models\Customer::withoutEvents(fn () => \App\Models\Customer::factory()->create(['tenant_id' => $this->tenant->id]));

        $payment = PaymentReceived::withoutEvents(function () use ($customer) {
            return PaymentReceived::create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $customer->id,
                'payment_number' => 'PAY-R-000001',
                'payment_date' => now(),
                'amount' => 500.00,
                'payment_method' => 'cash',
                'created_by' => $this->user->id,
            ]);
        });

        $payment->delete();

        $this->assertSoftDeleted('payments_received', ['id' => $payment->id]);
        $this->assertNotNull(PaymentReceived::withTrashed()->find($payment->id));
    }

    public function test_payment_made_supports_soft_delete(): void
    {
        $this->createAuthenticatedUser();

        $vendor = \App\Models\Vendor::withoutEvents(fn () => \App\Models\Vendor::factory()->create(['tenant_id' => $this->tenant->id]));

        $payment = PaymentMade::withoutEvents(function () use ($vendor) {
            return PaymentMade::create([
                'tenant_id' => $this->tenant->id,
                'vendor_id' => $vendor->id,
                'payment_number' => 'PAY-M-000001',
                'payment_date' => now(),
                'amount' => 300.00,
                'payment_method' => 'bank_transfer',
                'created_by' => $this->user->id,
            ]);
        });

        $payment->delete();

        $this->assertSoftDeleted('payments_made', ['id' => $payment->id]);
        $this->assertNotNull(PaymentMade::withTrashed()->find($payment->id));
    }

    public function test_payroll_supports_soft_delete(): void
    {
        $this->createAuthenticatedUser();

        $employee = Employee::withoutEvents(function () {
            return Employee::create([
                'tenant_id' => $this->tenant->id,
                'employee_id' => 'EMP-SD-001',
                'first_name' => 'Soft',
                'last_name' => 'Delete',
                'hire_date' => now()->subYear(),
            ]);
        });

        $payroll = Payroll::withoutEvents(function () use ($employee) {
            return Payroll::create([
                'tenant_id' => $this->tenant->id,
                'employee_id' => $employee->id,
                'payroll_number' => 'PAY-SD-000001',
                'pay_period_start' => now()->startOfMonth(),
                'pay_period_end' => now()->endOfMonth(),
                'pay_date' => now(),
                'basic_salary' => 2000.00,
                'allowances' => 0,
                'overtime_hours' => 0,
                'overtime_amount' => 0,
                'gross_salary' => 2000.00,
                'tax_deduction' => 0,
                'other_deductions' => 0,
                'total_deductions' => 0,
                'net_salary' => 2000.00,
                'status' => 'draft',
                'created_by' => $this->user->id,
            ]);
        });

        $payroll->delete();

        $this->assertSoftDeleted('payrolls', ['id' => $payroll->id]);
        $this->assertNotNull(Payroll::withTrashed()->find($payroll->id));
    }
}
