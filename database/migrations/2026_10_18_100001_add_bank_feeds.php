<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Session 17: bank feeds (Mono).
 *
 * - plans: how many bank accounts a business may link (null = no limit);
 * - bank_feed_connections: one linked bank account, feeding one MyBooks
 *   bank account. Only the last 4 digits of the account number are kept;
 *   Mono never gives login details;
 * - bank_feed_lines: every transaction pulled from the bank. The bank's own
 *   transaction id is unique per connection, so pulling twice adds nothing.
 *   A line is matched to (or creates) one MyBooks record; a record is linked
 *   to at most one line per direction.
 */
return new class extends Migration
{
    /** Starting limits for the seeded plans (editable in the database). */
    private const LIMITS = ['starter' => 1, 'professional' => 3, 'enterprise' => 10];

    public function up(): void
    {
        if (! Schema::hasColumn('plans', 'bank_feed_accounts_limit')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->unsignedInteger('bank_feed_accounts_limit')->nullable();
            });
            // Only when the column is first added, so a re-run never undoes
            // a plan that was later set to "no limit" (null).
            foreach (self::LIMITS as $slug => $limit) {
                DB::table('plans')->where('slug', $slug)->update(['bank_feed_accounts_limit' => $limit]);
            }
        }

        if (! Schema::hasTable('bank_feed_connections')) {
            Schema::create('bank_feed_connections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('bank_id')->nullable()->constrained('banks')->cascadeOnDelete();
                $table->string('new_bank_name')->nullable();          // create this MyBooks account when linking finishes
                $table->string('provider', 20);                       // mono
                $table->string('provider_account_id', 100)->nullable()->unique(); // the bank link's id at the provider; not a secret
                $table->string('link_ref', 64)->nullable()->unique();  // ties a pending link to this business
                $table->string('institution_name')->nullable();
                $table->string('account_name')->nullable();
                $table->string('account_mask', 8)->nullable();         // last 4 digits only
                $table->string('currency', 3)->default('NGN');
                $table->string('status', 24)->default('pending');      // pending, linked, needs_reauthorisation, error, unlinked
                $table->string('data_status', 20)->nullable();
                $table->bigInteger('provider_balance')->nullable();    // kobo, as at balance_at
                $table->timestamp('balance_at')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamp('last_sync_requested_at')->nullable();
                $table->text('last_error')->nullable();
                $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('linked_at')->nullable();
                $table->timestamp('unlinked_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
                $table->index(['bank_id', 'status']);
            });
        }

        if (! Schema::hasTable('bank_feed_lines')) {
            Schema::create('bank_feed_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('connection_id')->constrained('bank_feed_connections')->cascadeOnDelete();
                $table->foreignId('bank_id')->constrained('banks')->cascadeOnDelete();
                $table->string('provider_transaction_id', 100);
                $table->date('date');
                $table->decimal('amount', 15, 2);                       // always positive; see direction
                $table->string('direction', 6);                         // debit (money out), credit (money in)
                $table->text('narration')->nullable();
                $table->decimal('balance_after', 15, 2)->nullable();
                $table->string('status', 10)->default('new');           // new, matched, created, ignored
                $table->string('matched_type', 60)->nullable();
                $table->unsignedBigInteger('matched_id')->nullable();
                $table->foreignId('bank_transaction_id')->nullable()->constrained('bank_transactions')->nullOnDelete();
                $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('matched_at')->nullable();
                $table->json('rejected_matches')->nullable();           // ["App\\Models\\PaymentReceived:12", ...]
                $table->timestamps();

                $table->unique(['connection_id', 'provider_transaction_id'], 'bank_feed_lines_provider_txn_unique');
                // One record is linked to at most one line per direction
                // (a transfer between two accounts has a debit and a credit).
                $table->unique(['matched_type', 'matched_id', 'direction'], 'bank_feed_lines_record_unique');
                $table->index(['tenant_id', 'status']);
                $table->index(['bank_id', 'date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_feed_lines');
        Schema::dropIfExists('bank_feed_connections');
        if (Schema::hasColumn('plans', 'bank_feed_accounts_limit')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->dropColumn('bank_feed_accounts_limit');
            });
        }
    }
};
