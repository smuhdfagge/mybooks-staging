<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Next document number per business and document type, handed out under a
 * row lock so two people saving at the same moment can't get the same
 * number (finding R2). Each row starts from the highest number already
 * used, the first time that type is numbered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 100);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
