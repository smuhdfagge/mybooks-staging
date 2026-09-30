<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Finding S7.
 * - two_factor_last_used_at: the time step of the last accepted code, so
 *   the same code can't be used twice.
 * - Recovery codes were stored encrypted (readable with APP_KEY). They are
 *   now stored as bcrypt hashes. Existing codes are decrypted and hashed
 *   here, so they keep working. Rows already hashed are skipped, so the
 *   migration can run again safely.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'two_factor_last_used_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('two_factor_last_used_at')->nullable()->after('two_factor_confirmed_at');
            });
        }

        DB::table('users')
            ->whereNotNull('two_factor_recovery_codes')
            ->orderBy('id')
            ->select(['id', 'two_factor_recovery_codes'])
            ->chunkById(200, function ($users) {
                foreach ($users as $user) {
                    $stored = $user->two_factor_recovery_codes;

                    // Already hashed: a JSON list, not an encrypted payload.
                    if (is_array(json_decode($stored, true))) {
                        continue;
                    }

                    try {
                        $codes = json_decode(Crypt::decryptString($stored), true);
                    } catch (DecryptException) {
                        // Encrypted with a key we no longer have: nothing to
                        // convert. Leave it; the user can regenerate codes.
                        Log::warning("S7 migration: could not decrypt recovery codes for user {$user->id}.");

                        continue;
                    }

                    $hashed = collect(is_array($codes) ? $codes : [])
                        ->map(fn ($code) => Hash::make(strtoupper(trim((string) $code))))
                        ->values()
                        ->all();

                    DB::table('users')->where('id', $user->id)
                        ->update(['two_factor_recovery_codes' => json_encode($hashed)]);
                }
            });
    }

    public function down(): void
    {
        // Hashes can't be turned back into codes. Only the column is removed.
        if (Schema::hasColumn('users', 'two_factor_last_used_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('two_factor_last_used_at');
            });
        }
    }
};
