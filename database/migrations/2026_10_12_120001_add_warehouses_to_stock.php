<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Warehouses (session 12): stock is kept per item per warehouse.
 *
 * - Every stock-moving document and every stock history row records its
 *   warehouse (warehouse_id).
 * - Every business gets a default "Main warehouse" (or its first existing
 *   warehouse becomes the default), and all its existing stock, cost
 *   layers, batches, serial numbers, history and documents are put in it.
 *   Nothing changes for a business that never adds a second warehouse:
 *   quantities, costs and the journal are left as they are.
 *
 * Rerunnable: columns are only added when missing, and the data step only
 * fills rows that still have no warehouse.
 */
return new class extends Migration
{
    /** Tables that get a warehouse_id column. */
    private const NEW_COLUMNS = [
        'inventory_histories',
        'inventory_layer_consumptions',
        'invoices',
        'sales_receipts',
        'bills',
        'credit_notes',
        'vendor_credits',
        'delivery_notes',
    ];

    /** Tables whose rows without a warehouse go into the default one. */
    private const FILL = [
        'inventory_layers',
        'inventory_batches',
        'serial_numbers',
        'inventory_histories',
        'inventory_layer_consumptions',
        'invoices',
        'sales_receipts',
        'bills',
        'credit_notes',
        'vendor_credits',
        'delivery_notes',
    ];

    public function up(): void
    {
        // One call per table, written out, so static analysis sees the columns.
        if (! Schema::hasColumn('inventory_histories', 'warehouse_id')) {
            Schema::table('inventory_histories', function (Blueprint $table) {
                $table->foreignId('warehouse_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('inventory_layer_consumptions', 'warehouse_id')) {
            Schema::table('inventory_layer_consumptions', function (Blueprint $table) {
                $table->foreignId('warehouse_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('invoices', 'warehouse_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreignId('warehouse_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('sales_receipts', 'warehouse_id')) {
            Schema::table('sales_receipts', function (Blueprint $table) {
                $table->foreignId('warehouse_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('bills', 'warehouse_id')) {
            Schema::table('bills', function (Blueprint $table) {
                $table->foreignId('warehouse_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('credit_notes', 'warehouse_id')) {
            Schema::table('credit_notes', function (Blueprint $table) {
                $table->foreignId('warehouse_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('vendor_credits', 'warehouse_id')) {
            Schema::table('vendor_credits', function (Blueprint $table) {
                $table->foreignId('warehouse_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('delivery_notes', 'warehouse_id')) {
            Schema::table('delivery_notes', function (Blueprint $table) {
                $table->foreignId('warehouse_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            });
        }

        foreach (DB::table('tenants')->orderBy('id')->pluck('id') as $tenantId) {
            $warehouseId = $this->defaultWarehouse((int) $tenantId);
            $this->moveInventoryRows((int) $tenantId, $warehouseId);

            foreach (self::FILL as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'warehouse_id')) {
                    DB::table($table)->where('tenant_id', $tenantId)->whereNull('warehouse_id')
                        ->update(['warehouse_id' => $warehouseId]);
                }
            }
        }
    }

    /** The business's default warehouse, made if it has none. */
    private function defaultWarehouse(int $tenantId): int
    {
        $default = DB::table('warehouses')->where('tenant_id', $tenantId)->where('is_default', true)->orderBy('id')->value('id');
        if ($default) {
            // Exactly one default.
            DB::table('warehouses')->where('tenant_id', $tenantId)->where('id', '!=', $default)->update(['is_default' => false]);

            return (int) $default;
        }

        $first = DB::table('warehouses')->where('tenant_id', $tenantId)->orderBy('id')->value('id');
        if ($first) {
            DB::table('warehouses')->where('id', $first)->update(['is_default' => true, 'is_active' => true]);

            return (int) $first;
        }

        return (int) DB::table('warehouses')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => 'Main warehouse',
            'code' => 'MAIN',
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Stock records without a warehouse go into the default one. If the item
     * already has a record there (the switched-off module was used), the two
     * are added together, at their combined average cost.
     */
    private function moveInventoryRows(int $tenantId, int $warehouseId): void
    {
        $loose = DB::table('inventories')->where('tenant_id', $tenantId)->whereNull('warehouse_id')->get();

        foreach ($loose as $row) {
            $existing = DB::table('inventories')->where('tenant_id', $tenantId)
                ->where('item_id', $row->item_id)->where('warehouse_id', $warehouseId)->first();

            if (! $existing) {
                DB::table('inventories')->where('id', $row->id)->update(['warehouse_id' => $warehouseId]);

                continue;
            }

            $qty = (float) $existing->quantity + (float) $row->quantity;
            $value = (float) $existing->quantity * (float) $existing->unit_cost + (float) $row->quantity * (float) $row->unit_cost;
            DB::table('inventories')->where('id', $existing->id)->update([
                'quantity' => round($qty, 4),
                'reserved_quantity' => round((float) $existing->reserved_quantity + (float) $row->reserved_quantity, 4),
                'unit_cost' => $qty > 0 ? round($value / $qty, 4) : $existing->unit_cost,
                'updated_at' => now(),
            ]);
            DB::table('inventories')->where('id', $row->id)->delete();
        }
    }

    public function down(): void
    {
        // The data stays where it is; only the new columns go.
        foreach (self::NEW_COLUMNS as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'warehouse_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropConstrainedForeignId('warehouse_id');
                });
            }
        }
    }
};
