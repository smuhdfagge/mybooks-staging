<?php

namespace Tests\Feature\Regression;

use Tests\TestCase;

/**
 * Bugs that PHPStan found once model relationships had types (finding L5).
 */
class PhpstanFindingsTest extends TestCase
{
    public function test_bill_reference_typed_on_the_form_is_saved(): void
    {
        $this->createAuthenticatedUser(['create bills', 'edit bills']);
        $vendor = \App\Models\Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $form = [
            'vendor_id' => $vendor->id, 'bill_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'reference' => 'INV-7781',
            'items' => [['description' => 'Feed', 'quantity' => 1, 'unit_price' => 100]],
        ];

        $this->post(route('bills.store'), $form)->assertSessionHasNoErrors();
        $bill = \App\Models\Bill::sole();
        $this->assertSame('INV-7781', $bill->vendor_bill_number);

        $this->put(route('bills.update', $bill), array_merge($form, ['reference' => 'INV-7782']))->assertSessionHasNoErrors();
        $this->assertSame('INV-7782', $bill->fresh()->vendor_bill_number);
    }

    public function test_api_stock_adjustment_works_and_keeps_cost_layers(): void
    {
        $this->createAuthenticatedUser(['view inventory', 'adjust inventory']);
        $item = \App\Models\Item::factory()->create(['tenant_id' => $this->tenant->id, 'track_inventory' => true, 'cost_price' => 250]);
        $inventory = \App\Models\Inventory::create(['tenant_id' => $this->tenant->id, 'item_id' => $item->id, 'quantity' => 0, 'reserved_quantity' => 0]);
        $api = $this->actingAs($this->user, 'sanctum');

        $api->postJson("/api/v1/inventory/{$inventory->id}/adjust", ['type' => 'add', 'quantity' => 10, 'reason' => 'Opening count'])->assertOk();
        $api->postJson("/api/v1/inventory/{$inventory->id}/adjust", ['type' => 'subtract', 'quantity' => 3, 'reason' => 'Damaged'])->assertOk();
        $api->postJson("/api/v1/inventory/{$inventory->id}/adjust", ['type' => 'subtract', 'quantity' => 30, 'reason' => 'Too many'])
            ->assertStatus(422);

        $this->assertSame(7.0, (float) $inventory->fresh()->quantity);
        $this->assertSame(7.0, (float) \App\Models\InventoryLayer::where('item_id', $item->id)->sum('remaining_quantity'));
        $this->assertSame([10.0, -3.0], \App\Models\InventoryHistory::where('item_id', $item->id)->orderBy('id')->pluck('quantity')->map(fn ($q) => (float) $q)->all());

        $api->getJson("/api/v1/inventory/{$inventory->id}/history")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_expense_import_finds_accounts_by_code(): void
    {
        $this->createAuthenticatedUser();
        $account = \App\Models\ChartOfAccount::where('tenant_id', $this->tenant->id)->where('type', 'expense')->firstOrFail();
        $import = \App\Models\Import::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'type' => 'expenses', 'format' => 'csv',
            'status' => 'processing', 'original_filename' => 'x.csv', 'file_path' => 'x.csv',
        ]);

        $service = new \App\Services\ImportService;
        $tenant = new \ReflectionProperty($service, 'tenantId');
        $tenant->setValue($service, $this->tenant->id);
        $method = new \ReflectionMethod($service, 'importExpenses');
        $method->invoke($service, $import, [
            ['date' => now()->toDateString(), 'account' => $account->account_code, 'amount' => 1500, 'description' => 'Diesel'],
        ]);

        $errors = (new \ReflectionProperty($service, 'errors'))->getValue($service);
        $this->assertSame(1, $import->fresh()->successful_rows, json_encode($errors));
        $this->assertSame($account->id, \App\Models\Expense::sole()->expense_account_id);
    }

    public function test_payroll_journal_names_the_employee(): void
    {
        $this->createAuthenticatedUser();
        $employee = \App\Models\Employee::withoutEvents(fn () => \App\Models\Employee::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => 'EMP-001', 'first_name' => 'Hauwa', 'last_name' => 'Musa',
            'email' => 'hauwa@example.com', 'hire_date' => now()->subYear(), 'status' => 'active',
        ]));
        $payroll = \App\Models\Payroll::withoutEvents(fn () => \App\Models\Payroll::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id, 'payroll_number' => 'PAY-000001',
            'pay_period_start' => now()->startOfMonth(), 'pay_period_end' => now()->endOfMonth(), 'pay_date' => now(),
            'basic_salary' => 1000, 'gross_salary' => 1000, 'total_deductions' => 0, 'net_salary' => 1000,
            'status' => 'paid', 'created_by' => $this->user->id,
        ]));

        $journal = app(\App\Services\JournalService::class)->createPayrollJournal($payroll);

        $this->assertStringContainsString('Hauwa Musa', $journal->description);
    }
}
