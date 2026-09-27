<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statutory_tax_templates', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 3);            // e.g. NGA, KEN, GHA, ZAF
            $table->string('name');                        // e.g. "Nigeria PAYE 2024"
            $table->unsignedSmallInteger('tax_year');      // e.g. 2024, 2025
            $table->json('brackets');                      // array of {name, min, max, rate, fixed_amount}
            $table->enum('period', ['monthly', 'annual'])->default('annual');
            $table->json('employer_contributions')->nullable(); // default employer contribution rules
            $table->text('description')->nullable();
            $table->boolean('is_current')->default(false); // latest version for this country
            $table->timestamps();

            $table->index(['country_code', 'tax_year']);
            $table->index(['country_code', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statutory_tax_templates');
    }
};
