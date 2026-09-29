<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Accumulated depreciation had the "fixed_asset" sub-type, so the old cash
 * flow showed each month's depreciation as cash from selling assets
 * (finding A7). Give it its own sub-type; the balance sheet still lists it
 * under fixed assets.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('chart_of_accounts')
            ->where('type', 'asset')
            ->whereRaw('LOWER(name) LIKE ?', ['%accumulated depreciation%'])
            ->update(['sub_type' => 'accumulated_depreciation']);
    }

    public function down(): void
    {
        DB::table('chart_of_accounts')
            ->where('sub_type', 'accumulated_depreciation')
            ->update(['sub_type' => 'fixed_asset']);
    }
};
