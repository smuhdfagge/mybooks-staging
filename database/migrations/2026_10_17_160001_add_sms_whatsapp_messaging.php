<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Session 16: SMS and WhatsApp reminders.
 *
 * - plans: a monthly SMS and WhatsApp allowance (MyBooks pays the provider);
 * - customers: opt-out switches for SMS and WhatsApp;
 * - message_settings: per business, which messages go by SMS / WhatsApp and
 *   the SMS wording;
 * - customer_messages: every SMS / WhatsApp message, its status and cost.
 *   dedupe_key is unique per business so a scheduled reminder can't go twice.
 */
return new class extends Migration
{
    /** Starting allowances for the seeded plans (editable by the platform admin). */
    private const ALLOWANCES = [
        'starter' => [100, 50],
        'professional' => [300, 150],
        'enterprise' => [1000, 500],
    ];

    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'sms_monthly_limit')) {
                $table->unsignedInteger('sms_monthly_limit')->default(0);
            }
            if (! Schema::hasColumn('plans', 'whatsapp_monthly_limit')) {
                $table->unsignedInteger('whatsapp_monthly_limit')->default(0);
            }
        });

        // Only plans still at 0, so a re-run never overwrites an admin's change.
        foreach (self::ALLOWANCES as $slug => [$sms, $whatsapp]) {
            DB::table('plans')->where('slug', $slug)->where('sms_monthly_limit', 0)->where('whatsapp_monthly_limit', 0)
                ->update(['sms_monthly_limit' => $sms, 'whatsapp_monthly_limit' => $whatsapp]);
        }

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'sms_opt_out')) {
                $table->boolean('sms_opt_out')->default(false);
            }
            if (! Schema::hasColumn('customers', 'whatsapp_opt_out')) {
                $table->boolean('whatsapp_opt_out')->default(false);
            }
        });

        if (! Schema::hasTable('message_settings')) {
            Schema::create('message_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
                foreach (['invoice_sent', 'payment_reminder', 'overdue', 'payment_received'] as $type) {
                    $table->boolean($type.'_sms')->default(false);
                    $table->boolean($type.'_whatsapp')->default(false);
                    $table->text($type.'_text')->nullable(); // SMS wording; null = the default
                }
                $table->json('limit_notices')->nullable(); // {"sms": "2026-10"} once warned that month
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('customer_messages')) {
            Schema::create('customer_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type', 30);            // invoice_sent, payment_reminder, overdue, payment_received, test
                $table->string('channel', 10);         // sms, whatsapp
                $table->string('to', 20);              // +2348031234567
                $table->text('body');                  // the SMS text, or the WhatsApp template filled in
                $table->json('params')->nullable();    // WhatsApp template values
                $table->unsignedTinyInteger('segments')->default(1); // SMS pages; 1 for WhatsApp
                $table->string('status', 12)->default('queued');     // queued, sending, sent, delivered, failed
                $table->string('provider', 20)->nullable();          // termii, meta, log
                $table->string('provider_message_id', 100)->nullable();
                $table->decimal('cost', 12, 4)->nullable();
                $table->string('error')->nullable();
                $table->string('dedupe_key', 120)->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('send_after')->nullable();   // quiet hours and retries
                $table->timestamp('dispatched_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'dedupe_key']);
                $table->index(['tenant_id', 'channel', 'created_at']);
                $table->index(['status', 'send_after']);
                $table->index('provider_message_id');
                $table->index('to');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_messages');
        Schema::dropIfExists('message_settings');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['sms_opt_out', 'whatsapp_opt_out']);
        });
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['sms_monthly_limit', 'whatsapp_monthly_limit']);
        });
    }
};
