<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('exports')) {
            Schema::create('exports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('type'); // full_backup, customers, invoices, etc.
                $table->string('format'); // csv, xlsx, pdf, json, zip
                $table->string('status')->default('pending'); // pending, processing, completed, failed
                $table->string('filename')->nullable();
                $table->string('file_path')->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->json('options')->nullable(); // date range, filters, etc.
                $table->json('included_data')->nullable(); // for full backups, which models to include
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'type']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
