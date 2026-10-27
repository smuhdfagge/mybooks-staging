<?php

namespace Tests\Feature\Imports;

use App\Models\Import;
use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The import screen must offer only what it can actually import.
 *
 * Invoices, bills and journal entries were on the menu with templates to
 * download, and failed at the end of the job with "Unsupported import
 * type": ImportService has no reader for them. Someone could choose one,
 * fill in the template, upload it, map every column, and only then be
 * told. Budget lines were there too and always failed with "Budget not
 * found", because they belong to a budget and are imported from its own
 * page.
 */
class ImportTypesOnOfferTest extends TestCase
{
    use RefreshDatabase;

    public static function offered(): array
    {
        return array_map(fn ($t) => [$t], array_keys(Import::getSupportedImportTypes()));
    }

    #[DataProvider('offered')]
    public function test_every_type_on_offer_can_be_imported(string $type): void
    {
        $user = $this->createAuthenticatedUser();
        Storage::fake('imports');
        Storage::disk('imports')->put('imports/t.csv', "a,b\n1,2\n");

        $import = Import::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'type' => $type,
            'format' => Import::FORMAT_CSV,
            'status' => Import::STATUS_PENDING,
            'original_filename' => 't.csv',
            'file_path' => 'imports/t.csv',
            'column_mapping' => [],
            'options' => [],
        ]);

        app(ImportService::class)->processImport($import);

        // The rows are nonsense, so they will be rejected — the point is
        // that the type itself is handled rather than refused outright.
        $this->assertNotSame(Import::STATUS_FAILED, $import->fresh()->status, "{$type} cannot be imported.");
    }

    #[DataProvider('offered')]
    public function test_every_type_on_offer_has_a_template(string $type): void
    {
        $this->assertNotEmpty(ImportService::getSampleData($type), "{$type} has no sample template.");
    }

    public function test_a_type_with_no_reader_is_not_offered(): void
    {
        $offered = Import::getSupportedImportTypes();

        foreach ([Import::TYPE_INVOICES, Import::TYPE_BILLS, Import::TYPE_JOURNALS, Import::TYPE_BUDGET_LINES] as $type) {
            $this->assertArrayNotHasKey($type, $offered);
        }
    }

    public function test_choosing_one_anyway_is_refused(): void
    {
        $this->createAuthenticatedUser(['import data']);

        // Not merely hidden from the form.
        $this->post(route('imports.upload'), ['type' => Import::TYPE_INVOICES])
            ->assertSessionHasErrors('type');
    }

    public function test_a_template_names_only_columns_the_importer_reads(): void
    {
        // The employee template used to offer a "name" column that the
        // importer never read, so every row failed on first_name.
        $employees = ImportService::getSampleData(Import::TYPE_EMPLOYEES)[0];

        $this->assertArrayHasKey('first_name', $employees);
        $this->assertArrayHasKey('last_name', $employees);
        $this->assertArrayHasKey('hire_date', $employees, 'Hire date is required, so the template must show it.');
    }

    public function test_the_templates_show_amounts_the_way_the_import_reads_them(): void
    {
        $money = ['amount', 'sale_price', 'purchase_price', 'salary', 'debit', 'credit', 'initial_stock'];

        foreach ([Import::TYPE_ITEMS, Import::TYPE_EXPENSES, Import::TYPE_EMPLOYEES, Import::TYPE_OPENING_BALANCES] as $type) {
            foreach (ImportService::getSampleData($type) as $row) {
                foreach (array_intersect_key($row, array_flip($money)) as $column => $value) {
                    // A sample showing "95,000.00" would teach people to
                    // write the one thing the import cannot read. People
                    // copy templates.
                    $this->assertStringNotContainsString(',', (string) $value, "{$type}.{$column} shows a separator.");
                }
            }
        }
    }

    public function test_an_older_import_still_shows_its_name(): void
    {
        // The labels stay whole, so a row recorded before a type was
        // withdrawn is not shown as a bare slug.
        $this->assertSame('Invoices', Import::getImportTypes()[Import::TYPE_INVOICES]);
        $this->assertSame('Budget Line Items', Import::getImportTypes()[Import::TYPE_BUDGET_LINES]);
    }
}
