<?php

namespace Tests\Feature\Regression;

use App\Models\Employee;
use App\Models\Tenant;
use Tests\TestCase;

/**
 * Round 3, Phase A: urgent correctness fixes.
 */
class PhaseARegressionTest extends TestCase
{
    private function employeeFor(Tenant $tenant, array $attrs = []): Employee
    {
        return Employee::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $tenant->id,
            'employee_id' => Employee::generateEmployeeId($tenant->id),
            'first_name' => 'Amina',
            'last_name' => 'Bello',
            'hire_date' => '2026-01-05',
        ], $attrs));
    }

    public function test_r1_two_businesses_can_both_have_employee_emp_00001(): void
    {
        [$tenantA] = $this->createTenantWithSubscription();
        [$tenantB] = $this->createTenantWithSubscription();

        $a = $this->employeeFor($tenantA);
        $b = $this->employeeFor($tenantB);

        $this->assertSame('EMP-00001', $a->employee_id);
        $this->assertSame('EMP-00001', $b->employee_id);
    }

    public function test_r1_employee_ids_are_still_unique_within_one_business(): void
    {
        [$tenant] = $this->createTenantWithSubscription();
        $this->employeeFor($tenant, ['employee_id' => 'EMP-00007']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->employeeFor($tenant, ['employee_id' => 'EMP-00007']);
    }

    public function test_r1_generator_skips_ids_already_taken_by_imported_staff(): void
    {
        [$tenant] = $this->createTenantWithSubscription();
        $this->employeeFor($tenant, ['employee_id' => 'EMP-00002']);
        // An imported employee with a free-form ID is the latest row.
        $this->employeeFor($tenant, ['employee_id' => 'STAFF-9']);

        $next = Employee::generateEmployeeId($tenant->id);

        $this->assertSame('EMP-00003', $next);
    }

    public function test_r1_api_rejects_a_duplicate_employee_id_in_the_same_business(): void
    {
        $this->createAuthenticatedUser(['view employees', 'create employees']);
        $this->employeeFor($this->tenant, ['employee_id' => 'EMP-00010']);

        $this->postJson(route('api.employees.store'), [
            'employee_id' => 'EMP-00010',
            'first_name' => 'Musa',
            'last_name' => 'Garba',
            'email' => 'musa@example.com',
            'hire_date' => '2026-02-01',
        ])->assertStatus(422)->assertJsonValidationErrors('employee_id');
    }
}
