<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Session 18: e-invoicing with the Nigeria Revenue Service (NRS).
 *
 * - e_invoice_settings: one row per business: on/off, sandbox or live, how
 *   documents are submitted, and the business's NRS keys. The keys are
 *   encrypted by the model (text columns, as the encrypted value is long).
 * - e_invoice_submissions: what happened when a document was sent to NRS.
 *   Kept apart from invoices and credit notes on purpose: sending never
 *   changes the books. One row per document (unique per invoice and per
 *   credit note); a row can only be an invoice's or a credit note's.
 * - permissions: view e-invoices, submit e-invoices, manage e-invoicing.
 *
 * Business TINs reuse the tax_number columns that already exist on
 * tenants and customers.
 */
return new class extends Migration
{
    private array $permissions = ['view e-invoices', 'submit e-invoices', 'manage e-invoicing'];

    public function up(): void
    {
        if (! Schema::hasTable('e_invoice_settings')) {
            Schema::create('e_invoice_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
                $table->boolean('enabled')->default(false);
                $table->string('environment', 10)->default('sandbox');   // sandbox, live
                $table->string('submit_mode', 10)->default('manual');    // manual, auto
                $table->text('api_key')->nullable();                     // encrypted
                $table->text('api_secret')->nullable();                  // encrypted
                $table->text('service_id')->nullable();                  // encrypted; 8 characters, part of every IRN
                $table->text('business_id')->nullable();                 // encrypted
                $table->text('public_key')->nullable();                  // encrypted
                $table->text('certificate')->nullable();                 // encrypted
                $table->timestamp('last_tested_at')->nullable();
                $table->boolean('last_test_ok')->nullable();
                $table->string('last_test_message')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('e_invoice_submissions')) {
            Schema::create('e_invoice_submissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('invoice_id')->nullable()->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('credit_note_id')->nullable()->unique()->constrained()->cascadeOnDelete();
                $table->string('status', 16)->default('not_submitted');  // not_submitted, pending, accepted, rejected, failed
                $table->string('kind', 3)->default('b2b');               // b2b, b2c
                $table->string('environment', 10)->nullable();           // sandbox, live, simulated
                $table->string('irn', 120)->nullable();
                $table->text('csid')->nullable();                        // NRS's cryptographic stamp
                $table->longText('qr_image')->nullable();                // base64 PNG from NRS
                $table->text('qr_payload')->nullable();                  // text to turn into a QR when NRS gave no image
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->timestamp('last_attempt_at')->nullable();
                $table->timestamp('next_retry_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->text('last_error')->nullable();                  // plain English
                $table->timestamp('late_warned_at')->nullable();         // B2C 24-hour warning sent
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
                $table->index(['status', 'next_retry_at']);
                $table->index('irn');
            });
        }

        foreach ($this->permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()->each(function (Role $role) {
            if (in_array($role->name, ['super-admin', 'admin'], true)) {
                $role->givePermissionTo($this->permissions);
            } elseif ($role->name === 'accountant') {
                $role->givePermissionTo(['view e-invoices', 'submit e-invoices']);
            }
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('e_invoice_submissions');
        Schema::dropIfExists('e_invoice_settings');
        Permission::whereIn('name', $this->permissions)->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
