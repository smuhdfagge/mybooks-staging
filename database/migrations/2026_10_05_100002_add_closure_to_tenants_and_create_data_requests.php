<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nigeria Data Protection Act (finding O7): an owner can close the
 * business, and its data is erased 30 days later unless the closure is
 * cancelled; and a log of data-protection requests. The log has no
 * foreign keys so it outlives the business it records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'closure_requested_at')) {
                $table->timestamp('closure_requested_at')->nullable();
            }
            if (! Schema::hasColumn('tenants', 'closure_purge_at')) {
                $table->timestamp('closure_purge_at')->nullable()->index();
            }
            if (! Schema::hasColumn('tenants', 'closure_requested_by')) {
                $table->unsignedBigInteger('closure_requested_by')->nullable();
            }
        });

        if (! Schema::hasTable('data_requests')) {
            Schema::create('data_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('tenant_name')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('requester')->nullable();
                $table->string('type', 30);
                $table->string('status', 20);
                $table->text('details')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->string('handled_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('data_requests');

        Schema::table('tenants', function (Blueprint $table) {
            foreach (['closure_requested_at', 'closure_purge_at', 'closure_requested_by'] as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    if ($column === 'closure_purge_at') {
                        $table->dropIndex(['closure_purge_at']);
                    }
                    $table->dropColumn($column);
                }
            }
        });
    }
};
