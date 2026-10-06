<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Assembly with bills of materials (session 14).
 *
 * - A finished item can have more than one bill of materials (versions),
 *   with optional extra costs per batch (labour, power, packaging).
 * - An assembly order gets a date, a warehouse for the finished goods, the
 *   quantity planned and made (in units of the finished item), its costs,
 *   and lines for the components used and the extra costs, so a build can
 *   record what was really used and be undone exactly.
 * - Old orders: the quantity was a number of batches; the quantity planned
 *   is that times the batch size. "In progress" becomes "draft".
 * - Stock history gets an "assembly" type.
 * - Every business gets a "Production Costs Applied" account (5500, or the
 *   next free code) to credit extra costs moved into stock.
 *
 * Rerunnable: tables, columns and indexes are only added when missing and
 * the account only when the business has none.
 */
return new class extends Migration
{
    private const CODES = ['5500', '5510', '5520', '5530', '5540', '5550', '5560', '5570', '5580', '5590'];

    public function up(): void
    {
        // More than one bill per finished item. MySQL needs another index
        // starting with tenant_id for its foreign key before the unique one goes.
        if (! Schema::hasIndex('bill_of_materials', 'bom_tenant_item_idx')) {
            Schema::table('bill_of_materials', function (Blueprint $table) {
                $table->index(['tenant_id', 'item_id'], 'bom_tenant_item_idx');
            });
        }
        if (Schema::hasIndex('bill_of_materials', 'bill_of_materials_tenant_id_item_id_unique')) {
            Schema::table('bill_of_materials', function (Blueprint $table) {
                $table->dropUnique('bill_of_materials_tenant_id_item_id_unique');
            });
        }
        if (! Schema::hasColumn('bill_of_materials', 'version')) {
            Schema::table('bill_of_materials', function (Blueprint $table) {
                $table->string('version', 50)->nullable()->after('name');
            });
        }

        if (! Schema::hasTable('bom_costs')) {
            Schema::create('bom_costs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('bill_of_materials_id')->constrained('bill_of_materials')->cascadeOnDelete();
                $table->string('description');
                $table->decimal('amount', 15, 2)->default(0);
                $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('assembly_orders', 'kind')) {
            Schema::table('assembly_orders', function (Blueprint $table) {
                $table->string('kind', 20)->default('build')->after('order_number');
            });
        }
        if (! Schema::hasColumn('assembly_orders', 'assembly_date')) {
            Schema::table('assembly_orders', function (Blueprint $table) {
                $table->date('assembly_date')->nullable()->after('kind');
            });
        }
        if (! Schema::hasColumn('assembly_orders', 'to_warehouse_id')) {
            Schema::table('assembly_orders', function (Blueprint $table) {
                $table->foreignId('to_warehouse_id')->nullable()->after('warehouse_id')->constrained('warehouses')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('assembly_orders', 'planned_quantity')) {
            Schema::table('assembly_orders', function (Blueprint $table) {
                $table->decimal('planned_quantity', 15, 4)->nullable()->after('quantity');
            });
        }
        if (! Schema::hasColumn('assembly_orders', 'quantity_made')) {
            Schema::table('assembly_orders', function (Blueprint $table) {
                $table->decimal('quantity_made', 15, 4)->nullable()->after('planned_quantity');
            });
        }
        if (! Schema::hasColumn('assembly_orders', 'components_cost')) {
            Schema::table('assembly_orders', function (Blueprint $table) {
                $table->decimal('components_cost', 15, 2)->default(0)->after('status');
            });
        }
        if (! Schema::hasColumn('assembly_orders', 'extra_cost')) {
            Schema::table('assembly_orders', function (Blueprint $table) {
                $table->decimal('extra_cost', 15, 2)->default(0)->after('components_cost');
            });
        }
        if (! Schema::hasColumn('assembly_orders', 'unit_cost')) {
            Schema::table('assembly_orders', function (Blueprint $table) {
                $table->decimal('unit_cost', 15, 4)->default(0)->after('total_cost');
            });
        }
        if (! Schema::hasColumn('assembly_orders', 'cancelled_at')) {
            Schema::table('assembly_orders', function (Blueprint $table) {
                $table->timestamp('cancelled_at')->nullable()->after('completed_at');
            });
        }

        if (! Schema::hasTable('assembly_order_items')) {
            Schema::create('assembly_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('assembly_order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('item_id')->constrained()->cascadeOnDelete();
                $table->foreignId('bom_item_id')->nullable()->constrained('bom_items')->nullOnDelete();
                $table->decimal('planned_quantity', 15, 4)->default(0);
                $table->decimal('quantity', 15, 4)->nullable(); // actually used (or got back)
                $table->decimal('cost', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('assembly_order_costs')) {
            Schema::create('assembly_order_costs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('assembly_order_id')->constrained()->cascadeOnDelete();
                $table->string('description');
                $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->decimal('planned_amount', 15, 2)->default(0);
                $table->decimal('amount', 15, 2)->nullable(); // actually spent
                $table->timestamps();
            });
        }

        // Stock history rows for builds ("Used in ASM-000001", "Made in ...").
        Schema::table('inventory_histories', function (Blueprint $table) {
            $table->enum('type', ['in', 'out', 'adjustment', 'transfer', 'reserved', 'unreserved', 'assembly'])
                ->default('in')->change();
        });

        // Old orders: quantity was batches; the plan is in finished units now.
        DB::table('assembly_orders')->whereNull('planned_quantity')->orderBy('id')->each(function ($row) {
            $output = (float) (DB::table('bill_of_materials')->where('id', $row->bill_of_materials_id)->value('output_quantity') ?: 1);
            $planned = round((float) $row->quantity * $output, 4);
            DB::table('assembly_orders')->where('id', $row->id)->update([
                'planned_quantity' => $planned,
                'quantity_made' => $row->status === 'completed' ? $planned : null,
                'assembly_date' => $row->assembly_date ?? substr((string) ($row->completed_at ?? $row->created_at ?? now()), 0, 10),
                'to_warehouse_id' => $row->to_warehouse_id ?? $row->warehouse_id,
                'components_cost' => $row->status === 'completed' ? $row->total_cost : 0,
            ]);
        });
        DB::table('assembly_orders')->where('status', 'in_progress')->update(['status' => 'draft']);

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $this->addProductionCostsAccount((int) $tenantId);
        }
    }

    private function addProductionCostsAccount(int $tenantId): void
    {
        $tenant = DB::table('tenants')->where('id', $tenantId)->first(['settings']);
        $settings = json_decode((string) ($tenant->settings ?? ''), true) ?: [];
        $mapped = $settings['account_mappings']['production_costs_applied'] ?? null;
        if ($mapped && DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->where('account_code', $mapped)->exists()) {
            return;
        }

        $accounts = DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->whereIn('account_code', self::CODES)->pluck('name', 'account_code');
        $existing = $accounts->get('5500');
        if ($existing !== null && preg_match('/production|applied|absorb/i', (string) $existing)) {
            return; // already there
        }

        $code = collect(self::CODES)->first(fn ($c) => ! $accounts->has($c));
        if (! $code) {
            return; // every code taken; the business can map one in its settings
        }

        DB::table('chart_of_accounts')->insert([
            'tenant_id' => $tenantId,
            'account_code' => $code,
            'name' => 'Production Costs Applied',
            'type' => 'expense',
            'sub_type' => 'cost_of_goods_sold',
            'is_system' => true,
            'is_active' => true,
            'current_balance' => 0,
            'opening_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($code !== '5500') {
            $settings['account_mappings']['production_costs_applied'] = $code;
            DB::table('tenants')->where('id', $tenantId)->update(['settings' => json_encode($settings)]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assembly_order_costs');
        Schema::dropIfExists('assembly_order_items');
        Schema::dropIfExists('bom_costs');
        if (Schema::hasColumn('assembly_orders', 'to_warehouse_id')) {
            Schema::table('assembly_orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('to_warehouse_id'));
        }
        foreach (['kind', 'assembly_date', 'planned_quantity', 'quantity_made', 'components_cost', 'extra_cost', 'unit_cost', 'cancelled_at'] as $column) {
            if (Schema::hasColumn('assembly_orders', $column)) {
                Schema::table('assembly_orders', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
        if (Schema::hasColumn('bill_of_materials', 'version')) {
            Schema::table('bill_of_materials', fn (Blueprint $table) => $table->dropColumn('version'));
        }
        // The unique index is not put back: a business may now have two bills for one item.
        // The Production Costs Applied account is left in place: it may hold postings.
    }
};
