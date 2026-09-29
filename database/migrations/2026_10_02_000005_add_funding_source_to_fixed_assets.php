<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How an asset was paid for decides what its purchase journal credits
 * (finding A11). Existing assets are left empty: their journals already
 * credited Cash.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fixed_assets', 'funding_source')) {
            Schema::table('fixed_assets', function (Blueprint $table) {
                $table->string('funding_source', 30)->nullable()->after('purchase_cost');
            });
        }
    }

    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropColumn('funding_source');
        });
    }
};
