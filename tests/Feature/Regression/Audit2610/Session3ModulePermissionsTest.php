<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Models\CustomReport;
use App\Models\Export;
use App\Models\Import;
use App\Models\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 3: exports, imports and reports must
 * respect the module's own permission, not just "export reports",
 * "import data" or "view reports" (MB-06, MB-07, MB-12), and the Sales
 * role must not see VAT returns.
 */
class Session3ModulePermissionsTest extends TestCase
{
    private const PAYROLL_REPORTS = [
        'payroll-summary', 'payroll-by-department', 'employee-earnings', 'payroll-register', 'ytd-earnings',
        'tax-liability-payroll', 'employer-contributions', 'bank-disbursement', 'salary-revision-history',
    ];

    private const PAYROLL_API = [
        'payroll-summary', 'payroll-register', 'ytd-earnings', 'tax-liability-payroll',
        'employer-contributions', 'bank-disbursement', 'salary-revision-history',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    // MB-06
    public function test_export_reports_alone_cannot_export_sensitive_records(): void
    {
        $this->createAuthenticatedUser(['export reports']);

        foreach ([Export::TYPE_EMPLOYEES, Export::TYPE_PAYROLL, Export::TYPE_ACTIVITY_LOGS, Export::TYPE_CUSTOMERS] as $type) {
            $this->post(route('exports.store'), ['type' => $type, 'format' => 'csv'])->assertForbidden();
            $this->post(route('exports.quick'), ['type' => $type, 'format' => 'csv'])->assertForbidden();
        }
        $this->post(route('exports.store'), ['type' => Export::TYPE_FULL_BACKUP, 'format' => 'json'])->assertForbidden();
        $this->get(route('exports.backup'))->assertForbidden();
        $this->post(route('exports.backup.process'), ['format' => 'json', 'included_data' => ['customers']])->assertForbidden();
        $this->assertSame(0, Export::count());
    }

    public function test_an_export_needs_the_view_permission_of_its_records(): void
    {
        $this->createAuthenticatedUser(['export reports', 'view customers']);

        $this->post(route('exports.store'), ['type' => Export::TYPE_CUSTOMERS, 'format' => 'csv'])->assertRedirect(route('exports.index'));
        $this->post(route('exports.store'), ['type' => Export::TYPE_EMPLOYEES, 'format' => 'csv'])->assertForbidden();
        $this->assertSame([Export::TYPE_CUSTOMERS], Export::pluck('type')->all());

        $this->get(route('exports.create'))->assertOk()
            ->assertSee('<option value="customers"', false)
            ->assertDontSee('<option value="employees"', false);
    }

    public function test_a_full_backup_needs_export_data_and_each_part(): void
    {
        $this->createAuthenticatedUser(['export reports', 'export data', 'view customers']);

        $this->get(route('exports.backup'))->assertOk()->assertSee('value="customers"', false)->assertDontSee('value="employees"', false);
        $this->post(route('exports.backup.process'), ['format' => 'json', 'included_data' => ['customers', 'employees']])->assertForbidden();
        $this->post(route('exports.backup.process'), ['format' => 'json', 'included_data' => ['customers']])->assertRedirect(route('exports.index'));
        $this->post(route('exports.store'), ['type' => Export::TYPE_FULL_BACKUP, 'format' => 'json'])->assertForbidden(); // all parts
    }

    public function test_someone_elses_sensitive_export_cannot_be_seen_or_downloaded(): void
    {
        Storage::fake('exports');
        $this->createAuthenticatedUser(['export reports', 'view customers']);
        Storage::disk('exports')->put('x/employees.csv', 'name,salary');
        $export = Export::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'type' => Export::TYPE_EMPLOYEES, 'format' => 'csv',
            'status' => Export::STATUS_COMPLETED, 'file_path' => 'x/employees.csv', 'filename' => 'employees.csv', 'expires_at' => now()->addDay(),
        ]);

