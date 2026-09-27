<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rotate the APP_KEY and optionally revoke all Sanctum tokens.
 *
 * This command:
 *  1. Moves current APP_KEY → APP_PREVIOUS_KEYS in .env (Laravel 11+ supports this).
 *  2. Generates a fresh APP_KEY via `key:generate`.
 *  3. Optionally revokes all Sanctum personal-access tokens.
 *
 * After running you MUST restart all workers/servers so the new key is loaded.
 */
class KeyRotateCommand extends Command
{
    protected $signature = 'key:rotate
                            {--revoke-tokens : Also revoke all Sanctum personal-access tokens}
                            {--force : Skip confirmation prompts}';

    protected $description = 'Rotate the APP_KEY and optionally revoke Sanctum tokens';

    public function handle(): int
    {
        if (! $this->option('force') && app()->environment('production')) {
            if (! $this->confirm('⚠️ You are in PRODUCTION. Rotating the key will invalidate all encrypted data not using previous_keys support. Continue?')) {
                $this->info('Aborted.');
                return self::SUCCESS;
            }
        }

        // ── Step 1: Preserve current key ──
        $currentKey = config('app.key');

        if (empty($currentKey)) {
            $this->error('No APP_KEY found. Run php artisan key:generate first.');
            return self::FAILURE;
        }

        $this->info('Current APP_KEY: ' . Str::mask($currentKey, '*', 12));

        // Read current previous keys
        $previousKeys = config('app.previous_keys', []);
        $previousKeys[] = $currentKey;

        // Write previous keys to .env
        $envPath = app()->environmentFilePath();
        $envContent = file_get_contents($envPath);

        // Update or add APP_PREVIOUS_KEYS
        $previousKeysValue = implode(',', $previousKeys);

        if (preg_match('/^APP_PREVIOUS_KEYS=.*$/m', $envContent)) {
            $envContent = preg_replace(
                '/^APP_PREVIOUS_KEYS=.*$/m',
                'APP_PREVIOUS_KEYS="' . $previousKeysValue . '"',
                $envContent
            );
        } else {
            // Insert after APP_KEY line
            $envContent = preg_replace(
                '/^(APP_KEY=.*)$/m',
                '$1' . PHP_EOL . 'APP_PREVIOUS_KEYS="' . $previousKeysValue . '"',
                $envContent
            );
        }

        file_put_contents($envPath, $envContent);
        $this->info('Preserved previous key in APP_PREVIOUS_KEYS.');

        // ── Step 2: Generate new key ──
        $this->call('key:generate', ['--force' => true]);
        $this->info('New APP_KEY generated.');

        // ── Step 3: Optionally revoke Sanctum tokens ──
        if ($this->option('revoke-tokens')) {
            $this->revokeSanctumTokens();
        }

        $this->newLine();
        $this->warn('IMPORTANT: Restart all queue workers, Octane servers, and clear config cache:');
        $this->line('  php artisan config:clear');
        $this->line('  php artisan queue:restart');

        return self::SUCCESS;
    }

    protected function revokeSanctumTokens(): void
    {
        if (! \Schema::hasTable('personal_access_tokens')) {
            $this->warn('personal_access_tokens table not found — skipping token revocation.');
            return;
        }

        $count = DB::table('personal_access_tokens')->count();

        if ($count === 0) {
            $this->info('No Sanctum tokens to revoke.');
            return;
        }

        if (! $this->option('force') && ! $this->confirm("Revoke all {$count} Sanctum tokens?")) {
            return;
        }

        DB::table('personal_access_tokens')->delete();
        $this->info("Revoked {$count} Sanctum token(s).");
    }
}
