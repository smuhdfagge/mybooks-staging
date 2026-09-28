<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureItem;
use App\Models\SalaryStructureVersion;
use Tests\TestCase;

class SalaryStructureVersioningTest extends TestCase
{
    protected function createStructureWithItems(array $overrides = [], array $allowances = [], array $deductions = []): SalaryStructure
    {
        $structure = SalaryStructure::withoutEvents(function () use ($overrides) {
            return SalaryStructure::create(array_merge([
                'tenant_id' => $this->tenant->id,
                'name' => 'Standard Package',
                'basic_salary' => 5000.00,
                'is_active' => true,
                'effective_from' => now()->startOfYear(),
                'version' => 1,
                'created_by' => $this->user->id,
            ], $overrides));
        });

        foreach ($allowances as $i => $allowance) {
            SalaryStructureItem::create(array_merge([
                'salary_structure_id' => $structure->id,
                'type' => 'allowance',
                'sort_order' => $i,
                'is_taxable' => true,
            ], $allowance));
        }

        foreach ($deductions as $i => $deduction) {
            SalaryStructureItem::create(array_merge([
                'salary_structure_id' => $structure->id,
                'type' => 'deduction',
                'sort_order' => $i,
                'is_taxable' => false,
            ], $deduction));
        }

        return $structure->fresh(['items']);
    }

