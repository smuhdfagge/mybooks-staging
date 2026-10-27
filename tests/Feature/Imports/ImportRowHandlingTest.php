<?php

namespace Tests\Feature\Imports;

use App\Models\ChartOfAccount;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Import;
use App\Models\Item;
use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * What a row does when it cannot be imported.
 *
 * The theme the rest of the import work shares: a row that cannot be
 * handled must say so, and must not leave anything behind.
 */
class ImportRowHandlingTest extends TestCase
{
    use RefreshDatabase;

    private function runImport(string $type, string $csv, array $mapping, array $options = []): Import
    {
        Storage::fake('imports');
        Storage::disk('imports')->put('imports/test.csv', $csv);

        $user = $this->user;

        $import = Import::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'type' => $type,
            'format' => Import::FORMAT_CSV,
            'status' => Import::STATUS_PENDING,
            'original_filename' => 'test.csv',
            'file_path' => 'imports/test.csv',
            'column_mapping' => $mapping,
            'options' => $options,
        ]);

        app(ImportService::class)->processImport($import);

        return $import->fresh();
    }

    public function test_an_unknown_item_type_fails_the_row_rather_than_becoming_a_product(): void
    {
        $this->createAuthenticatedUser();

        $import = $this->runImport(
            'items',
            "name,type,sale_price\nConsulting,consultancy,50000\n",
            ['name' => 'name', 'type' => 'type', 'sale_price' => 'sale_price'],
        );

        // It used to be imported as a product: something the business
        // holds in stock and counts.
        $this->assertSame(0, Item::where('tenant_id', $this->user->tenant_id)->where('name', 'Consulting')->count());
        $this->assertSame(1, $import->failed_rows);
        $this->assertStringContainsString('not a type I know', implode(' ', $import->errors ?? []));
    }

    public function test_the_two_types_it_does_know_still_import(): void
    {
        $this->createAuthenticatedUser();

        $this->runImport(
            'items',
            "name,type,sale_price\nConsulting, Service ,50000\nStapler,product,1500\n",
            ['name' => 'name', 'type' => 'type', 'sale_price' => 'sale_price'],
        );

        $items = Item::where('tenant_id', $this->user->tenant_id)->pluck('type', 'name');

        // Spaces and capitals are the way a person types, not an error.
        $this->assertSame('service', $items['Consulting'] ?? null);
        $this->assertSame('product', $items['Stapler'] ?? null);
    }

    public function test_an_income_account_can_be_imported(): void
    {
        $user = $this->createAuthenticatedUser();

        $import = $this->runImport(
            'chart_of_accounts',
            "code,name,type\n4500,Haulage Income,income\n4600,Rental Income,revenue\n",
            ['code' => 'code', 'name' => 'name', 'type' => 'type'],
        );

        // Neither spelling worked: "income" was refused by the importer's own
        // list of types, and "revenue" — which the template told people to
        // write — was refused by the column. So the Sales Revenue row in the
        // downloadable template could never be imported.
        $this->assertSame(0, $import->failed_rows, implode(' | ', $import->errors ?? []));

        $types = ChartOfAccount::where('tenant_id', $user->tenant_id)
            ->whereIn('account_code', ['4500', '4600'])
            ->pluck('type', 'account_code');

        $this->assertSame('income', $types['4500'] ?? null);
        // Written the way other software writes it, and still an income account.
        $this->assertSame('income', $types['4600'] ?? null);
    }

    public function test_an_account_type_that_is_not_a_type_is_still_refused(): void
    {
        $this->createAuthenticatedUser();

        $import = $this->runImport(
            'chart_of_accounts',
            "code,name,type\n4700,Something,turnover\n",
            ['code' => 'code', 'name' => 'name', 'type' => 'type'],
        );

        $this->assertSame(1, $import->failed_rows);
        $this->assertStringContainsString('Invalid account type', implode(' ', $import->errors ?? []));
    }

    public function test_a_failed_employee_row_leaves_no_department_behind(): void
    {
        $user = $this->createAuthenticatedUser();
        $taken = Employee::create([
            'tenant_id' => $user->tenant_id,
            'employee_id' => 'EMP-0001',
            'first_name' => 'Musa',
            'last_name' => 'Ibrahim',
            'email' => 'musa@example.test',
            'hire_date' => '2025-01-06',
            'status' => 'active',
        ]);

        $import = $this->runImport(
            'employees',
            "name,email,employee_id,department,designation,hire_date\nAisha Bello,aisha@example.test,EMP-0001,Logistics,Driver,01/03/2026\n",
            ['name' => 'name', 'email' => 'email', 'employee_id' => 'employee_id', 'department' => 'department', 'designation' => 'designation', 'hire_date' => 'hire_date'],
        );

        // The duplicate ID was checked after the department and the
        // designation had already been created, so a row that could never
        // succeed still left both behind.
        $this->assertSame(1, $import->failed_rows);
        $this->assertSame(0, Department::where('tenant_id', $user->tenant_id)->where('name', 'Logistics')->count());
        $this->assertSame(0, Designation::where('tenant_id', $user->tenant_id)->where('name', 'Driver')->count());
        $this->assertSame('EMP-0001', $taken->fresh()->employee_id);
    }

    public function test_a_good_employee_row_still_brings_its_department(): void
    {
        $user = $this->createAuthenticatedUser();

        $this->runImport(
            'employees',
            "name,email,department,designation,hire_date\nAisha Bello,aisha@example.test,Logistics,Driver,01/03/2026\n",
            ['name' => 'name', 'email' => 'email', 'department' => 'department', 'designation' => 'designation', 'hire_date' => 'hire_date'],
        );

        $employee = Employee::where('tenant_id', $user->tenant_id)->where('email', 'aisha@example.test')->first();

        $this->assertNotNull($employee, 'The employee row did not import.');
        $this->assertSame('Aisha', $employee->first_name);
        $this->assertSame('Bello', $employee->last_name);
        $this->assertSame('Logistics', $employee->department?->name);
        $this->assertSame('Driver', $employee->designation?->name);
        // Read day first, like every other date in the import.
        $this->assertSame('2026-03-01', $employee->hire_date?->format('Y-m-d'));
    }
}
