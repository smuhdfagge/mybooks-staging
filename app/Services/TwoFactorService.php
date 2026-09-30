<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    /** Wrong codes allowed per account before a pause (S7). */
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 300;

    protected Google2FA $engine;

    public function __construct()
    {
        $this->engine = new Google2FA;
    }

    /**
     * Generate a new secret key for the user.
     */
    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey();
    }

    /**
     * Generate a QR code SVG for the user to scan.
     */
    public function generateQrCodeSvg(User $user, string $secret): string
    {
        $companyName = config('app.name', 'MyBooks');

        $qrCodeUrl = $this->engine->getQRCodeUrl(
            $companyName,
            $user->email,
            $secret
        );

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd
        );

        $writer = new Writer($renderer);

        $rawSvg = $writer->writeString($qrCodeUrl);

        // Sanitize SVG output to strip any potential XSS vectors
        return SvgSanitizer::sanitize($rawSvg);
    }

    /**
     * Check a code for a secret that isn't saved yet (setup). Returns the
     * code's time step, or null when it is wrong.
     */
    public function verify(string $secret, string $code): ?int
    {
        $step = $this->engine->verifyKeyNewer($secret, $code, 0);

        return is_int($step) ? $step : null;
    }

    /**
     * Check a code against the user's saved secret. A code that was already
     * accepted (or an older one) is refused, so it can't be replayed within
     * its window (S7).
     */
    public function verifyForUser(User $user, string $code): bool
    {
        $secret = $this->getDecryptedSecret($user);

        if (! $secret) {
            return false;
        }

        $step = $this->engine->verifyKeyNewer($secret, $code, (int) ($user->two_factor_last_used_at ?? 0));

        if (! is_int($step)) {
            return false;
        }

        // Conditional update, so two requests racing with one code can't both win.
        $claimed = $user->newQuery()->whereKey($user->getKey())
            ->where(fn ($q) => $q->whereNull('two_factor_last_used_at')->orWhere('two_factor_last_used_at', '<', $step))
            ->update(['two_factor_last_used_at' => $step]);

        if ($claimed === 0) {
            return false;
        }

        $user->forceFill(['two_factor_last_used_at' => $step])->syncOriginalAttribute('two_factor_last_used_at');

        return true;
    }

    /**
     * Recovery codes are stored as bcrypt hashes, never in a readable form
     * (S7). Returns what goes in two_factor_recovery_codes.
     */
    public function hashRecoveryCodes(array $codes): string
    {
        return json_encode(array_map(
            fn (string $code) => Hash::make($this->normaliseRecoveryCode($code)),
            array_values($codes)
        ));
    }

    /**
     * Use up a recovery code. Returns false when it doesn't match.
     */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $hashes = $this->recoveryCodeHashes($user);
        $code = $this->normaliseRecoveryCode($code);

        foreach ($hashes as $i => $hash) {
            if (Hash::check($code, $hash)) {
                unset($hashes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => json_encode(array_values($hashes))])->save();

                return true;
            }
        }

        return false;
    }

    public function recoveryCodesLeft(User $user): int
    {
        return count($this->recoveryCodeHashes($user));
    }

    // ── Per-account attempt limit (S7) ──────────────────────────

    public function tooManyAttempts(User $user): bool
    {
        return RateLimiter::tooManyAttempts($this->throttleKey($user), self::MAX_ATTEMPTS);
    }

    public function secondsUntilUnlocked(User $user): int
    {
        return RateLimiter::availableIn($this->throttleKey($user));
    }

    public function recordFailedAttempt(User $user): void
    {
        RateLimiter::hit($this->throttleKey($user), self::DECAY_SECONDS);
    }

    public function clearAttempts(User $user): void
    {
        RateLimiter::clear($this->throttleKey($user));
    }

    protected function throttleKey(User $user): string
    {
        return 'two-factor:'.$user->getTable().':'.$user->getKey();
    }

    /** @return array<int, string> */
    protected function recoveryCodeHashes(User $user): array
    {
        $codes = json_decode((string) $user->two_factor_recovery_codes, true);

        return is_array($codes) ? array_values($codes) : [];
    }

    protected function normaliseRecoveryCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    /**
     * Generate recovery codes.
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(sprintf(
                '%s-%s',
                bin2hex(random_bytes(4)),
                bin2hex(random_bytes(4))
            ));
        }

        return $codes;
    }

    /**
     * Get the decrypted secret from a user.
     */
    public function getDecryptedSecret(User $user): ?string
    {
        if (empty($user->two_factor_secret)) {
            return null;
        }

        return Crypt::decryptString($user->two_factor_secret);
    }
}
