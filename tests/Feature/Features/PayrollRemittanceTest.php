<?php

namespace Tests\Feature\Features;

use App\Models\Employee;
use App\Models\Import;
use App\Models\PensionFundAdministrator;
use App\Models\State;
use App\Services\ImportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Nigeria payroll statutory remittances: employee statutory details,
 * contribution settings, schedules (PAYE by state, pension by PFA, NHF,
 * NSITF, ITF), recording remittances and due dates.
 */
class PayrollRemittanceTest extends TestCase
{
    private function state(string $name): State
    {
        return State::where('name', $name)->firstOrFail();
    }

    private function pfa(string $name): PensionFundAdministrator
    {
        return PensionFundAdministrator::whereNull('tenant_id')->where('name', $name)->firstOrFail();
    }

    // ── Part 1: employee statutory details ─────────────────────────

    public function test_the_pencom_pfa_list_and_nigerian_states_are_seeded(): void
    {
        $this->assertSame(19, PensionFundAdministrator::whereNull('tenant_id')->count());
        $this->assertNotNull($this->pfa('Stanbic IBTC Pension Managers Limited'));
        $this->assertSame(37, State::whereHas('country', fn ($q) => $q->where('code', 'NG'))->count());
    }

    public function test_employee_form_saves_statutory_details_and_normalises_the_rsa_pin(): void
    {
        $this->createAuthenticatedUser(['create employees', 'view employees']);

        $this->get(route('employees.create'))->assertOk()->assertSee('RSA PIN')->assertSee('Stanbic IBTC Pension Managers Limited');

        $this->post(route('employees.store'), [
            'first_name' => 'Aminu', 'last_name' => 'Bello', 'hire_date' => '2026-01-05', 'employment_type' => 'full-time',
            'tax_id' => '1234567890', 'tax_state_id' => $this->state('Kano')->id,
            'pension_fund_administrator_id' => $this->pfa('Stanbic IBTC Pension Managers Limited')->id,
            'rsa_pin' => 'pen 1001 2345 6789', 'nhf_number' => 'NHF-778899',
        ])->assertRedirect(route('employees.index'));

        $employee = Employee::where('first_name', 'Aminu')->sole();
        $this->assertSame('PEN100123456789', $employee->rsa_pin);
        $this->assertSame('Kano', $employee->taxState->name);
        $this->assertSame('NHF-778899', $employee->nhf_number);
        // Stored encrypted, like the tax ID.
        $raw = DB::table('employees')->where('id', $employee->id)->value('rsa_pin');
        $this->assertStringNotContainsString('PEN100123456789', (string) $raw);
    }

    public function test_rsa_pin_must_be_pen_and_twelve_digits(): void
    {
        $this->createAuthenticatedUser(['create employees']);
        $base = ['first_name' => 'A', 'last_name' => 'B', 'hire_date' => '2026-01-05', 'employment_type' => 'full-time'];

        foreach (['PEN12345', 'PEN1234567890123', 'ABC123456789012', 'PEN12345678901X'] as $bad) {
            $this->post(route('employees.store'), $base + ['rsa_pin' => $bad])->assertSessionHasErrors('rsa_pin');
        }
        $this->post(route('employees.store'), $base + ['rsa_pin' => 'PEN200987654321'])->assertSessionHasNoErrors();
    }

    public function test_a_pfa_added_by_another_business_cannot_be_chosen(): void
    {
        [$other] = $this->createTenantWithSubscription();
        $this->createAuthenticatedUser(['create employees']);
        $theirs = PensionFundAdministrator::create(['tenant_id' => $other->id, 'name' => 'Their Own PFA']);
        $ours = PensionFundAdministrator::create(['tenant_id' => $this->tenant->id, 'name' => 'Our Own PFA']);
        $base = ['first_name' => 'A', 'last_name' => 'B', 'hire_date' => '2026-01-05', 'employment_type' => 'full-time'];

        $this->post(route('employees.store'), $base + ['pension_fund_administrator_id' => $theirs->id])
            ->assertSessionHasErrors('pension_fund_administrator_id');
        $this->post(route('employees.store'), $base + ['pension_fund_administrator_id' => $ours->id])->assertSessionHasNoErrors();
    }

    public function test_api_masks_rsa_pin_and_nhf_number_without_payroll_rights(): void
    {
        $this->createAuthenticatedUser(['view employees']);
        $employee = Employee::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => 'EMP-90001', 'first_name' => 'Ngozi', 'last_name' => 'Eze',
            'hire_date' => '2026-01-01', 'tax_state_id' => $this->state('Lagos')->id,
            'pension_fund_administrator_id' => $this->pfa('Leadway Pensure PFA Limited')->id,
            'rsa_pin' => 'PEN100555666777', 'nhf_number' => 'NHF12345678',
        ]);

        $data = $this->getJson("/api/v1/employees/{$employee->id}")->assertOk()->json('data');
        $this->assertSame('****6777', $data['rsa_pin']);
        $this->assertSame('****5678', $data['nhf_number']);
        $this->assertSame('Lagos', $data['tax_state']['name']);
        $this->assertSame('Leadway Pensure PFA Limited', $data['pension_fund_administrator']['name']);

        $this->user->givePermissionTo(Permission::findOrCreate('edit payroll', 'web'));
        $data = $this->getJson("/api/v1/employees/{$employee->id}")->assertOk()->json('data');
        $this->assertSame('PEN100555666777', $data['rsa_pin']);
        $this->assertSame('NHF12345678', $data['nhf_number']);
    }

    public function test_employee_import_reads_statutory_columns(): void
    {
        Storage::fake('imports');
        $this->createAuthenticatedUser(['import data']);

        $csv = "name,email,hire_date,salary,tax_id,tax_state,pfa,rsa_pin,nhf_number\n"
            ."Musa Danladi,musa@example.com,2026-02-01,300000,2233445566,Kano,Stanbic IBTC Pension Managers Limited,PEN100111222333,NHF001\n"
            ."Bad Pin,bad@example.com,2026-02-01,300000,,Lagos,,PEN123,\n";
        $path = $this->tenant->id.'/employees.csv';
        Storage::disk('imports')->put($path, $csv);
        $import = Import::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'type' => Import::TYPE_EMPLOYEES,
            'format' => Import::FORMAT_CSV, 'status' => Import::STATUS_MAPPING,
            'original_filename' => 'employees.csv', 'file_path' => $path, 'file_size' => strlen($csv),
        ]);

        app(ImportService::class)->processImport($import);

        $musa = Employee::where('email', 'musa@example.com')->sole();
        $this->assertSame('Musa', $musa->first_name);
        $this->assertSame('Danladi', $musa->last_name);
        $this->assertSame('Kano', $musa->taxState->name);
        $this->assertSame('Stanbic IBTC Pension Managers Limited', $musa->pensionFundAdministrator->name);
        $this->assertSame('PEN100111222333', $musa->rsa_pin);
        $this->assertEqualsWithDelta(300000, (float) $musa->salary, 0.001);
        $this->assertSame(0, Employee::where('email', 'bad@example.com')->count());
        $this->assertStringContainsString('RSA PIN', implode(' ', $import->fresh()->errors ?? []));
    }
}
