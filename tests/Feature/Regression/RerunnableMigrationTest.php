<?php

namespace Tests\Feature\Regression;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * On MySQL a migration that stops part way leaves its first tables behind
 * without being recorded, and the next run failed with "Table 'warehouses'
 * already exists". The inventory migration can now be run again.
 */
class RerunnableMigrationTest extends TestCase
{
    private function migration(): object
    {
        return require database_path('migrations/2026_04_06_000004_create_inventory_module_enhancements_table.php');
    }

    public function test_inventory_migration_can_run_again_when_everything_exists(): void
    {
        $this->migration()->up();

        $this->assertTrue(Schema::hasTable('warehouses'));
        $this->assertTrue(Schema::hasColumn('items', 'valuation_method'));
    }

    public function test_inventory_migration_creates_only_what_is_missing(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::drop('uom_conversions');
        Schema::drop('serial_numbers');
        Schema::enableForeignKeyConstraints();

        $this->migration()->up();

        $this->assertTrue(Schema::hasTable('uom_conversions'));
        $this->assertTrue(Schema::hasTable('serial_numbers'));
    }

    public function test_zero_dates_are_repaired_so_tables_can_be_altered(): void
    {
        if (! in_array(\DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Zero dates only exist on MySQL/MariaDB.');
        }

        $this->createAuthenticatedUser();
        $item = \App\Models\Item::factory()->create(['tenant_id' => $this->tenant->id]);
        \DB::statement("SET SESSION sql_mode = ''");
        \DB::update("UPDATE items SET created_at = '0000-00-00 00:00:00' WHERE id = ?", [$item->id]);
        \DB::statement("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");

        (require database_path('migrations/2026_04_06_000003a_repair_zero_dates.php'))->up();

        $this->assertNotNull($item->fresh()->created_at);
        $this->assertGreaterThan(1000, $item->fresh()->created_at->year);
    }
}
