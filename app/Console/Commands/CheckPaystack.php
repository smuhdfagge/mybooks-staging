<?php

namespace App\Console\Commands;

use App\Services\Billing\AutoRenewal;
use App\Services\Billing\PaystackGateway;
use Illuminate\Console\Command;

/**
 * Checks the Paystack set-up for subscription payments: the keys and their
 * mode, the URLs Paystack needs, and, with one read-only call (list one
 * transaction), that Paystack accepts the secret key. Never prints a key.
 */
class CheckPaystack extends Command
{
    protected $signature = 'billing:check-paystack
                            {--offline : Check the settings only, without calling Paystack}';

    protected $description = 'Check the Paystack keys and URLs for subscription payments (never shows the keys)';

    public function handle(PaystackGateway $gateway, AutoRenewal $renewal): int
    {
        $secret = (string) config('services.paystack.secret_key');
        $public = (string) config('services.paystack.public_key');
        $secretMode = $this->mode($secret, 'sk');
        $publicMode = $this->mode($public, 'pk');
        $appUrl = (string) config('app.url');
        $failed = false;
        $rows = [];

        if ($secret === '') {
            $rows[] = ['Secret key (PAYSTACK_SECRET_KEY)', 'not set', 'FAIL'];
            $failed = true;
        } else {
            $rows[] = ['Secret key (PAYSTACK_SECRET_KEY)', $secretMode ? "set, {$secretMode} mode" : 'set, but not an sk_test_ or sk_live_ key', $secretMode ? 'OK' : 'CHECK'];
        }
        if ($public === '') {
            $rows[] = ['Public key (PAYSTACK_PUBLIC_KEY)', 'not set (not used for payments yet)', 'OK'];
        } else {
            $matches = $publicMode && $publicMode === $secretMode;
            $rows[] = ['Public key (PAYSTACK_PUBLIC_KEY)', $publicMode ? "set, {$publicMode} mode" : 'set, but not a pk_test_ or pk_live_ key', $matches ? 'OK' : 'CHECK'];
        }
        if ($secretMode === 'live' && ! app()->isProduction()) {
            $rows[] = ['Environment', 'live key outside production (APP_ENV='.app()->environment().'): real cards will be charged', 'CHECK'];
        } elseif ($secretMode === 'test' && app()->isProduction()) {
            $rows[] = ['Environment', 'test key in production: no real payments will go through', 'CHECK'];
        }
        $rows[] = ['Currency (PAYSTACK_CURRENCY)', (string) config('services.paystack.currency'), 'OK'];
        $https = str_starts_with($appUrl, 'https://') && ! preg_match('#^https://(localhost|127\.0\.0\.1)#', $appUrl);
        $rows[] = ['APP_URL', $appUrl, $https ? 'OK' : 'CHECK'];
        $rows[] = ['Automatic renewal', $renewal->enabled() ? 'on (needs the scheduler running)' : 'off (MYBOOKS_FEATURE_AUTO_RENEWAL)', 'OK'];
        if (app()->configurationIsCached()) {
            $rows[] = ['Config cache', 'cached: after editing .env run php artisan config:cache again', 'OK'];
        }

        if ($secret !== '' && ! $this->option('offline')) {
            $ping = $gateway->ping();
            $rows[] = ['Connection to Paystack', $ping['message'], $ping['ok'] ? 'OK' : 'FAIL'];
            $failed = $failed || ! $ping['ok'];
        }

        $this->table(['Check', 'Result', ''], $rows);
        $this->newLine();
        $this->line('Set this webhook URL in the Paystack dashboard (Settings > API Keys & Webhooks, '.($secretMode ?? 'same').' mode):');
        $this->line('  '.route('billing.webhook'));
        $this->line('Customers come back to '.route('billing.callback').' (sent with each payment; nothing to set).');

        if ($failed) {
            $this->newLine();
            $this->error('Paystack is not ready: fix the FAIL lines above.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** "test" or "live" from the key's prefix; never the key itself. */
    private function mode(string $key, string $prefix): ?string
    {
        return match (true) {
            str_starts_with($key, $prefix.'_test_') => 'test',
            str_starts_with($key, $prefix.'_live_') => 'live',
            default => null,
        };
    }
}
