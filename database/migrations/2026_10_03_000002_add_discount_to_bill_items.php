<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bill lines can carry a discount (the API has always accepted one), but
 * there was no column, so it was only kept in the bill's total discount and
 * lost when the bill was edited (found while doing R3).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bill_items', 'discount')) {
            Schema::table('bill_items', function (Blueprint $table) {
                $table->decimal('discount', 15, 2)->default(0)->after('unit_price');
            });
        }
    }

    public function down(): void
    {
        Schema::table('bill_items', function (Blueprint $table) {
            $table->dropColumn('discount');
        });
    }
};