        $this->get(route('exports.show', $export))->assertForbidden();
        $this->get(route('exports.download', $export))->assertForbidden();
        $this->delete(route('exports.destroy', $export))->assertForbidden();
        $this->get(route('exports.index'))->assertOk()->assertDontSee(route('exports.download', $export), false);
    }

    // MB-12
    public function test_import_data_alone_cannot_import_any_type(): void
    {
        Storage::fake('imports');
        $this->createAuthenticatedUser(['import data']);
        $file = UploadedFile::fake()->createWithContent('rows.csv', "name\nAda\n");

        foreach (array_keys(Import::getSupportedImportTypes()) as $type) {
            $this->post(route('imports.upload'), ['type' => $type, 'file' => $file])->assertForbidden();
            $this->get(route('imports.template', ['type' => $type]))->assertForbidden();
        }
        $this->assertSame(0, Import::count());
    }

    public function test_an_import_needs_the_create_permission_of_its_type(): void
    {
        Storage::fake('imports');
        $this->createAuthenticatedUser(['import data', 'create customers']);
        $file = UploadedFile::fake()->createWithContent('rows.csv', "name\nAda\n");

        $this->post(route('imports.upload'), ['type' => Import::TYPE_CUSTOMERS, 'file' => $file])->assertRedirect();
        $this->assertSame(1, Import::count());
        $this->post(route('imports.upload'), ['type' => Import::TYPE_EMPLOYEES, 'file' => $file])->assertForbidden();

        $employees = Import::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'type' => Import::TYPE_EMPLOYEES, 'format' => Import::FORMAT_CSV,
            'status' => Import::STATUS_FAILED, 'original_filename' => 'e.csv', 'file_path' => 'e.csv', 'column_mapping' => [], 'options' => [],
        ]);
        $this->get(route('imports.mapping', $employees))->assertForbidden();
        $this->post(route('imports.process', $employees), ['mapping' => ['name' => 'first_name']])->assertForbidden();
        $this->post(route('imports.retry', $employees))->assertForbidden();

        $this->get(route('imports.create'))->assertOk()
            ->assertSee('<option value="customers"', false)
            ->assertDontSee('<option value="employees"', false);
    }

    // MB-07
    public function test_view_reports_alone_cannot_see_payroll_reports(): void
    {
        $this->createAuthenticatedUser(['view reports']);

        foreach (self::PAYROLL_REPORTS as $report) {
            $this->get(route("reports.{$report}"))->assertForbidden();
            $this->get(route("reports.export.{$report}"))->assertForbidden();
        }
        $this->get(route('reports.index'))->assertOk()->assertDontSee(route('reports.payroll-summary'), false);

        $token = $this->user->createToken('t')->plainTextToken;
        foreach (self::PAYROLL_API as $report) {
            $this->withToken($token)->getJson("/api/v1/reports/{$report}")->assertForbidden();
        }
    }

    public function test_view_payroll_opens_the_payroll_reports(): void
    {
        $this->createAuthenticatedUser(['view reports', 'view payroll']);

        $this->get(route('reports.payroll-summary'))->assertOk();
        $this->get(route('reports.index'))->assertOk()->assertSee(route('reports.payroll-summary'), false);
    }

    public function test_a_custom_report_needs_the_view_permission_of_its_data(): void
    {
        $this->createAuthenticatedUser(['view reports', 'view invoices']);
        $payroll = CustomReport::create([
            'tenant_id' => $this->tenant->id, 'created_by' => $this->user->id, 'name' => 'Salaries', 'data_source' => 'payroll',
            'columns' => ['employee.full_name', 'net_salary'], 'is_public' => true,
        ]);

        $this->post(route('reports.custom.store'), ['name' => 'Pay', 'data_source' => 'payroll', 'columns' => ['net_salary']])->assertForbidden();
        $this->get(route('reports.custom.run', $payroll))->assertForbidden();
        $this->get(route('reports.custom.edit', $payroll))->assertForbidden();
        $this->get(route('reports.custom.get-columns', ['data_source' => 'payroll']))->assertForbidden();
        $this->get(route('reports.custom.index'))->assertOk()->assertDontSee('Salaries');
        $this->get(route('reports.custom.create'))->assertOk()->assertDontSee('value="payroll"', false);

        $this->post(route('reports.custom.store'), ['name' => 'Sales', 'data_source' => 'invoices', 'columns' => ['invoice_number']])->assertSessionHasNoErrors();
    }

    // VAT returns for the Sales role
    public function test_view_reports_alone_does_not_open_vat_returns(): void
    {
        $this->createAuthenticatedUser(['view reports']);

        $this->get(route('reports.vat-return'))->assertForbidden();
        $this->get(route('reports.vat-return.export'))->assertForbidden();
        $this->get(route('reports.vat-gst-return'))->assertForbidden();
        $this->get(route('reports.index'))->assertOk()->assertDontSee(route('reports.vat-return'), false);

        $this->user->givePermissionTo(Permission::findOrCreate('view vat-returns', 'web'));
        $this->get(route('reports.vat-return'))->assertOk();
    }

    public function test_the_migration_gives_vat_returns_to_accounting_roles_only(): void
    {
        Permission::where('name', 'view vat-returns')->delete();
        foreach (['file vat-returns', 'view journals', 'view reports'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $accountant = Role::create(['name' => 'Bookkeeper', 'guard_name' => 'web', 'tenant_id' => null]);
        $accountant->givePermissionTo('file vat-returns');
        $viewer = Role::create(['name' => 'Auditor', 'guard_name' => 'web', 'tenant_id' => null]);
        $viewer->givePermissionTo('view journals');
        $sales = Role::create(['name' => 'Shop floor', 'guard_name' => 'web', 'tenant_id' => null]);
        $sales->givePermissionTo('view reports');

        (require database_path('migrations/2026_10_28_100001_add_view_vat_returns_permission.php'))->up();

        $this->assertTrue($accountant->fresh()->hasPermissionTo('view vat-returns'));
        $this->assertTrue($viewer->fresh()->hasPermissionTo('view vat-returns'));
        $this->assertFalse($sales->fresh()->hasPermissionTo('view vat-returns'));
    }
}