    protected function createEmployee(array $attrs = []): Employee
    {
        return Employee::withoutEvents(function () use ($attrs) {
            return Employee::create(array_merge([
                'tenant_id' => $this->tenant->id,
                'employee_id' => 'EMP-'.str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT),
                'first_name' => 'Test',
                'last_name' => 'Employee',
                'hire_date' => now()->subYear(),
                'status' => 'active',
            ], $attrs));
        });
    }

    // ── Version Snapshot Tests ─────────────────────────────────

    public function test_create_version_snapshot_captures_current_state(): void
    {
        $this->createAuthenticatedUser();

        $structure = $this->createStructureWithItems(
            ['basic_salary' => 5000, 'version' => 1],
            [['name' => 'Housing', 'amount_type' => 'percentage', 'amount' => 15]],
            [['name' => 'Pension', 'amount_type' => 'percentage', 'amount' => 5]],
        );

        $version = $structure->createVersionSnapshot('Initial setup');

        $this->assertInstanceOf(SalaryStructureVersion::class, $version);
        $this->assertEquals(1, $version->version);
        $this->assertEquals('Standard Package', $version->name);
        $this->assertEquals(5000, $version->basic_salary);
        $this->assertEquals('Initial setup', $version->change_reason);
        $this->assertCount(2, $version->items);
        $this->assertEquals('Housing', $version->items[0]['name']);
        $this->assertEquals('Pension', $version->items[1]['name']);
    }

    public function test_update_increments_version_and_creates_history(): void
    {
        $this->createAuthenticatedUser();

        $structure = $this->createStructureWithItems(
            ['basic_salary' => 5000, 'version' => 1],
            [['name' => 'Housing', 'amount_type' => 'fixed', 'amount' => 500]],
        );

        // Simulate the controller's update flow
        $structure->load('items');
        $structure->createVersionSnapshot('Salary review');

        $structure->update([
            'basic_salary' => 6000,
            'version' => 2,
        ]);

        $structure->refresh();
        $this->assertEquals(6000.00, (float) $structure->basic_salary);
        $this->assertEquals(2, $structure->version);

        // Version history should have the OLD values
        $versionRecord = SalaryStructureVersion::where('salary_structure_id', $structure->id)->first();
        $this->assertNotNull($versionRecord);
        $this->assertEquals(1, $versionRecord->version);
        $this->assertEquals(5000, (float) $versionRecord->basic_salary);
        $this->assertEquals('Salary review', $versionRecord->change_reason);
    }

    public function test_multiple_updates_create_sequential_versions(): void
    {
        $this->createAuthenticatedUser();

        $structure = $this->createStructureWithItems(['basic_salary' => 3000, 'version' => 1]);

        // First update: 3000 → 4000
        $structure->load('items');
        $structure->createVersionSnapshot('Q1 raise');
        $structure->update(['basic_salary' => 4000, 'version' => 2]);

        // Second update: 4000 → 5000
        $structure->load('items');
        $structure->createVersionSnapshot('Q2 raise');
        $structure->update(['basic_salary' => 5000, 'version' => 3]);

        $versions = SalaryStructureVersion::where('salary_structure_id', $structure->id)
            ->orderBy('version')
            ->get();

        $this->assertCount(2, $versions);
        $this->assertEquals(1, $versions[0]->version);
        $this->assertEquals(3000, (float) $versions[0]->basic_salary);
        $this->assertEquals('Q1 raise', $versions[0]->change_reason);
        $this->assertEquals(2, $versions[1]->version);
        $this->assertEquals(4000, (float) $versions[1]->basic_salary);
        $this->assertEquals('Q2 raise', $versions[1]->change_reason);

        // Current structure is v3 at 5000
        $structure->refresh();
        $this->assertEquals(3, $structure->version);
        $this->assertEquals(5000, (float) $structure->basic_salary);
    }

    // ── Payroll Snapshot Tests ─────────────────────────────────

    public function test_to_snapshot_contains_complete_structure_data(): void
    {
        $this->createAuthenticatedUser();

        $structure = $this->createStructureWithItems(
            ['name' => 'Senior Dev', 'basic_salary' => 8000, 'version' => 3],
            [
                ['name' => 'Housing', 'amount_type' => 'percentage', 'amount' => 20],
                ['name' => 'Transport', 'amount_type' => 'fixed', 'amount' => 300],
            ],
            [
                ['name' => 'Pension', 'amount_type' => 'percentage', 'amount' => 7.5],
            ],
        );

        $snapshot = $structure->toSnapshot();

        $this->assertEquals($structure->id, $snapshot['structure_id']);
        $this->assertEquals(3, $snapshot['version']);
        $this->assertEquals('Senior Dev', $snapshot['name']);
        $this->assertEquals(8000, $snapshot['basic_salary']);
        $this->assertCount(3, $snapshot['items']);
        $this->assertArrayHasKey('snapshot_at', $snapshot);
    }

    public function test_payroll_stores_structure_snapshot_at_generation_time(): void
    {
        $this->createAuthenticatedUser();

        $structure = $this->createStructureWithItems(
            ['basic_salary' => 5000, 'version' => 2],
            [['name' => 'Housing', 'amount_type' => 'fixed', 'amount' => 500]],
        );

        $employee = $this->createEmployee(['salary_structure_id' => $structure->id]);

        // Create a payroll with a snapshot
        $payroll = Payroll::withoutEvents(function () use ($employee, $structure) {
            return Payroll::create([
                'tenant_id' => $this->tenant->id,
                'employee_id' => $employee->id,
                'salary_structure_id' => $structure->id,
                'salary_structure_snapshot' => $structure->toSnapshot(),
                'payroll_number' => 'PAY-000099',
                'pay_period_start' => now()->startOfMonth(),
                'pay_period_end' => now()->endOfMonth(),
                'pay_date' => now(),
                'basic_salary' => 5000,
                'allowances' => 500,
                'overtime_hours' => 0,
                'overtime_amount' => 0,
                'gross_salary' => 5500,
                'tax_deduction' => 0,
                'other_deductions' => 0,
                'total_deductions' => 0,
                'net_salary' => 5500,
                'status' => 'draft',
                'created_by' => $this->user->id,
            ]);
        });

        // Now update the structure
        $structure->update(['basic_salary' => 9000, 'version' => 3]);

        // Payroll snapshot should still reflect the OLD values
        $payroll->refresh();
        $snapshot = $payroll->salary_structure_snapshot;

        $this->assertEquals(2, $snapshot['version']);
        $this->assertEquals(5000, $snapshot['basic_salary']);
        $this->assertCount(1, $snapshot['items']);
        $this->assertEquals('Housing', $snapshot['items'][0]['name']);
    }

    public function test_editing_structure_does_not_change_existing_payroll_values(): void
    {
        $this->createAuthenticatedUser();

        $structure = $this->createStructureWithItems(
            ['basic_salary' => 5000, 'version' => 1],
        );

        $employee = $this->createEmployee(['salary_structure_id' => $structure->id]);

        $payroll = Payroll::withoutEvents(function () use ($employee, $structure) {
            return Payroll::create([
                'tenant_id' => $this->tenant->id,
                'employee_id' => $employee->id,
                'salary_structure_id' => $structure->id,
                'salary_structure_snapshot' => $structure->toSnapshot(),
                'payroll_number' => 'PAY-000100',
                'pay_period_start' => now()->startOfMonth(),
                'pay_period_end' => now()->endOfMonth(),
                'pay_date' => now(),
                'basic_salary' => 5000,
                'allowances' => 0,
                'overtime_hours' => 0,
                'overtime_amount' => 0,
                'gross_salary' => 5000,
                'tax_deduction' => 0,
                'other_deductions' => 0,
                'total_deductions' => 0,
                'net_salary' => 5000,
                'status' => 'approved',
                'created_by' => $this->user->id,
            ]);
        });

        // Drastically change the structure
        $structure->update(['basic_salary' => 99999, 'name' => 'Renamed', 'version' => 2]);

        // Payroll record is immutable — values unchanged
        $payroll->refresh();
        $this->assertEquals(5000.00, (float) $payroll->basic_salary);
        $this->assertEquals(5000.00, (float) $payroll->net_salary);

        // Snapshot still shows original
        $this->assertEquals(1, $payroll->salary_structure_snapshot['version']);
        $this->assertEquals('Standard Package', $payroll->salary_structure_snapshot['name']);
        $this->assertEquals(5000, $payroll->salary_structure_snapshot['basic_salary']);
    }

    // ── Versions Relationship Tests ────────────────────────────

    public function test_versions_relationship_ordered_desc(): void
    {
        $this->createAuthenticatedUser();

        $structure = $this->createStructureWithItems(['version' => 3]);

        SalaryStructureVersion::create([
            'salary_structure_id' => $structure->id, 'version' => 1,
            'name' => 'v1', 'basic_salary' => 3000, 'items' => [],
            'created_at' => now()->subDays(2),
        ]);

        SalaryStructureVersion::create([
            'salary_structure_id' => $structure->id, 'version' => 2,
            'name' => 'v2', 'basic_salary' => 4000, 'items' => [],
            'created_at' => now()->subDay(),
        ]);

        $versions = $structure->versions;

        $this->assertCount(2, $versions);
        $this->assertEquals(2, $versions->first()->version); // Descending
        $this->assertEquals(1, $versions->last()->version);
    }

    public function test_version_items_snapshot_includes_all_item_fields(): void
    {
        $this->createAuthenticatedUser();

        $structure = $this->createStructureWithItems(
            [],
            [['name' => 'Housing', 'amount_type' => 'percentage', 'amount' => 15, 'is_taxable' => true]],
            [['name' => 'Pension', 'amount_type' => 'fixed', 'amount' => 200, 'is_taxable' => false]],
        );

        $version = $structure->createVersionSnapshot();

        $housing = collect($version->items)->firstWhere('name', 'Housing');
        $pension = collect($version->items)->firstWhere('name', 'Pension');

        $this->assertEquals('allowance', $housing['type']);
        $this->assertEquals('percentage', $housing['amount_type']);
        $this->assertEquals(15, (float) $housing['amount']);
        $this->assertTrue($housing['is_taxable']);

        $this->assertEquals('deduction', $pension['type']);
        $this->assertEquals('fixed', $pension['amount_type']);
        $this->assertEquals(200, (float) $pension['amount']);
        $this->assertFalse($pension['is_taxable']);
    }

    // ── Controller Integration Tests ───────────────────────────

    public function test_update_via_controller_creates_version_and_bumps(): void
    {
        $this->createAuthenticatedUser();

        // Grant the required permission
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'create payroll']);
        $this->user->givePermissionTo($permission);

        $structure = $this->createStructureWithItems(
            ['basic_salary' => 5000, 'version' => 1],
            [['name' => 'Housing', 'amount_type' => 'fixed', 'amount' => 500]],
        );

        $response = $this->actingAs($this->user)->put(route('salary-structures.update', $structure), [
            'name' => 'Updated Package',
            'basic_salary' => 6000,
            'effective_from' => now()->startOfYear()->toDateString(),
            'change_reason' => 'Annual review',
        ]);

        $response->assertRedirect(route('salary-structures.show', $structure));

        // Version bumped
        $structure->refresh();
        $this->assertEquals(2, $structure->version);
        $this->assertEquals(6000.00, (float) $structure->basic_salary);

        // Old version preserved
        $version = SalaryStructureVersion::where('salary_structure_id', $structure->id)->first();
        $this->assertNotNull($version);
        $this->assertEquals(1, $version->version);
        $this->assertEquals(5000, (float) $version->basic_salary);
        $this->assertEquals('Standard Package', $version->name);
        $this->assertEquals('Annual review', $version->change_reason);
        $this->assertCount(1, $version->items);
        $this->assertEquals('Housing', $version->items[0]['name']);
    }

    public function test_new_structure_starts_at_version_1(): void
    {
        $this->createAuthenticatedUser();

        $structure = $this->createStructureWithItems();

        $this->assertEquals(1, $structure->version);
        $this->assertCount(0, $structure->versions);
    }
}
