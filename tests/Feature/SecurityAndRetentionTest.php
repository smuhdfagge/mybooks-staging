<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\SalaryStructure;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SecurityAndRetentionTest extends TestCase
{
    protected function createEmployee(array $attrs = []): Employee
    {
        return Employee::withoutEvents(function () use ($attrs) {
            return Employee::create(array_merge([
                'tenant_id' => $this->tenant->id,
                'employee_id' => 'EMP-'.str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT),
                'first_name' => 'Test',
                'last_name' => 'Employee',
                'email' => 'test'.rand(1, 99999).'@example.com',
                'hire_date' => now()->subYear(),
                'status' => 'active',
            ], $attrs));
        });
    }

    // ── Sensitive Field Audit Tests ────────────────────────────

    public function test_salary_change_creates_sensitive_field_audit(): void
    {
        $this->createAuthenticatedUser();
        $employee = $this->createEmployee(['salary' => '50000']);

        // Update salary — this should trigger AuditsSensitiveFields
        $employee->salary = '60000';
        $employee->save();

        $audit = ActivityLog::where('action', 'sensitive_field_changed')
            ->where('model_type', Employee::class)
            ->where('model_id', $employee->id)
            ->first();

        $this->assertNotNull($audit, 'Sensitive field audit log should exist');
        $this->assertContains('salary', $audit->changed_fields);
        $this->assertEquals('***', $audit->old_values['salary']);
        $this->assertEquals('increased', $audit->new_values['salary']);
    }

    public function test_bank_account_change_creates_masked_audit(): void
    {
        $this->createAuthenticatedUser();
        $employee = $this->createEmployee([
            'bank_account_number' => '1234567890',
        ]);

        $employee->bank_account_number = '9876543210';
        $employee->save();

        $audit = ActivityLog::where('action', 'sensitive_field_changed')
            ->where('model_type', Employee::class)
            ->where('model_id', $employee->id)
            ->first();

        $this->assertNotNull($audit, 'Bank change audit should exist');
        $this->assertContains('bank_account_number', $audit->changed_fields);
        // Masked values should show last 4 digits
        $this->assertStringEndsWith('7890', $audit->old_values['bank_account_number']);
        $this->assertStringEndsWith('3210', $audit->new_values['bank_account_number']);
        // But NOT the full number
        $this->assertStringStartsWith('*', $audit->old_values['bank_account_number']);
    }

    public function test_status_change_creates_plain_audit(): void
    {
        $this->createAuthenticatedUser();
        $employee = $this->createEmployee(['status' => 'active']);

        $employee->status = 'terminated';
        $employee->termination_date = now();
        $employee->save();

        $audit = ActivityLog::where('action', 'sensitive_field_changed')
            ->where('model_type', Employee::class)
            ->where('model_id', $employee->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertContains('status', $audit->changed_fields);
        $this->assertEquals('active', $audit->old_values['status']);
        $this->assertEquals('terminated', $audit->new_values['status']);
    }

    public function test_salary_structure_basic_salary_change_audited(): void
    {
        $this->createAuthenticatedUser();

        $structure = SalaryStructure::withoutEvents(function () {
            return SalaryStructure::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'Test Structure',
                'basic_salary' => 3000.00,
                'is_active' => true,
                'effective_from' => now()->startOfYear(),
                'created_by' => $this->user->id,
            ]);
        });

        $structure->basic_salary = 4500.00;
        $structure->save();

        $audit = ActivityLog::where('action', 'sensitive_field_changed')
            ->where('model_type', SalaryStructure::class)
            ->where('model_id', $structure->id)
            ->first();

        $this->assertNotNull($audit, 'Salary structure change should be audited');
        $this->assertContains('basic_salary', $audit->changed_fields);
        $this->assertEquals('increased', $audit->new_values['basic_salary']);
    }

    public function test_non_sensitive_change_does_not_create_sensitive_audit(): void
    {
        $this->createAuthenticatedUser();
        $employee = $this->createEmployee();

        $employee->notes = 'Updated note';
        $employee->save();

        $audit = ActivityLog::where('action', 'sensitive_field_changed')
            ->where('model_type', Employee::class)
            ->where('model_id', $employee->id)
            ->first();

        $this->assertNull($audit, 'Non-sensitive field change should not create sensitive audit');
    }

    public function test_multiple_sensitive_fields_in_one_update(): void
    {
        $this->createAuthenticatedUser();
        $employee = $this->createEmployee([
            'salary' => '50000',
            'bank_name' => 'Old Bank',
        ]);

        $employee->salary = '55000';
        $employee->bank_name = 'New Bank';
        $employee->save();

        $audit = ActivityLog::where('action', 'sensitive_field_changed')
            ->where('model_type', Employee::class)
            ->where('model_id', $employee->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertCount(2, $audit->changed_fields);
        $this->assertContains('salary', $audit->changed_fields);
        $this->assertContains('bank_name', $audit->changed_fields);
    }

    public function test_sensitive_audit_description_includes_field_labels(): void
    {
        $this->createAuthenticatedUser();
        $employee = $this->createEmployee(['salary' => '50000']);

        $employee->salary = '60000';
        $employee->save();

        $audit = ActivityLog::where('action', 'sensitive_field_changed')
            ->where('model_id', $employee->id)
            ->first();

        $this->assertStringContains('Base Salary', $audit->description);
    }

    // ── Data Retention / Purge Tests ──────────────────────────

    public function test_retention_config_has_defaults(): void
    {
        $config = config('mybooks.retention');

        $this->assertNotNull($config);
        $this->assertEquals(84, $config['terminated_employee_months']);
        $this->assertEquals(84, $config['activity_log_months']);
        $this->assertEquals(12, $config['soft_deleted_months']);
    }

    public function test_purge_command_dry_run_shows_counts(): void
    {
        $this->createAuthenticatedUser();

        // Create a terminated employee past retention (> 84 months ago)
        $employee = $this->createEmployee([
            'status' => 'terminated',
            'termination_date' => now()->subMonths(85),
        ]);

        $result = Artisan::call('retention:purge', ['--dry-run' => true]);

        $this->assertEquals(0, $result);
        // Employee should NOT be anonymized (dry run)
        $employee->refresh();
        $this->assertEquals('Test', $employee->first_name);
    }

    public function test_purge_anonymizes_terminated_employee_past_retention(): void
    {
        $this->createAuthenticatedUser();

        $employee = $this->createEmployee([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'bank_account_number' => '1234567890',
            'tax_id' => 'TAX123',
            'status' => 'terminated',
            'termination_date' => now()->subMonths(85),
        ]);

        Artisan::call('retention:purge', [
            '--force' => true,
            '--tenant' => $this->tenant->id,
        ]);

        $employee->refresh();
        $this->assertEquals('ANONYMIZED', $employee->first_name);
        $this->assertEquals('EMPLOYEE', $employee->last_name);
        $this->assertStringContains('redacted.local', $employee->email);
        $this->assertNull($employee->getRawOriginal('bank_account_number'));
        $this->assertNull($employee->getRawOriginal('tax_id'));
    }

    public function test_purge_does_not_anonymize_active_employees(): void
    {
        $this->createAuthenticatedUser();

        $employee = $this->createEmployee([
            'first_name' => 'Active',
            'status' => 'active',
        ]);

        Artisan::call('retention:purge', [
            '--force' => true,
            '--tenant' => $this->tenant->id,
        ]);

        $employee->refresh();
        $this->assertEquals('Active', $employee->first_name);
    }

    public function test_purge_does_not_anonymize_recently_terminated(): void
    {
        $this->createAuthenticatedUser();

        $employee = $this->createEmployee([
            'first_name' => 'Recent',
            'status' => 'terminated',
            'termination_date' => now()->subMonths(6),
        ]);

        Artisan::call('retention:purge', [
            '--force' => true,
            '--tenant' => $this->tenant->id,
        ]);

        $employee->refresh();
        $this->assertEquals('Recent', $employee->first_name);
    }

    public function test_purge_creates_anonymization_audit_log(): void
    {
        $this->createAuthenticatedUser();

        $employee = $this->createEmployee([
            'status' => 'terminated',
            'termination_date' => now()->subMonths(85),
        ]);

        Artisan::call('retention:purge', [
            '--force' => true,
            '--tenant' => $this->tenant->id,
        ]);

        $audit = ActivityLog::where('action', 'anonymized')
            ->where('model_type', Employee::class)
            ->where('model_id', $employee->id)
            ->first();

        $this->assertNotNull($audit, 'Anonymization should be logged');
        $this->assertStringContains('retention policy', $audit->description);
    }

    public function test_purge_prunes_old_activity_logs(): void
    {
        $this->createAuthenticatedUser();

        // Create an old activity log and backdate it via DB update
        $log = ActivityLog::withoutEvents(function () {
            return ActivityLog::create([
                'tenant_id' => $this->tenant->id,
                'user_id' => $this->user->id,
                'user_name' => 'Test',
                'action' => 'created',
                'model_type' => Employee::class,
                'model_id' => 1,
                'description' => 'Old log',
            ]);
        });

        // Force backdate via raw DB update to bypass Eloquent timestamp handling
        \Illuminate\Support\Facades\DB::table('activity_logs')
            ->where('id', $log->id)
            ->update(['created_at' => now()->subMonths(85)]);

        $this->assertDatabaseCount('activity_logs', 1);

        Artisan::call('retention:purge', [
            '--force' => true,
            '--tenant' => $this->tenant->id,
        ]);

        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_purge_hard_deletes_old_soft_deleted_employees(): void
    {
        $this->createAuthenticatedUser();

        $employee = $this->createEmployee(['status' => 'terminated']);
        $employee->deleted_at = now()->subMonths(13);
        $employee->saveQuietly();

        // Confirm it's soft-deleted
        $this->assertSoftDeleted('employees', ['id' => $employee->id]);

        Artisan::call('retention:purge', [
            '--force' => true,
            '--tenant' => $this->tenant->id,
        ]);

        // Should be permanently gone
        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
    }

    // ── Helper ──

    protected function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'."
        );
    }
}
