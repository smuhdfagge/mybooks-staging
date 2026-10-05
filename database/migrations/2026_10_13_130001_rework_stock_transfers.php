<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stock transfers between warehouses (session 13).
 *
 * - A transfer gets its own date, a reference and a received date.
 * - Each line records what was received, sent back and lost, and the cost
 *   that left the source warehouse, arrived, and was lost.
 * - The old "completed" status is now "received".
 * - Every business gets a "Stock Losses" expense account (5400) for goods
 *   lost on the way. If 5400 is already used for something else, the next
 *   free code is used and remembered in the business's account mappings.
 *
 * Rerunnable: columns are only added when missing and the account only
 * when the business has none.
 */
return new class extends Migration
{
    /** Codes tried in turn for the stock losses account. */
    private const CODES = ['5400', '5410', '5420', '5430', '5440', '5450', '5460', '5470', '5480', '5490'];

    public function up(): void
    {
        if (! Schema::hasColumn('stock_transfers', 'transfer_date')) {
            Schema::table('stock_transfers', function (Blueprint $table) {
                $table->date('transfer_date')->nullable()->after('transfer_number');
            });
        }
        if (! Schema::hasColumn('stock_transfers', 'reference')) {
            Schema::table('stock_transfers', function (Blueprint $table) {
                $table->string('reference', 100)->nullable()->after('status');
            });
        }
        if (! Schema::hasColumn('stock_transfers', 'received_date')) {
            Schema::table('stock_transfers', function (Blueprint $table) {
                $table->date('received_date')->nullable()->after('received_at');
            });
        }
        if (! Schema::hasColumn('stock_transfers', 'cancelled_at')) {
            Schema::table('stock_transfers', function (Blueprint $table) {
                $table->timestamp('cancelled_at')->nullable()->after('received_date');
            });
        }

        // One call per column, written out, so static analysis sees the columns.
        if (! Schema::hasColumn('stock_transfer_items', 'quantity_returned')) {
            Schema::table('stock_transfer_items', function (Blueprint $table) {
                $table->decimal('quantity_returned', 15, 4)->default(0)->after('quantity_received');
            });
        }
        if (! Schema::hasColumn('stock_transfer_items', 'quantity_lost')) {
            Schema::table('stock_transfer_items', function (Blueprint $table) {
                $table->decimal('quantity_lost', 15, 4)->default(0)->after('quantity_returned');
            });
        }
        if (! Schema::hasColumn('stock_transfer_items', 'shipped_cost')) {
            Schema::table('stock_transfer_items', function (Blueprint $table) {
                $table->decimal('shipped_cost', 15, 2)->default(0)->after('quantity_lost');
            });
        }
        if (! Schema::hasColumn('stock_transfer_items', 'received_cost')) {
            Schema::table('stock_transfer_items', function (Blueprint $table) {
                $table->decimal('received_cost', 15, 2)->default(0)->after('shipped_cost');
            });
        }
        if (! Schema::hasColumn('stock_transfer_items', 'lost_cost')) {
            Schema::table('stock_transfer_items', function (Blueprint $table) {
                $table->decimal('lost_cost', 15, 2)->default(0)->after('received_cost');
            });
        }

        // Old transfers: dated the day they were made; "completed" is "received".
        DB::table('stock_transfers')->whereNull('transfer_date')->orderBy('id')
            ->each(function ($row) {
                DB::table('stock_transfers')->where('id', $row->id)
                    ->update(['transfer_date' => substr((string) ($row->shipped_at ?? $row->created_at ?? now()), 0, 10)]);
            });
        DB::table('stock_transfers')->where('status', 'completed')->update(['status' => 'received']);

        foreach (DB::table('tenants')->pluck('id', 'id') as $tenantId) {
            $this->addStockLossesAccount((int) $tenantId);
        }
    }

    private function addStockLossesAccount(int $tenantId): void
    {
        $tenant = DB::table('tenants')->where('id', $tenantId)->first(['settings']);
        $settings = json_decode((string) ($tenant->settings ?? ''), true) ?: [];
        $mapped = $settings['account_mappings']['stock_losses'] ?? null;
        if ($mapped && DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->where('account_code', $mapped)->exists()) {
            return;
        }

        $accounts = DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->whereIn('account_code', self::CODES)->pluck('name', 'account_code');
        $existing = $accounts->get('5400');
        if ($existing !== null && preg_match('/loss|shrink/i', (string) $existing)) {
            return; // already there
        }

        $code = collect(self::CODES)->first(fn ($c) => ! $accounts->has($c));
        if (! $code) {
            return; // every code taken; the business can map one in its settings
        }

        DB::table('chart_of_accounts')->insert([
            'tenant_id' => $tenantId,
            'account_code' => $code,
            'name' => 'Stock Losses',
            'type' => 'expense',
            'sub_type' => 'cost_of_goods_sold',
            'is_system' => true,
            'is_active' => true,
            'current_balance' => 0,
            'opening_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($code !== '5400') {
            $settings['account_mappings']['stock_losses'] = $code;
            DB::table('tenants')->where('id', $tenantId)->update(['settings' => json_encode($settings)]);
        }
    }

    public function down(): void
    {
        DB::table('stock_transfers')->where('status', 'received')->update(['status' => 'completed']);
        foreach (['quantity_returned', 'quantity_lost', 'shipped_cost', 'received_cost', 'lost_cost'] as $column) {
            if (Schema::hasColumn('stock_transfer_items', $column)) {
                Schema::table('stock_transfer_items', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
        foreach (['transfer_date', 'reference', 'received_date', 'cancelled_at'] as $column) {
            if (Schema::hasColumn('stock_transfers', $column)) {
                Schema::table('stock_transfers', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
        // The Stock Losses account is left in place: it may hold postings.
    }
};
